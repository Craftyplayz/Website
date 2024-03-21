<!DOCTYPE php>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factorio Saves</title>
    <link rel="stylesheet" href="/css/main.css">
    <link rel="stylesheet" href="/css/factorio.css">
</head>

<body>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>

    <div class="title">
        <h1>My Factorio Saves</h1>
        <h3>Still need to render/upload a few maps</h3>
    </div>
    <div class="gallery">
    <?php
// Define an array of map names
$mapNames = ['beef', 'Krastorio2', 'rampant', 'Save1', 'Space', 'Space2']; // Add your map names here

// Loop through each map name and echo the corresponding div
foreach ($mapNames as $folderName) {
    $link = '/factorio/map?map=' . $folderName . '/';
    $thumbnailPath = 'https://craftyplayz.co.uk/' . $folderName . '/Images/thumbnail.png';
    echo '<div class="gallery-item" title="' . $folderName . '">';
    echo "\n";
    echo '<a href="' . $link . '"><img src="' . $thumbnailPath . '" class="gallery-image" /></a>';
    echo "\n";
    echo '</div>';
}
?>

    </div>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>
</body>

</html>