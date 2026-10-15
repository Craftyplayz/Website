<?php
// Shared leaderboards for the Chapter Oracle: one board per book series and game mode.
// Each name keeps its best score per board. Stored as JSON in data/, which
// data/.htaccess blocks from being downloaded directly.
//
//   GET  leaderboard.php?series=Harry%20Potter&mode=timed   → { entries: [...] }
//   POST leaderboard.php  {series, mode, name, score[, books]} → { entries, rank, best, improved }
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const MODES            = ['normal', 'timed', 'minimum', 'specific', 'descriptor'];
const HALF_POINT_MODES = ['descriptor']; // hints there are worth half a point
const BOARD_SIZE       = 100;   // entries kept per board
const SHOWN            = 50;    // entries sent back
const MAX_SCORE        = 1000000;
const POSTS_PER_MINUTE = 12;    // per visitor address

$dataDir = __DIR__ . '/data';
$store   = $dataDir . '/leaderboards.json';

function fail(int $status, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

// A series is a folder in books/ holding at least one EPUB (same rule as series.php)
function validSeries($series): bool
{
    if (!is_string($series) || $series === '' || $series[0] === '.' || strpbrk($series, "/\\\0") !== false) {
        return false;
    }
    $dir = __DIR__ . '/books/' . $series;
    if (!is_dir($dir)) {
        return false;
    }
    foreach (scandir($dir) ?: [] as $file) {
        if ($file[0] !== '.' && strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'epub') {
            return true;
        }
    }
    return false;
}

function lower(string $s): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

// Highest score first; ties go to whoever got there first
function sortBoard(array &$board): void
{
    usort($board, fn ($a, $b) => $b['score'] <=> $a['score'] ?: strcmp($a['date'], $b['date']));
}

// Opens the store under a lock, passes its data to $fn, and saves it afterwards when $write
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
    $data += ['boards' => [], 'posts' => []];
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
    foreach (array_slice($board, 0, SHOWN) as $i => $entry) {
        $out[] = ['rank' => $i + 1] + $entry;
    }
    return $out;
}

if (!is_dir($dataDir) && !@mkdir($dataDir, 0755, true)) {
    fail(503, 'The leaderboard is unavailable right now.');
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $series = $_GET['series'] ?? null;
    $mode   = $_GET['mode'] ?? null;
    if (!validSeries($series) || !in_array($mode, MODES, true)) {
        fail(400, 'Unknown series or game mode.');
    }
    if (!is_file($store)) {
        echo json_encode(['entries' => []]);
        exit;
    }
    $board = withStore($store, false, fn (array $data) => $data['boards'][$series][$mode] ?? []);
    echo json_encode(['entries' => publicEntries($board)], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method !== 'POST') {
    fail(405, 'Use GET or POST.');
}

$raw = file_get_contents('php://input', false, null, 0, 2048);
$body = json_decode((string) $raw, true);
if (!is_array($body)) {
    fail(400, 'Send the score as JSON.');
}

$series = $body['series'] ?? null;
$mode   = $body['mode'] ?? null;
$score  = $body['score'] ?? null;
$books  = $body['books'] ?? null;
$name   = is_string($body['name'] ?? null) ? $body['name'] : '';
$name   = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

if (!validSeries($series) || !in_array($mode, MODES, true)) {
    fail(400, 'Unknown series or game mode.');
}
// Whole points, or exact halves in HALF_POINT_MODES
$halfOk = is_float($score) && in_array($mode, HALF_POINT_MODES, true) && fmod($score * 2, 1.0) === 0.0;
if (!(is_int($score) || $halfOk) || $score < 0.5 || $score > MAX_SCORE) {
    fail(400, 'That score is not valid.');
}
if (is_float($score) && floor($score) === $score) {
    $score = (int) $score; // JSON 2.0 arrives as a float
}
if (!preg_match('/^[^\p{C}]{1,20}$/u', $name)) {
    fail(400, 'Names must be 1 to 20 characters.');
}
if ($mode === 'specific' ? (!is_int($books) || $books < 1 || $books > 500) : $books !== null) {
    fail(400, 'Book count is only sent for specific books mode.');
}

$visitor = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
$now     = time();

$response = withStore($store, true, function (array &$data) use ($series, $mode, $name, $score, $books, $visitor, $now) {
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

    $board    = $data['boards'][$series][$mode] ?? [];
    $key      = lower($name);
    $existing = null;
    foreach ($board as $i => $entry) {
        if (lower($entry['name']) === $key) {
            $existing = $i;
            break;
        }
    }

    $improved = $existing === null || $score > $board[$existing]['score'];
    if ($improved) {
        $entry = ['name' => $name, 'score' => $score, 'date' => gmdate('Y-m-d\TH:i:s\Z', $now)];
        if ($books !== null) {
            $entry['books'] = $books;
        }
        if ($existing !== null) {
            $board[$existing] = $entry;
        } else {
            $board[] = $entry;
        }
        sortBoard($board);
        $board = array_slice($board, 0, BOARD_SIZE);
        $data['boards'][$series][$mode] = $board;
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
