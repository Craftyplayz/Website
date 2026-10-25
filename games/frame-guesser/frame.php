<?php
// Streams the current or next frame of the player's run by its one-round token,
// so the image URL never names the show or episode (see lib.php).
require __DIR__ . '/lib.php';

fg_session(true); // read and release the lock at once: <img> requests run in parallel
$t = $_GET['t'] ?? '';
$run = $_SESSION['run'] ?? null;
$round = null;
if (is_array($run) && is_string($t) && preg_match('/^[a-f0-9]{24}$/', $t)) {
    foreach (['cur', 'next'] as $k) {
        if (($run[$k]['token'] ?? null) === $t) {
            $round = $run[$k];
        }
    }
}
if ($round === null) {
    http_response_code(404);
    header('Cache-Control: no-store');
    exit;
}

$ch = curl_init(fg_url(fg_frame_parts($round)));
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 12, CURLOPT_USERAGENT => FG_UA]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($code !== 200 || !is_string($body) || $body === '') {
    http_response_code(502);
    header('Cache-Control: no-store');
    exit;
}
// Each token is used for one round only, so the browser may keep the image; private keeps it out of shared caches
header('Content-Type: image/jpeg');
header('Cache-Control: private, max-age=3600');
header('Content-Length: ' . strlen($body));
echo $body;
