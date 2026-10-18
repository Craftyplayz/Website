<?php
// Server-side proxy for https://api.minecraftitems.xyz so the API key in /php/config.php
// never reaches the browser.
//
//   GET  api.php?item=diamond_sword[&rp=<packId>,<packId>...][&cmd=123] -> PNG (disk cached)
//   POST api.php?rp=upload   {"name": "...", "url": "https://.../pack.zip"} -> JSON from the API
//   GET  api.php?rp=status&id=<packId>                          -> JSON from the API
//   GET  api.php?rp=defaults                                    -> the packs in ./resourcepacks
//   POST api.php?share=create / GET api.php?share=<id>          -> share links (stored in ./shares)

// config.php starts a session and prints markup meant for full pages - swallow all of it,
// and stop it from sending no-cache headers or holding the session lock (images load in parallel).
session_cache_limiter('');
ob_start();
include $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
ob_end_clean();
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
header_remove('Set-Cookie');

const MC_API = 'https://api.minecraftitems.xyz';
$key = isset($mcitemskey) ? $mcitemskey : '';
$uuidRe = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

function fetch_url($url, $postJson = null)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    if ($postJson !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postJson));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    return [$code ?: 502, $body === false ? '' : $body, $type];
}

function json_out($code, $data)
{
    http_response_code($code);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo is_string($data) ? $data : json_encode($data);
    exit;
}

/* ---------- item images ---------- */

// One render from the API, cached on disk. Returns the PNG bytes, or null if the item doesn't exist.
// Negative results (.miss) are re-checked after a day.
function render_item($item, $rp, $cmd)
{
    global $key;
    $cacheDir = __DIR__ . '/cache';
    $cacheFile = $cacheDir . '/' . $item . ($rp ? '.' . strtolower($rp) : '') . ($cmd !== '' ? '.c' . $cmd : '');
    if (is_file($cacheFile . '.png')) return file_get_contents($cacheFile . '.png');
    if (is_file($cacheFile . '.miss') && filemtime($cacheFile . '.miss') > time() - 86400) return null;

    $query = [];
    if ($rp) $query['rp'] = $rp;
    if ($rp && $cmd !== '') $query['cmd'] = $cmd;
    if ($rp) $query['apiKey'] = $key;
    [$code, $body, $type] = fetch_url(MC_API . '/api/item/' . $item . ($query ? '?' . http_build_query($query) : ''));

    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
    if ($code === 200 && strpos((string)$type, 'image/') === 0) {
        @file_put_contents($cacheFile . '.png', $body);
        return $body;
    }
    if ($code === 404) @touch($cacheFile . '.miss');
    return null;
}

if (isset($_GET['item'])) {
    $item = strtolower(preg_replace('/^minecraft:/', '', $_GET['item']));
    $cmd = isset($_GET['cmd']) ? $_GET['cmd'] : '';
    if (!preg_match('/^[a-z0-9_]{1,80}$/', $item)) json_out(400, ['error' => 'bad item']);
    if ($cmd !== '' && !preg_match('/^-?\d{1,12}(\.\d+)?$/', $cmd)) $cmd = '';

    // rp can be a comma separated priority list (highest first), max 5 packs
    $rps = [];
    foreach (explode(',', isset($_GET['rp']) ? $_GET['rp'] : '') as $r) {
        if (preg_match($uuidRe, $r) && count($rps) < 5) $rps[] = strtolower($r);
    }

    $png = render_item($item, '', '');
    // The API takes one pack per render, so stack them here: the first pack whose render
    // differs from vanilla is the one that changes this item.
    foreach ($rps as $rp) {
        $packed = render_item($item, $rp, $cmd);
        if ($packed !== null && $packed !== $png) {
            $png = $packed;
            break;
        }
    }

    if ($png === null) {
        http_response_code(404);
        header('Cache-Control: public, max-age=86400');
        exit;
    }
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=2592000, immutable');
    echo $png;
    exit;
}

