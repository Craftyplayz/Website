<?php
// Castle Crafty admin data: the home server's shop-log container writes admin.json and the monthly ledger files
// (shop-admin/ledger/YYYY-MM.jsonl), and Apache there only serves them with the X-Shop-Key header. This proxy adds
// the key server-side (it never reaches the browser) and caches 60 s.
// Behind /private/'s password; reached as /shop/admin/data (?ledger=YYYY-MM for a month of the log).
$site_page = [];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
@include __DIR__ . '/key.php'; // $shopadminkey; gitignored, uploaded to the server by hand
session_write_close();
header('Cache-Control: no-store');

$fail = function ($msg) {
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $msg]);
    exit;
};
if (empty($shopadminkey)) $fail('The shop admin key file (private/shop-admin/key.php) is missing on this server.');

$month = isset($_GET['ledger']) ? (string)$_GET['ledger'] : '';
if ($month !== '' && !preg_match('/^\d{4}-\d{2}$/', $month)) $fail('That month is not valid.');
$path = $month !== '' ? "ledger/$month.jsonl" : 'admin.json';
header('Content-Type: ' . ($month !== '' ? 'application/x-ndjson' : 'application/json') . '; charset=utf-8');

$cache = sys_get_temp_dir() . '/craftyplayz-shop-admin-' . ($month !== '' ? $month : 'admin') . '.cache';
if (is_file($cache) && time() - filemtime($cache) < 60) { readfile($cache); exit; }

$ch = curl_init('https://assets.craftyplayz.com/minecraft/shop-admin/' . $path);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20, CURLOPT_ENCODING => '',
    CURLOPT_USERAGENT => 'craftyplayz.com shop admin', CURLOPT_HTTPHEADER => ['X-Shop-Key: ' . $shopadminkey],
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$ok = $code === 200 && ($month !== '' || json_decode((string)$body) !== null);
if ($ok) {
    @file_put_contents($cache, $body, LOCK_EX);
    echo $body;
} elseif (is_file($cache)) {
    header('X-Stale: 1'); // the home server is unreachable: show the last copy
    readfile($cache);
} else {
    $fail($code === 404 && $month !== '' ? 'There is no log for that month.' : ($code ? "The home server answered $code." : "Couldn't reach the home server."));
}
