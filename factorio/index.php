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
        <h2>Currently playing a Krastorio2/Space Exploration Playthrough</h2>
    </div>
    <div class="gallery">
        <?php

        $mapNames = [
        'beef',
        'Krastorio2',
        'rampant',
        'Save1', 
        'Space',
        'Space2',
        'BisTall',
        'BisTallT'
    ];
        foreach ($mapNames as $folderName) {
            $link = '/factorio/map?map=' . $folderName;
            $thumbnailPath = 'https://craftyplayz.co.uk/factorio/' . $folderName . '/Images/thumbnail.png';
            echo '<div class="gallery-item" title="' . $folderName . '">';
            echo "\n";
            echo '<a href="https://craftyplayz.com/factorio/map?map=' . $folderName . '"><img src="' . $thumbnailPath . '" class="gallery-image" /></a>';
            echo "\n";
            echo '</div>';
        }
        ?>
    </div>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>
    <script>
        const navSlide = () => {
            const burger = document.querySelector('.hamburger');
            const nav = document.querySelector('.nav-links');
            burger.addEventListener('click', () => {
                nav.classList.toggle('nav-active');
            });
        }
        navSlide();
    </script>


    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://kit.fontawesome.com/af6ee4244b.js" crossorigin="anonymous"></script>

    <script>
        $(document).ready(function () {
            $('#toggleHeader').click(function () {
                $('#header').toggle();
            });
        });
    </script>
</body>

</html>