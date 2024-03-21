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
    <h1>Files for download</h1>
</div>
<div class="title">
<?php
    $directory = $_SERVER['DOCUMENT_ROOT'] . '/Code/downloads';
    $excludedFiles = ['.', '..', 'index.php', 'jelly.php'];

    $files = scandir($directory);
    foreach ($files as $file) {
        if (!in_array($file, $excludedFiles) && is_file($directory . $file)) {
            echo '<a href="/Code/downloads/' . $file . '">' . $file . '</a><br>';
        }
    }
?>
</div>



<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
?>

</body>
</html>