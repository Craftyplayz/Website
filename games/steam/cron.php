<?php
// Hourly job: refreshes the Steam cache and records today's row of the profile History charts,
// so a day is recorded even when nobody opens /games/steam/profile/.
// Crontab:  0 * * * * php /path/to/site/games/steam/cron.php >> /path/to/site/games/steam/profile/cache/cron.log 2>&1
// Prints nothing on success; on failure prints why and exits 1.
// Over HTTP (for an online cron service) it needs $steamcronkey from php/config.php, sent as an X-Cron-Key header
// or ?key=. Without a matching key it's a 404. Answers "OK", or a 500 with why.

$web = PHP_SAPI !== 'cli';
$site_page = ['config' => true];
// php/config.php reads $_SESSION['time'], which only a browser visit sets, so its warning would fill the log hourly
$level = error_reporting(E_ALL & ~E_WARNING);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
error_reporting($level);
require __DIR__ . '/steam.php';

if ($web) {
    $key = isset($_SERVER['HTTP_X_CRON_KEY']) ? $_SERVER['HTTP_X_CRON_KEY'] : (isset($_GET['key']) ? $_GET['key'] : '');
    if (empty($steamcronkey) || !is_string($key) || !hash_equals($steamcronkey, $key)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    ignore_user_abort(true);
    set_time_limit(300);
}

function steam_cron_fail($why)
{
    global $web;
    if ($web) {
        http_response_code(500);
        echo $why . PHP_EOL;
    } else {
        fwrite(STDERR, date('c') . ' ' . $why . PHP_EOL);
    }
    exit(1);
}

if (empty($steamkey) || empty($steamid)) steam_cron_fail('No Steam key or id in php/config.php');

$games = steam_games();
// no visitor is waiting, so every stale game's achievements can be refreshed, not just 20
$progress = steam_progress(steam_achievements($games, 1000));
$badges = steam_api('IPlayerService/GetBadges/v1', ['steamid' => $steamid], 3000);
$badges = isset($badges['response']['badges']) ? $badges['response'] : null;

if (!$games) steam_cron_fail('GetOwnedGames returned nothing');
if (!$progress) steam_cron_fail('No achievement data');
if (!$badges) steam_cron_fail('GetBadges returned nothing');
if (steam_history($badges, $games, $progress) === false) steam_cron_fail('Could not write ' . STEAM_HISTORY);
if (steam_playtime($games) === false) steam_cron_fail('Could not write ' . STEAM_PLAYTIME);
if ($web) echo 'OK' . PHP_EOL;
