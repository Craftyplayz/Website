<?php
// Helpers shared by the Steam pages (/games/steam/). Pages set 'config' => true for $steamkey and $steamid.

// The cache lives in the system temp folder so no data file lands in the repo.
define('STEAM_CACHE', sys_get_temp_dir() . '/craftyplayz-steam');
// Games that never show anywhere in the section
const STEAM_HIDE = [730, 682130, 469820, 1085750, 459820, 1463120];
const STEAM_STATES = ['Offline', 'Online', 'Busy', 'Away', 'Snooze', 'Looking to trade', 'Looking to play'];
const STEAM_DECK = [1 => 'Unsupported', 2 => 'Playable', 3 => 'Verified'];
const STEAM_IMG = 'https://shared.akamai.steamstatic.com/store_item_assets/';
const STEAM_UA = 'Mozilla/5.0 (compatible; CraftyPlayz Steam stats; +https://craftyplayz.com/games/steam/)';
const STEAM_IMG_COMMUNITY = 'https://shared.akamai.steamstatic.com/community_assets/images/';

// Fetch many JSON URLs through the disk cache, in parallel. $urls is [key => url]; returns [key => array|null].
// Fresh copies come from disk. Missing ones are always fetched; at most $refresh stale ones are, so a page
// that needs 100+ games never waits on all of them at once. A failed fetch keeps the stale copy and isn't
// retried for 10 minutes, so an app Steam always errors on (some do) doesn't slow every page view.
function steam_fetch($urls, $ttl, $refresh = 1000)
{
    $out = [];
    $todo = [];
    foreach ($urls as $k => $url) {
        $file = STEAM_CACHE . '/' . md5($url) . '.json'; // md5 so the API key never shows in a file name
        $age = is_file($file) ? time() - filemtime($file) : null;
        $out[$k] = $age === null ? null : json_decode((string)@file_get_contents($file), true);
        if (is_file($file . '.miss') && time() - filemtime($file . '.miss') < 600) continue;
        if ($age === null || $out[$k] === null) $todo[$k] = $url;
        elseif ($age >= $ttl && $refresh-- > 0) $todo[$k] = $url;
    }
    if (!$todo) return $out;
    if (!is_dir(STEAM_CACHE)) @mkdir(STEAM_CACHE, 0700, true);

    $mh = curl_multi_init();
    curl_multi_setopt($mh, CURLMOPT_MAX_TOTAL_CONNECTIONS, 12);
    $handles = [];
    foreach ($todo as $k => $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 12, CURLOPT_ENCODING => '', CURLOPT_USERAGENT => STEAM_UA]);
        curl_multi_add_handle($mh, $ch);
        $handles[$k] = $ch;
    }
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running) curl_multi_select($mh, 1);
    } while ($running && $status == CURLM_OK);
    foreach ($handles as $k => $ch) {
        $body = curl_multi_getcontent($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // a 4xx with a JSON body (a game without stats, a private list) is a real answer, so it's cached too
        $data = $code && $code < 500 && $code != 429 ? json_decode((string)$body, true) : null;
        $file = STEAM_CACHE . '/' . md5($todo[$k]) . '.json';
        if (is_array($data)) {
            $out[$k] = $data;
            @file_put_contents($file, $body, LOCK_EX);
            @unlink($file . '.miss');
        } else {
            @touch($file . '.miss');
        }
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $out;
}

function steam_json($url, $ttl = 600)
{
    return steam_fetch([$url], $ttl)[0];
}

// Web API URL with the site's key. $method is 'Interface/Method/v1'.
function steam_url($method, $params = [])
{
    global $steamkey;
    return 'https://api.steampowered.com/' . $method . '/?' . http_build_query(['key' => $steamkey] + $params);
}

function steam_api($method, $params = [], $ttl = 600)
{
    return steam_json(steam_url($method, $params), $ttl);
}

function steam_profile()
{
    global $steamid;
    $r = steam_api('ISteamUser/GetPlayerSummaries/v2', ['steamids' => $steamid], 300);
    return isset($r['response']['players'][0]) ? $r['response']['players'][0] : null;
}

function steam_recent()
{
    global $steamid;
    $r = steam_api('IPlayerService/GetRecentlyPlayedGames/v1', ['steamid' => $steamid]);
    $games = isset($r['response']['games']) ? $r['response']['games'] : [];
    return array_values(array_filter($games, function ($g) {
        return !in_array($g['appid'], STEAM_HIDE);
    }));
}

// Every owned game keyed by appid, most played first, hidden games removed.
// Recently played games can be missing from the owned list (free weekends, family sharing), so they're added.
function steam_games()
{
    global $steamid;
    static $games = null;
    if ($games !== null) return $games;
    $r = steam_api('IPlayerService/GetOwnedGames/v1', ['steamid' => $steamid, 'include_appinfo' => 1, 'include_extended_appinfo' => 1, 'include_played_free_games' => 1]);
    $games = [];
    foreach (array_merge(isset($r['response']['games']) ? $r['response']['games'] : [], steam_recent()) as $g) {
        if (!isset($games[$g['appid']]) && !in_array($g['appid'], STEAM_HIDE)) $games[$g['appid']] = $g;
    }
    uasort($games, function ($a, $b) {
        return $b['playtime_forever'] <=> $a['playtime_forever'];
    });
    return $games;
}

// My achievements for every played game that has them: [appid => [['apiname', 'achieved', 'unlocktime', 'name', 'description'], ...]].
// Games played in the past two weeks refresh every 10 minutes, the rest once a day.
function steam_achievements($games, $refresh = 20)
{
    global $steamid;
    $hot = $cold = [];
    foreach ($games as $id => $g) {
        if (empty($g['has_community_visible_stats']) || $g['playtime_forever'] <= 0) continue;
        $url = steam_url('ISteamUserStats/GetPlayerAchievements/v1', ['steamid' => $steamid, 'appid' => $id, 'l' => 'english']);
        if (!empty($g['playtime_2weeks'])) $hot[$id] = $url;
        else $cold[$id] = $url;
    }
    $out = [];
    foreach (steam_fetch($hot, 600) + steam_fetch($cold, 86400, $refresh) as $id => $r) {
        if (!empty($r['playerstats']['achievements'])) $out[$id] = $r['playerstats']['achievements'];
    }
    return $out;
}

// Per-game totals from steam_achievements(): [appid => ['done', 'total', 'pct', 'last']]
function steam_progress($ach)
{
    $out = [];
    foreach ($ach as $id => $list) {
        $done = 0;
        $last = 0;
        foreach ($list as $a) {
            if ($a['achieved']) {
                $done++;
                $last = max($last, (int)$a['unlocktime']);
            }
        }
        $out[$id] = ['done' => $done, 'total' => count($list), 'pct' => $done / count($list) * 100, 'last' => $last];
    }
    return $out;
}

// Daily totals behind the History charts on /games/steam/profile/: ['Y-m-d' => [level, xp, games, hours, achievements, badges]].
// Both the page and cron.php call this. Today's row is rewritten whenever the totals change, so each day keeps
// its last reading. Pass nulls to only read. Returns false instead of the history if the file can't be written.
const STEAM_HISTORY = __DIR__ . '/profile/cache/history.json';

function steam_history($badges = null, $games = null, $progress = null)
{
    $history = is_file(STEAM_HISTORY) ? json_decode((string)file_get_contents(STEAM_HISTORY), true) ?: [] : [];
    if (!$badges || !$games || !$progress) return $history;
    $row = [
        'level' => $badges['player_level'],
        'xp' => $badges['player_xp'],
        'games' => count($games),
        'hours' => round(array_sum(array_column($games, 'playtime_forever')) / 60),
        'achievements' => array_sum(array_column($progress, 'done')),
        'badges' => count($badges['badges']),
    ];
    $totals = [];
    foreach ($games as $id => $g) if ($g['playtime_forever'] > 0) $totals[$id] = (int)$g['playtime_forever'];
    if (steam_hourly($row, $totals) === false) return false;
    $day = date('Y-m-d');
    if (isset($history[$day]) && $history[$day] == $row) return $history;
    $history[$day] = $row;
    return @file_put_contents(STEAM_HISTORY, json_encode($history), LOCK_EX) === false ? false : $history;
}

// The past 8 days hour by hour, for the 7-day views on the profile page: ['rows' => [hour start => History row],
// 'play' => [hour start => [appid => minutes]], 'hour', 'base' => totals at the start of 'hour', 'totals' => latest totals].
// Same rules as steam_playtime(), per hour: missed hours pile onto the next reading. Pass nothing to only read.
const STEAM_HOURLY = __DIR__ . '/profile/cache/hourly.json';

function steam_hourly($row = null, $totals = null)
{
    $log = is_file(STEAM_HOURLY) ? json_decode((string)file_get_contents(STEAM_HOURLY), true) ?: [] : [];
    $log += ['rows' => [], 'play' => [], 'hour' => null, 'base' => [], 'totals' => []];
    if (!$row) return $log;
    $hour = (int)(floor(time() / 3600) * 3600);
    if ($log['hour'] !== $hour) {
        $log['base'] = $log['hour'] ? $log['totals'] : $totals;
        $log['hour'] = $hour;
    } elseif ($log['totals'] == $totals && $log['rows'][$hour] == $row) {
        return $log;
    }
    $played = [];
    foreach ($totals as $id => $min) {
        $d = $min - (isset($log['base'][$id]) ? $log['base'][$id] : 0);
        if ($d > 0) $played[$id] = $d;
    }
    arsort($played);
    $log['rows'][$hour] = $row;
    $log['play'][$hour] = $played;
    $log['totals'] = $totals;
    $cut = $hour - 8 * 86400;
    foreach (['rows', 'play'] as $k) $log[$k] = array_filter($log[$k], function ($t) use ($cut) {
        return $t > $cut;
    }, ARRAY_FILTER_USE_KEY);
    return @file_put_contents(STEAM_HOURLY, json_encode($log), LOCK_EX) === false ? false : $log;
}

// Minutes played per game per day, for the profile page: ['days' => ['Y-m-d' => [appid => minutes]], 'gaps' => ['Y-m-d' => 'Y-m-d of
// the reading before, when that wasn't the day before'], 'day', 'base' => totals at the start of 'day', 'totals' => latest totals].
// A day is today's totals minus the last reading of the previous recorded day, so missed days pile onto the next one (see 'gaps').
// Both the page and cron.php call this. Pass null to only read. Returns false if the file can't be written.
const STEAM_PLAYTIME = __DIR__ . '/profile/cache/playtime.json';

function steam_playtime($games = null)
{
    $log = is_file(STEAM_PLAYTIME) ? json_decode((string)file_get_contents(STEAM_PLAYTIME), true) ?: [] : [];
    $log += ['days' => [], 'gaps' => [], 'day' => null, 'base' => [], 'totals' => []];
    if (!$games) return $log;
    $totals = [];
    foreach ($games as $id => $g) if ($g['playtime_forever'] > 0) $totals[$id] = (int)$g['playtime_forever'];
    $today = date('Y-m-d');
    if ($log['day'] !== $today) {
        // first reading ever: no base yet, so today starts from now
        $log['base'] = $log['day'] ? $log['totals'] : $totals;
        if ($log['day'] && $log['day'] !== date('Y-m-d', strtotime('-1 day'))) $log['gaps'][$today] = $log['day'];
        $log['day'] = $today;
    } elseif ($log['totals'] == $totals) {
        return $log;
    }
    $played = [];
    foreach ($totals as $id => $min) {
        $d = $min - (isset($log['base'][$id]) ? $log['base'][$id] : 0);
        if ($d > 0) $played[$id] = $d;
    }
    arsort($played);
    $log['days'][$today] = $played;
    $log['totals'] = $totals;
    return @file_put_contents(STEAM_PLAYTIME, json_encode($log), LOCK_EX) === false ? false : $log;
}

// Steam level for a total XP: levels 1-10 cost 100 XP each, 11-20 cost 200, and so on
function steam_level($xp)
{
    $level = 0;
    while ($xp >= ($cost = 100 * (intdiv($level, 10) + 1))) {
        $xp -= $cost;
        $level++;
    }
    return $level;
}

// The History series from steam_history() with the time before recording began filled in, one point a month from $start
// (account creation). Returns [key => [[unix time, value, kind], ...]], where kind is null for a recorded day,
// 'rebuilt' for one worked out from Steam's own dates, and 'guess' for a straight line from 0 at $start up to the
// first recorded day. Achievements come exactly from unlock times. Badges, XP and level come from each badge's
// completion time, which is only its latest level, so a levelled badge counts all its XP late. Steam keeps no
// history of hours played or games owned, so those two are the guesses.
// When games/steam/backfill.php has built profile/cache/backfill.json, that daily estimate is used instead: kind 'est' for
// every line except achievements, which stay 'rebuilt' (they're exact).
const STEAM_BACKFILL = __DIR__ . '/profile/cache/backfill.json';

function steam_backfill_file()
{
    static $b = false;
    if ($b === false) $b = is_file(STEAM_BACKFILL) ? json_decode((string)file_get_contents(STEAM_BACKFILL), true) : null;
    return $b;
}

function steam_backfill($history, $ach, $badges, $start)
{
    $file = steam_backfill_file();
    if ($file && !empty($file['series'])) {
        $out = [];
        $t0 = strtotime($file['start']);
        $first = $history ? array_key_first($history) : null;
        foreach ($file['series'] as $k => $vals) {
            foreach ($vals as $i => $v) {
                $t = strtotime('+' . $i . ' days', $t0);
                if ($first && date('Y-m-d', $t) >= $first) break;
                $out[$k][] = [$t, $v, $k === 'achievements' ? 'rebuilt' : 'est'];
            }
        }
        foreach ($history as $day => $row) foreach ($row as $k => $v) $out[$k][] = [strtotime($day), $v, null];
        return $out;
    }
    $out = [];
    $first = $history ? strtotime(array_key_first($history)) : time();
    if ($start && $start < $first) {
        $unlocks = [];
        foreach ($ach as $list) foreach ($list as $a) if ($a['achieved']) $unlocks[] = (int)$a['unlocktime'];
        $earned = [];
        foreach ($badges ? $badges['badges'] : [] as $b) $earned[] = [max((int)$b['completion_time'], $start), (int)$b['xp']];
        sort($unlocks);
        usort($earned, function ($a, $b) {
            return $a[0] <=> $b[0];
        });
        $ends = $history ? reset($history) : null;
        $u = $e = $count = $xp = 0;
        for ($t = $start; $t < $first; $t = strtotime('first day of next month 00:00', $t)) {
            while ($u < count($unlocks) && $unlocks[$u] <= $t) $u++;
            for (; $e < count($earned) && $earned[$e][0] <= $t; $e++) {
                $count++;
                $xp += $earned[$e][1];
            }
            $f = ($t - $start) / ($first - $start);
            $out['achievements'][] = [$t, $u, 'rebuilt'];
            $out['badges'][] = [$t, $count, 'rebuilt'];
            $out['xp'][] = [$t, $xp, 'rebuilt'];
            $out['level'][] = [$t, steam_level($xp), 'rebuilt'];
            if ($ends) {
                $out['hours'][] = [$t, $ends['hours'] * $f, 'guess'];
                $out['games'][] = [$t, $ends['games'] * $f, 'guess'];
            }
        }
    }
    foreach ($history as $day => $row) foreach ($row as $k => $v) $out[$k][] = [strtotime($day), $v, null];
    return $out;
}

// Global unlock rates: [appid => [apiname => percent]]
function steam_rarity($appids, $refresh = 20)
{
    $urls = [];
    foreach ($appids as $id) $urls[$id] = 'https://api.steampowered.com/ISteamUserStats/GetGlobalAchievementPercentagesForApp/v2/?gameid=' . (int)$id;
    $out = [];
    foreach (steam_fetch($urls, 3 * 86400, $refresh) as $id => $r) {
        $out[$id] = [];
        foreach (isset($r['achievementpercentages']['achievements']) ? $r['achievementpercentages']['achievements'] : [] as $a) {
            $out[$id][$a['name']] = (float)$a['percent'];
        }
    }
    return $out;
}

// Achievement icons and hidden flags from each game's schema: [appid => [apiname => schema entry]]
function steam_schema($appids)
{
    $urls = [];
    foreach ($appids as $id) $urls[$id] = steam_url('ISteamUserStats/GetSchemaForGame/v2', ['appid' => (int)$id, 'l' => 'english']);
    $out = [];
    foreach (steam_fetch($urls, 7 * 86400) as $id => $r) {
        $out[$id] = [];
        foreach (isset($r['game']['availableGameStats']['achievements']) ? $r['game']['availableGameStats']['achievements'] : [] as $a) {
            // the schema still points at the old community CDN, which 404s for some games (Factorio); the new one has them all
            foreach (['icon', 'icongray'] as $k) {
                if (isset($a[$k])) $a[$k] = preg_replace('~^https?://[^/]+/steamcommunity/public/images/~', STEAM_IMG_COMMUNITY, $a[$k]);
            }
            $out[$id][$a['name']] = $a;
        }
    }
    return $out;
}

// Badge names and images. The API only gives badge IDs, so these come from the public badges page,
// cached a day: ['b13' => [name, img], 'g212910' => ..., 'g212910f' => foil game badge]
function steam_badge_info()
{
    global $steamid;
    $file = STEAM_CACHE . '/badge-names.json';
    $cached = is_file($file) ? json_decode((string)@file_get_contents($file), true) : null;
    if (is_array($cached) && time() - filemtime($file) < 86400) return $cached;
    if (is_file($file . '.miss') && time() - filemtime($file . '.miss') < 600) return $cached ?: [];

    $ch = curl_init('https://steamcommunity.com/profiles/' . $steamid . '/badges/?l=english');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 10, CURLOPT_ENCODING => '', CURLOPT_USERAGENT => STEAM_UA]);
    $html = (string)curl_exec($ch);
    curl_close($ch);
    $out = [];
    foreach (array_slice(explode('class="badge_row ', $html), 1) as $row) {
        if (!preg_match('~/(badges|gamecards)/(\d+)/?(\?border=1)?"~', $row, $m) || !preg_match('~badge_info_title">([^<]*)<~', $row, $t)) continue;
        // icons load lazily on Steam, so the real URL is usually in data-delayed-image
        $img = preg_match('~data-delayed-image="([^"]+)"~', $row, $i) || preg_match('~<img src="([^"]+)"[^>]*class="badge_icon"~', $row, $i) ? $i[1] : '';
        $out[($m[1] == 'badges' ? 'b' : 'g') . $m[2] . (empty($m[3]) ? '' : 'f')] = [html_entity_decode(trim($t[1]), ENT_QUOTES), $img];
    }
    if (!is_dir(STEAM_CACHE)) @mkdir(STEAM_CACHE, 0700, true);
    if ($out) {
        @file_put_contents($file, json_encode($out), LOCK_EX);
        return $out;
    }
    @touch($file . '.miss');
    return $cached ?: [];
}

