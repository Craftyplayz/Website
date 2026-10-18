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
        <?php
    $folder = basename((string)($_GET['pet'] ?? ''));  // folder name only, no ../
    ?>

    <div class="title">
        <h1><?= h($folder) ?> Photos</h1>
    </div>



    <div class="gallery">
        <?php
        $images = scandir($_SERVER['DOCUMENT_ROOT'] . '/media/pets/' . $folder);
        foreach ($images as $image) {
            if (!in_array($image, ['.', '..', 'raw', 'Raw'])) {
                echo '<div class="gallery-item">';
                echo '<a href="/media/pets/' . $folder . '/raw/' . pathinfo($image, PATHINFO_FILENAME) . '"><img src="/media/pets/' . $folder . '/' . $image . '" class="gallery-image" /></a>';
                echo '</div>';
            }
        }
        ?>
    </div>

    </main>
    <?php site_footer(); ?>

</body>

</html>
