<?php
// One-off build of the estimated Steam history before daily recording began: writes profile/cache/backfill.json,
// which steam_backfill() and the profile page read. Inputs: profile/cache/acquired.json (appid => [date acquired,
// license name], made from the Steam licenses page), live Steam data, history.json and playtime.json.
// Run:  php games/steam/backfill.php   or open /games/steam/backfill.php?key=<$steamcronkey> (404 without it).
// Re-run only after changing acquired.json; the result is meant to stay fixed.
//
// Model (agreed with the site owner, see the Steam profile page's "Estimated" note):
// - Each game's minutes up to the day per-game recording began are placed between its acquired date and last played.
// - A share that grows with achievement completion (20% at 0%, 80% at 100%) follows the weeks achievements were
//   unlocked; the rest tapers off after purchase with a 30-day half-life.
// - Weekends (x1.7) and school holidays (x1.5; NSW until the move in Dec 2019, England after) are busier.
// - Minutes are then played out as seeded sessions (mostly 1-3 h, some 15-30 min, a few long ones), never more than
//   10 h in a day across all games. Same input, same output.
// - Badges: Years of Service levels land on each anniversary, game badge levels are spread from the game's acquired
//   date to the final level, others from joining to completion. Level comes from the summed XP.

$web = PHP_SAPI !== 'cli';
$site_page = ['config' => true];
$level = error_reporting(E_ALL & ~E_WARNING);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
error_reporting($level);
require __DIR__ . '/steam.php';

