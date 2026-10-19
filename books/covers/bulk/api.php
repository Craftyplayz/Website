<?php
// Cached proxy for the Open Library search API (free, no key). Open Library can take a few
// seconds to answer, so each search is kept on disk for a day.
//
//   GET api.php?q=<search>&max=10|40 -> Open Library search.json (English books only)
//   q may use Open Library's search syntax, e.g. series_name:"Dune" or author:"Frank Herbert".

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
$cacheFile = $cacheDir . '/ol-' . md5($max . '|' . strtolower($q)) . '.json';
if (is_file($cacheFile) && filemtime($cacheFile) > time() - 86400) json_out(200, file_get_contents($cacheFile), true);

$url = 'https://openlibrary.org/search.json?' . http_build_query([
    'q' => $q,
    'language' => 'eng',
    'limit' => $max,
    'fields' => 'key,title,author_name,cover_i,first_publish_year,series_name,series_position',
]);
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_CONNECTTIMEOUT => 5,
    // Open Library asks API users to identify themselves
    CURLOPT_USERAGENT => 'CraftyPlayz Bulk Cover Downloader (+https://www.craftyplayz.com/books/covers/bulk/)',
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Only good answers are cached; errors and timeouts are passed through and retried next time.
if ($code !== 200 || $body === false || !isset(json_decode($body, true)['docs'])) {
    json_out($code >= 400 ? $code : 502, '{"error":"Open Library is unavailable right now"}', false);
}
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
@file_put_contents($cacheFile, $body);
json_out(200, $body, true);
