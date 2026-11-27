<?php
$site_page = ['components' => true, 'ui' => true, 'css' => ['/assets/css/pages/minecraft-models.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

// models.json is read on every view so new items show up without code changes
$data = json_decode((string)@file_get_contents(__DIR__ . '/models.json'), true);
$items = (is_array($data) && isset($data['items']) && is_array($data['items']))
    ? array_values(array_filter($data['items'], function ($i) { return is_array($i) && is_string($i['name'] ?? null) && $i['name'] !== ''; }))
    : null;

$groups = [];
if ($items) {
    // listed categories first, in their order, then any new ones the items use
    foreach (array_merge((array)($data['categories'] ?? []), array_column($items, 'category')) as $c) {
        if (is_string($c) && $c !== '') $groups[$c] = [];
    }
    $groups['Other'] = [];
    foreach ($items as $n => $i) {
        $c = is_string($i['category'] ?? null) && $i['category'] !== '' ? $i['category'] : 'Other';
        $groups[$c][$n] = $i;
    }
    $groups = array_filter($groups);
}
$updated = is_string($data['updated'] ?? null) && strtotime($data['updated']) ? date('j M Y', strtotime($data['updated'])) : '';
$pack = is_string($data['resource_pack'] ?? null) && preg_match('#^https?://#', $data['resource_pack']) ? $data['resource_pack'] : '';
$slug = function ($s) { return 'cat-' . strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $s)); };
// versioned (?v=mtime) so re-rendered files get past the 30-day Cloudflare cache
$img = function ($p) { return is_string($p) && $p !== '' ? asset('/minecraft/unlimitedsmp/models/' . ltrim($p, '/')) : ''; };
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>

    <main class="page mdl" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <header class="page-hero">
            <div class="page-hero-body">
                <div class="grow">
                    <h1 class="page-title">Rename Items</h1>
                    <p class="page-lede">Rename a normal item in an anvil (costs a little XP) to turn it into one of these. You need the server resource pack to see them.</p>
                </div>
                <?php if ($pack || $updated): ?>
                    <div class="mdl-pack">
                        <?php if ($pack): ?><a class="btn primary" href="<?= h($pack) ?>">Download resource pack</a><?php endif; ?>
                        <?php if ($updated): ?><small class="muted">Updated <?= h($updated) ?></small><?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </header>

        <?php if (!$items): ?>
            <p class="err">Couldn't load the item list. Try again in a minute.</p>
        <?php else: ?>
            <div class="stat-row">
                <?php foreach ($groups as $c => $list): ?>
                    <a class="stat-card" href="#<?= h($slug($c)) ?>"><b><?= count($list) ?></b><span><?= h($c) ?></span></a>
                <?php endforeach; ?>
            </div>

            <div class="bar">
                <input class="field" type="search" id="mdl-q" placeholder="Search items" aria-label="Search items">
                <span class="muted" id="mdl-count"><?= count($items) ?> items</span>
            </div>
            <p class="note" id="mdl-none" hidden>No items match that search.</p>

            <?php $eager = 12; foreach ($groups as $c => $list): ?>
                <section class="mdl-sec" id="<?= h($slug($c)) ?>">
                    <h2 class="sec-h"><?= h($c) ?> <span class="n"><?= count($list) ?></span></h2>
                    <div class="mdl-grid">
                        <?php foreach ($list as $n => $i): ?>
                            <a class="mdl-card" href="#<?= h(rawurlencode($i['name'])) ?>" data-i="<?= $n ?>">
                                <?php if ($src = $img($i['image'] ?? null)): ?>
                                    <img src="<?= h($src) ?>" alt="" width="256" height="256" decoding="async"<?= $eager-- > 0 ? '' : ' loading="lazy"' ?> onerror="this.remove()">
                                <?php endif; ?>
                                <span><?= h($i['name']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <aside class="drawer mdl-drawer" id="mdl-drawer" aria-label="Item details" aria-hidden="true" tabindex="-1">
                <button class="close" aria-label="Close" title="Close">&times;</button>
                <div id="mdl-detail"></div>
            </aside>
        <?php endif; ?>
    </main>

    <?php site_footer(null, false); ?>

    <?php if ($items): ?>
        <?php // give_command is admin-only and the site has no admin mode, so it never reaches the browser ?>
        <script>
            window.MDL_ITEMS = <?= json_encode(array_map(function ($i) use ($img) {
                foreach (['image', 'model_3d', 'texture'] as $k) if (is_string($i[$k] ?? null) && $i[$k] !== '') $i[$k] = substr($img($i[$k]), strlen('/minecraft/unlimitedsmp/models/'));
                return array_diff_key($i, ['give_command' => 1]);
            }, $items), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
        </script>
        <script src="<?= asset('/minecraft/unlimitedsmp/models/models.js') ?>" defer></script>
    <?php endif; ?>
</body>

</html>
