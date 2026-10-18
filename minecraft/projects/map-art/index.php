<?php
$site_page = ['legacy' => true];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <link rel="stylesheet" href="/assets/css/pages/minecraft.css">
</head>


<body>
    <?php site_header(); ?>
    <main class="page page-legacy" id="main">

    <div class="title" id="screenshots">
        <h1>Maparts</h1>
        <h2>Some mapart i have made in my minecraft server</h2>
    </div>

    <div class="screenshots">
        <?php
        $images = scandir($_SERVER['DOCUMENT_ROOT'] . '/media/minecraft/mapart');
        foreach ($images as $image) {
            if ($image !== '.' && $image !== '..') {
                echo '<div class="screenshot"><img src="/media/minecraft/mapart/' . $image . '" alt="Screenshot" class="mapart-image"></div></a>' . PHP_EOL;
            }
        }
        ?>
    </div>



    </main>
    <?php site_footer(); ?>
</body>

</html>