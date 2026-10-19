<?php
// Server-side proxy for the Google Books volumes search, so $googlebookskey in /php/config.php
// never reaches the browser.
//
//   GET api.php?q=<search>&max=10|40 -> Google Books JSON (cached on disk for a day)

// config.php starts a session and prints markup meant for full pages - swallow all of it,
// and stop it from sending no-cache headers or holding the session lock.
session_cache_limiter('');
ob_start();
include $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
ob_end_clean();
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
header_remove('Set-Cookie');

function json_out($code, $body, $cache)
{
    http_response_code($code);
    header('Content-Type: application/json');
    header('Cache-Control: ' . ($cache ? 'public, max-age=86400' : 'no-store'));
    echo $body;
    exit;
}

$q = trim((string)($_GET['q'] ?? ''));
$max = (int)($_GET['max'] ?? 10) === 40 ? 40 : 10;
if ($q === '' || strlen($q) > 300) json_out(400, '{"error":"q must be 1-300 characters"}', false);

$cacheDir = __DIR__ . '/cache';
$cacheFile = $cacheDir . '/' . md5($max . '|' . strtolower($q)) . '.json';
if (is_file($cacheFile) && filemtime($cacheFile) > time() - 86400) json_out(200, file_get_contents($cacheFile), true);

$url = 'https://www.googleapis.com/books/v1/volumes?' . http_build_query([
    'q' => $q,
    'maxResults' => $max,
    'printType' => 'books',
    'key' => isset($googlebookskey) ? $googlebookskey : '',
]);
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_CONNECTTIMEOUT => 5,
    // The key is IP-restricted to the server's IPv4 address; over IPv6 Google sees a different, unlisted address.
    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Only good answers are cached; errors (quota, outages) are passed through and retried next time.
if ($code !== 200 || $body === false || json_decode($body) === null) {
    // Google's reason code (e.g. API_KEY_IP_ADDRESS_BLOCKED) says what to fix; it never contains the key.
    $reason = json_decode((string)$body, true)['error']['details'][0]['reason'] ?? null;
    json_out($code >= 400 ? $code : 502, json_encode(['error' => 'Google Books is unavailable right now', 'reason' => $reason]), false);
}
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
@file_put_contents($cacheFile, $body);
json_out(200, $body, true);