/* ---------- my server's player files ---------- */
// GET api.php?srv=stats|advancements|data   -> that folder's directory listing (HTML)
// GET api.php?srv=<folder>&f=<file>         -> one file. If-None-Match / If-Modified-Since are passed on,
//                                              so an unchanged file comes back as a bodyless 304.
// The viewer only comes here when the asset server's CORS rules stop the browser fetching it directly.
if (isset($_GET['srv'])) {
    $dir = $_GET['srv'];
    $f = isset($_GET['f']) ? $_GET['f'] : '';
    if (!in_array($dir, ['stats', 'advancements', 'data'], true) || ($f !== '' && !preg_match('/^[A-Za-z0-9_-][A-Za-z0-9._-]{0,99}$/', $f))) json_out(400, ['error' => 'bad file']);
    $send = [];
    foreach (['HTTP_IF_NONE_MATCH' => 'If-None-Match', 'HTTP_IF_MODIFIED_SINCE' => 'If-Modified-Since'] as $k => $h) {
        // mod_deflate tags compressed responses' ETags with -gzip / -br; the asset server doesn't know those
        if (!empty($_SERVER[$k])) $send[] = $h . ': ' . preg_replace('/-(gzip|br|deflate)"/', '"', substr(str_replace(["\r", "\n"], '', $_SERVER[$k]), 0, 200));
    }
    $got = [];
    $ch = curl_init('https://assets.craftyplayz.com/minecraft-players/' . $dir . '/' . rawurlencode($f));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => $send,
        CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$got) {
            if (preg_match('/^(etag|last-modified):\s*(.+?)\s*$/i', $line, $m)) $got[strtolower($m[1])] = $m[2];
            return strlen($line);
        },
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || !$code) json_out(502, ['error' => 'The server could not be reached']);
    http_response_code(in_array($code, [200, 304, 404], true) ? $code : 502);
    header('Cache-Control: no-store');
    header('Content-Type: ' . ($f === '' ? 'text/html; charset=utf-8' : 'application/octet-stream'));
    if (isset($got['etag'])) header('ETag: ' . $got['etag']);
    if (isset($got['last-modified'])) header('Last-Modified: ' . $got['last-modified']);
    if ($code !== 304) echo $body;
    exit;
}

/* ---------- share links ---------- */
// POST api.php?share=create  (body: gzipped JSON {v, players: [...]})  -> {"id": "..."}
// GET  api.php?share=<id>                                              -> the gzipped JSON
$shareDir = __DIR__ . '/shares';
if (isset($_GET['share'])) {
    if ($_GET['share'] === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw = file_get_contents('php://input', false, null, 0, 4 * 1024 * 1024 + 1);
        if ($raw === false || strlen($raw) < 20 || strlen($raw) > 4 * 1024 * 1024) json_out(413, ['error' => 'Share data must be under 4 MB compressed.']);
        $json = @gzdecode($raw, 40 * 1024 * 1024);
        $data = $json === false ? null : json_decode($json, true);
        if (!is_array($data) || !isset($data['players']) || !is_array($data['players'])) json_out(400, ['error' => 'Invalid share data.']);

        // same data = same link, so re-sharing never creates duplicates
        $id = substr(hash('sha256', $raw), 0, 12);
        if (!is_dir($shareDir)) {
            @mkdir($shareDir, 0755, true);
            @file_put_contents($shareDir . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
        }
        if (!is_file("$shareDir/$id.gz") && @file_put_contents("$shareDir/$id.gz", $raw) === false) json_out(500, ['error' => 'Could not save the share on the server.']);
        json_out(200, ['id' => $id]);
    }
    if (preg_match('/^[0-9a-f]{12}$/', $_GET['share']) && is_file("$shareDir/{$_GET['share']}.gz")) {
        header('Content-Type: application/octet-stream');
        header('Cache-Control: public, max-age=31536000, immutable');
        readfile("$shareDir/{$_GET['share']}.gz");
        exit;
    }
    json_out(404, ['error' => 'That share link does not exist (or has been removed).']);
}

/* ---------- resource packs ---------- */
$rpAction = isset($_GET['rp']) ? $_GET['rp'] : '';

if ($rpAction === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true);
    $name = trim((string)($in['name'] ?? ''));
    $url = trim((string)($in['url'] ?? ''));
    if ($name === '' || strlen($name) > 60) json_out(400, ['error' => 'Give the pack a name (max 60 characters).']);
    if (!preg_match('#^https://[^\s]{4,500}$#i', $url)) json_out(400, ['error' => 'The download URL must be a direct https:// link to a .zip file.']);
    [$code, $body] = fetch_url(MC_API . '/api/setup/resourcepack?apiKey=' . urlencode($key), [
        'packName' => $name,
        'downloadUrl' => $url,
    ]);
    json_out($code, $body !== '' ? $body : ['error' => 'No response from minecraftitems.xyz']);
}

