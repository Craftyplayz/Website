<?php
// Cross Quiz grid builder. The same seed and options always give the same puzzle, so a
// link with ?seed= rebuilds it exactly. Questions come from questions.json (see
// _dev/scripts/opentdb-import.php).

const CQ_SIZES = [
    // words: answers placed before gap-filling adds more; clues: what a puzzle usually ends up with
    's' => ['name' => 'Small', 'words' => 8, 'max' => 10, 'clues' => 9],
    'm' => ['name' => 'Medium', 'words' => 14, 'max' => 13, 'clues' => 15],
    'l' => ['name' => 'Large', 'words' => 20, 'max' => 16, 'clues' => 21],
];
const CQ_DIFFS = [0 => 'Any difficulty', 1 => 'Easy', 2 => 'Medium', 3 => 'Hard'];

function cq_bank(): array
{
    static $bank = null;
    if ($bank === null) {
        $bank = json_decode((string) @file_get_contents(__DIR__ . '/questions.json'), true);
        if (!is_array($bank) || empty($bank['questions'])) $bank = ['categories' => [], 'questions' => []];
    }
    return $bank;
}

// Seeded random numbers. mt_rand() is reproducible for a given mt_srand() seed.
function cq_rand(int $max): int
{
    return mt_rand(0, $max - 1);
}

function cq_shuffle(array $a): array
{
    for ($i = count($a) - 1; $i > 0; $i--) {
        $j = cq_rand($i + 1);
        [$a[$i], $a[$j]] = [$a[$j], $a[$i]];
    }
    return $a;
}

// Read and tidy the options from the query string.
function cq_options(array $get, array $bank): array
{
    $ids = array_column($bank['categories'], 'id');
    $cats = $get['c'] ?? [];
    if (is_string($cats)) $cats = explode('.', $cats);
    $cats = array_values(array_intersect($ids, is_array($cats) ? $cats : []));
    if (count($cats) === count($ids)) $cats = [];
    $size = is_string($get['size'] ?? null) && isset(CQ_SIZES[$get['size']]) ? $get['size'] : 'm';
    $diff = (int) ($get['d'] ?? 0);
    if (!isset(CQ_DIFFS[$diff])) $diff = 0;
    $seed = is_string($get['seed'] ?? null) ? preg_replace('/[^a-z0-9]/', '', strtolower($get['seed'])) : '';
    $seed = substr($seed, 0, 12);
    if ($seed === '') $seed = base_convert((string) random_int(36 ** 5, 36 ** 6 - 1), 10, 36);
    return ['c' => $cats, 'size' => $size, 'd' => $diff, 'seed' => $seed];
}

// Query string that rebuilds this puzzle; without the seed it gives a new puzzle with the same settings.
function cq_query(array $o, bool $with_seed = true): string
{
    $q = [];
    if ($with_seed) $q['seed'] = $o['seed'];
    if ($o['size'] !== 'm') $q['size'] = $o['size'];
    if ($o['d']) $q['d'] = $o['d'];
    if ($o['c']) $q['c'] = implode('.', $o['c']);
    return $q ? '?' . http_build_query($q) : '';
}

function cq_pool(array $bank, array $o): array
{
    $cats = array_flip($o['c']);
    return array_values(array_filter($bank['questions'], fn ($q) => (!$cats || isset($cats[$q['c']])) && (!$o['d'] || $q['d'] === $o['d'])));
}

// Can $word go at ($x, $y) in direction $dir (0 across, 1 down)? Returns the number of
// crossings, or -1 if it doesn't fit.
function cq_fits(array $grid, string $word, int $x, int $y, int $dir, array $box, int $max): int
{
    [$dx, $dy] = $dir ? [0, 1] : [1, 0];
    $len = strlen($word);
    $ex = $x + $dx * ($len - 1);
    $ey = $y + $dy * ($len - 1);
    if (max($box[2], $ex) - min($box[0], $x) + 1 > $max || max($box[3], $ey) - min($box[1], $y) + 1 > $max) return -1;
    if (isset($grid[($x - $dx) . ',' . ($y - $dy)]) || isset($grid[($ex + $dx) . ',' . ($ey + $dy)])) return -1;
    $cross = 0;
    for ($i = 0; $i < $len; $i++) {
        $cx = $x + $dx * $i;
        $cy = $y + $dy * $i;
        $cell = $grid["$cx,$cy"] ?? null;
        if ($cell) {
            if ($cell['l'] !== $word[$i] || $cell['d'][$dir]) return -1;
            $cross++;
        } elseif (isset($grid[($cx + $dy) . ',' . ($cy + $dx)]) || isset($grid[($cx - $dy) . ',' . ($cy - $dx)])) {
            return -1; // would sit side by side with another word
        }
    }
    return $cross === $len ? -1 : $cross;
}

