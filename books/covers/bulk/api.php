<?php
// Cached proxy for the Open Library search API (free, no key). Open Library can take a few
// seconds to answer, so each search is kept on disk for a day.
//
//   GET api.php?q=<search>&max=10|40 -> Open Library search.json (English books only)
//   q may use Open Library's search syntax, e.g. series_name:"Dune" or author:"Frank Herbert".
//   GET api.php?work=/works/OL82563W -> {"editions":[{"t":"title","c":[coverIds],"p":"publisher","y":2014,"f":"Paperback"}]}
//       every English (or unlabelled) edition of that book that has a cover, cached for a week

header_remove('Set-Cookie');

function json_out($code, $body, $cache)
{
    http_response_code($code);
    header('Content-Type: application/json');
    header('Cache-Control: ' . ($cache ? 'public, max-age=86400' : 'no-store'));
    echo $body;
    exit;
}

function ol_get($url)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 5,
        // Open Library asks API users to identify themselves
        CURLOPT_USERAGENT => 'CraftyPlayz Bulk Cover Downloader (+https://www.craftyplayz.com/books/covers/bulk/)',
    ]);
    $body = curl_exec($ch);
    return [curl_getinfo($ch, CURLINFO_HTTP_CODE), $body === false ? '' : $body];
}

$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);

/* ---------- every edition of one book, trimmed to what the set finder needs ---------- */
if (isset($_GET['work'])) {
    $work = (string)$_GET['work'];
    if (!preg_match('~^/works/OL\d+W$~', $work)) json_out(400, '{"error":"work must look like /works/OL123W"}', false);

    $cacheFile = $cacheDir . '/ed2-' . substr($work, 7) . '.json';
    if (is_file($cacheFile) && filemtime($cacheFile) > time() - 7 * 86400) json_out(200, file_get_contents($cacheFile), true);

    [$code, $body] = ol_get('https://openlibrary.org' . $work . '/editions.json?limit=1000');
    $data = json_decode($body, true);
    if ($code !== 200 || !isset($data['entries'])) json_out($code >= 400 ? $code : 502, '{"error":"Open Library is unavailable right now"}', false);

    $editions = [];
    foreach ($data['entries'] as $e) {
        $covers = array_values(array_filter($e['covers'] ?? [], fn($c) => $c > 0));
        $langs = array_column($e['languages'] ?? [], 'key');
        // editions often have no language; keep those, drop ones that are clearly not English
        if (!$covers || ($langs && !in_array('/languages/eng', $langs, true))) continue;
        preg_match('/\d{4}/', $e['publish_date'] ?? '', $year);
        $editions[] = [
            't' => trim($e['title'] ?? ''),
            'c' => $covers,
            'p' => trim($e['publishers'][0] ?? ''),
            'y' => $year ? (int)$year[0] : null,
            'f' => $e['physical_format'] ?? '',
        ];
    }
    $out = json_encode(['editions' => $editions]);
    @file_put_contents($cacheFile, $out);
    json_out(200, $out, true);
}

/* ---------- search ---------- */
$q = trim((string)($_GET['q'] ?? ''));
$max = (int)($_GET['max'] ?? 10) === 40 ? 40 : 10;
if ($q === '' || strlen($q) > 300) json_out(400, '{"error":"q must be 1-300 characters"}', false);

$cacheFile = $cacheDir . '/ol-' . md5($max . '|' . strtolower($q)) . '.json';
if (is_file($cacheFile) && filemtime($cacheFile) > time() - 86400) json_out(200, file_get_contents($cacheFile), true);

[$code, $body] = ol_get('https://openlibrary.org/search.json?' . http_build_query([
    'q' => $q,
    'language' => 'eng',
    'limit' => $max,
    'fields' => 'key,title,author_name,cover_i,first_publish_year,series_name,series_position',
]));

// Only good answers are cached; errors and timeouts are passed through and retried next time.
if ($code !== 200 || !isset(json_decode($body, true)['docs'])) {
    json_out($code >= 400 ? $code : 502, '{"error":"Open Library is unavailable right now"}', false);
}
@file_put_contents($cacheFile, $body);
json_out(200, $body, true);
