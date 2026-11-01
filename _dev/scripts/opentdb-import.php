<?php
// Cross Quiz question bank: downloads every Open Trivia DB question, keeps the ones whose
// answers fit in a crossword, and writes games/quizzes/cross-quiz/questions.json.
//
//   php _dev/scripts/opentdb-import.php                 download (about 15-20 min, the API allows one call per 5 s) then build
//   php _dev/scripts/opentdb-import.php --no-download   rebuild questions.json from the last download
//   php _dev/scripts/opentdb-import.php --raw=FILE      where the raw download is kept (default: the system temp folder)
//
// Questions are CC BY-SA 4.0 from opentdb.com; the page credits them.

$opts = getopt('', ['no-download', 'raw:']);
$raw_file = $opts['raw'] ?? sys_get_temp_dir() . '/craftyplayz-opentdb-raw.json';
$out_file = dirname(__DIR__, 2) . '/games/quizzes/cross-quiz/questions.json';

function otdb_get(string $url): array
{
    static $last = 0.0;
    for ($try = 0; $try < 6; $try++) {
        $wait = $last + 5.5 - microtime(true);
        if ($wait > 0) usleep((int) ($wait * 1e6));
        $c = curl_init($url);
        curl_setopt_array($c, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'craftyplayz.com cross quiz importer',
        ]);
        $body = curl_exec($c);
        $code = curl_getinfo($c, CURLINFO_RESPONSE_CODE);
        curl_close($c);
        $last = microtime(true);
        $json = is_string($body) ? json_decode($body, true) : null;
        if ($code === 200 && is_array($json) && ($json['response_code'] ?? 0) !== 5) return $json;
        fwrite(STDERR, "  retry ($code) $url\n");
    }
    throw new RuntimeException("Gave up on $url");
}

if (!isset($opts['no-download'])) {
    $cats = otdb_get('https://opentdb.com/api_category.php')['trivia_categories'];
    $token = otdb_get('https://opentdb.com/api_token.php?command=request')['token'];
    $all = [];
    foreach ($cats as $cat) {
        $count = otdb_get('https://opentdb.com/api_count.php?category=' . $cat['id'])['category_question_count'];
        foreach (['easy', 'medium', 'hard'] as $diff) {
            $left = (int) ($count["total_{$diff}_question_count"] ?? 0);
            while ($left > 0) {
                $n = min(50, $left);
                $r = otdb_get("https://opentdb.com/api.php?amount=$n&category={$cat['id']}&difficulty=$diff&encode=url3986&token=$token");
                if ($r['response_code'] !== 0) break; // 1 = not enough left, 4 = token has seen them all
                foreach ($r['results'] as $q) {
                    $all[] = [
                        'type' => rawurldecode($q['type']),
                        'difficulty' => rawurldecode($q['difficulty']),
                        'category' => rawurldecode($q['category']),
                        'question' => rawurldecode($q['question']),
                        'correct' => rawurldecode($q['correct_answer']),
                        'incorrect' => array_map('rawurldecode', $q['incorrect_answers']),
                    ];
                }
                $left -= count($r['results']);
            }
            fwrite(STDERR, sprintf("%-40s %-6s total so far %d\n", $cat['name'], $diff, count($all)));
        }
    }
    file_put_contents($raw_file, json_encode($all, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fwrite(STDERR, 'Saved ' . count($all) . " raw questions to $raw_file\n");
}

$all = json_decode((string) @file_get_contents($raw_file), true);
if (!is_array($all)) {
    fwrite(STDERR, "No raw download at $raw_file. Run without --no-download first.\n");
    exit(1);
}

// "Entertainment: Video Games" -> group "Entertainment", name "Video Games"
function cat_split(string $c): array
{
    return str_contains($c, ': ') ? explode(': ', $c, 2) : ['Topics', $c];
}

// Turn an answer into grid letters. Returns null when it can't go in a crossword.
function grid_answer(string $a): ?array
{
    $a = trim(html_entity_decode($a, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $a);
    if ($ascii === false) return null;
    if (preg_match('/[0-9]/', $ascii)) return null;          // years, numbers, "Area 51"
    if (preg_match('/[^A-Za-z \'\-.&]/', $ascii)) return null; // brackets, slashes, commas, symbols
    $ascii = str_replace('&', ' and ', $ascii);
    $words = preg_split('/[ \-]+/', trim(str_replace(['\'', '.'], '', $ascii)), -1, PREG_SPLIT_NO_EMPTY);
    if (count($words) > 1 && in_array(strtolower($words[0]), ['a', 'an'], true)) array_shift($words); // "A crowbar"
    if (!$words || count($words) > 3) return null;
    $letters = strtoupper(implode('', $words));
    $len = strlen($letters);
    if ($len < 3 || $len > 13) return null;
    $enum = implode(',', array_map('strlen', $words));
    return [$letters, $enum];
}

$bad_q = '/\b(which of (these|the following)|following|all of the above|none of the above|not one of|true or false|pictured|this image|shown)\b/i';
$cats = [];
$seen = [];
$keep = [];
$skipped = ['type' => 0, 'answer' => 0, 'question' => 0, 'gives away' => 0, 'duplicate' => 0];
foreach ($all as $q) {
    if ($q['type'] !== 'multiple') { $skipped['type']++; continue; }
    $g = grid_answer($q['correct']);
    if (!$g) { $skipped['answer']++; continue; }
    [$letters, $enum] = $g;
    $text = trim(preg_replace('/\s+/', ' ', html_entity_decode($q['question'], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if (preg_match($bad_q, $text)) { $skipped['question']++; continue; }
    if (stripos($text, trim($q['correct'])) !== false) { $skipped['gives away']++; continue; }
    $key = $letters . '|' . strtolower($text);
    if (isset($seen[$key])) { $skipped['duplicate']++; continue; }
    $seen[$key] = true;

    [$group, $name] = cat_split($q['category']);
    $cid = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
    $cats[$cid] ??= ['id' => $cid, 'group' => $group, 'name' => $name];
    $keep[] = [
        'a' => $letters,
        'e' => $enum,
        'q' => $text,
        'c' => $cid,
        'd' => ['easy' => 1, 'medium' => 2, 'hard' => 3][$q['difficulty']] ?? 2,
        'o' => array_values(array_map(fn ($o) => trim(html_entity_decode($o, ENT_QUOTES | ENT_HTML5, 'UTF-8')), array_merge([$q['correct']], $q['incorrect']))),
    ];
}

$counts = array_count_values(array_column($keep, 'c'));
foreach ($cats as $id => &$c) $c['n'] = $counts[$id];
unset($c);
uasort($cats, fn ($x, $y) => [$x['group'] === 'Topics' ? 0 : 1, $x['group'], $x['name']] <=> [$y['group'] === 'Topics' ? 0 : 1, $y['group'], $y['name']]);
$cats = array_values($cats);

@mkdir(dirname($out_file), 0775, true);
file_put_contents($out_file, json_encode([
    'source' => 'Open Trivia DB (opentdb.com), CC BY-SA 4.0',
    'built' => date('Y-m-d'),
    'categories' => $cats,
    'questions' => $keep,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

fwrite(STDERR, sprintf("Kept %d of %d questions (skipped: %s)\n", count($keep), count($all),
    implode(', ', array_map(fn ($k, $v) => "$k $v", array_keys($skipped), $skipped))));
foreach ($cats as $c) fwrite(STDERR, sprintf("  %-5d %s: %s\n", $c['n'], $c['group'], $c['name']));
fwrite(STDERR, "Wrote $out_file\n");
