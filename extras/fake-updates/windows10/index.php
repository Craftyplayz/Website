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
        <nav class="crumbs" aria-label="Breadcrumb"><a href="/">Home</a> <span class="crumbs-sep">›</span> <a href="/extras/">Extras</a> <span class="crumbs-sep">›</span> <a href="/extras/fake-updates/">Fake Update Screens</a> <span class="crumbs-sep">›</span> <span aria-current="page">Windows 10</span></nav>
        <h1 class="page-title">Windows 10</h1>
        <p class="page-lede">Open one, then press F11 to go full screen.</p>

        <div class="fu-grid">
            <a class="fu-card" href="/extras/fake-updates/windows10/update.html">
                <img src="/media/fake-updates/win10update.jpeg" width="1280" height="720" alt="" decoding="async">
                <span>Update screen <small>"Working on updates" with a spinner</small></span>
            </a>
            <a class="fu-card" href="/extras/fake-updates/windows10/crash.html">
                <img src="/media/fake-updates/win10crash.jpeg" width="1280" height="720" alt="" decoding="async">
                <span>Crash screen <small>A blue screen of death</small></span>
            </a>
        </div>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
