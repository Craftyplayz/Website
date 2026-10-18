<?php
$site_page = [
    'title' => 'Extras',
    'description' => 'Random bits and pieces',
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
        <?php site_hub('extras'); ?>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
