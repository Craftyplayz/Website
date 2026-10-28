<?php
// YouTube Timeline proxy for the YouTube Data API v3, so the key stays server-side.
//   GET api.php?list=PL...[&token=...] -> one page (50 videos) of a playlist with publish dates, plus the next page's token
// Key: $youtubekey in php/config.php, else $googlebookskey (any Google key works once "YouTube Data API v3" is enabled on its project).

// config.php starts a session and prints markup meant for full pages - swallow all of it
session_cache_limiter('');
ob_start();
include $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
ob_end_clean();
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
header_remove('Set-Cookie');
header('Content-Type: application/json; charset=utf-8');

$key = $youtubekey ?? $googlebookskey ?? '';
$cache = sys_get_temp_dir() . '/craftyplayz-yt';

function yt_out($data)
{
    // errors go out as 200 + {"error"}: the host swaps 4xx/5xx bodies for its own page
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// GETs the URLs in parallel; returns [http code, decoded JSON or null] per key
function yt_get($urls)
{
    $mh = curl_multi_init();
    $hs = [];
    foreach ($urls as $k => $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 12, CURLOPT_ENCODING => '', CURLOPT_USERAGENT => 'craftyplayz.com YouTube Timeline']);
        curl_multi_add_handle($mh, $ch);
        $hs[$k] = $ch;
    }
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running) curl_multi_select($mh, 1);
    } while ($running && $status == CURLM_OK);
    $out = [];
    foreach ($hs as $k => $ch) {
        $out[$k] = [curl_getinfo($ch, CURLINFO_HTTP_CODE), json_decode((string)curl_multi_getcontent($ch), true)];
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $out;
}

$list = (string)($_GET['list'] ?? '');
$tok = (string)($_GET['token'] ?? '');
if (!preg_match('/^[A-Za-z0-9_-]{10,80}$/', $list) || !preg_match('/^[A-Za-z0-9_-]{0,200}$/', $tok)) yt_out(['error' => 'That isn\'t a playlist link.'], 400);
if ($key === '') yt_out(['error' => 'The YouTube API key isn\'t set up on this site yet.'], 503);

$file = "$cache/" . md5("$list|$tok") . '.json';
$age = is_file($file) ? time() - filemtime($file) : null;
if ($age !== null && $age < 3600) yt_out(json_decode(file_get_contents($file), true));

$api = 'https://www.googleapis.com/youtube/v3/';
$urls = ['items' => $api . 'playlistItems?' . http_build_query(['part' => 'snippet,contentDetails', 'maxResults' => 50, 'playlistId' => $list, 'pageToken' => $tok, 'key' => $key])];
if ($tok === '') $urls['head'] = $api . 'playlists?' . http_build_query(['part' => 'snippet,contentDetails', 'id' => $list, 'key' => $key]);
$r = yt_get($urls);
[$code, $items] = $r['items'];

if ($code == 404 || ($tok === '' && $code == 200 && empty($r['head'][1]['items']))) yt_out(['error' => 'Couldn\'t find that playlist. Check it\'s public or unlisted, not private.'], 404);
if ($code != 200 || !isset($items['items'])) {
    if ($age !== null) yt_out(json_decode(file_get_contents($file), true) + ['stale' => $age]); // stale beats blank
    $reason = $items['error']['errors'][0]['reason'] ?? '';
    yt_out(['error' => match (true) {
        $reason === 'quotaExceeded' => 'The site has used today\'s YouTube allowance. Try again tomorrow.',
        $code == 400 || $code == 403 => 'The site\'s YouTube API key isn\'t set up yet.', // key blocked, wrong IP or API not enabled
        default => 'Couldn\'t reach YouTube. Try again in a minute.',
    }]);
}

$videos = [];
foreach ($items['items'] as $it) {
    $s = $it['snippet'] ?? [];
    $videos[] = [
        'id' => $it['contentDetails']['videoId'] ?? '',
        'title' => $s['title'] ?? '',
        'channel' => $s['videoOwnerChannelTitle'] ?? '',
        'date' => $it['contentDetails']['videoPublishedAt'] ?? null, // missing for private and deleted videos
    ];
}
$out = ['videos' => $videos, 'next' => $items['nextPageToken'] ?? null];
if ($tok === '') {
    $p = $r['head'][1]['items'][0];
    $out += ['title' => $p['snippet']['title'] ?? 'Playlist', 'channel' => $p['snippet']['channelTitle'] ?? '', 'count' => $p['contentDetails']['itemCount'] ?? count($videos)];
}
if (!is_dir($cache)) @mkdir($cache, 0700, true);
@file_put_contents($file, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
yt_out($out);