// Store data (name, art, price, reviews, release, platforms) for many apps, 100 per request: [appid => item]
function steam_items($appids, $ttl = 86400)
{
    $appids = array_values(array_unique(array_map('intval', $appids)));
    sort($appids); // same list, same cache file
    $urls = [];
    foreach (array_chunk($appids, 100) as $i => $chunk) {
        $q = [
            'ids' => array_map(function ($id) {
                return ['appid' => $id];
            }, $chunk),
            'context' => ['language' => 'english', 'country_code' => 'GB'],
            'data_request' => ['include_basic_info' => true, 'include_reviews' => true, 'include_release' => true, 'include_assets' => true, 'include_platforms' => true],
        ];
        $urls[$i] = 'https://api.steampowered.com/IStoreBrowseService/GetItems/v1/?input_json=' . rawurlencode(json_encode($q));
    }
    $out = [];
    foreach (steam_fetch($urls, $ttl) as $r) {
        foreach (isset($r['response']['store_items']) ? $r['response']['store_items'] : [] as $item) {
            if (!empty($item['success'])) $out[$item['appid']] = $item;
        }
    }
    return $out;
}

// Store art URL. $which is an asset name from GetItems: header, small_capsule, library_capsule (portrait), library_hero (wide)...
// Newer games keep art under hashed paths, so the plain guess is only the fallback.
function steam_art($id, $items, $which = 'header')
{
    $a = isset($items[$id]['assets']) ? $items[$id]['assets'] : null;
    if ($a && !empty($a[$which]) && !empty($a['asset_url_format'])) return STEAM_IMG . str_replace('${FILENAME}', $a[$which], $a['asset_url_format']);
    $guess = ['library_capsule' => 'library_600x900.jpg', 'library_hero' => 'library_hero.jpg'];
    return STEAM_IMG . 'steam/apps/' . (int)$id . '/' . (isset($guess[$which]) ? $guess[$which] : 'header.jpg');
}

