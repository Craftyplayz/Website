<?php
$site_page = ['components' => true, 'css' => ['/assets/css/pages/extras-fake-updates.css']];
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
        <h1 class="page-title">Fake PC Updates and Crashes</h1>
        <p class="page-lede">Full-screen fake update and crash screens to prank someone with.</p>

        <div class="fu-grid">
            <a class="fu-card" href="/extras/fake-updates/windows10/">
                <img src="/media/fake-updates/win10update.jpeg" width="1280" height="720" alt="" decoding="async">
                <span>Windows 10 <small>Update and crash screens · 2</small></span>
            </a>
        </div>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
