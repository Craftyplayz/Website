<?php
$site_page = [
    'components' => true,
    'css' => ['/assets/css/pages/minecraft-downloads.css'],
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
        <h1 class="page-title">Stonecrest World Downloads</h1>
        <p class="page-lede">The Stonecrest server world, in two sizes.</p>

        <section class="card dl-card">
            <h2>Downloads</h2>
            <div class="rows">
                <a class="rowi" href="https://craftyplayz.co.uk/stonecrest/stonecrest-trimmed.zip"><span class="grow">Trimmed world</span><small>1.6 GB</small></a>
                <a class="rowi" href="https://craftyplayz.co.uk/stonecrest/stonecrest-untrimmed.zip"><span class="grow">Untrimmed world</span><small>12.2 GB</small></a>
            </div>
            <p class="muted">The trimmed version drops every chunk that was inhabited for less than 2 minutes (trimmed with MCA Selector).</p>
            <p class="muted">Any issues, contact me on Discord: <span class="mono">craftyplayz</span></p>
        </section>
    </main>
    <?php site_footer(); ?>
</body>

</html>
