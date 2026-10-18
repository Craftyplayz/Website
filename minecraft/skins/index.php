<?php
$site_page = [
    'components' => true,
    'css' => ['/assets/css/pages/minecraft-gallery.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
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
        <h1 class="page-title">My Minecraft Skins</h1>
        <p class="page-lede">Made by <a href="https://www.twitch.tv/spooky_clang">Spooky Clang</a>, except the original rainbow skin. Click one to download it.</p>

        <div class="skins">
            <?php foreach (glob(SITE_ROOT . '/media/minecraft/skins/*.png') ?: [] as $image):
                $file = basename($image);
                $name = ucfirst(pathinfo($file, PATHINFO_FILENAME));
                $size = @getimagesize($image) ?: [276, 368]; ?>
                <a class="skin<?= $file === 'purple.png' ? ' skin-hl' : '' ?>" href="/media/minecraft/skins/<?= h(rawurlencode($file)) ?>" download>
                    <img src="/media/minecraft/skins/<?= h(rawurlencode($file)) ?>" width="<?= (int)$size[0] ?>" height="<?= (int)$size[1] ?>" alt="<?= h($name) ?> skin" loading="lazy" decoding="async">
                    <span><?= h($name) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </main>
    <?php site_footer(); ?>
</body>

</html>
