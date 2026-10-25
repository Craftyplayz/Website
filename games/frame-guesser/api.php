<?php
// Frame Guesser game API: the Chapter Oracle's rules, with seasons for books and
// episodes for chapters. The run, its answers and its score live in the session (see lib.php).
//
//   POST {action: 'start', mode, title[, seasons: [names]]} → state
//   POST {action: 'season', season}    → {correct, answer?, revealed?, state}   step 1, only with 2+ seasons
//   POST {action: 'episode', episode}  → {correct, answer, points, revealed?, state}
//   POST {action: 'zoom'}              → state   (minimum context: show more of the frame)
//   POST {action: 'finish'}            → state   (timed: the clock ran out)
// answer is {season, episode} of the frame just played; state never holds the current frame's answer.
// Any action after the timed clock has run out ends the run and answers {timeUp: true, state}.
// Untimed runs end on a wrong season, or on a wrong episode when only one season is in play.
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

function name_ok($v): bool
{
    return $v === null || (is_string($v) && strlen($v) <= 300);
}

// Logs the current frame as played and keeps it as 'prev' so its full frame can still be shown
function finish_round(array &$run, array $fields): void
{
    $round = array_merge($run['cur'], ['pickedSeason' => null, 'pickedEpisode' => null, 'points' => 0], $fields);
    if ($run['deadline'] !== null) {
        $round['timeLeft'] = max(0, (int) round(($run['deadline'] - microtime(true)) * 1000));
    }
    if ($run['mode'] === 'minimum') {
        $round['zoomUsed'] = $run['cur']['zoom'] + 1;
    }
    $run['rounds'][] = $round;
    $run['prev'] = $run['cur'];
}

function advance(array &$run): void
{
    $groups = fg_groups($run['title'], $run['seasons']);
    $run['cur'] = $run['next'];
    $run['next'] = $groups ? fg_pick($run['title'], $groups, $run['cur']) : $run['cur'];
    $run['stage'] = count($run['seasons']) > 1 ? 'season' : 'episode';
}

function end_run(array &$run, string $why): void
{
    $run['over'] = true;
    $run['end'] = $why;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    fail(405, 'Use POST.');
}
$body = json_decode((string) file_get_contents('php://input', false, null, 0, 50000), true);
if (!is_array($body)) {
    fail(400, 'Send JSON.');
}
fg_session();
$action = $body['action'] ?? '';

if ($action === 'start') {
    $mode = $body['mode'] ?? null;
    $title = $body['title'] ?? null;
    if (!is_string($mode) || !isset(FG_MODES[$mode]) || !is_string($title)) {
        fail(400, 'Pick a show and a game mode.');
    }
    $index = fg_index();
    if ($index['error']) {
        fail(503, "Couldn't reach the frame library. Try again in a few minutes.");
    }
    $show = null;
    foreach ($index['titles'] as $t) {
        if ($t['name'] === $title) {
            $show = $t;
        }
    }
    if ($show === null) {
        fail(409, 'That show is no longer in the library. Reload the page.');
    }
    $all = array_column($show['groups'], 'name');
    $want = $mode === 'specific' ? ($body['seasons'] ?? null) : $all;
    if (!is_array($want) || count($want) > 500) {
        fail(400, 'Pick at least one season.');
    }
    $seasons = array_values(array_filter($all, fn ($s) => in_array($s, $want, true)));
    if (!$seasons) {
        fail(400, 'Pick at least one season.');
    }
    $groups = fg_groups($title, $seasons);
    $cur = fg_pick($title, $groups);
    $run = [
        'id' => bin2hex(random_bytes(8)), 'mode' => $mode, 'title' => $title, 'seasons' => $seasons, 'score' => 0,
        'deadline' => isset(FG_MODES[$mode]['seconds']) ? microtime(true) + FG_MODES[$mode]['seconds'] : null,
        'rounds' => [], 'cur' => $cur, 'next' => fg_pick($title, $groups, $cur), 'prev' => null,
        'stage' => count($seasons) > 1 ? 'season' : 'episode', 'over' => false, 'end' => null, 'saved' => false,
    ];
    $_SESSION['run'] = $run;
    out(fg_state($run));
}

$run = $_SESSION['run'] ?? null;
if (!is_array($run) || $run['over']) {
    fail(409, 'This game has ended. Start a new one.');
}

if ($action === 'finish') {
    if ($run['deadline'] === null || microtime(true) < $run['deadline'] - 1) {
        fail(409, 'The clock is still running.');
    }
    finish_round($run, ['result' => 'timeout']);
    end_run($run, 'time');
    $_SESSION['run'] = $run;
    out(fg_state($run));
}

if ($run['deadline'] !== null && microtime(true) > $run['deadline'] + 1.5) { // a little grace for the network
    finish_round($run, ['result' => 'timeout']);
    end_run($run, 'time');
    $_SESSION['run'] = $run;
    out(['timeUp' => true, 'state' => fg_state($run)]);
}

$cur = $run['cur'];
$answer = ['season' => $cur['season'], 'episode' => $cur['episode']];

if ($action === 'zoom') {
    if ($run['mode'] !== 'minimum') {
        fail(400, 'Zooming is only for minimum context.');
    }
    $run['cur']['zoom'] = min($cur['zoom'] + 1, count(FG_ZOOMS) - 1);
    $_SESSION['run'] = $run;
    out(fg_state($run));
}

if ($action === 'season') {
    $season = $body['season'] ?? null;
    if ($run['stage'] !== 'season' || !name_ok($season)) {
        fail(409, 'Pick the season first.');
    }
    if ($season === $cur['season']) {
        $run['stage'] = 'episode';
        $_SESSION['run'] = $run;
        out(['correct' => true, 'state' => fg_state($run)]);
    }
    finish_round($run, ['result' => 'wrong-season', 'pickedSeason' => $season]);
    if ($run['deadline'] !== null) {
        advance($run); // timed: a wrong season just scores nothing
    } else {
        end_run($run, 'wrong-season');
    }
    $_SESSION['run'] = $run;
    out(['correct' => false, 'answer' => $answer, 'revealed' => fg_frame_url($run, $run['prev'], true), 'state' => fg_state($run)]);
}

if ($action === 'episode') {
    $episode = $body['episode'] ?? null;
    if ($run['stage'] !== 'episode' || !is_string($episode) || strlen($episode) > 300) {
        fail(409, 'Pick the episode now.');
    }
    $correct = $episode === $cur['episode'];
    $points = !$correct ? 0 : ($run['mode'] === 'minimum' ? count(FG_ZOOMS) - $cur['zoom'] : 1);
    finish_round($run, [
        'result' => $correct ? 'correct' : 'wrong-episode', 'points' => $points,
        'pickedSeason' => count($run['seasons']) > 1 ? $cur['season'] : null, 'pickedEpisode' => $episode,
    ]);
    $run['score'] += $points;
    // With one season in play there's no season step to fail, so a wrong episode ends the run instead
    if (!$correct && $run['deadline'] === null && count($run['seasons']) === 1) {
        end_run($run, 'wrong-episode');
    } else {
        advance($run);
    }
    $_SESSION['run'] = $run;
    out(['correct' => $correct, 'answer' => $answer, 'points' => $points, 'revealed' => fg_frame_url($run, $run['prev'], true), 'state' => fg_state($run)]);
}

fail(400, 'Unknown action.');
