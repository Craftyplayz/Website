<?php
// Streams a frame of the player's run by its one-round token, so the image URL
// never names the show, season or episode (see lib.php). Tokens work for the
// current frame, the next one (preloaded) and the one just answered.
// In minimum context the frame is cut down to the zoom step the run has reached;
// ?z= only gives each step its own address for the browser cache.
require __DIR__ . '/lib.php';

fg_session(true); // read and release the lock at once: <img> requests run in parallel
$t = $_GET['t'] ?? '';
$run = $_SESSION['run'] ?? null;
$round = null;
$answered = false;
if (is_array($run) && is_string($t) && preg_match('/^[a-f0-9]{24}$/', $t)) {
    foreach (['cur', 'next', 'prev'] as $k) {
        if (($run[$k]['token'] ?? null) === $t) {
            $round = $run[$k];
            $answered = $k === 'prev' && ($run['over'] || $run['cur']['token'] !== $t);
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

$step = $run['mode'] === 'minimum' && !$answered ? $round['zoom'] : count(FG_ZOOMS) - 1;
if ($step < count(FG_ZOOMS) - 1) {
    // Crop round the frame's chosen centre and scale it back up to full size
    $im = @imagecreatefromstring($body);
    if (!$im) {
        http_response_code(502);
        header('Cache-Control: no-store');
        exit;
    }
    [$w, $h] = [imagesx($im), imagesy($im)];
    $cropW = max(1, (int) round($w * FG_ZOOMS[$step]));
    $cropH = max(1, (int) round($h * FG_ZOOMS[$step]));
    $x = (int) min(max(0, round($round['cx'] * $w - $cropW / 2)), $w - $cropW);
    $y = (int) min(max(0, round($round['cy'] * $h - $cropH / 2)), $h - $cropH);
    $out = imagecreatetruecolor($w, $h);
    imagecopyresampled($out, $im, 0, 0, $x, $y, $w, $h, $cropW, $cropH);
    ob_start();
    imagejpeg($out, null, 85);
    $body = ob_get_clean();
}

// Each token is used for one round only, so the browser may keep the image; private keeps it out of shared caches
header('Content-Type: image/jpeg');
header('Cache-Control: private, max-age=3600');
header('Content-Length: ' . strlen($body));
echo $body;
