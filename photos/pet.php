<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Untitled</title>
    <meta http-equiv="refresh" content="-1">
    <link rel="stylesheet" href="/css/gallery.css">
    <style>.gallery-image{height: 300px; width: 300px;} </style>
</head>

<body background="/images/background.jpg">
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>
        <?php
    $folder = $_GET['pet'];
    ?>

    <div class="title">
        <h1><?php echo $folder?> Photos</h1>
    </div>



    <div class="gallery">
        <?php
        $images = scandir($_SERVER['DOCUMENT_ROOT'] . '/images/galleries/pets/' . $folder);
        foreach ($images as $image) {
            if (!in_array($image, ['.', '..', 'raw', 'Raw'])) {
                echo '<div class="gallery-item">';
                echo '<a href="/images/galleries/pets/' . $folder . '/raw/' . pathinfo($image, PATHINFO_FILENAME) . '"><img src="/images/galleries/pets/' . $folder . '/' . $image . '" class="gallery-image" /></a>';
                echo '</div>';
            }
        }
        ?>
    </div>

    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>

</body>

</html>
