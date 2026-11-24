<?php
// UnlimitedSMP live stats: the home server's server-stats container (Z:/shop-stock/server-stats.js) writes
// server-stats.json every minute. This fetches it server-side (no CORS needed), caches 55 s and falls back to the
// last good copy when the home server is down. Included by index.php; requested directly it returns the JSON
// for the page's one-minute polling.

function smp_stats() {
    $cache = sys_get_temp_dir() . '/craftyplayz-smp-stats.json';
    if (is_file($cache) && time() - filemtime($cache) < 55) return [file_get_contents($cache), false];

    $ch = curl_init('https://assets.craftyplayz.com/minecraft/server-stats.json');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 10, CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'craftyplayz.com UnlimitedSMP page',
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200 && json_decode((string)$body) !== null) {
        @file_put_contents($cache, $body, LOCK_EX);
        return [$body, false];
    }
    if (is_file($cache)) return [file_get_contents($cache), true];
    return [null, true];
}

if (realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    session_write_close();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    [$body, $stale] = smp_stats();
    if ($body === null) {
        http_response_code(502);
        echo json_encode(['error' => "Couldn't reach the server stats."]);
        exit;
    }
    if ($stale) header('X-Stale: 1');
    echo $body;
}
