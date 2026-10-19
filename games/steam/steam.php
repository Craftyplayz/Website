<?php
// Helpers shared by the Steam pages (/games/steam/). Pages set 'config' => true for $steamkey and $steamid.

// The cache lives in the system temp folder so no data file lands in the repo.
define('STEAM_CACHE', sys_get_temp_dir() . '/craftyplayz-steam');
// Games that never show anywhere in the section
const STEAM_HIDE = [730, 682130, 469820, 1085750, 459820, 1463120];
const STEAM_STATES = ['Offline', 'Online', 'Busy', 'Away', 'Snooze', 'Looking to trade', 'Looking to play'];
const STEAM_DECK = [1 => 'Unsupported', 2 => 'Playable', 3 => 'Verified'];
const STEAM_IMG = 'https://shared.akamai.steamstatic.com/store_item_assets/';

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
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 12, CURLOPT_ENCODING => '']);
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
            $out[$id][$a['name']] = $a;
        }
    }
    return $out;
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

// Store art URL. $which is an asset name from GetItems: header, main_capsule, small_capsule, library_capsule...
// Newer games keep art under hashed paths, so the plain /header.jpg guess is only the fallback.
function steam_art($id, $items, $which = 'header')
{
    $a = isset($items[$id]['assets']) ? $items[$id]['assets'] : null;
    if ($a && !empty($a[$which]) && !empty($a['asset_url_format'])) return STEAM_IMG . str_replace('${FILENAME}', $a[$which], $a['asset_url_format']);
    return STEAM_IMG . 'steam/apps/' . (int)$id . '/header.jpg';
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

// Playtime minutes as "45 min" or "1,234 h"
function steam_hours($min)
{
    if ($min < 60) return $min . ' min';
    return number_format(round($min / 60)) . ' h';
}

function steam_time($ts)
{
    return '<time datetime="' . date('c', $ts) . '" title="' . date('j M Y, H:i', $ts) . '">' . steam_ago($ts) . '</time>';
}

// Tabs between the Steam pages
function steam_nav($current)
{
    $pages = ['/games/steam/' => 'Overview', '/games/steam/library/' => 'Library', '/games/steam/achievements/' => 'Achievements', '/games/steam/wishlist/' => 'Wishlist'];
    echo '<nav class="jump steam-nav" aria-label="Steam pages">';
    foreach ($pages as $url => $label) echo '<a href="' . $url . '"' . ($url === $current ? ' aria-current="page"' : '') . '>' . $label . '</a>';
    echo '</nav>';
}

// Game card used by the overview and the library. $p is the game's steam_progress() entry or null.
function steam_card($g, $items, $p, $lazy)
{
    $id = (int)$g['appid'];
    $link = '/games/steam/game/?game=' . $id;
    $last = isset($g['rtime_last_played']) ? (int)$g['rtime_last_played'] : 0;
    $pct = $p ? round($p['pct']) : -1;
    $attrs = ' data-name="' . h(mb_strtolower($g['name'])) . '" data-min="' . (int)$g['playtime_forever'] . '" data-last="' . $last . '" data-pct="' . ($p ? round($p['pct'], 2) : -1) . '"';
    ?>
    <article class="card steam-game"<?= $attrs ?>>
        <a class="steam-art" href="<?= $link ?>" tabindex="-1" aria-hidden="true">
            <img src="<?= h(steam_art($id, $items)) ?>" alt="" width="460" height="215" <?= $lazy ? 'loading="lazy" ' : '' ?>decoding="async">
        </a>
        <h3><a href="<?= $link ?>"><?= h($g['name']) ?></a></h3>
        <div class="kv">
            <span>Playtime</span><span><?= $g['playtime_forever'] ? steam_hours($g['playtime_forever']) : 'Never played' ?></span>
            <?php if (!empty($g['playtime_2weeks'])): ?>
                <span>Past 2 weeks</span><span><?= steam_hours($g['playtime_2weeks']) ?></span>
            <?php endif; ?>
            <?php if ($last): ?>
                <span>Last played</span><span><?= steam_time($last) ?></span>
            <?php endif; ?>
            <?php if ($p): ?>
                <span>Achievements</span><span><?= $p['done'] ?> / <?= $p['total'] ?> (<?= $pct ?>%)</span>
            <?php endif; ?>
        </div>
        <?php if ($p): ?>
            <div class="progress" role="progressbar" aria-label="Achievements" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><i style="width: <?= $pct ?>%"></i></div>
        <?php endif; ?>
    </article>
    <?php
}