// Rarity tier for a global unlock percentage: [label, badge class]
function steam_tier($pct)
{
    if ($pct === null) return ['', ''];
    if ($pct < 1) return ['Ultra rare', 'gold'];
    if ($pct < 5) return ['Very rare', 'p'];
    if ($pct < 20) return ['Rare', 'b'];
    if ($pct < 50) return ['Uncommon', 'g'];
    return ['Common', ''];
}

function steam_rarity_badge($pct)
{
    if ($pct === null) return '';
    $t = steam_tier($pct);
    return '<span class="badge ' . $t[1] . '" title="' . $t[0] . ': ' . $pct . '% of players have this">' . ($pct < 10 ? round($pct, 1) : round($pct)) . '%</span>';
}

// "3 days ago" style text for a unix time
function steam_ago($ts)
{
    $s = time() - $ts;
    if ($s < 86400) return 'Today';
    $d = floor($s / 86400);
    if ($d == 1) return 'Yesterday';
    if ($d < 60) return $d . ' days ago';
    if ($d < 730) return floor($d / 30) . ' months ago';
    return floor($d / 365) . ' years ago';
}

// Playtime minutes as "45 min" or "1,234 h"; rounded hours show the exact time on hover
function steam_hours($min)
{
    if ($min < 60) return $min . ' min';
    return '<span title="' . steam_hm($min) . '">' . number_format(round($min / 60)) . ' h</span>';
}