function cq_place(array &$grid, array &$box, string $word, int $x, int $y, int $dir): void
{
    [$dx, $dy] = $dir ? [0, 1] : [1, 0];
    for ($i = 0, $len = strlen($word); $i < $len; $i++) {
        $k = ($x + $dx * $i) . ',' . ($y + $dy * $i);
        $grid[$k] ??= ['l' => $word[$i], 'd' => [false, false]];
        $grid[$k]['d'][$dir] = true;
    }
    $box = [min($box[0], $x), min($box[1], $y), max($box[2], $x + $dx * ($len - 1)), max($box[3], $y + $dy * ($len - 1))];
}

// One attempt at a layout. Each step tries every unused answer in every spot that crosses the
// grid and places the one that crosses the most words, so answers share as many letters as
// possible and solved words give letters for the clues around them.
function cq_layout(array $cands, int $want, int $max, array $all, array $index): array
{
    $used = [];
    $grid = [];
    $at = [];      // letter => cells holding it, so only matching squares are tried
    $placed = [];
    $box = [0, 0, 0, 0];
    $put = function (array $q, int $x, int $y, int $dir) use (&$grid, &$at, &$box, &$placed, &$used) {
        [$dx, $dy] = $dir ? [0, 1] : [1, 0];
        foreach (str_split($q['a']) as $i => $l) {
            if (!isset($grid[($x + $dx * $i) . ',' . ($y + $dy * $i)])) $at[$l][] = [$x + $dx * $i, $y + $dy * $i];
        }
        cq_place($grid, $box, $q['a'], $x, $y, $dir);
        $placed[] = ['q' => $q, 'x' => $x, 'y' => $y, 'dir' => $dir];
        $used[$q['a']] = true;
    };
    $put($cands[0], 0, 0, 0);
    $box = [0, 0, strlen($cands[0]['a']) - 1, 0];
    $left = array_slice($cands, 1);
    $crossings = 0;

    // Fill to the target, then keep adding answers that cross two or more words, then
    // take out answers that cross only one (they touch the grid at a single square).
    $extra = intdiv($want, 2);
    while (count($placed) < $want + $extra && $left) {
        $need = count($placed) < $want ? 1 : 2;
        $area = ($box[2] - $box[0] + 1) * ($box[3] - $box[1] + 1);
        // first look for a gap that crosses two or more letters and an answer that fits it
        $slot = cq_best_slot($grid, $box, $max, $all, $index, $used, $area);
        if ($slot) {
            $put($all[$slot[1]], $slot[2], $slot[3], $slot[4]);
            $crossings += $slot[5];
            continue;
        }
        if ($need > 1) break;
        $best = null;
        foreach ($left as $ci => $q) {
            if (isset($used[$q['a']])) continue;
            $word = $q['a'];
            $len = strlen($word);
            for ($i = 0; $i < $len; $i++) {
                foreach ($at[$word[$i]] ?? [] as [$gx, $gy]) {
                    $cell = $grid["$gx,$gy"];
                    foreach ([0, 1] as $dir) {
                        if ($cell['d'][$dir]) continue;
                        $x = $dir ? $gx : $gx - $i;
                        $y = $dir ? $gy - $i : $gy;
                        $cross = cq_fits($grid, $word, $x, $y, $dir, $box, $max);
                        if ($cross < $need) continue;
                        $w = max($box[2], $x + ($dir ? 0 : $len - 1)) - min($box[0], $x) + 1;
                        $h = max($box[3], $y + ($dir ? $len - 1 : 0)) - min($box[1], $y) + 1;
                        // crossings count most, then keeping the grid small and square, then chance
                        $score = $cross * 60 + min($len, 9) * 2 - ($w * $h - $area) * 2 - abs($w - $h) * 3 + cq_rand(12);
                        if (!$best || $score > $best[0]) $best = [$score, $ci, $x, $y, $dir, $cross];
                    }
                }
            }
        }
        if (!$best) break;
        $put($left[$best[1]], $best[2], $best[3], $best[4]);
        $crossings += $best[5];
        unset($left[$best[1]]);
    }

    // Take out answers that cross only one other, longest first, while there are too many.
    // Such an answer touches one other word, so taking it out never splits the grid.
    while (count($placed) > $want) {
        $use = cq_cell_use($placed);
        $drop = null;
        foreach ($placed as $pi => $p) {
            $cross = 0;
            foreach (cq_cells($p) as $k) if ($use[$k] > 1) $cross++;
            if ($cross > 1 || $pi === 0) continue;
            if ($drop === null || strlen($p['q']['a']) > strlen($placed[$drop]['q']['a'])) $drop = $pi;
        }
        if ($drop === null) break;
        array_splice($placed, $drop, 1);
    }

    // rebuild the grid from what's left
    $grid = [];
    $box = [PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MIN, PHP_INT_MIN];
    foreach ($placed as $p) cq_place($grid, $box, $p['q']['a'], $p['x'], $p['y'], $p['dir']);
    $use = cq_cell_use($placed);
    $crossings = count(array_filter($use, fn ($n) => $n > 1));
    $singles = 0;
    foreach ($placed as $p) {
        $cross = 0;
        foreach (cq_cells($p) as $k) if ($use[$k] > 1) $cross++;
        if ($cross < 2) $singles++;
    }
    return ['grid' => $grid, 'box' => $box, 'placed' => $placed, 'crossings' => $crossings, 'singles' => $singles];
}

