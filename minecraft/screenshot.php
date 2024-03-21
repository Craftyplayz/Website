<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php
        echo "screenshot";
        echo $_GET['id'];
        ?>
    </title>
    <link rel="stylesheet" href="/css/minecraft.css">
    <link rel="stylesheet" href="/css/global.css">
</head>

<body>
    <?php
    $imagePath = "/images/minecraft/screenshots/";
    $imageId = $_GET['id'];
    $imageAlt = "Screenshot " . $imageId;
    $imageClass = "screenshot-image";
    ?>

    <img src="<?php echo $imagePath . $imageId; ?>" alt="<?php echo $imageAlt; ?>" class="screenshotfull">
    <button onclick="window.history.back()" style="display: block; margin: 0 auto;">Back</button>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>
</body>

</html>