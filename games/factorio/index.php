<?php
$site_page = ['components' => true, 'css' => ['/assets/css/pages/games-factorio.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

$mapNames = [
    'beef',
    // 'Krastorio2',
    'Krastorio2-Temp',
    'rampant',
    'Save1',
    'Space',
    'Space2',
    'BisTall',
    'BisTallT'
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <link rel="preconnect" href="https://assets.craftyplayz.com">
</head>

<body>
    <?php site_header(); ?>

    <main class="page" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Factorio Saves</h1>
        <p class="page-lede">Maps of my Factorio saves. Currently playing a Megabase Factory (aim of 10k spm).</p>

        <div class="fmaps">
            <?php foreach ($mapNames as $i => $name): ?>
                <a class="card fmap" href="/games/factorio/map/?map=<?= rawurlencode($name) ?>">
                    <img src="https://assets.craftyplayz.com/factorio/<?= rawurlencode($name) ?>/Images/thumbnail.png" alt="" width="300" height="300" <?= $i > 3 ? 'loading="lazy" ' : '' ?>decoding="async">
                    <span><?= h($name) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </main>

    <?php site_footer(); ?>
    <script>
        // a missing thumbnail leaves its empty tile instead of a broken-image icon
        document.addEventListener('error', function (e) {
            if (e.target.closest && e.target.closest('.fmap')) e.target.style.visibility = 'hidden';
        }, true);
    </script>
</body>

</html>