// Exact playtime as "1,234 hours 5 minutes". Steam only reports whole minutes, so there are no seconds.
function steam_hm($min)
{
    $h = intdiv((int)$min, 60);
    $m = (int)$min % 60;
    $parts = [];
    if ($h) $parts[] = number_format($h) . ($h == 1 ? ' hour' : ' hours');
    if ($m || !$h) $parts[] = $m . ($m == 1 ? ' minute' : ' minutes');
    return implode(' ', $parts);
}

function steam_time($ts)
{
    return '<time datetime="' . date('c', $ts) . '" title="' . date('j M Y, H:i', $ts) . '">' . steam_ago($ts) . '</time>';
}

// Items on my profile (background, avatar frame...), cached a day: ['profile_background' => [...], ...], empty ones removed
function steam_equipped()
{
    global $steamid;
    $r = steam_api('IPlayerService/GetProfileItemsEquipped/v1', ['steamid' => $steamid], 86400);
    return isset($r['response']) ? array_filter($r['response']) : [];
}

// Completion ring for a percentage, drawn by CSS from --p
function steam_ring($pct, $label = '')
{
    $pct = max(0, min(100, (int)round($pct)));
    return '<span class="steam-ring' . ($pct == 100 ? ' full' : '') . '" style="--p: ' . $pct . '" role="img" aria-label="' . h($label ?: $pct . '% complete') . '"><b>' . $pct . '<small>%</small></b></span>';
}

