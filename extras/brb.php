<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crafty</title>
    <link rel="stylesheet" href="/css/homepage.css">
    <meta name="description" content="Crafty's Website">
    <?php if (isset($_GET['url'])) {$json = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/shorten.json');$data = json_decode($json, true);foreach ($data as $key => $value) {if ($key == $_GET['url']) {echo '<meta http-equiv="refresh" content="0; url=' . $value . '">';}}}?>
    <style>
        body {
            overflow: hidden;
        }
    </style>
</head>

<body id="home">

    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>

    <div class="about">
        <h1>Be Right Back</h1>
        <h2>The stream isn't ending, but I'm gone for now</h2>
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

        $images = scandir($imageFolder);
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
    <script src="/konami.js"></script>

    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>
</body>

</html>