<?php
$site_page = [
    'title' => 'Minecraft Downloads',
    'description' => 'Data packs and other files to download',
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

$files = array_values(array_filter(scandir(__DIR__) ?: [], function ($f) {
    return $f[0] !== '.' && $f !== 'index.php' && is_file(__DIR__ . '/' . $f);
}));
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
        <h1 class="page-title"><?= h($site_page['title']) ?></h1>
        <p class="page-lede"><?= h($site_page['description']) ?></p>
        <div class="hub-grid">
            <?php foreach ($files as $f): ?>
                <a class="hub-card" href="/minecraft/downloads/<?= h(rawurlencode($f)) ?>" download>
                    <b><?= h(pathinfo($f, PATHINFO_FILENAME)) ?></b>
                    <span><?= h(strtoupper(pathinfo($f, PATHINFO_EXTENSION))) ?> · <?= h(number_format(filesize(__DIR__ . '/' . $f) / 1024, 0)) ?> KB</span>
                </a>
            <?php endforeach; ?>
        </div>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
