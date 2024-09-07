<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapart</title>
    <meta http-equiv="refresh" content="-1">
    <link rel="stylesheet" href="/css/minecraft.css">
</head>


<body>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>

    <div class="title" id="screenshots">
        <h1>Maparts</h1>
        <h2>Some mapart i have made in my minecraft server</h2>
    </div>

    <div class="screenshots">
        <?php
        $images = scandir($_SERVER['DOCUMENT_ROOT'] . '/images/minecraft/mapart');
        foreach ($images as $image) {
            if ($image !== '.' && $image !== '..') {
                echo '<div class="screenshot"><img src="/images/minecraft/mapart/' . $image . '" alt="Screenshot" class="mapart-image"></div></a>' . PHP_EOL;
            }
        }
        ?>
    </div>



    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>
</body>

</html>