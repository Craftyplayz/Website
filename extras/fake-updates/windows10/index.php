<?php
$site_page = ['legacy' => true];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <link rel="stylesheet" href="/assets/css/pages/gallery.css">
    <style>
        .gallery-image {
            height: 281x;
            width: 500px;
        }
    </style>
</head>

<body>
    <?php site_header(); ?>
    <main class="page page-legacy" id="main">


    <div class="title">
        <h1>Windows 10</h1>
    </div>

    <div class="gallery">
        <div class="gallery-item">
            <a href="/extras/fake-updates/windows10/crash.html"><img class="gallery-image"
                    src="/media/fake-updates/win10crash.jpeg"></img></a>
        </div>
        <div class="gallery-item">
            <a href="/extras/fake-updates/windows10/update.html"><img class="gallery-image"
                    src="/media/fake-updates/win10update.jpeg"></img></a>
        </div>
    </div>

    </main>
    <?php site_footer(); ?>

</body>

</html>