<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Me</title>
    <link rel="stylesheet" href="css/homepage.css">
    <!-- <meta http-equiv="refresh" content="3"> -->
</head>

<body>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>


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
            <p>20 Years old</p><br>
            <p>He/Him</p><br>
            <p>Born in NSW Australia and lived there for 16 years</p><br>
            <p>Now living in the UK since 2019</p><br>
        </div>
        <div class="item">
            <h1>Intrests</h1>
            <p>Programming - Mostly creating websites such as this one</p><br>
            <p><a href="/steam">Gaming</a> - My main games are Minecraft, Rocket League & Factorio<br>You can see my steam stats here -<a href="/steam/"> Steam Stats</a></p><br>
            <p>Books - Im currently reading the Heroes of Olympus series<br>I have read Harry Potter over 125 times now - <a href="/books">Books Ive read</a></p><br>
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

    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>

</body>

</html>