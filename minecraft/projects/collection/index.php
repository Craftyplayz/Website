<?php
$mc_edit = !empty($mc_edit);
$site_page = ['components' => true, 'ui' => true, 'css' => ['/assets/css/pages/minecraft-collection.css']];
if ($mc_edit) $site_page['title'] = 'Edit Collection Checklist';
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

$list = json_decode((string)file_get_contents(__DIR__ . '/list.json'), true) ?: ['items' => [], 'templates' => [], 'sherds' => []];
$done = is_file(__DIR__ . '/done.json') ? (json_decode((string)file_get_contents(__DIR__ . '/done.json'), true) ?: []) : [];
$icons = $_SERVER['DOCUMENT_ROOT'] . '/minecraft/tools/item-list/minecraft_';
foreach ($list as &$sec) {
    foreach ($sec as &$e) {
        if (isset($e['id']) && !is_file($icons . $e['id'] . '.png')) unset($e['id']);
    }
}
unset($sec, $e);
$secs = ['items' => 'Items', 'templates' => 'Smithing templates', 'sherds' => 'Pottery sherds'];
$counts = [];
foreach ($secs as $s => $_) {
    $n = 0;
    foreach ($list[$s] as $e) $n += isset($done[$e['k']]) ? 1 : 0;
    $counts[$s] = [$n, count($list[$s])];
}
$all = array_sum(array_column($counts, 0));
$allTotal = array_sum(array_column($counts, 1));
$pct = $allTotal ? number_format(floor($all / $allTotal * 10000) / 100, 2, '.', '') : '0.00';
$data = ['list' => $list, 'done' => (object)$done, 'edit' => $mc_edit];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <?php if ($mc_edit): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
</head>

<body>
    <?php site_header(); ?>

    <main class="page mcc" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title"><?= $mc_edit ? 'Edit Collection Checklist' : 'Collection Checklist' ?></h1>
        <p class="page-lede">Every item, smithing template and pottery sherd I want in my collection, and how many I have so far. <a href="/minecraft/projects/collection/hallway/">Walk the chests in 3D</a>.</p>

        <div class="stat-row">
            <?php foreach ($secs as $s => $label): ?>
                <div class="stat-card" data-stat="<?= h($s) ?>">
                    <b><?= number_format($counts[$s][0]) ?> / <?= number_format($counts[$s][1]) ?></b>
                    <span><?= h($label) ?></span>
                </div>
            <?php endforeach; ?>
            <div class="stat-card stat-ring">
                <span class="ring<?= $pct >= 100 ? ' full' : '' ?>" id="mcc-ring" style="--p: <?= $pct ?>" role="img" aria-label="<?= $all ?> of <?= $allTotal ?> collected"><b><?= $pct ?>%</b></span>
                <span>Overall<small id="mcc-all"><?= number_format($all) ?> of <?= number_format($allTotal) ?></small></span>
            </div>
        </div>

        <div class="bar">
            <div class="stabs" id="mcc-tabs" style="margin:0">
                <?php foreach ($secs as $s => $label): ?>
                    <button type="button" data-s="<?= h($s) ?>"><?= h($label) ?> <span class="muted" data-n="<?= h($s) ?>"><?= $counts[$s][0] ?>/<?= $counts[$s][1] ?></span></button>
                <?php endforeach; ?>
            </div>
            <input class="field" type="search" id="mcc-q" placeholder="Search" aria-label="Search this list" autocomplete="off">
            <label class="check"><input type="checkbox" id="mcc-missing"> Missing only</label>
            <span class="muted" id="mcc-count"></span>
        </div>
        <p class="muted mcc-key"><span class="badge">+</span> longer-duration potion &nbsp; <span class="badge">JE</span> Java Edition only</p>

        <div class="mcc-grid" id="mcc-grid"></div>
        <p><button type="button" class="btn" id="mcc-more" hidden>Show more</button></p>
        <noscript><p class="err">This list needs JavaScript.</p></noscript>
    </main>

    <?php site_footer(null, false); ?>
    <script>window.MCC = <?= json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?>;</script>
    <script src="<?= h(asset('/minecraft/projects/collection/collection.js')) ?>" defer></script>
</body>

</html>
