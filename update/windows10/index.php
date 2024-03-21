<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Windows 10</title>
    <link rel="stylesheet" href="/css/gallery.css">
    <style>
        .gallery-image {
            height: 281x;
            width: 500px;
        }
    </style>
</head>

<body>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>


    <div class="title">
        <h1>Windows 10</h1>
    </div>

    <div class="gallery">
        <div class="gallery-item">
            <a href="/update/windows10/crash.html"><img class="gallery-image"
                    src="/images/update/win10crash.jpeg"></img></a>
        </div>
        <div class="gallery-item">
            <a href="/update/windows10/update.html"><img class="gallery-image"
                    src="/images/update/win10update.jpeg"></img></a>
        </div>
    </div>

    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>

</body>

</html>