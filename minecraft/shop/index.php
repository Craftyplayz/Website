<?php
$site_page = ['components' => true, 'css' => ['/minecraft/shop/styles.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

// [item id in /minecraft/tools/item-list/, name, price]
$products = [
    ['iron_block', 'Block of Iron', '2 diamonds for 32 blocks'],
    ['gold_block', 'Block of Gold', '2 diamonds per 32'],
    ['enchanted_book', 'Enchanted Books', 'All books 1 diamond, except Swift Sneak'],
    ['golden_carrot', 'Golden Carrot', '1 diamond per stack'],
    ['prismarine', 'Prismarine', '1 diamond per stack'],
    ['prismarine_bricks', 'Prismarine Bricks', '2 diamonds per stack'],
    ['sea_lantern', 'Sea Lantern', '2 diamonds per stack'],
    ['bone', 'Bone', '1 diamond per stack'],
    ['string', 'String', '1 diamond per stack'],
    ['gunpowder', 'Gunpowder', '1 diamond per stack'],
    ['arrow', 'Arrow', '1 diamond per stack'],
    ['leather', 'Leather', '2 diamonds per stack'],
    ['slime_block', 'Slime Block', '1 diamond per stack'],
    ['light_gray_wool', 'Wool', '1 diamond per stack, all colours available'],
    ['redstone', 'Redstone Dust', '1 diamond per stack'],
    ['lapis_lazuli', 'Lapis Lazuli', '2 diamonds per stack'],
    ['item_frame', 'Item Frame', '2 diamonds per stack'],
    ['glowstone', 'Glowstone', '1 diamond per stack'],
    ['bricks', 'Bricks', '2 stacks per diamond'],
    ['glass', 'Glass', '1 diamond per stack'],
    ['name_tag', 'Name Tag', '2 diamonds per 16'],
    ['bookshelf', 'Bookshelf', '2 diamonds per stack'],
    ['beacon', 'Beacon', '4 diamonds each, or 12 diamonds for a full kit'],
    ['shulker_box', 'Shulker Box', '1 diamond each'],
    ['elytra', 'Elytra', '6 diamonds each'],
    ['diamond_pickaxe', 'Diamond Tools', '8 diamonds per tool, fully enchanted'],
    ['diamond_helmet', 'Diamond Armour', '8 diamonds per piece, fully enchanted'],
    ['experience_bottle', "Bottle o' Enchanting", '4 diamonds per stack'],
    ['quartz', 'Nether Quartz', '1 diamond per stack'],
    ['obsidian', 'Obsidian', '1 diamond per stack'],
    ['crying_obsidian', 'Crying Obsidian', '1 diamond per stack'],
    ['soul_sand', 'Soul Sand', '1 diamond per stack'],
    ['gravel', 'Gravel', '2 stacks per diamond'],
    ['blackstone', 'Blackstone', '2 stacks per diamond'],
];
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
        <h1 class="page-title">Captainalism</h1>
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
