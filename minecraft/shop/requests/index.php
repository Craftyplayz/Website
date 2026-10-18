<?php
$site_page = ['components' => true, 'css' => ['/minecraft/shop/styles.css']];
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
        <h1 class="page-title">Captainalism Special Requests</h1>
        <p class="page-lede">Bulk orders, partnerships or new stock.</p>

        <section class="card shop-card">
            <p>For special requests, contact <b>Chewy</b> or <b>Crafty</b> on Discord with your request.</p>
            <p class="muted">For example bulk purchases, partnership offers, or asking for a new item to be stocked.</p>
        </section>
    </main>
    <?php site_footer(); ?>
</body>

</html>