// Banner at the top of every Steam page: art behind, the page title, me (avatar, frame, level, status) and the tabs.
// $o: title, lede (HTML, already escaped), bg (image URL; my profile background when left out), current (tab URL).
function steam_hero($o)
{
    global $steamid;
    $profile = steam_profile();
    $equipped = steam_equipped();
    $badges = steam_api('IPlayerService/GetBadges/v1', ['steamid' => $steamid], 86400);
    $level = isset($badges['response']['player_level']) ? (int)$badges['response']['player_level'] : null;
    $bg = !empty($o['bg']) ? $o['bg'] : (!empty($equipped['profile_background']['image_large']) ? STEAM_IMG_COMMUNITY . $equipped['profile_background']['image_large'] : '');
    $frame = !empty($equipped['avatar_frame']['image_small']) ? STEAM_IMG_COMMUNITY . $equipped['avatar_frame']['image_small'] : '';
    $state = $profile ? (int)$profile['personastate'] : 0;
    $playing = $profile && !empty($profile['gameextrainfo']);
    $current = isset($o['current']) ? $o['current'] : '';
    $tabs = ['/games/steam/' => 'Overview', '/games/steam/library/' => 'Library', '/games/steam/achievements/' => 'Achievements', '/games/steam/wishlist/' => 'Wishlist', '/games/steam/profile/' => 'Profile'];
    ?>
    <header class="steam-hero">
        <?php if ($bg): ?><img class="steam-hero-bg" src="<?= h($bg) ?>" alt="" fetchpriority="high" decoding="async" onerror="this.remove()"><?php endif; ?>
        <div class="steam-hero-body">
            <div class="grow">
                <h1 class="page-title"><?= h($o['title']) ?></h1>
                <?php if (!empty($o['lede'])): ?><p class="page-lede"><?= $o['lede'] ?></p><?php endif; ?>
            </div>
            <?php if ($profile): ?>
                <a class="steam-me" href="/games/steam/profile/" title="My Steam profile">
                    <span class="steam-me-pic">
                        <img src="<?= h($profile['avatarmedium']) ?>" alt="" width="64" height="64">
                        <?php if ($frame): ?><img class="steam-me-frame" src="<?= h($frame) ?>" alt="" width="84" height="84" onerror="this.remove()"><?php endif; ?>
                    </span>
                    <span class="steam-me-text">
                        <b><?= h($profile['personaname']) ?><?php if ($level !== null): ?> <span class="steam-level" title="Steam level"><?= $level ?></span><?php endif; ?></b>
                        <small class="steam-dot<?= $playing ? ' ingame' : ($state ? ' online' : '') ?>"><?= $playing ? 'Playing ' . h($profile['gameextrainfo']) : (isset(STEAM_STATES[$state]) ? STEAM_STATES[$state] : 'Online') ?></small>
                    </span>
                </a>
            <?php endif; ?>
        </div>
        <nav class="steam-tabs" aria-label="Steam pages">
            <?php foreach ($tabs as $url => $label): ?><a href="<?= $url ?>"<?= $url === $current ? ' aria-current="page"' : '' ?>><?= $label ?></a><?php endforeach; ?>
        </nav>
    </header>
    <?php
}

