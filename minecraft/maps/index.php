<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minecraft Maps</title>
    <link rel="stylesheet" href="/css/minecraft.css">
</head>

<body>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>

    <div class="title">
        <h1>Minecraft Maps</h1>
    </div>

    <div class="title">
<?php
$maps = scandir(__DIR__);

foreach ($maps as $map) {
    if ($map === '.' || $map === '..' || !is_dir(__DIR__ . '/' . $map)) {
        continue;
    }
    $id = htmlspecialchars($map);
    echo '<a style="color: white;" href="/minecraft/maps/' . rawurlencode($map) . '/web/index.html"><h2>' . $id . '</h2></a>' . PHP_EOL;
}
?>
    </div>

    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>
</body>

</html>
