<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crafty Playz</title>
</head>
<body>
<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
?>

<div class="title">
    <h1>Minecraft Helpers</h1>
</div>
<div class="title">
<?php
$dir = __DIR__;              // current folder
$items = scandir($dir);

foreach ($items as $item) {
    if ($item === '.' || $item === '..') continue;   // skip system entries

    // optional: skip this file itself
    if ($item === basename(__FILE__)) continue;

    echo '<a href="' . htmlspecialchars($item) . '">' . htmlspecialchars($item) . '</a><br>';
}
?>

</div>



<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
?>

</body>
</html>