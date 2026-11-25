<?php
// One map art request, saved by request.php in requests/<id>/: full details, materials and the files
// (schematic, map art PNG, original picture). view.php?id=<id> shows the page; &f=nbt|png|jpg downloads a file.
$id = (string)($_GET['id'] ?? '');
$dir = __DIR__ . "/requests/$id";
$r = preg_match('/^[0-9a-f]{16}$/', $id) ? json_decode((string)@file_get_contents("$dir/request.json"), true) : null;
header('X-Robots-Tag: noindex');
if (!is_array($r)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit("This map art request doesn't exist. Check the link, or message Crafty on Discord.");
}
$files = [
    'nbt' => ['mapart.nbt', 'application/octet-stream', "{$r['mc']}-{$r['size'][0]}x{$r['size'][1]}.nbt"],
    'png' => ['mapart.png', 'image/png', "{$r['mc']}-{$r['size'][0]}x{$r['size'][1]}.png"],
    'jpg' => ['original.jpg', 'image/jpeg', "{$r['mc']}-original.jpg"],
];
$f = (string)($_GET['f'] ?? '');
if ($f !== '') {
    if (!isset($files[$f]) || !is_file("$dir/{$files[$f][0]}")) { http_response_code(404); exit; }
    header('Content-Type: ' . $files[$f][1]);
    header('Cache-Control: private, max-age=86400');
    if (isset($_GET['dl'])) header('Content-Disposition: attachment; filename="' . $files[$f][2] . '"');
    readfile("$dir/{$files[$f][0]}");
    exit;
}
$h = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$n = function ($v) { return number_format($v); };
$u = function ($f, $dl = false) use ($id) { return "view.php?id=$id&amp;f=$f" . ($dl ? '&amp;dl=1' : ''); };
[$w, $hh] = $r['size'];
$maps = $w * $hh;
?><!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Map art request from <?= $h($r['mc']) ?> · Castle Crafty</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;700&family=Cormorant+Garamond:wght@500;600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="mapart.css?v=1">
<style>
.req{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,420px);gap:20px;align-items:start;padding:16px 0 24px}
.req .frame img{display:block;width:100%;height:auto;image-rendering:pixelated}
.req .orig{margin-top:12px}.req .orig img{image-rendering:auto;max-height:70vh;object-fit:contain}
.req .files{display:grid;gap:8px;margin:14px 0 0}
.req .files .btn{text-align:center;text-decoration:none}
.req .bd dd{overflow-wrap:anywhere}
.req .style{margin:0;padding:0;list-style:none}
.req .notes{white-space:pre-wrap;overflow-wrap:anywhere;padding:8px 10px;background:#050507;border:1px solid #23232b;border-radius:3px}
@media (max-width:900px){.req{grid-template-columns:minmax(0,1fr)}}
</style>
</head>
<body>
<div class="tower">
<header class="top"><p class="kicker"><a href="/shop/">Castle Crafty</a> · <a href="./">Map art</a></p><h1>Map art request</h1>
<p class="lead">From <?= $h($r['mc']) ?> · <?= $h(gmdate('j M Y, H:i', $r['time'])) ?> UTC</p></header>
<div class="req">
<section class="storey" aria-label="Pictures">
<div class="frame"><img src="<?= $u('png') ?>" width="<?= $w * 128 ?>" height="<?= $hh * 128 ?>" alt="The map art"></div>
<?php if (!empty($r['original'])): ?><div class="frame orig"><img src="<?= $u('jpg') ?>" alt="The original picture" loading="lazy"></div><?php endif ?>
</section>
<section class="storey" aria-labelledby="hd">
<p class="level">Request</p><h2 id="hd">Details</h2>
<dl class="bd">
<dt>Minecraft</dt><dd><?= $h($r['mc']) ?></dd>
<dt>Discord</dt><dd><?= $r['discord'] !== '' ? $h($r['discord']) : 'not given' ?></dd>
<dt>Size</dt><dd><?= "$w × $hh map" . ($maps > 1 ? 's' : '') . ' (' . $w * 128 . ' × ' . $hh * 128 . ' blocks)' ?></dd>
<?php if ($r['picture'] !== ''): ?><dt>Picture</dt><dd><?= $h($r['picture']) ?></dd><?php endif ?>
<dt class="tot">Price</dt><dd class="tot"><?= $n($r['price']) ?> diamonds</dd>
<dt>Deposit (50%)</dt><dd><?= $n($r['deposit']) ?> diamonds</dd>
<dt>Items</dt><dd><?= $n($r['items']) ?> blocks (<?= $n(ceil($r['items'] / 64)) ?> stacks)</dd>
<?php if ($r['support']): ?><dt>Support blocks</dt><dd><?= $n($r['support']) ?> (not in the schematic)</dd><?php endif ?>
</dl>
<h3 class="lab">Style</h3>
<ul class="style"><?php foreach ($r['style'] as $s): ?><li><?= $h($s) ?></li><?php endforeach ?></ul>
<?php if ($r['notes'] !== ''): ?><h3 class="lab">Notes</h3><p class="notes"><?= $h($r['notes']) ?></p><?php endif ?>
<div class="files">
<?php if (!empty($r['nbt'])): ?><a class="btn big" href="<?= $u('nbt', true) ?>">Download schematic (.nbt)</a><?php else: ?><p class="hint">No schematic: the browser couldn't make one. Remake it in MapartCraft from the original picture.</p><?php endif ?>
<a class="btn" href="<?= $u('png', true) ?>">Download map art (.png)</a>
<?php if (!empty($r['original'])): ?><a class="btn" href="<?= $u('jpg', true) ?>">Download original picture</a><?php endif ?>
</div>
<h3 class="lab">Materials <small><?= count($r['materials']) ?> kinds</small></h3>
<ul class="mats"><?php foreach ($r['materials'] as $m): ?><li><i style="background:rgb(<?= implode(',', array_map('intval', $m[2] ?? [112, 112, 112])) ?>)"></i><span><?= $h($m[0]) ?></span><b><?= $n($m[1]) ?></b><small><?= $n(ceil($m[1] / 64)) ?> stacks</small></li><?php endforeach ?></ul>
</section>
</div>
<footer><a href="./">Map art quotes</a> · <a href="/shop/">Castle Crafty</a></footer>
</div>
</body>
</html>
