<?php
$site_page = [
    'title' => 'Sitemap',
    'description' => 'Every page on the site',
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
        <h1 class="page-title"><?= h($site_page['title']) ?></h1>
        <p class="page-lede"><?= h($site_page['description']) ?></p>
        <?php foreach (site_data()['sections'] as $section): ?>
            <h2 style="margin: 24px 0 8px;"><a href="<?= h($section['url']) ?>"><?= h($section['title']) ?></a></h2>
            <?php site_hub($section['id']); ?>
        <?php endforeach; ?>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