// Portrait cover like the Steam library, used by the overview and the library. $p is the game's steam_progress() entry or null.
// Name and hours always show on the cover; hovering lifts it and adds the last-played line.
function steam_card($g, $items, $p, $lazy)
{
    $id = (int)$g['appid'];
    $last = isset($g['rtime_last_played']) ? (int)$g['rtime_last_played'] : 0;
    $attrs = ' data-name="' . h(mb_strtolower($g['name'])) . '" data-min="' . (int)$g['playtime_forever'] . '" data-last="' . $last . '" data-pct="' . ($p ? round($p['pct'], 2) : -1) . '"';
    ?>
    <a class="steam-cap" href="/games/steam/game/?game=<?= $id ?>"<?= $attrs ?>>
        <img src="<?= h(steam_art($id, $items, 'library_capsule')) ?>" data-alt="<?= h(steam_art($id, $items)) ?>" alt="" width="300" height="450" <?= $lazy ? 'loading="lazy" ' : '' ?>decoding="async">
        <?php if ($p): ?><?= steam_ring($p['pct'], $p['done'] . ' of ' . $p['total'] . ' achievements') ?><?php endif; ?>
        <?php if (!empty($g['playtime_2weeks'])): ?><span class="steam-cap-new">Recent</span><?php endif; ?>
        <span class="steam-cap-info">
            <b><?= h($g['name']) ?></b>
            <small><?= $g['playtime_forever'] ? steam_hours($g['playtime_forever']) : 'Never played' ?><?= $p ? ' · ' . $p['done'] . '/' . $p['total'] : '' ?></small>
            <?php if ($last): ?><small class="steam-cap-more">Last played <?= mb_strtolower(steam_ago($last)) ?></small><?php endif; ?>
        </span>
    </a>
    <?php
}

