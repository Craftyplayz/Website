<?php
// Frame Guesser shared code: the frame index (crawled from the asset host) and
// the player's run (kept in a PHP session).
//
// Hiding the answer: a frame's real URL names its show, season and episode, so
// the browser never sees it during a round. The run lives in the session: PHP
// picks each frame, keeps the answer, gives the page a random one-round token
// for frame.php, grades the guess and keeps the score. The leaderboard saves
// the score from the session, so nothing the browser sends can change it.
// A session beats a signed token here because the score has to live
// server-side anyway, and it needs no secret key or encryption.

const FG_BASE  = 'https://assets.craftyplayz.com/videoframegame/';
const FG_TTL   = 86400; // the index is rebuilt once a day
const FG_UA    = 'craftyplayz.com Frame Guesser (+https://craftyplayz.com/games/frame-guesser/)';
define('FG_CACHE', sys_get_temp_dir() . '/craftyplayz-framegame');

// The Chapter Oracle's modes, with seasons for books and episodes for chapters
const FG_MODES = [
    'normal'   => ['title' => 'Normal'],
    'timed'    => ['title' => 'Timed', 'seconds' => 60],
    'minimum'  => ['title' => 'Minimum Context'],
    'specific' => ['title' => 'Specific Seasons'],
];
// Minimum context: how much of the frame's width each zoom step shows. A right
// episode scores one point per step left unused (7 at the first, 1 at the full frame).
const FG_ZOOMS = [0.2, 0.28, 0.38, 0.5, 0.65, 0.82, 1];

// Asset host URL for a list of folder/file names
function fg_url(array $parts, bool $dir = false): string
{
    return FG_BASE . implode('/', array_map('rawurlencode', $parts)) . ($dir && $parts ? '/' : '');
}

// Folders and .jpg files linked from a listing page (Apache "Index of" or the
// host's generated pages, where links carry a class first), in natural order
function fg_parse_listing(string $html): array
{
    $out = ['dirs' => [], 'files' => []];
    preg_match_all('/<a\b[^>]*?\shref="([^"]+)"/i', $html, $m);
    foreach ($m[1] as $href) {
        $href = html_entity_decode($href, ENT_QUOTES);
        // skip the parent link (absolute), sort links (?C=N) and anything off-site
        if ($href[0] === '/' || $href[0] === '?' || strpos($href, '://') !== false) {
            continue;
        }
        $name = rawurldecode(rtrim($href, '/'));
        if ($name === '' || $name[0] === '.' || strpbrk($name, "/\\\0") !== false) {
            continue;
        }
        if (substr($href, -1) === '/') {
            $out['dirs'][] = $name;
        } elseif (preg_match('/\.jpe?g$/i', $name)) {
            $out['files'][] = $name;
        }
    }
    foreach ($out as &$list) {
        natcasesort($list);
        $list = array_values(array_unique($list));
    }
    return $out;
}

// Fetches many folder listings at once. Keys are "a/b/c"; a failed fetch is null.
function fg_listings(array $paths): array
{
    $out = [];
    if (!$paths) {
        return $out;
    }
    $mh = curl_multi_init();
    curl_multi_setopt($mh, CURLMOPT_MAX_TOTAL_CONNECTIONS, 12);
    $handles = [];
    foreach ($paths as $parts) {
        $ch = curl_init(fg_url($parts, true));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 12, CURLOPT_ENCODING => '', CURLOPT_USERAGENT => FG_UA]);
        curl_multi_add_handle($mh, $ch);
        $handles[implode('/', $parts)] = $ch;
    }
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running) {
            curl_multi_select($mh, 1);
        }
    } while ($running && $status == CURLM_OK);
    foreach ($handles as $key => $ch) {
        $body = curl_multi_getcontent($ch);
        $out[$key] = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200 ? fg_parse_listing((string) $body) : null;
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $out;
}

// Walks the host: title → (season →) episode or film → frames.
// A title whose folders hold folders has seasons; one whose folders hold .jpg
// files is a single list (films, or episodes without seasons). Empty folders
// and anything deeper are left out. Returns [titles, complete] or [null, false].
function fg_crawl(): array
{
    $root = fg_listings([[]])[''];
    if ($root === null) {
        return [null, false];
    }
    $lists = fg_listings(array_map(fn ($t) => [$t], $root['dirs']));
    $level2 = [];
    foreach ($root['dirs'] as $t) {
        foreach ($lists[$t]['dirs'] ?? [] as $d) {
            $level2[] = [$t, $d];
        }
    }
    $lists += fg_listings($level2);
    $level3 = [];
    foreach ($level2 as [$t, $s]) {
        foreach ($lists["$t/$s"]['dirs'] ?? [] as $e) {
            $level3[] = [$t, $s, $e];
        }
    }
    $lists += fg_listings($level3);
    $complete = !in_array(null, $lists, true);

    $titles = [];
    foreach ($root['dirs'] as $t) {
        $children = $lists[$t]['dirs'] ?? [];
        $seasonal = false;
        foreach ($children as $c) {
            $seasonal = $seasonal || !empty($lists["$t/$c"]['dirs']);
        }
        $groups = [];
        if ($seasonal) {
            foreach ($children as $s) {
                $eps = [];
                foreach ($lists["$t/$s"]['dirs'] ?? [] as $e) {
                    if ($frames = $lists["$t/$s/$e"]['files'] ?? []) {
                        $eps[] = ['name' => $e, 'frames' => $frames];
                    }
                }
                if ($eps) {
                    $groups[] = ['name' => $s, 'episodes' => $eps];
                }
            }
        } else {
            $eps = [];
            foreach ($children as $e) {
                if ($frames = $lists["$t/$e"]['files'] ?? []) {
                    $eps[] = ['name' => $e, 'frames' => $frames];
                }
            }
            if ($eps) {
                $groups[] = ['name' => null, 'episodes' => $eps];
            }
        }
        if ($groups) {
            $titles[] = ['name' => $t, 'groups' => $groups];
        }
    }
    return [$titles, $complete];
}