// The packs in ./resourcepacks are offered to everyone. Each one is registered with the API the
// first time it is seen (and again if the file changes); the pack ids are kept in cache/default-packs.json.
//   GET api.php?rp=defaults -> [{"file": "...", "name": "...", "id": "<packId>" | null, "status": "..."}]
if ($rpAction === 'defaults') {
    $packDir = __DIR__ . '/resourcepacks';
    $regFile = __DIR__ . '/cache/default-packs.json';
    if (!is_dir(dirname($regFile))) @mkdir(dirname($regFile), 0755, true);
    $lock = fopen($regFile . '.lock', 'c');
    if ($lock) flock($lock, LOCK_EX);
    $reg = is_file($regFile) ? (json_decode(file_get_contents($regFile), true) ?: []) : [];

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http';
    $base = $scheme . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/resourcepacks/';

    $out = [];
    $seen = [];
    foreach (glob($packDir . '/*.zip') ?: [] as $path) {
        $file = basename($path);
        $seen[$file] = true;
        $sig = filesize($path) . '-' . filemtime($path);
        $r = $reg[$file] ?? null;
        $stale = !$r || $r['sig'] !== $sig;
        // failed registrations are retried after 10 minutes
        if ($stale || (empty($r['id']) && ($r['tried'] ?? 0) < time() - 600)) {
            $name = substr(preg_replace('/[-_]+/', ' ', pathinfo($file, PATHINFO_FILENAME)), 0, 60);
            [$code, $body] = fetch_url(MC_API . '/api/setup/resourcepack?apiKey=' . urlencode($key), [
                'packName' => $name,
                'downloadUrl' => $base . rawurlencode($file),
            ]);
            $j = json_decode($body, true) ?: [];
            $id = $j['packId'] ?? $j['id'] ?? $j['resourcePackId'] ?? ($j['pack']['id'] ?? $j['pack']['packId'] ?? null);
            $ok = $code >= 200 && $code < 300 && is_string($id) && preg_match($uuidRe, $id);
            $r = [
                'sig' => $sig,
                'name' => $name,
                'id' => $ok ? strtolower($id) : null,
                'status' => $ok ? (string)($j['status'] ?? 'processing') : 'error: ' . ($j['error'] ?? $j['message'] ?? 'HTTP ' . $code),
                'tried' => time(),
            ];
            $reg[$file] = $r;
        }
        $out[] = ['file' => $file, 'name' => $r['name'], 'id' => $r['id'], 'status' => $r['status']];
    }
    $reg = array_intersect_key($reg, $seen);
    @file_put_contents($regFile, json_encode($reg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
    usort($out, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
    json_out(200, $out);
}

if ($rpAction === 'status' && isset($_GET['id']) && preg_match($uuidRe, $_GET['id'])) {
    [$code, $body] = fetch_url(MC_API . '/api/resourcepack/' . $_GET['id'] . '?apiKey=' . urlencode($key));
    json_out($code, $body !== '' ? $body : ['error' => 'No response from minecraftitems.xyz']);
}

json_out(400, ['error' => 'Unknown request']);
