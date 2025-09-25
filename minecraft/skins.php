<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minecraft Skins</title>
    <meta http-equiv="refresh" content="-1">
    <link rel="stylesheet" href="/css/gallery.css">
</head>

<body>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>

    <div class="title">
        <h1>My Minecraft Skins</h1>
        <h2>Made by <a title="Except the original rainbow skin" href="https://www.twitch.tv/spooky_clang">Spooky Clang</a></h2>
    </div>

    <div class="gallery">
<?php
$dir = $_SERVER['DOCUMENT_ROOT'] . '/images/minecraft/skins/';
$images = glob($dir . '*.png');
foreach ($images as $image) {
    $name = basename($image);
    $borderStyle = ($name === "purple.png") ? ' style="border: 3px solid purple;"' : '';
    echo '<div class="gallery-item"' . $borderStyle . '>';
    echo '<a href="/images/minecraft/skins/download/' . $name . '" target="_blank"><img src="/images/minecraft/skins/' . $name . '"></a>';
    echo '</div>';
}
?>
    </div>

    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php'; 
    ?>

</body>

</html>