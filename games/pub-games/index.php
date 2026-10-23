<?php
$site_page = [
    'components' => true,
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
        <h1 class="page-title">Pub Games</h1>
        <p class="page-lede">Rules and scorekeepers for the games you play down the pub.</p>
        <?php site_hub('games', ['Pub Games'], false); ?>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
