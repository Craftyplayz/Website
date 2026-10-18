<?php
$site_page = ['components' => true, 'css' => ['/minecraft/shop/styles.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

$books = [
    'Mending', 'Unbreaking 3', 'Protection 4', 'Fortune 3', 'Efficiency 5',
    'Depth Strider 3', 'Silk Touch', 'Aqua Affinity', 'Feather Falling 4', 'Sharpness 5',
    'Smite 5', 'Respiration 3', 'Looting 3', 'Punch 2', 'Thorns 3',
    'Blast Protection 4', 'Veinminer',
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
        <p class="page-lede">Every book is 1 diamond, or 5 books for 3 diamonds.</p>

        <section class="card shop-card">
            <h2><img src="/minecraft/tools/item-list/minecraft_enchanted_book.png" alt=""> Books <span class="n"><?= count($books) ?></span></h2>
            <div class="rows">
                <?php foreach ($books as $name): ?>
                    <div class="rowi"><?= h($name) ?></div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
    <?php site_footer(); ?>
</body>

</html>
