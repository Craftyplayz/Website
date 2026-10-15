<?php
// Shared leaderboards for the Chapter Oracle: one board per book series and game mode.
// Each name keeps its best score per board. Stored as JSON in data/, which
// data/.htaccess blocks from being downloaded directly.
//
// A score can carry a replay of the run (each question shown and how it was
// answered). Replays are kept as separate files in data/replays/, one per
// leaderboard entry, so the boards themselves stay small.
//
//   GET  leaderboard.php?series=Harry%20Potter&mode=timed   → { entries: [...] }
//   GET  leaderboard.php?replay=<id>                        → { replay: {...} }
//   POST leaderboard.php  {series, mode, name, score[, books][, replay]}
//                                                           → { entries, rank, best, improved }
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const MODES            = ['normal', 'timed', 'minimum', 'specific', 'descriptor'];
const HALF_POINT_MODES = ['descriptor']; // hints there are worth half a point
const REPLAY_MODES     = ['normal', 'timed', 'specific', 'descriptor'];
const BOARD_SIZE       = 100;   // entries kept per board
const SHOWN            = 50;    // entries sent back
const MAX_SCORE        = 1000000;
const POSTS_PER_MINUTE = 12;    // per visitor address
const MAX_BODY         = 1500000; // bytes; a long run's replay holds every passage shown
const MAX_QUESTIONS    = 1000;

$dataDir   = __DIR__ . '/data';
$store     = $dataDir . '/leaderboards.json';
$replayDir = $dataDir . '/replays';

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

// array_is_list() without needing PHP 8.1
function isList(array $a): bool
{
    return $a === [] || array_keys($a) === range(0, count($a) - 1);
}

function str($v, int $max): ?string
{
    return is_string($v) && strlen($v) <= $max ? $v : null;
}

function strList($v, int $count, int $max): ?array
{
    if (!is_array($v) || !isList($v) || count($v) > $count) {
        return null;
    }
    foreach ($v as $s) {
        if (str($s, $max) === null) {
            return null;
        }
    }
    return $v;
}

function intIn($v, int $min, int $max): bool
{
    return is_int($v) && $v >= $min && $v <= $max;
}

// Limits are in bytes: curly quotes and accents take more than one each.
// Keeps only the known fields of a submitted replay, or null if anything is
// malformed or its points don't add up to the score. A bad replay never
// blocks the score itself; it's just saved without one.
function cleanReplay($replay, string $mode, $score): ?array
{
    if (!in_array($mode, REPLAY_MODES, true) || !is_array($replay)) {
        return null;
    }
    $books = $replay['books'] ?? null;
    $qs    = $replay['questions'] ?? null;
    $end   = $replay['end'] ?? null;
    if (!is_array($books) || !isList($books) || !$books || count($books) > 500
        || !is_array($qs) || !isList($qs) || count($qs) > MAX_QUESTIONS
        || !in_array($end, ['wrong-book', 'time', 'complete', 'lives'], true)) {
        return null;
    }

    $out = ['v' => 1, 'end' => $end, 'books' => [], 'questions' => []];
    foreach ($books as $b) {
        $title = str($b['title'] ?? null, 1000);
        if (!intIn($b['index'] ?? null, 0, 499) || $title === null) {
            return null;
        }
        $out['books'][] = ['index' => $b['index'], 'title' => $title];
    }
    if ($mode === 'timed') {
        if (!intIn($replay['duration'] ?? null, 1, 3600)) {
            return null;
        }
        $out['duration'] = $replay['duration'];
    }

    $total = 0;
    foreach ($qs as $q) {
        if (!is_array($q)) {
            return null;
        }
        $points  = $q['points'] ?? null;
        $passage = str($q['passage'] ?? null, 12000);
        $chapter = str($q['chapter'] ?? null, 1000);
        if (!(is_int($points) || is_float($points)) || $points < 0 || $points > MAX_SCORE
            || $passage === null || $chapter === null || !intIn($q['book'] ?? null, 0, 499)) {
            return null;
        }
        $total += $points;
        $clean = ['passage' => $passage, 'book' => $q['book'], 'chapter' => $chapter];

        if ($mode === 'descriptor') {
            $answer = str($q['answer'] ?? null, 200);
            $guess  = str($q['guess'] ?? null, 200);
            $before = strList($q['before'] ?? [], 10, 12000);
            $after  = strList($q['after'] ?? [], 10, 12000);
            if ($answer === null || $guess === null || $before === null || $after === null
                || !intIn($q['start'] ?? null, 0, 12000) || !is_bool($q['hint'] ?? null)
                || !in_array($q['result'] ?? null, ['correct', 'typo', 'wrong'], true)) {
                return null;
            }
            $clean += ['start' => $q['start'], 'answer' => $answer, 'guess' => $guess,
                       'before' => $before, 'after' => $after, 'hint' => $q['hint'], 'result' => $q['result']];
        } else {
            $pickedBook    = $q['pickedBook'] ?? null;
            $pickedChapter = $q['pickedChapter'] ?? null;
            if (!in_array($q['result'] ?? null, ['correct', 'wrong-chapter', 'wrong-book', 'timeout'], true)
                || ($pickedBook !== null && !intIn($pickedBook, 0, 499))
                || ($pickedChapter !== null && str($pickedChapter, 1000) === null)) {
                return null;
            }
            $clean += ['pickedBook' => $pickedBook, 'pickedChapter' => $pickedChapter, 'result' => $q['result']];
            if ($mode === 'timed') {
                if (!intIn($q['timeLeft'] ?? null, 0, 3600000)) {
                    return null;
                }
                $clean['timeLeft'] = $q['timeLeft'];
            }
        }
        $clean['points'] = $points;
        $out['questions'][] = $clean;
    }
    return abs($total - $score) < 0.001 ? $out : null;
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
    echo json_encode(['replay' => $replay], JSON_UNESCAPED_UNICODE);
    exit;
}

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

$raw = file_get_contents('php://input', false, null, 0, MAX_BODY + 1);
if (strlen((string) $raw) > MAX_BODY) {
    fail(413, 'That run is too long to save.');
}
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

$replay = cleanReplay($body['replay'] ?? null, $mode, $score);
if ($replay !== null && !is_dir($replayDir) && !@mkdir($replayDir, 0755, true)) {
    $replay = null; // can't store it; still save the score
}

$visitor = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
$now     = time();

$response = withStore($store, true, function (array &$data) use ($series, $mode, $name, $score, $books, $replay, $replayDir, $visitor, $now) {
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
        if ($replay !== null) {
            $id = bin2hex(random_bytes(8));
            if (@file_put_contents(replayPath($replayDir, $id), json_encode($replay, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX)) {
                $entry['replay'] = $id;
            }
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
