<?php
// Shared leaderboards for Frame Guesser: one board per set of titles and game mode,
// stored like the Chapter Oracle's (data/, blocked by data/.htaccess). Each name keeps
// its best score per board. The score and replay come from the finished run in the
// session, never from the browser, so they can't be faked.
//
//   GET  leaderboard.php?titles=A|B&mode=normal → { entries: [...] }
//   GET  leaderboard.php?replay=<id>            → { replay: {...} }
//   POST leaderboard.php  {name}                → { entries, rank, best, improved }
require __DIR__ . '/lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const BOARD_SIZE       = 100;
const SHOWN            = 50;
const POSTS_PER_MINUTE = 12;

$dataDir   = __DIR__ . '/data';
$store     = $dataDir . '/leaderboards.json';
$replayDir = $dataDir . '/replays';

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

function replayPath(string $dir, string $id): string
{
    return $dir . '/' . $id . '.json';
}

function deleteReplay(string $dir, array $entry): void
{
    if (isset($entry['replay']) && preg_match('/^[a-f0-9]{16}$/', $entry['replay'])) {
        @unlink(replayPath($dir, $entry['replay']));
    }
}

if (!is_dir($dataDir) && !@mkdir($dataDir, 0755, true)) {
    fail(503, 'The leaderboard is unavailable right now.');
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET' && isset($_GET['replay'])) {
    $id = $_GET['replay'];
    $raw = is_string($id) && preg_match('/^[a-f0-9]{16}$/', $id) ? @file_get_contents(replayPath($replayDir, $id)) : false;
    $replay = $raw ? json_decode($raw, true) : null;
    if (!is_array($replay)) {
        fail(404, 'That replay is no longer available.');
    }
    echo json_encode(['replay' => $replay], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($method === 'GET') {
    $titles = $_GET['titles'] ?? null;
    $mode   = $_GET['mode'] ?? null;
    if (!is_string($titles) || strlen($titles) > 20000 || !is_string($mode) || !isset(FG_MODES[$mode])) {
        fail(400, 'Unknown titles or game mode.');
    }
    $board = is_file($store) ? withStore($store, false, fn (array $data) => $data['boards'][$titles][$mode] ?? []) : [];
    echo json_encode(['entries' => publicEntries($board)], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method !== 'POST') {
    fail(405, 'Use GET or POST.');
}

$body = json_decode((string) file_get_contents('php://input', false, null, 0, 2000), true);
$name = is_array($body) && is_string($body['name'] ?? null) ? $body['name'] : '';
$name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
if (!preg_match('/^[^\p{C}]{1,20}$/u', $name)) {
    fail(400, 'Names must be 1 to 20 characters.');
}

fg_session();
$run = $_SESSION['run'] ?? null;
if (!is_array($run) || !$run['over']) {
    fail(409, 'Finish a game first.');
}
if ($run['saved']) {
    fail(409, 'This game is already on the leaderboard.');
}
if ($run['score'] < 1) {
    fail(400, 'Score at least one point to join the leaderboard.');
}
$key     = implode('|', $run['titles']); // shortcut: a title with | in its name could share a board; fine unless that happens
$mode    = $run['mode'];
$score   = $run['score'];
$replay  = ['v' => 1, 'mode' => $mode, 'titles' => $run['titles'], 'end' => $run['end'], 'rounds' => fg_public_rounds($run['rounds'])];
$visitor = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
$now     = time();
if (!is_dir($replayDir)) {
    @mkdir($replayDir, 0755, true);
}

$response = withStore($store, true, function (array &$data) use ($key, $mode, $name, $score, $replay, $replayDir, $visitor, $now) {
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

    $board    = $data['boards'][$key][$mode] ?? [];
    $lname    = lower($name);
    $existing = null;
    foreach ($board as $i => $entry) {
        if (lower($entry['name']) === $lname) {
            $existing = $i;
            break;
        }
    }

    $improved = $existing === null || $score > $board[$existing]['score'];
    if ($improved) {
        $entry = ['name' => $name, 'score' => $score, 'date' => gmdate('Y-m-d\TH:i:s\Z', $now)];
        $id = bin2hex(random_bytes(8));
        if (@file_put_contents(replayPath($replayDir, $id), json_encode($replay, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX)) {
            $entry['replay'] = $id;
        }
        if ($existing !== null) {
            deleteReplay($replayDir, $board[$existing]); // the old best's replay
            $board[$existing] = $entry;
        } else {
            $board[] = $entry;
        }
        sortBoard($board);
        foreach (array_slice($board, BOARD_SIZE) as $dropped) {
            deleteReplay($replayDir, $dropped);
        }
        $board = array_slice($board, 0, BOARD_SIZE);
        $data['boards'][$key][$mode] = $board;
    }

    $rank = null;
    $best = null;
    foreach ($board as $i => $entry) {
        if (lower($entry['name']) === $lname) {
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
$_SESSION['run']['saved'] = true;
echo json_encode($response, JSON_UNESCAPED_UNICODE);