// Find the gap that crosses the most placed letters (at least two) and that some unused
// answer fits. Walks every row and column of the grid; a gap ends where a square beside it
// is taken or a word in the same direction is in the way.
function cq_best_slot(array $grid, array $box, int $max, array $all, array $index, array $used, int $area): ?array
{
    $best = null;
    foreach ([0, 1] as $dir) {
        // across: lines are rows (b = y), positions run along x (a); down: the other way round
        [$b0, $b1, $a0, $a1] = $dir ? [$box[0], $box[2], $box[1], $box[3]] : [$box[1], $box[3], $box[0], $box[2]];
        $key = $dir ? fn ($a, $b) => "$b,$a" : fn ($a, $b) => "$a,$b";
        $side = $dir ? fn ($a, $b) => [($b + 1) . ",$a", ($b - 1) . ",$a"] : fn ($a, $b) => ["$a," . ($b + 1), "$a," . ($b - 1)];
        for ($b = $b0; $b <= $b1; $b++) {
            for ($start = $a1 - $max + 1; $start <= $a0 + $max - 3; $start++) {
                if (isset($grid[$key($start - 1, $b)])) continue;
                $pattern = [];
                $cross = 0;
                for ($len = 1; $len <= 13; $len++) {
                    $a = $start + $len - 1;
                    if (max($a1, $a) - min($a0, $start) + 1 > $max) break;
                    $cell = $grid[$key($a, $b)] ?? null;
                    if ($cell) {
                        if ($cell['d'][$dir]) break;
                        $pattern[$len - 1] = $cell['l'];
                        $cross++;
                    } else {
                        [$s1, $s2] = $side($a, $b);
                        if (isset($grid[$s1]) || isset($grid[$s2])) break;
                    }
                    if ($len < 3 || $cross < 2 || $cross === $len || isset($grid[$key($a + 1, $b)]) || !isset($index[$len])) continue;
                    // answers of this length with these letters in these places
                    $sets = [];
                    foreach ($pattern as $pos => $l) {
                        $set = $index[$len][$pos][$l] ?? null;
                        if (!$set) continue 2;
                        $sets[] = $set;
                    }
                    usort($sets, fn ($x, $y) => count($x) <=> count($y));
                    $fit = $sets[0];
                    for ($k = 1; $k < count($sets) && $fit; $k++) $fit = array_intersect_key($fit, $sets[$k]);
                    foreach ($fit as $ai => $_) if (isset($used[$all[$ai]['a']])) unset($fit[$ai]);
                    if (!$fit) continue;
                    $x = $dir ? $b : $start;
                    $y = $dir ? $start : $b;
                    $w = max($box[2], $x + ($dir ? 0 : $len - 1)) - min($box[0], $x) + 1;
                    $h = max($box[3], $y + ($dir ? $len - 1 : 0)) - min($box[1], $y) + 1;
                    $score = $cross * 60 + min($len, 9) * 2 - ($w * $h - $area) * 2 - abs($w - $h) * 3 + cq_rand(12);
                    if (!$best || $score > $best[0]) {
                        $ids = array_keys($fit);
                        $best = [$score, $ids[cq_rand(count($ids))], $x, $y, $dir, $cross];
                    }
                }
            }
        }
    }
    return $best;
}

