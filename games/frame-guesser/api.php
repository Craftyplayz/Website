<?php
// Frame Guesser game API. The run, its answers and its score live in the session (see lib.php).
//
//   POST {action: 'start', mode, titles: [names]}           → state
//   POST {action: 'guess', title, season, episode}           → {result, state}
//   POST {action: 'finish'}  (timed mode, when the clock ends) → state
// state never holds the current frame's answer; result reveals the round just played.
require __DIR__ . '/lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function fail(int $status, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

function out(array $data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function name_or_null($v): bool
{
    return $v === null || (is_string($v) && strlen($v) <= 300);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    fail(405, 'Use POST.');
}
$body = json_decode((string) file_get_contents('php://input', false, null, 0, 20000), true);
if (!is_array($body)) {
    fail(400, 'Send JSON.');
}
fg_session();
$action = $body['action'] ?? '';

if ($action === 'start') {
    $mode = $body['mode'] ?? null;
    $want = $body['titles'] ?? null;
    if (!is_string($mode) || !isset(FG_MODES[$mode]) || !is_array($want) || !$want || count($want) > 200) {
        fail(400, 'Pick a game mode and at least one title.');
    }
    $index = fg_index();
    if ($index['error']) {
        fail(503, "Couldn't reach the frame library. Try again in a few minutes.");
    }
    $titles = array_values(array_filter($index['titles'], fn ($t) => in_array($t['name'], $want, true)));
    if (count($titles) !== count(array_unique($want, SORT_REGULAR))) {
        fail(409, 'The list of titles has changed. Reload the page and pick again.');
    }
    $names = array_column($titles, 'name');
    sort($names, SORT_NATURAL | SORT_FLAG_CASE);
    $cur = fg_pick($titles);
    $run = [
        'id' => bin2hex(random_bytes(8)), 'mode' => $mode, 'titles' => $names, 'score' => 0,
        'lives' => FG_MODES[$mode]['lives'] ?? null, 'deadline' => isset(FG_MODES[$mode]['seconds']) ? microtime(true) + FG_MODES[$mode]['seconds'] : null,
        'rounds' => [], 'cur' => $cur, 'next' => fg_pick($titles, $cur), 'over' => false, 'end' => null, 'saved' => false,
    ];
    $_SESSION['run'] = $run;
    out(fg_state($run));
}

$run = $_SESSION['run'] ?? null;
if (!is_array($run) || $run['over']) {
    fail(409, 'This game has ended. Start a new one.');
}
$timeUp = $run['deadline'] !== null && microtime(true) > $run['deadline'] + 1.5; // a little grace for the network

if ($action === 'finish') {
    if ($run['deadline'] === null || microtime(true) < $run['deadline'] - 1) {
        fail(409, 'The clock is still running.');
    }
    $run['over'] = true;
    $run['end'] = 'time';
    $_SESSION['run'] = $run;
    out(fg_state($run));
}

if ($action !== 'guess') {
    fail(400, 'Unknown action.');
}
if ($timeUp) {
    $run['over'] = true;
    $run['end'] = 'time';
    $_SESSION['run'] = $run;
    out(['result' => null, 'state' => fg_state($run)]);
}
$guess = ['title' => $body['title'] ?? null, 'season' => $body['season'] ?? null, 'episode' => $body['episode'] ?? null];
if (!is_string($guess['title']) || !name_or_null($guess['season']) || !is_string($guess['episode']) || strlen($guess['title'] . $guess['episode']) > 600) {
    fail(400, 'That guess is not valid.');
}

$cur = $run['cur'];
$rightSeason = $guess['title'] === $cur['title'] && $guess['season'] === $cur['season'];
$exact = $rightSeason && $guess['episode'] === $cur['episode'];
$points = $exact ? FG_EXACT : ($rightSeason && $cur['season'] !== null ? FG_SEASON : 0);

$run['score'] += $points;
$run['rounds'][] = $cur + ['guess' => $guess, 'points' => $points];
if ($run['lives'] !== null && $points === 0) {
    $run['lives']--;
}
$mode = FG_MODES[$run['mode']];
if ($run['lives'] === 0) {
    [$run['over'], $run['end']] = [true, 'lives'];
} elseif (isset($mode['rounds']) && count($run['rounds']) >= $mode['rounds']) {
    [$run['over'], $run['end']] = [true, 'complete'];
} else {
    $titles = array_values(array_filter(fg_index()['titles'], fn ($t) => in_array($t['name'], $run['titles'], true)));
    $run['cur'] = $run['next'];
    $run['next'] = $titles ? fg_pick($titles, $run['cur']) : $run['cur'];
}
$_SESSION['run'] = $run;

out([
    'result' => ['title' => $cur['title'], 'season' => $cur['season'], 'episode' => $cur['episode'], 'points' => $points, 'kind' => $exact ? 'exact' : ($points ? 'season' : 'miss')],
    'state' => fg_state($run),
]);