// Achievement tile: icon, name, description and a global rarity bar. $a has name, description, achieved, unlocktime;
// $meta is its schema entry (icon, icongray, hidden); $game is [appid, name] when the tile should say which game it's from.
function steam_ach_card($a, $meta, $pct, $game = null, $attrs = '')
{
    $got = !empty($a['achieved']);
    $icon = $meta ? ($got ? (isset($meta['icon']) ? $meta['icon'] : '') : (isset($meta['icongray']) ? $meta['icongray'] : '')) : '';
    $hidden = !$got && !empty($meta['hidden']);
    $t = steam_tier($pct);
    $desc = isset($a['description']) ? $a['description'] : '';
    ?>
    <div class="steam-ach<?= $got ? '' : ' locked' ?><?= $t[1] ? ' t-' . $t[1] : '' ?>"<?= $attrs ?>>
        <?php if ($icon): ?><img src="<?= h($icon) ?>" alt="" width="64" height="64" loading="lazy" decoding="async"><?php else: ?><span class="steam-ach-noicon"></span><?php endif; ?>
        <div class="grow">
            <b><?= h($a['name']) ?></b>
            <?php if ($game): ?><small><a href="/games/steam/game/?game=<?= (int)$game[0] ?>"><?= h($game[1]) ?></a></small><?php endif; ?>
            <?php if ($hidden && $desc !== ''): ?>
                <details><summary>Hidden achievement</summary><?= h($desc) ?></details>
            <?php elseif ($desc !== ''): ?>
                <small class="steam-ach-desc"><?= h($desc) ?></small>
            <?php endif; ?>
            <?php if ($pct !== null): ?>
                <span class="steam-rare" title="<?= $t[0] ?>: <?= $pct ?>% of players have this"><i style="width: <?= max(2, min(100, $pct)) ?>%"></i></span>
                <small class="steam-rare-n"><?= $pct < 10 ? round($pct, 1) : round($pct) ?>% of players · <?= $t[0] ?></small>
            <?php endif; ?>
        </div>
        <small class="steam-ach-when"><?= $got ? steam_time($a['unlocktime']) : 'Locked' ?></small>
    </div>
    <?php
}
