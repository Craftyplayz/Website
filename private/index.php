<?php
$site_page = ['components' => true];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

// folder => description; folders missing on this server are left out
$sections = [
    'bs' => 'Discord exports',
    'transcripts' => 'Transcripts and recordings',
    'booru' => 'Booru',
    'kindle' => 'KOReader control panel',
    'downloads' => 'Files for download',
    'scripts' => 'Small test scripts',
    'gifs' => 'Meme GIF maker',
    'minecraft/farms' => 'Tick off farms built',
    'minecraft/collection' => 'Tick off collected items',
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <meta name="robots" content="noindex, nofollow">
</head>

<body>
    <?php site_header(); ?>

    <main class="page" id="main">
        <h1 class="page-title">Private</h1>
        <p class="page-lede">Password-protected pages.</p>

        <div class="hub-grid">
            <?php foreach ($sections as $dir => $label): if (!is_dir(__DIR__ . '/' . $dir)) continue; ?>
                <a class="hub-card" href="/private/<?= $dir ?>/"><b><?= h($label) ?></b><span>/private/<?= $dir ?>/</span></a>
            <?php endforeach; ?>
        </div>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
