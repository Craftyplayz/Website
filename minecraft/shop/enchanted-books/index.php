<?php
$site_page = ['components' => true, 'css' => ['/minecraft/shop/styles.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

$books = [
    ['Mending', 2], ['Unbreaking 3', 2], ['Protection 4', 2], ['Fortune 3', 2], ['Efficiency 5', 2],
    ['Depth Strider 3', 1], ['Silk Touch', 1], ['Aqua Affinity', 1], ['Feather Falling 4', 1], ['Sharpness 5', 1],
    ['Smite 5', 1], ['Respiration 3', 1], ['Looting 3', 1], ['Punch 2', 1], ['Thorns 3', 1],
    ['Blast Protection 4', 1], ['Veinminer', 1],
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
        <h1 class="page-title">Captainalism Enchanted Books</h1>
        <p class="page-lede">Price list for enchanted books.</p>

        <section class="card shop-card">
            <h2><img src="/minecraft/tools/item-list/minecraft_enchanted_book.png" alt=""> Books <span class="n"><?= count($books) ?></span></h2>
            <div class="rows">
                <?php foreach ($books as [$name, $price]): ?>
                    <div class="rowi"><span class="grow"><?= h($name) ?></span><small><?= $price ?> diamond<?= $price === 1 ? '' : 's' ?></small></div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
    <?php site_footer(); ?>
</body>

</html>
