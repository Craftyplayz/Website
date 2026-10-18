<?php
$site_page = ['legacy' => true];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <link rel="stylesheet" href="/assets/css/pages/homepage.css">
    <!-- <meta http-equiv="refresh" content="3"> -->
</head>

<body>
    <?php site_header(); ?>
    <main class="page page-legacy" id="main">


    <div class="title">
        <h1>About Me</h1>
    </div>

    <div class="about-me">
        <div class="item">
            <h1>Hobbies</h1>
            <p>Programming - Mostly creating websites such as this one</p><br>
        </div>
        <div class="item">
            <h1>Background</h1>
            <p>22 Years old</p><br>
            <p>Born in NSW Australia and lived there for 16 years</p><br>
            <p>Now living in the UK since 2019</p><br>
        </div>
        <div class="item">
            <h1>Intrests</h1>
            <p><a href="/games/steam/">Gaming</a> - My main games are Minecraft, Rocket League & Factorio<br>You can see my steam stats here -<a href="/games/steam/"> Steam Stats</a></p><br>
            <p>Books - Im currently reading the Heroes of Olympus series<br>I have read Harry Potter over 135 times now - <a href="/books">Books Ive read</a></p><br>
        </div>
        <div class="item">
            <h1>Contact me</h1>
            <p>Please dont. I dont like people</p><br>
        </div>
    </div>

    <script>
        const items = document.querySelectorAll('.about-me .item');
        const isDesktop = window.innerWidth > 768;

        if (isDesktop) {
            let maxHeight = 0;
            items.forEach(item => {
                const height = item.offsetHeight;
                if (height > maxHeight) {
                    maxHeight = height;
                }
            });

            items.forEach(item => {
                item.style.height = `${maxHeight}px`;
            });
        }
    </script>

    </main>
    <?php site_footer(); ?>

</body>

</html>