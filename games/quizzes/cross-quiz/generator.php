<?php
// Cross Quiz grid builder. The same seed and options always give the same puzzle, so a
// link with ?seed= rebuilds it exactly. Questions come from questions.json (see
// _dev/scripts/opentdb-import.php).

const CQ_SIZES = [
    's' => ['name' => 'Small', 'words' => 8, 'max' => 11],
    'm' => ['name' => 'Medium', 'words' => 14, 'max' => 15],
    'l' => ['name' => 'Large', 'words' => 20, 'max' => 19],
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

// One attempt at a layout from a shuffled list of candidate questions.
function cq_layout(array $cands, int $want, int $max): array
{
    $grid = [];
    $placed = [];
    $first = $cands[0];
    $box = [0, 0, 0, 0];
    cq_place($grid, $box, $first['a'], 0, 0, 0);
    $placed[] = ['q' => $first, 'x' => 0, 'y' => 0, 'dir' => 0];
    $box = [0, 0, strlen($first['a']) - 1, 0];
    $left = array_slice($cands, 1);
    $crossings = 0;

    for ($pass = 0; $pass < 3 && count($placed) < $want; $pass++) {
        $next = [];
        foreach ($left as $q) {
            if (count($placed) >= $want) break;
            $word = $q['a'];
            $best = null;
            foreach ($grid as $k => $cell) {
                [$gx, $gy] = array_map('intval', explode(',', $k));
                for ($i = 0, $len = strlen($word); $i < $len; $i++) {
                    if ($word[$i] !== $cell['l']) continue;
                    foreach ([0, 1] as $dir) {
                        if ($cell['d'][$dir]) continue;
                        $x = $dir ? $gx : $gx - $i;
                        $y = $dir ? $gy - $i : $gy;
                        $cross = cq_fits($grid, $word, $x, $y, $dir, $box, $max);
                        if ($cross < 1) continue;
                        $w = max($box[2], $x + ($dir ? 0 : $len - 1)) - min($box[0], $x) + 1;
                        $h = max($box[3], $y + ($dir ? $len - 1 : 0)) - min($box[1], $y) + 1;
                        // more crossings first, then a squarer, smaller grid, then chance
                        $score = $cross * 1000 - $w * $h - abs($w - $h) * 4 + cq_rand(20);
                        if (!$best || $score > $best[0]) $best = [$score, $x, $y, $dir, $cross];
                    }
                }
            }
            if ($best) {
                cq_place($grid, $box, $word, $best[1], $best[2], $best[3]);
                $placed[] = ['q' => $q, 'x' => $best[1], 'y' => $best[2], 'dir' => $best[3]];
                $crossings += $best[4];
            } else {
                $next[] = $q;
            }
        }
        $left = $next;
    }
    return ['grid' => $grid, 'box' => $box, 'placed' => $placed, 'crossings' => $crossings];
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

    $best = null;
    for ($try = 0; $try < 16; $try++) {
        $list = cq_shuffle($cands);
        // start from one of the longer answers so the rest have something to cross
        usort($list, fn ($a, $b) => (strlen($b['a']) > 6) <=> (strlen($a['a']) > 6));
        $l = cq_layout($list, $size['words'], $size['max']);
        $w = $l['box'][2] - $l['box'][0] + 1;
        $h = $l['box'][3] - $l['box'][1] + 1;
        $score = count($l['placed']) * 10000 + $l['crossings'] * 100 + intdiv(count($l['grid']) * 100, $w * $h);
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