// "x,y" keys of the squares a placed answer covers
function cq_cells(array $p): array
{
    $out = [];
    for ($i = 0, $len = strlen($p['q']['a']); $i < $len; $i++) {
        $out[] = ($p['dir'] ? $p['x'] : $p['x'] + $i) . ',' . ($p['dir'] ? $p['y'] + $i : $p['y']);
    }
    return $out;
}

// how many answers use each square
function cq_cell_use(array $placed): array
{
    $use = [];
    foreach ($placed as $p) foreach (cq_cells($p) as $k) $use[$k] = ($use[$k] ?? 0) + 1;
    return $use;
}

// Build the puzzle: grid size, cells, numbered clues.
function cq_build(array $pool, array $o): ?array
{
    $size = CQ_SIZES[$o['size']];
    mt_srand(crc32($o['seed'] . '|' . $o['size'] . '|' . $o['d'] . '|' . implode('.', $o['c'])));
    $pool = cq_shuffle($pool);

    // one question per answer, at most a few hundred to try
    $cands = [];
    foreach ($pool as $q) {
        if (strlen($q['a']) > $size['max']) continue;
        $cands[$q['a']] ??= $q;
        if (count($cands) >= $size['words'] * 8) break;
    }
    $cands = array_values($cands);
    if (count($cands) < 4) return null;

    // every answer that fits, indexed by length, position and letter, for filling gaps
    $all = [];
    $index = [];
    $seen = [];
    foreach ($pool as $q) {
        if (strlen($q['a']) > $size['max'] || isset($seen[$q['a']])) continue;
        $seen[$q['a']] = true;
        $ai = count($all);
        $all[] = $q;
        foreach (str_split($q['a']) as $pos => $l) $index[strlen($q['a'])][$pos][$l][$ai] = true;
    }

    $best = null;
    for ($try = 0; $try < 16; $try++) {
        $list = cq_shuffle($cands);
        // start from one of the longer answers so the rest have something to cross
        usort($list, fn ($a, $b) => (strlen($b['a']) > 6) <=> (strlen($a['a']) > 6));
        $l = cq_layout($list, $size['words'], $size['max'], $all, $index);
        $w = $l['box'][2] - $l['box'][0] + 1;
        $h = $l['box'][3] - $l['box'][1] + 1;
        $score = count($l['placed']) * 10000 + $l['crossings'] * 100 - $l['singles'] * 150 + intdiv(count($l['grid']) * 100, $w * $h);
        if (!$best || $score > $best[0]) $best = [$score, $l];
        if (count($l['placed']) >= $size['words'] && $try >= 5) break;
    }
    $l = $best[1];
    if (count($l['placed']) < 4) return null;

    [$x0, $y0] = $l['box'];
    $cols = $l['box'][2] - $x0 + 1;
    $rows = $l['box'][3] - $y0 + 1;

    // number the starts in reading order
    $starts = [];
    foreach ($l['placed'] as $p) $starts[($p['y'] - $y0) * $cols + ($p['x'] - $x0)] = true;
    ksort($starts);
    $num = [];
    $n = 0;
    foreach (array_keys($starts) as $i) $num[$i] = ++$n;

    $cells = array_fill(0, $cols * $rows, null);
    foreach ($l['grid'] as $k => $cell) {
        [$gx, $gy] = array_map('intval', explode(',', $k));
        $cells[($gy - $y0) * $cols + ($gx - $x0)] = $cell['l'];
    }

    $clues = [[], []];
    foreach ($l['placed'] as $p) {
        $start = ($p['y'] - $y0) * $cols + ($p['x'] - $x0);
        $step = $p['dir'] ? $cols : 1;
        $q = $p['q'];
        $clues[$p['dir']][] = [
            'n' => $num[$start],
            'dir' => $p['dir'],
            'cells' => array_map(fn ($i) => $start + $i * $step, range(0, strlen($q['a']) - 1)),
            'q' => $q['q'],
            'e' => $q['e'],
            'c' => $q['c'],
            'd' => $q['d'],
            'o' => cq_shuffle($q['o']),
        ];
    }
    foreach ($clues as &$list) usort($list, fn ($a, $b) => $a['n'] <=> $b['n']);
    unset($list);

    return ['cols' => $cols, 'rows' => $rows, 'cells' => $cells, 'num' => $num, 'clues' => $clues];
}
