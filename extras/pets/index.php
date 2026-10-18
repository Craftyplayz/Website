<?php
$site_page = ['legacy' => true];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <link rel="stylesheet" href="/assets/css/pages/gallery.css">
    <style>.gallery-image{height: 300px; width: 300px;} </style>
</head>

<body>
    <?php site_header(); ?>
    <main class="page page-legacy" id="main">

    <div class="title">
        <h1>People's Pets</h1>
        <h2>Click on an image to view more</h2>
    </div>
    <div class="gallery">


        <?php
        $folderPath = '/media/pets/';
        /* list names of all folders in the folder */
        $folders = scandir($_SERVER['DOCUMENT_ROOT'] . $folderPath);
        foreach ($folders as $folder) {
            $excludedFolders = ['.', '..', '!videos'];
            if (in_array($folder, $excludedFolders)) {
                continue;
            }

            echo '<div class="gallery-item" title="' . $folder . '">';
            echo '<a href="/extras/pets/pet/?pet=' . rawurlencode($folder) . '"><img src="' . $folderPath . $folder . '/thumb.webp" class="gallery-image" /></a>';
            echo '</div>';
        }
        ?>
<!-- <div class="title">
    <h1>If you want your pet added send me the photos</h1>
</div> -->
    </div>
    </main>
    <?php site_footer(); ?>

</body>

</html>