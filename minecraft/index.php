<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crafty Playz</title>
    <link rel="stylesheet" href="/css/minecraft.css">
</head>

<body>

    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>

    <div class="about">
        <h1>I play alot of Minecraft</h1>
        <a style="color: white;" href="#screenshots"><h2>View some screenshots</h2></a>
        <a style="color: white;" href="skins"><h2>View my Skins</h2></a>
    </div>

    <div class="slideshow">
        <?php

        function isMobile()
        {
            return preg_match('/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $_SERVER['HTTP_USER_AGENT']);
        }

        if (isMobile()) {
            $imageFolder = '/images/SlideShows/homepagemobile/';
            echo '<script>console.log("Mobile");</script>';
        } else {
            $imageFolder = '/images/SlideShows/homepage/';
            echo '<script>console.log("Desktop");</script>';
        }

        $images = scandir($_SERVER['DOCUMENT_ROOT'] . $imageFolder);
        $shuffledImages = $images;
        shuffle($shuffledImages);

        foreach ($shuffledImages as $image) {
            if ($image !== '.' && $image !== '..') {
                echo '<img src="' . $imageFolder . $image . '" alt="Slideshow Image">' . PHP_EOL;
            }
        }
        ?>
    </div>

    <script>
        const images = document.querySelectorAll('.slideshow img');
        let currentIndex = 0;

        function changeImage() {
            images[currentIndex].style.opacity = 0;
            currentIndex = (currentIndex + 1) % images.length;
            images[currentIndex].style.opacity = 1;
            setTimeout(changeImage, 5000);
        }

        changeImage();
    </script>
    <div class="title" id="screenshots">
        <h1>Minecraft Screenshots</h1>
        <h2>Some screenshots from my years of playing minecraft</h2>
        <h2>Click any image to view fullscreen</h2>
    </div>

    <div class="screenshots">
        <?php
        $images = scandir($_SERVER['DOCUMENT_ROOT'] . '/images/minecraft/screenshots');
        foreach ($images as $image) {
            if ($image !== '.' && $image !== '..') {
                echo '<a href="/minecraft/screenshot?id=' . $image . '">' . PHP_EOL . '<div class="screenshot"><img src="/images/minecraft/screenshots/' . $image . '" alt="Screenshot" class="screenshot-image"></div></a>' . PHP_EOL;
            }
        }
        ?>
    </div>
    <script>

    </script>
    <script src="js/konami.js"></script>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>
</body>

</html>