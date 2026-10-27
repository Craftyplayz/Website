<?php
// All Possible Farms. private/minecraft/farms/ sets $mc_edit = true and requires this file to make the ticks editable.
$mc_edit = !empty($mc_edit);
$site_page = ['components' => true, 'ui' => true, 'css' => ['/assets/css/pages/minecraft-farms.css']];
if ($mc_edit) $site_page['title'] = 'Edit All Possible Farms';
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

$items = json_decode((string)@file_get_contents(__DIR__ . '/items.json'), true) ?: [];
$done = is_file(__DIR__ . '/done.json') ? (json_decode((string)file_get_contents(__DIR__ . '/done.json'), true) ?: []) : [];
// item ids without a local texture, so the browser goes straight to the fallback icon
$tex = array_flip(scandir($_SERVER['DOCUMENT_ROOT'] . '/minecraft/tools/item-list') ?: []);
$noTex = [];
$renew = $infinite = $farmed = 0;
foreach ($items as $it) {
    if (!isset($tex['minecraft_' . $it['id'] . '.png'])) $noTex[$it['id']] = 1;
    $renew += !empty($it['renew']);
    $infinite += !empty($it['infinite']);
    $farmed += !empty($done[$it['k']]);
}
$total = count($items);
$pct = $total ? (int)floor($farmed * 100 / $total) : 0;
$data = ['items' => $items, 'done' => (object)$done, 'noTex' => array_keys($noTex), 'edit' => $mc_edit];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <?php if ($mc_edit): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
</head>

<body>
    <?php site_header(); ?>

    <main class="page mcf" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title"><?= $mc_edit ? 'Edit All Possible Farms' : 'All Possible Farms' ?></h1>
        <p class="page-lede">How to farm every Minecraft item, and which ones I've farmed.<?= $mc_edit ? ' Tick an item to save it.' : '' ?></p>

        <div class="stat-row">
            <div class="stat-card"><b><?= number_format($total) ?></b><span>Items</span></div>
            <div class="stat-card"><b><?= number_format($renew) ?></b><span>Renewable</span></div>
            <div class="stat-card"><b><?= number_format($infinite) ?></b><span>Infinitely automatic</span></div>
            <div class="stat-card stat-ring">
                <span class="ring<?= $pct == 100 ? ' full' : '' ?>" id="ring" style="--p: <?= $pct ?>" role="img" aria-label="<?= $farmed ?> of <?= $total ?> farmed"><b><?= $pct ?><small>%</small></b></span>
                <span><b id="farmed"><?= number_format($farmed) ?> / <?= number_format($total) ?></b><small>farmed</small></span>
            </div>
        </div>

        <div class="bar">
            <input class="field mcf-q" id="q" type="search" placeholder="Search items, methods..." aria-label="Search" autocomplete="off">
            <select class="field" id="cat" aria-label="Category"><option value="">All categories</option></select>
            <select class="field" id="type" aria-label="Farm type"><option value="">All farm types</option></select>
            <select class="field" id="method" aria-label="Method"><option value="">All methods</option></select>
            <label class="check"><input type="checkbox" id="renew"> Renewable</label>
            <label class="check"><input type="checkbox" id="inf"> Infinitely automatic</label>
            <label class="check"><input type="checkbox" id="be"> Works in Bedrock</label>
            <label class="check"><input type="checkbox" id="todo"> Not yet farmed</label>
            <span class="muted" id="count"></span>
        </div>

        <div class="mcf-list" id="list"></div>
        <p class="mcf-more"><button class="btn" id="more" hidden>Show more</button></p>
    </main>

    <aside class="drawer mcf-drawer" id="drawer" aria-label="Item details" tabindex="-1">
        <button class="close" id="close" aria-label="Close" title="Close">&times;</button>
        <div id="detail"></div>
    </aside>

    <?php site_footer(null, false); ?>
    <script>window.MCF = <?= json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;</script>
    <script src="<?= h(asset('/minecraft/projects/farms/farms.js')) ?>" defer></script>
</body>

</html>
