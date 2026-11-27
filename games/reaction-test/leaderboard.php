<?php
// Global leaderboard for Reaction Test. Each name keeps its best (lowest) average.
// Stored as JSON in data/, which data/.htaccess blocks from direct download.
//
//   GET  leaderboard.php                      -> { entries: [...] }
//   POST leaderboard.php {name, score, tries} -> { entries, rank, best, improved }
require __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const BOARD_SIZE       = 100;  // entries kept
const SHOWN            = 20;   // entries sent back
const POSTS_PER_MINUTE = 12;   // per visitor address
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

// Fastest first; ties go to whoever got there first
function sortBoard(array &$board): void
{
    usort($board, fn ($a, $b) => $a['score'] <=> $b['score'] ?: strcmp($a['date'], $b['date']));
}

function publicEntries(array $board): array
{
    $out = [];
    foreach (array_slice($board, 0, SHOWN) as $i => $entry) {
        $out[] = ['rank' => $i + 1] + $entry;
    }
    return $out;
}

// Opens the store under a lock, passes its data to $fn, and saves afterwards when $write
function withStore(string $store, bool $write, callable $fn)
{
    $handle = @fopen($store, 'c+');
    if (!$handle || !flock($handle, $write ? LOCK_EX : LOCK_SH)) {
        fail(503, 'The leaderboard is unavailable right now.');
    }
    $raw  = stream_get_contents($handle);
    $data = $raw ? json_decode($raw, true) : null;
    if (!is_array($data)) {
        $data = [];
    }
    $data += ['entries' => [], 'posts' => []];
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

if (!is_dir($dataDir) && !@mkdir($dataDir, 0755, true)) {
    fail(503, 'The leaderboard is unavailable right now.');
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    if (!is_file($store)) {
        echo json_encode(['entries' => []]);
        exit;
    }
    $board = withStore($store, false, fn (array $data) => $data['entries']);
    echo json_encode(['entries' => publicEntries($board)], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method !== 'POST') {
    fail(405, 'Use GET or POST.');
}

$raw = file_get_contents('php://input', false, null, 0, MAX_BODY + 1);
if (strlen((string) $raw) > MAX_BODY) {
    fail(413, 'That is too much data.');
}
$checked = rt_check(json_decode((string) $raw, true));
if (is_string($checked)) {
    fail(400, $checked);
}
['name' => $name, 'score' => $score] = $checked;

$visitor = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
$now     = time();

$response = withStore($store, true, function (array &$data) use ($name, $score, $visitor, $now) {
    // Throttle per visitor; old timestamps are dropped as we go
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

    $board    = $data['entries'];
    $key      = lower($name);
    $existing = null;
    foreach ($board as $i => $entry) {
        if (lower($entry['name']) === $key) {
            $existing = $i;
            break;
        }
    }

    $improved = $existing === null || $score < $board[$existing]['score'];
    if ($improved) {
        $entry = ['name' => $name, 'score' => $score, 'date' => gmdate('Y-m-d\TH:i:s\Z', $now)];
        if ($existing !== null) {
            $board[$existing] = $entry;
        } else {
            $board[] = $entry;
        }
        sortBoard($board);
        $board = array_slice($board, 0, BOARD_SIZE);
        $data['entries'] = $board;
    }

    $rank = null;
    $best = null;
    foreach ($board as $i => $entry) {
        if (lower($entry['name']) === $key) {
            $rank = $i + 1;
            $best = $entry['score'];
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
