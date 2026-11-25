<?php
// Castle Crafty map art request: the quote page posts the map art PNG, a small copy of the original picture, the
// schematic (.nbt) and the settings here. This saves them under requests/<id>/ (server only, shown by view.php) and
// sends one message to Discord (#mapart requests) with the map art, the schematic and a link to the request page.
// The webhook URL is in webhook.php next to this file ($mapartwebhook), which is not in git.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function done($code, $msg = null)
{
    http_response_code($code);
    echo json_encode($msg === null ? ['ok' => true] : ['ok' => false, 'error' => $msg]);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') done(405, 'Send the form from the map art page.');
@include __DIR__ . '/webhook.php';
if (empty($mapartwebhook)) done(503, 'Requests are switched off at the moment.');

// at most 4 requests an hour from one address
$dir = sys_get_temp_dir() . '/craftyplayz-mapart';
@mkdir($dir, 0700, true);
$rl = $dir . '/' . md5($_SERVER['REMOTE_ADDR'] ?? '') . '.json';
$times = array_filter((array)json_decode((string)@file_get_contents($rl), true), function ($t) { return $t > time() - 3600; });
if (count($times) >= 4) done(429, 'You have sent a few requests already. Wait an hour, or message Crafty on Discord.');

// the request details
$raw = (string)($_POST['request'] ?? '');
$q = strlen($raw) < 20000 ? json_decode($raw, true) : null;
if (!is_array($q)) done(400, 'The request was incomplete.');
$mc = (string)($q['mc'] ?? '');
if (!preg_match('/^[A-Za-z0-9_]{3,16}$/', $mc)) done(400, 'Enter your Minecraft name: 3 to 16 letters, numbers or underscores.');
$clean = function ($s, $max) { return mb_substr(trim(preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/u', '', (string)$s)), 0, $max); };
$discord = $clean($q['discord'] ?? '', 40);
$notes = $clean($q['notes'] ?? '', 800);
$size = array_map('intval', (array)($q['size'] ?? []));
if (count($size) !== 2 || $size[0] < 1 || $size[0] > 10 || $size[1] < 1 || $size[1] > 10) done(400, 'The map size is not valid.');
$better = ($q['blocks'] ?? '') === 'better';
$stairs = !empty($q['stairs']);
// the price is worked out here again, so a changed page can't send a cheaper quote
$price = (int)round($size[0] * $size[1] * 64 * ($better ? 1.5 : 1) * ($stairs ? 2 : 1));
$deposit = (int)ceil($price / 2);

// the pictures
$map = $_FILES['map'] ?? null;
if (!$map || $map['error'] !== UPLOAD_ERR_OK || $map['size'] > 6 * 1024 * 1024) done(400, 'The map art picture was missing or too big.');
$info = @getimagesize($map['tmp_name']);
if (!$info || $info[2] !== IMAGETYPE_PNG || $info[0] !== $size[0] * 128 || $info[1] !== $size[1] * 128) done(400, "The map art picture doesn't match the size.");
$orig = $_FILES['original'] ?? null;
$hasOrig = $orig && $orig['error'] === UPLOAD_ERR_OK && $orig['size'] <= 4 * 1024 * 1024 && ($oi = @getimagesize($orig['tmp_name'])) && $oi[2] === IMAGETYPE_JPEG;
// the schematic: a gzipped NBT file made in the browser by MapartCraft's NBT worker
$nbt = $_FILES['nbt'] ?? null;
$hasNbt = $nbt && $nbt['error'] === UPLOAD_ERR_OK && $nbt['size'] <= 20 * 1024 * 1024 && @file_get_contents($nbt['tmp_name'], false, null, 0, 2) === "\x1f\x8b";

// materials: [name, count] for the request page, and the total for Discord
$mats = [];
foreach (array_slice((array)($q['materials'] ?? []), 0, 80) as $m) {
    if (!is_array($m) || count($m) < 2 || (int)$m[1] <= 0) continue;
    $rgb = array_map(function ($c) { return max(0, min(255, (int)$c)); }, array_slice((array)($m[2] ?? [112, 112, 112]), 0, 3));
    $mats[] = [$clean($m[0], 60), min((int)$m[1], 10000000), count($rgb) === 3 ? $rgb : [112, 112, 112]];
}
$items = array_sum(array_column($mats, 1));
$support = max(0, min((int)($q['support'] ?? 0), 10000000));

// the Discord message
$e = function ($s) { return str_replace(['@', '`', '*', '_', '~', '|', '>'], ['@​', "'", '\*', '\_', '\~', '\|', '\>'], $s); }; // no pings or formatting from player text
$style = [($better ? 'Better colour' : 'Carpet'), ($stairs ? 'Staircase' : 'Flat'), 'Dithering: ' . $clean($q['dither'] ?? '', 30), 'Better colour matching: ' . (!empty($q['betterMatching']) ? 'on' : 'off'), 'Crop: ' . $clean($q['crop'] ?? '', 10)];
if (!empty($q['manual']) && is_array($q['manual'])) $style[] = 'Manual crop: zoom ' . (int)$q['manual'][0] / 10 . '×, ' . (int)$q['manual'][1] . '% across, ' . (int)$q['manual'][2] . '% down';
if (!empty($q['preprocessing']) && is_array($q['preprocessing'])) $style[] = 'Brightness ' . (int)$q['preprocessing'][0] . '%, contrast ' . (int)$q['preprocessing'][1] . '%, saturation ' . (int)$q['preprocessing'][2] . '%';

// save it all for the request page (view.php?id=...); the id is random, so a page can't be guessed
$id = bin2hex(random_bytes(8));
$store = __DIR__ . "/requests/$id";
$link = "https://craftyplayz.com/shop/mapart/view.php?id=$id";
$saved = @mkdir($store, 0700, true)
    && move_uploaded_file($map['tmp_name'], "$store/mapart.png")
    && (!$hasOrig || move_uploaded_file($orig['tmp_name'], "$store/original.jpg"))
    && (!$hasNbt || move_uploaded_file($nbt['tmp_name'], "$store/mapart.nbt"))
    && file_put_contents("$store/request.json", json_encode([
        'time' => time(), 'mc' => $mc, 'discord' => $discord, 'notes' => $notes, 'picture' => $clean($q['picture'] ?? '', 120),
        'size' => $size, 'better' => $better, 'stairs' => $stairs, 'style' => $style, 'price' => $price, 'deposit' => $deposit,
        'materials' => $mats, 'items' => $items, 'support' => $support, 'original' => $hasOrig, 'nbt' => $hasNbt,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
if (!$saved) done(500, "The request couldn't be saved.");

$fields = [
    ['name' => 'Minecraft', 'value' => $e($mc), 'inline' => true],
    ['name' => 'Discord', 'value' => $discord !== '' ? $e($discord) : 'not given', 'inline' => true],
    ['name' => 'Size', 'value' => "{$size[0]} × {$size[1]} maps (" . ($size[0] * 128) . ' × ' . ($size[1] * 128) . ' blocks)', 'inline' => true],
    ['name' => 'Price', 'value' => number_format($price) . ' diamonds', 'inline' => true],
    ['name' => 'Deposit (50%)', 'value' => number_format($deposit) . ' diamonds', 'inline' => true],
    ['name' => 'Style', 'value' => implode("\n", $style)],
    ['name' => 'Items', 'value' => number_format($items) . ' blocks (' . number_format(ceil($items / 64)) . ' stacks) in ' . count($mats) . ' kinds' . ($support ? ', plus ' . number_format($support) . ' support blocks' : '')],
];
if ($notes !== '') $fields[] = ['name' => 'Notes', 'value' => mb_substr($e($notes), 0, 1020)];
$fields[] = ['name' => 'Request page', 'value' => $link . ($hasNbt ? '' : "\nNo schematic: the browser couldn't make one.")];
$payload = [
    'allowed_mentions' => ['parse' => []],
    'embeds' => [[
        'title' => 'Map art request from ' . $mc,
        'url' => $link,
        'color' => 0xd9a54e,
        'image' => ['url' => 'attachment://mapart.png'],
        'fields' => $fields,
        'footer' => ['text' => 'craftyplayz.com/shop/mapart'],
        'timestamp' => gmdate('c'),
    ]],
];
$post = ['payload_json' => json_encode($payload), 'files[0]' => new CURLFile("$store/mapart.png", 'image/png', 'mapart.png')];
// Discord takes 10 MB a message without a boost; a bigger schematic is only on the request page
if ($hasNbt && filesize("$store/mapart.nbt") <= 9 * 1024 * 1024) $post['files[1]'] = new CURLFile("$store/mapart.nbt", 'application/octet-stream', "$mc-{$size[0]}x{$size[1]}.nbt");

$ch = curl_init($mapartwebhook);
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 30, CURLOPT_USERAGENT => 'craftyplayz.com map art requests']);
curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($code < 200 || $code >= 300) done(502, "Discord didn't take the request.");

$times[] = time();
@file_put_contents($rl, json_encode(array_values($times)), LOCK_EX);
echo json_encode(['ok' => true, 'url' => $link]);
