<?php
// Global leaderboard for Typing Defence. Each name keeps its best score; top 100 kept, top 20 sent.
// Stored in data/leaderboard.json (data/.htaccess blocks direct downloads).
//   GET  leaderboard.php                              -> { entries: [...] }
//   POST leaderboard.php {name, score, wpm, duration} -> { entries, rank, best, improved }
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require __DIR__ . '/validate.php';

const BOARD_SIZE       = 100;
const SHOWN            = 20;
const POSTS_PER_MINUTE = 12; // per visitor address
const MAX_BODY         = 2000;

$dataDir = __DIR__ . '/data';
$store   = $dataDir . '/leaderboard.json';

function fail(int $status, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

function lower(string $s): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

function withStore(string $store, bool $write, callable $fn)
{
    $handle = @fopen($store, 'c+');
    if (!$handle || !flock($handle, $write ? LOCK_EX : LOCK_SH)) {
        fail(503, 'The leaderboard is unavailable right now.');
    }
    $raw  = stream_get_contents($handle);
    $data = $raw ? json_decode($raw, true) : null;
    $data = is_array($data) ? $data + ['board' => [], 'posts' => []] : ['board' => [], 'posts' => []];
    $result = $fn($data);
    if ($write) {
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fflush($handle);
    }
    flock($handle, LOCK_UN);
    fclose($handle);
    return $result;
}

function publicEntries(array $board): array
{
    $out = [];
    foreach (array_slice($board, 0, SHOWN) as $i => $e) {
        $out[] = ['rank' => $i + 1] + $e;
    }
    return $out;
}

if (!is_dir($dataDir) && !@mkdir($dataDir, 0755, true)) {
    fail(503, 'The leaderboard is unavailable right now.');
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    $board = is_file($store) ? withStore($store, false, fn (array $d) => $d['board']) : [];
    echo json_encode(['entries' => publicEntries($board)], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($method !== 'POST') {
    fail(405, 'Use GET or POST.');
}

$raw = file_get_contents('php://input', false, null, 0, MAX_BODY + 1);
if (strlen((string) $raw) > MAX_BODY) {
    fail(413, 'That request is too large.');
}
$v = td_validate(json_decode((string) $raw, true));
if (is_string($v)) {
    fail(400, $v);
}
[$name, $score, $wpm, $secs] = $v;

$visitor = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
$now     = time();

$response = withStore($store, true, function (array &$data) use ($name, $score, $wpm, $visitor, $now) {
    foreach ($data['posts'] as $who => $times) {
        $data['posts'][$who] = array_values(array_filter($times, fn ($t) => $t > $now - 60));
        if (!$data['posts'][$who]) {
            unset($data['posts'][$who]);
        }
    }
    if (count($data['posts'][$visitor] ?? []) >= POSTS_PER_MINUTE) {
        return null;
    }
    $data['posts'][$visitor][] = $now;

    $board = $data['board'];
    $key   = lower($name);
    $at    = null;
    foreach ($board as $i => $e) {
        if (lower($e['name']) === $key) {
            $at = $i;
            break;
        }
    }
    $improved = $at === null || $score > $board[$at]['score'];
    if ($improved) {
        $entry = ['name' => $name, 'score' => $score, 'wpm' => $wpm, 'date' => gmdate('Y-m-d\TH:i:s\Z', $now)];
        if ($at !== null) {
            $board[$at] = $entry;
        } else {
            $board[] = $entry;
        }
        usort($board, fn ($a, $b) => $b['score'] <=> $a['score'] ?: strcmp($a['date'], $b['date']));
        $board = array_slice($board, 0, BOARD_SIZE);
        $data['board'] = $board;
    }
    $rank = $best = null;
    foreach ($board as $i => $e) {
        if (lower($e['name']) === $key) {
            $rank = $i + 1;
            $best = $e['score'];
            break;
        }
    }
    return ['entries' => publicEntries($board), 'rank' => $rank, 'best' => $best, 'improved' => $improved];
});

if ($response === null) {
    http_response_code(429);
    header('Retry-After: 60');
    echo json_encode(['error' => 'Too many scores at once. Try again in a minute.']);
    exit;
}
echo json_encode($response, JSON_UNESCAPED_UNICODE);
