<?php
$site_page = ['components' => true];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

$mods = [
    ['Vortex', 'https://www.nexusmods.com/site/mods/1?tab=files', 'The mod manager. Every other mod here is installed with it.'],
    ['Skyrim Script Extender', 'https://www.nexusmods.com/skyrimspecialedition/mods/30379', 'Lets more complex mods work.'],
    ['Skyrim Special Edition Patches', 'https://www.nexusmods.com/skyrimspecialedition/mods/266', 'Fixes bugs in the game.'],
    ['SkyUI', 'https://www.nexusmods.com/skyrimspecialedition/mods/12604', 'Makes the UI more PC friendly.'],
    ['SSE Engine Fixes', 'https://www.nexusmods.com/skyrimspecialedition/mods/17230', 'Fixes issues with SKSE.'],
];
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
        <h1 class="page-title">Skyrim Mods</h1>
        <p class="page-lede">Skyrim Special Edition mods I use or find useful.</p>

        <section class="card">
            <h2>Mods <span class="n"><?= count($mods) ?></span></h2>
            <div class="rows">
                <?php foreach ($mods as [$name, $url, $about]): ?>
                    <div class="rowi">
                        <div class="grow"><a href="<?= h($url) ?>"><?= h($name) ?></a><br><small><?= h($about) ?></small></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <?php site_footer(); ?>
</body>

</html>
