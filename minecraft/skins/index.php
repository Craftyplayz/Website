<?php
$site_page = ['legacy' => true];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <link rel="stylesheet" href="/assets/css/pages/gallery.css">
</head>

<body>
    <?php site_header(); ?>
    <main class="page page-legacy" id="main">

    <div class="title">
        <h1>My Minecraft Skins</h1>
        <h2>Made by <a title="Except the original rainbow skin" href="https://www.twitch.tv/spooky_clang">Spooky Clang</a></h2>
    </div>

    <div class="gallery">
<?php
$dir = $_SERVER['DOCUMENT_ROOT'] . '/media/minecraft/skins/';
$images = glob($dir . '*.png');
foreach ($images as $image) {
    $name = basename($image);
    $borderStyle = ($name === "purple.png") ? ' style="border: 3px solid purple;"' : '';
    echo '<div class="gallery-item"' . $borderStyle . '>';
    echo '<a href="/media/minecraft/skins/' . $name . '" download><img src="/media/minecraft/skins/' . $name . '"></a>';
    echo '</div>';
}
?>
    </div>

    </main>
    <?php site_footer(); ?>

</body>

</html>