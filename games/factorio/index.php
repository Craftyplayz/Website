<?php
$site_page = ['legacy' => true];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE php>
<html lang="en">

<head>
    <?php page_head(); ?>
    <link rel="stylesheet" href="/assets/css/pages/factorio.css">
</head>

<body>
    <?php site_header(); ?>
    <main class="page page-legacy" id="main">
    <div class="title">
        <h1>My Factorio Saves</h1>
        <h2>Currently playing a Megabase Factory (aim of 10k spm)</h2>
    </div>
    <div class="gallery">
        <?php

        $mapNames = [
        'beef',
/*         'Krastorio2', */
        'Krastorio2-Temp',
        'rampant',
        'Save1', 
        'Space',
        'Space2',
        'BisTall',
        'BisTallT'
    ];
        foreach ($mapNames as $folderName) {
            $link = '/games/factorio/map/?map=' . $folderName;
            $thumbnailPath = 'https://assets.craftyplayz.com/factorio/' . $folderName . '/Images/thumbnail.png';
            echo '<div class="gallery-item" title="' . $folderName . '">';
            echo "\n";
            echo '<a href="/games/factorio/map/?map=' . $folderName . '"><img src="' . $thumbnailPath . '" class="gallery-image" /></a>';
            echo "\n";
            echo '</div>';
        }
        ?>
    </div>
    </main>
    <?php site_footer(); ?>
</body>

</html>