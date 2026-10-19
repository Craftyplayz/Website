<?php
$site_page = [
    'components' => true,
    'css' => ['/assets/css/pages/minecraft-gallery.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
$images = array_values(array_diff(scandir(SITE_ROOT . '/media/minecraft/mapart') ?: [], ['.', '..']));
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
        <h1 class="page-title">Map Art</h1>
        <p class="page-lede">Map art I've made on my Minecraft server. <?= count($images) ?> maps.</p>

        <div class="maps">
            <?php foreach ($images as $image): ?>
                <img src="/media/minecraft/mapart/<?= h(rawurlencode($image)) ?>" width="128" height="128" alt="Map art <?= h(pathinfo($image, PATHINFO_FILENAME)) ?>" loading="lazy" decoding="async">
            <?php endforeach; ?>
        </div>
    </main>
    <?php site_footer(); ?>
</body>

</html>
