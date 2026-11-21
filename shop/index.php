<?php
$site_page = ['components' => true, 'css' => ['/shop/styles.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

$shop = json_decode(file_get_contents(__DIR__ . '/data.json'), true); // one price list for every page and theme
$products = array_map(fn($p) => [$p['id'], $p['name'], $p['price']], $shop['products']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>
    <main class="page" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Castle Crafty</h1>
        <p class="page-lede">Price list. <?= count($products) ?> items, paid in diamonds.</p>

        <div class="products">
            <?php foreach ($products as [$id, $name, $price]): ?>
                <div class="product">
                    <img src="/minecraft/tools/item-list/minecraft_<?= h($id) ?>.png" width="48" height="48" alt="" loading="lazy" decoding="async">
                    <div><b><?= h($name) ?></b><span><?= h($price) ?></span></div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
    <?php site_footer(); ?>
</body>

</html>
