<?php
// Castle Crafty admin data: the home server's shop-log container writes admin.json, and Apache there only serves it
// with the X-Shop-Key header. This proxy adds the key server-side (it never reaches the browser) and caches 60 s.
// Behind /private/'s password; reached as /shop/admin/data through the root .htaccess.
$site_page = [];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
@include __DIR__ . '/key.php'; // $shopadminkey; gitignored, uploaded to the server by hand
session_write_close();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$fail = function ($msg) {
    http_response_code(502);
    echo json_encode(['error' => $msg]);
    exit;
};
if (empty($shopadminkey)) $fail('The shop admin key file (private/shop-admin/key.php) is missing on this server.');

$cache = sys_get_temp_dir() . '/craftyplayz-shop-admin.json';
if (is_file($cache) && time() - filemtime($cache) < 60) { readfile($cache); exit; }

$ch = curl_init('https://assets.craftyplayz.com/minecraft/shop-admin/admin.json');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 12, CURLOPT_ENCODING => '',
    CURLOPT_USERAGENT => 'craftyplayz.com shop admin', CURLOPT_HTTPHEADER => ['X-Shop-Key: ' . $shopadminkey],
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code === 200 && json_decode((string)$body) !== null) {
    @file_put_contents($cache, $body, LOCK_EX);
    echo $body;
} elseif (is_file($cache)) {
    header('X-Stale: 1'); // the home server is unreachable: show the last copy
    readfile($cache);
} else {
    $fail($code ? "The home server answered $code." : "Couldn't reach the home server.");
}
