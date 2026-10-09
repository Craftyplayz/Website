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
    <!--#header--><!--#/header-->

    <div class="title">
        <h1>My Factorio Saves</h1>
        <h2>Currently playing a Megabase Factory (aim of 10k spm)</h2>
    </div>
    <div class="gallery">
        <?php

        $mapNames = [
        'beef',
/*         'Krastorio2', */
        'Krastorio2-Temp',
        'rampant',
        'Save1', 
        'Space',
        'Space2',
        'BisTall',
        'BisTallT'
    ];
        foreach ($mapNames as $folderName) {
            $link = '/factorio/map?map=' . $folderName;
            $thumbnailPath = 'https://assets.craftyplayz.com/factorio/' . $folderName . '/Images/thumbnail.png';
            echo '<div class="gallery-item" title="' . $folderName . '">';
            echo "\n";
            echo '<a href="https://craftyplayz.com/factorio/map?map=' . $folderName . '"><img src="' . $thumbnailPath . '" class="gallery-image" /></a>';
            echo "\n";
            echo '</div>';
        }
        ?>
    </div>
    <!--#footer--><!--#/footer-->
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