// The cached index: ['titles' => [...], 'built' => time, 'stale' => bool, 'error' => bool].
// $refresh rebuilds early, at most once every 10 minutes, so it can't be used to hammer the host.
function fg_index(bool $refresh = false): array
{
    $file = FG_CACHE . '/index.json';
    $age = is_file($file) ? time() - filemtime($file) : null;
    $cached = $age === null ? null : json_decode((string) @file_get_contents($file), true);
    $missed = is_file("$file.miss") && time() - filemtime("$file.miss") < 600;
    if (($cached === null || $age >= FG_TTL || ($refresh && $age >= 600)) && !$missed) {
        [$titles, $complete] = fg_crawl();
        if ($titles !== null && ($complete || $cached === null)) {
            if (!is_dir(FG_CACHE)) {
                @mkdir(FG_CACHE, 0700, true);
            }
            $cached = ['titles' => $titles, 'built' => time()];
            @file_put_contents($file, json_encode($cached, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
            if (!$complete) {
                @touch($file, time() - FG_TTL + 600); // a partial crawl is kept for 10 minutes only
            }
            @unlink("$file.miss");
            return $cached + ['stale' => false, 'error' => false];
        }
        if (!is_dir(FG_CACHE)) {
            @mkdir(FG_CACHE, 0700, true);
        }
        @touch("$file.miss");
    }
    if ($cached === null) {
        return ['titles' => [], 'built' => null, 'stale' => false, 'error' => true];
    }
    return $cached + ['stale' => $age >= FG_TTL, 'error' => false];
}

// The game's own session cookie, so it never mixes with php/config.php's session
function fg_session(bool $readOnly = false): void
{
    session_name('fgsess');
    session_cache_limiter(''); // each endpoint sends its own Cache-Control; PHP's no-cache would stop frame preloading
    session_set_cookie_params(['lifetime' => 0, 'path' => '/games/frame-guesser/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax']);
    session_start($readOnly ? ['read_and_close' => true] : []);
}

// A random frame from the seasons in play ($groups), each episode equally likely.
// Avoids repeating $avoid's episode when there's another to pick.
function fg_pick(string $title, array $groups, ?array $avoid = null): array
{
    $eps = [];
    foreach ($groups as $g) {
        foreach ($g['episodes'] as $e) {
            $eps[] = [$g['name'], $e];
        }
    }
    for ($try = 0; $try < 8; $try++) {
        [$season, $e] = $eps[array_rand($eps)];
        if (!$avoid || count($eps) === 1 || [$avoid['season'], $avoid['episode']] !== [$season, $e['name']]) {
            break;
        }
    }
    return [
        'title' => $title, 'season' => $season, 'episode' => $e['name'], 'frame' => $e['frames'][array_rand($e['frames'])],
        'token' => bin2hex(random_bytes(12)), 'zoom' => 0,
        // centre of the minimum context crop, kept clear of the edges and letterbox bars
        'cx' => mt_rand(250, 750) / 1000, 'cy' => mt_rand(300, 700) / 1000,
    ];
}

// The seasons in play for a run, from the current index; null if the show has gone
function fg_groups(string $title, array $seasons): ?array
{
    foreach (fg_index()['titles'] as $t) {
        if ($t['name'] === $title) {
            return array_values(array_filter($t['groups'], fn ($g) => in_array($g['name'], $seasons, true))) ?: null;
        }
    }
    return null;
}

function fg_frame_parts(array $round): array
{
    return array_values(array_filter([$round['title'], $round['season'], $round['episode'], $round['frame']], fn ($p) => $p !== null));
}

// frame.php address for a round; minimum context adds the zoom step so each step is a new image
function fg_frame_url(array $run, array $round, bool $full = false): string
{
    return 'frame.php?t=' . $round['token'] . ($run['mode'] === 'minimum' ? '&z=' . ($full ? 'full' : $round['zoom']) : '');
}

// What the page may see of a run. Answers only appear for rounds already played.
function fg_state(array $run): array
{
    $state = ['mode' => $run['mode'], 'title' => $run['title'], 'seasons' => $run['seasons'], 'score' => $run['score'],
              'round' => count($run['rounds']), 'stage' => $run['stage'], 'over' => $run['over'], 'end' => $run['end']];
    if (isset(FG_MODES[$run['mode']]['seconds'])) {
        $state['timeLeft'] = max(0, (int) round($run['deadline'] * 1000 - microtime(true) * 1000));
    }
    if ($run['over']) {
        $state['rounds'] = fg_public_rounds($run['rounds']);
    } else {
        $state['frame'] = fg_frame_url($run, $run['cur']);
        $state['next'] = fg_frame_url($run, $run['next']);
        if ($run['mode'] === 'minimum') {
            $state['zoom'] = $run['cur']['zoom'];
        }
    }
    return $state;
}

// Played rounds for the game over screen and replays, with direct image links
// (the answer is already shown by then)
function fg_public_rounds(array $rounds): array
{
    return array_map(function ($r) {
        $out = ['season' => $r['season'], 'episode' => $r['episode'], 'image' => fg_url(fg_frame_parts($r)),
                'pickedSeason' => $r['pickedSeason'], 'pickedEpisode' => $r['pickedEpisode'], 'result' => $r['result'], 'points' => $r['points']];
        foreach (['timeLeft', 'zoomUsed'] as $k) {
            if (isset($r[$k])) {
                $out[$k] = $r[$k];
            }
        }
        return $out;
    }, $rounds);
}