if ($web) {
    $key = isset($_GET['key']) ? $_GET['key'] : '';
    if (empty($steamcronkey) || !is_string($key) || !hash_equals($steamcronkey, $key)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    set_time_limit(600);
}

const BF_CAP = 600;          // minutes in one day, all games together
const BF_GAME_DAY = 240;     // expected minutes of one game on one day, before sessions
const BF_MOVE = '2019-12-15'; // moved from Australia (NSW) to England
const BF_FILE = __DIR__ . '/profile/cache/backfill.json';
const BF_ACQUIRED = __DIR__ . '/profile/cache/acquired.json';

function bf_fail($why)
{
    global $web;
    if ($web) http_response_code(500);
    echo $why . PHP_EOL;
    exit(1);
}

function bf_say($s)
{
    echo $s . PHP_EOL;
}

// The local calendar day of a unix time: Sydney before the move, London after
function bf_day($ts)
{
    static $syd = null, $lon = null, $move = null;
    if (!$syd) {
        $syd = new DateTimeZone('Australia/Sydney');
        $lon = new DateTimeZone('Europe/London');
        $move = strtotime(BF_MOVE . ' 12:00 UTC');
    }
    return (new DateTime('@' . (int)$ts))->setTimezone($ts < $move ? $syd : $lon)->format('Y-m-d');
}

// Easter Sunday (anonymous Gregorian algorithm), for English Easter holidays
function bf_easter($y)
{
    $a = $y % 19; $b = intdiv($y, 100); $c = $y % 100; $d = intdiv($b, 4); $e = $b % 4;
    $f = intdiv($b + 8, 25); $g = intdiv($b - $f + 1, 3); $h = (19 * $a + $b - $d - $g + 15) % 30;
    $i = intdiv($c, 4); $k = $c % 4; $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7; $m = intdiv($a + 11 * $h + 22 * $l, 451);
    $month = intdiv($h + $l - 7 * $m + 114, 31);
    $day = (($h + $l - 7 * $m + 114) % 31) + 1;
    return sprintf('%04d-%02d-%02d', $y, $month, $day);
}

// School holiday ranges [from, to] (inclusive). NSW dates are the published ones for 2016-2019; England is by rule
// (exact dates vary by council, close enough for a simulation)
function bf_holidays($fromYear, $toYear)
{
    $out = [
        ['2015-12-18', '2016-01-26'], ['2016-04-11', '2016-04-22'], ['2016-07-04', '2016-07-15'], ['2016-09-26', '2016-10-07'],
        ['2016-12-21', '2017-01-26'], ['2017-04-10', '2017-04-21'], ['2017-07-03', '2017-07-14'], ['2017-09-25', '2017-10-06'],
        ['2017-12-20', '2018-01-29'], ['2018-04-16', '2018-04-27'], ['2018-07-09', '2018-07-20'], ['2018-10-01', '2018-10-12'],
        ['2018-12-20', '2019-01-28'], ['2019-04-15', '2019-04-26'], ['2019-07-08', '2019-07-19'], ['2019-09-30', '2019-10-11'],
        ['2019-12-20', '2020-01-03'],
    ];
    for ($y = max(2020, $fromYear); $y <= $toYear; $y++) {
        $e = strtotime(bf_easter($y));
        $monday = function ($s) {
            return date('Y-m-d', strtotime('monday this week', strtotime($s)));
        };
        $out[] = [$monday("$y-02-14"), date('Y-m-d', strtotime($monday("$y-02-14") . ' +6 days'))];        // Feb half term
        $out[] = [date('Y-m-d', strtotime('-9 days', $e)), date('Y-m-d', strtotime('+8 days', $e))];          // Easter
        $out[] = [$monday(date('Y-m-d', strtotime("last monday of may $y"))), date('Y-m-d', strtotime("last monday of may $y +6 days"))];
        $out[] = ["$y-07-22", "$y-09-02"];                                                                     // summer
        $out[] = [$monday(date('Y-m-d', strtotime("last monday of october $y"))), date('Y-m-d', strtotime("last monday of october $y +6 days"))];
        $out[] = ["$y-12-20", ($y + 1) . '-01-03'];                                                            // Christmas
    }
    return $out;
}

// Seeded random numbers so the simulation is the same on every run (mt_rand with a fixed seed is stable across PHP 7.1+)
function bf_rng($seed)
{
    mt_srand($seed);
    return function () {
        return mt_rand() / mt_getrandmax();
    };
}

// Play $minutes of game $id out as sessions over the days in $want (day index => relative weight), adding to $played.
// Minutes build up in a pool by weight; a session happens once the pool can fill one and the day says yes (weekends and
// holidays say yes far more often), or when it has waited long enough. Whatever is left plays on the last day.
function bf_play(&$played, $id, $minutes, $want, $calendar, $rand)
{
    $sum = array_sum($want);
    if ($sum <= 0 || $minutes <= 0) return;
    // no game expects more than BF_GAME_DAY minutes on one day: the excess spreads evenly over its other days, so a game
    // played for years (Rocket League, Factorio) isn't squeezed into the weeks its achievements were unlocked
    foreach ($want as $i => $w) $want[$i] = $minutes * $w / $sum;
    for ($round = 0; $round < 30; $round++) {
        $over = 0;
        $free = [];
        foreach ($want as $i => $m) {
            if ($m > BF_GAME_DAY) {
                $over += $m - BF_GAME_DAY;
                $want[$i] = BF_GAME_DAY;
            } elseif ($m < BF_GAME_DAY) {
                $free[] = $i;
            }
        }
        if ($over < 1 || !$free) break;
        foreach ($free as $i) $want[$i] += $over / count($free);
    }
    $sum = array_sum($want);
    $session = function () use ($rand) {
        $r = $rand();
        if ($r < 0.2) return 15 + (int)($rand() * 16);
        if ($r < 0.88) return 60 + (int)($rand() * 121);
        return 180 + (int)($rand() * 301);
    };
    $last = max(array_keys($want));
    $pool = 0.0;
    $left = $minutes;
    $len = $session();
    foreach ($want as $i => $w) {
        if ($left <= 0) break;
        $pool += $minutes * $w / $sum;
        $ready = $pool >= $len * (0.7 + 0.6 * $rand());
        if ($i < $last && !($ready && $rand() < min(1, 0.4 * $calendar[$i] * $calendar[$i])) && $pool < 3 * $len) continue;
        // a game that needs more than a session a day (idle games) keeps pace by playing a share of its backlog
        $min = $i == $last ? $left : (int)min($left, BF_CAP, round(min($pool, max($len * (0.8 + 0.4 * $rand()), $pool - 2 * $len))));
        if ($min <= 0) continue;
        $played[$i][$id] = (isset($played[$i][$id]) ? $played[$i][$id] : 0) + $min;
        $pool -= $min;
        $left -= $min;
        $len = $session();
    }
}

// ---------- inputs ----------
if (!is_file(BF_ACQUIRED)) bf_fail('Missing ' . BF_ACQUIRED);
$acquired = json_decode((string)file_get_contents(BF_ACQUIRED), true);
if (!is_array($acquired)) bf_fail('acquired.json is not valid JSON');
if (empty($steamkey) || empty($steamid)) bf_fail('No Steam key or id in php/config.php');

$profile = steam_profile();
$games = steam_games();
$ach = steam_achievements($games, 1000);
$badges = steam_api('IPlayerService/GetBadges/v1', ['steamid' => $steamid], 3000);
$badges = isset($badges['response']['badges']) ? $badges['response'] : null;
if (!$profile || empty($profile['timecreated'])) bf_fail('GetPlayerSummaries returned nothing');
if (!$games) bf_fail('GetOwnedGames returned nothing');
if (!$badges) bf_fail('GetBadges returned nothing');

$history = steam_history();
ksort($history);
$playlog = steam_playtime();
$today = date('Y-m-d');
$created = (int)$profile['timecreated'];
$start = bf_day($created);
// History lines are estimated up to the first recorded day; per-game minutes up to the first per-game reading
$lineEnd = $history ? array_key_first($history) : $today;
$pdays = $playlog['days'];
ksort($pdays);
$playEnd = $pdays ? array_key_first($pdays) : $today;

$days = [];
for ($t = strtotime($start); ($d = date('Y-m-d', $t)) < max($lineEnd, $playEnd); $t = strtotime('+1 day', $t)) $days[] = $d;
$index = array_flip($days);
$n = count($days);
if ($n < 2) bf_fail('Recording began on the day the account was made; nothing to estimate');
bf_say('Account made ' . $start . ', lines estimated to ' . $lineEnd . ', per-game minutes to ' . $playEnd . ' (' . $n . ' days)');

// calendar weight per day
$holidays = bf_holidays((int)substr($start, 0, 4), (int)substr($playEnd, 0, 4));
$calendar = [];
foreach ($days as $i => $d) {
    $k = in_array(date('N', strtotime($d)), ['6', '7']) ? 1.7 : 1.0;
    foreach ($holidays as $h) if ($d >= $h[0] && $d <= $h[1]) {
        $k *= 1.5;
        break;
    }
    $calendar[$i] = $k;
}

// minutes per game at the start of per-game recording = today's total minus everything recorded since
$recorded = [];
foreach ($pdays as $row) foreach ($row as $id => $min) $recorded[$id] = (isset($recorded[$id]) ? $recorded[$id] : 0) + $min;

// ---------- per-game play ----------
$played = array_fill(0, $n, []);   // day index => [appid => minutes]
$fallbacks = [];
$dated = [];       // appid => date counted in Games owned (acquired, or the fallback start)
foreach ($games as $id => $g) {
    $total = (int)$g['playtime_forever'] - (isset($recorded[$id]) ? $recorded[$id] : 0);
    $unlocks = [];
    foreach (isset($ach[$id]) ? $ach[$id] : [] as $a) if ($a['achieved'] && $a['unlocktime'] > 0) $unlocks[] = bf_day($a['unlocktime']);
    sort($unlocks);
    $last = !empty($g['rtime_last_played']) ? bf_day($g['rtime_last_played']) : null;

    $from = isset($acquired[$id]) ? $acquired[$id][0] : null;
    if (!$from) {
        // no license: first unlock, else far enough before last played to fit its hours at ~2 h a day
        $from = $unlocks ? $unlocks[0] : ($last ? date('Y-m-d', strtotime($last . ' -' . max(7, (int)ceil($total / 120)) . ' days')) : null);
        $fallbacks[] = $g['name'] . ' -> ' . ($from ?: 'none');
    }
    if ($from) $dated[$id] = $from;
    if ($total <= 0 || !$from) continue;
    // achievements unlocked before the acquired date (family sharing, free weekends) mean play started earlier
    if ($unlocks && $unlocks[0] < $from) $from = $unlocks[0];
    $from = max($from, $start);
    $to = $last && $last < $playEnd ? max($last, $from) : $days[$n - 1];
    if (!isset($index[$from]) || $from > $to) continue;
    $a = $index[$from];
    $b = $index[$to];

    // expected minutes per day: achievement weeks + purchase taper, then calendar
    $done = isset($ach[$id]) ? count($unlocks) / max(1, count($ach[$id])) : 0;
    $share = $unlocks ? 0.2 + 0.6 * $done : 0;
    $achW = $taper = [];
    $sumA = $sumT = 0;
    for ($i = $a; $i <= $b; $i++) {
        $taper[$i] = pow(0.5, ($i - $a) / 30);
        $sumT += $taper[$i];
        $achW[$i] = 0;
    }
    foreach ($unlocks as $u) {
        if (!isset($index[$u])) continue;
        // the play behind an unlock happens over the week leading up to it
        for ($j = 0; $j < 7; $j++) {
            $i = $index[$u] - $j;
            if ($i < $a || $i > $b) continue;
            $achW[$i] += (7 - $j) / 28;
            $sumA += (7 - $j) / 28;
        }
    }
    // Steam knows each game's exact minutes in the past 2 weeks: those go to the last 14 days, the rest to the model
    $recent = 0;
    $r0 = $b + 1;
    if (!empty($g['playtime_2weeks'])) {
        $cut = date('Y-m-d', strtotime('-13 days'));
        $recent = (int)$g['playtime_2weeks'];
        foreach ($pdays as $d => $row) if ($d >= $cut && isset($row[$id])) $recent -= $row[$id];
        $recent = max(0, min($total, $recent));
        $r0 = max($a, isset($index[$cut]) ? $index[$cut] : $n);
        if ($r0 > $b) $recent = 0;
    }
    $main = $total - $recent;
    if ($r0 <= $a) {
        $recent += $main;
        $main = 0;
    }

    $rand = bf_rng($id); // one game at a time, so reseeding the shared generator is safe
    if ($main > 0) {
        $want = [];
        for ($i = $a; $i < min($r0, $b + 1); $i++) $want[$i] = ((1 - ($sumA ? $share : 0)) * $taper[$i] / $sumT + ($sumA ? $share * $achW[$i] / $sumA : 0)) * $calendar[$i];
        bf_play($played, $id, $main, $want, $calendar, $rand);
    }
    if ($recent > 0) {
        $want = [];
        for ($i = $r0; $i <= $b; $i++) $want[$i] = $calendar[$i];
        bf_play($played, $id, $recent, $want, $calendar, $rand);
    }
}

// ---------- 10 h a day cap: move the excess to the nearest day with room ----------
$moved = 0;
for ($i = 0; $i < $n; $i++) {
    while (($over = array_sum($played[$i]) - BF_CAP) > 0) {
        arsort($played[$i]);
        $id = array_key_first($played[$i]);
        $take = min($over, $played[$i][$id]);
        for ($step = 1; $step < 120 && $take > 0; $step++) {
            foreach ([$i + $step, $i - $step] as $j) {
                if ($j < 0 || $j >= $n || $take <= 0) continue;
                $room = BF_CAP - array_sum($played[$j]);
                if ($room < 15) continue;
                $put = min($room, $take);
                $played[$j][$id] = (isset($played[$j][$id]) ? $played[$j][$id] : 0) + $put;
                $played[$i][$id] -= $put;
                $take -= $put;
                $moved += $put;
            }
        }
        if ($take > 0) break; // nowhere to put it (cannot happen in practice); keep the minutes rather than lose them
        if ($played[$i][$id] <= 0) unset($played[$i][$id]);
    }
}

// ---------- history lines ----------
$lineN = isset($index[$lineEnd]) ? $index[$lineEnd] : $n;
$first = $history ? reset($history) : null;
$series = ['hours' => [], 'games' => [], 'achievements' => [], 'badges' => [], 'xp' => [], 'level' => []];

// hours: estimated per-game minutes summed, scaled so the line meets the first recorded reading exactly
$cum = [];
$run = 0;
for ($i = 0; $i < $lineN; $i++) {
    $run += array_sum($played[$i]);
    $cum[$i] = $run;
}
$scale = $first && $run > 0 ? $first['hours'] * 60 / $run : 1;
for ($i = 0; $i < $lineN; $i++) $series['hours'][] = round($cum[$i] * $scale / 60, 1);

// games owned: today's library, counted from each game's acquired date
$owned = array_fill(0, $lineN, 0);
foreach ($games as $id => $g) {
    $d = isset($dated[$id]) ? $dated[$id] : null;
    if (!$d) continue;
    $i = $d <= $start ? 0 : (isset($index[$d]) ? $index[$d] : $lineN);
    if ($i < $lineN) $owned[$i]++;
}
$run = 0;
foreach ($owned as $i => $c) $series['games'][] = $run += $c;

// achievements: exact, from unlock days
$per = array_fill(0, $lineN, 0);
foreach ($ach as $list) foreach ($list as $a) {
    if (!$a['achieved'] || $a['unlocktime'] <= 0) continue;
    $d = bf_day($a['unlocktime']);
    $i = $d <= $start ? 0 : (isset($index[$d]) ? $index[$d] : $lineN);
    if ($i < $lineN) $per[$i]++;
}
$run = 0;
foreach ($per as $c) $series['achievements'][] = $run += $c;

// badges, XP, level: each badge's levels dated by type
$xpDay = $countDay = array_fill(0, $lineN, 0);
$put = function ($day, $xp, $count) use (&$xpDay, &$countDay, $index, $start, $lineN) {
    $i = $day <= $start ? 0 : (isset($index[$day]) ? $index[$day] : $lineN);
    if ($i >= $lineN) return;
    $xpDay[$i] += $xp;
    $countDay[$i] += $count;
};
foreach ($badges['badges'] as $b) {
    $levels = max(1, (int)$b['level']);
    $done = (int)$b['completion_time'];
    $xp = (int)$b['xp'];
    if ($b['badgeid'] == 1 && empty($b['appid'])) {
        // Years of Service: a level on each anniversary of the account
        for ($l = 1; $l <= $levels; $l++) $put(bf_day(strtotime('+' . $l . ' years', $created)), $xp / $levels, $l == 1 ? 1 : 0);
        continue;
    }
    if ($b['badgeid'] == 13 && empty($b['appid'])) {
        // Game Collector: its level is the number of games owned, so its XP grows with each purchase
        $dates = [];
        foreach ($acquired as $row) $dates[] = $row[0];
        sort($dates);
        foreach ($dates as $i => $d) $put($d, $xp / count($dates), $i == 0 ? 1 : 0);
        continue;
    }
    $from = !empty($b['appid']) && isset($acquired[$b['appid']]) ? strtotime($acquired[$b['appid']][0] . ' 12:00') : $created;
    $levels = $levels > 50 ? 1 : $levels; // event badges count levels in the hundreds; treat as one step
    for ($l = 1; $l <= $levels; $l++) {
        $t = $levels == 1 || $done <= $from ? $done : $from + ($done - $from) * $l / $levels;
        $put(bf_day($t), $xp / $levels, $l == 1 ? 1 : 0);
    }
}
$run = $runC = 0;
for ($i = 0; $i < $lineN; $i++) {
    $run += $xpDay[$i];
    $runC += $countDay[$i];
    $series['xp'][] = (int)round($run);
    $series['badges'][] = $runC;
    $series['level'][] = steam_level((int)round($run));
}

// ---------- write ----------
$out = ['built' => date('c'), 'start' => $start, 'lines_to' => $lineEnd, 'played_to' => $playEnd, 'series' => $series, 'played' => []];
foreach ($played as $i => $row) if ($row) {
    arsort($row);
    $out['played'][$days[$i]] = $row;
}
if (@file_put_contents(BF_FILE, json_encode($out), LOCK_EX) === false) bf_fail('Could not write ' . BF_FILE);

bf_say('Games dated from licenses: ' . count(array_intersect_key($acquired, $games)) . ' of ' . count($games));
if ($fallbacks) bf_say('No license, dated another way: ' . implode('; ', $fallbacks));
bf_say('Days with play: ' . count($out['played']) . ', hours placed: ' . round(array_sum(array_map('array_sum', $played)) / 60) . ', moved by the 10 h cap: ' . round($moved / 60) . ' h');
if ($first) bf_say('Joins recording on ' . $lineEnd . ': games ' . end($series['games']) . ' vs ' . $first['games'] . ', achievements ' . end($series['achievements']) . ' vs ' . $first['achievements'] . ', xp ' . end($series['xp']) . ' vs ' . $first['xp']);
bf_say('Wrote ' . BF_FILE . ' (' . round(filesize(BF_FILE) / 1024) . ' KB)');
