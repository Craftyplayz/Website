<?php
// Helpers shared by the Steam pages.

// Fetch a JSON API URL, cached on disk for $ttl seconds. Returns null on failure.
// The cache lives in the system temp folder so no data file lands in the repo.
function steam_json($url, $ttl = 600)
{
    $dir = sys_get_temp_dir() . '/craftyplayz-steam';
    $file = $dir . '/' . md5($url) . '.json'; // md5 so the API key never shows in a file name
    if (is_file($file) && time() - filemtime($file) < $ttl) {
        return json_decode(file_get_contents($file), true);
    }
    $json = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 8]]));
    $data = $json === false ? null : json_decode($json, true);
    if ($data === null) {
        // serve a stale copy rather than nothing when Steam is down
        return is_file($file) ? json_decode(file_get_contents($file), true) : null;
    }
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    @file_put_contents($file, $json, LOCK_EX);
    return $data;
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
