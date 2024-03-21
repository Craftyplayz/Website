<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Untitled</title>
    <link rel="stylesheet" href="/css/save.css">
    <!-- <meta http-equiv="refresh" content="30"> -->
</head>

<body>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>


<?php
$title = $_GET['title'];
$dir = $_SERVER['DOCUMENT_ROOT'] . '/images/books/' . $title;
$files = scandir($dir);
/* echo number of files minus 2 (for . and ..) */
echo count($files) - 2 . ' Covers<br>';
foreach ($files as $file) {
    /* echo file name unless it is . or .. */
    if ($file == '.' || $file == '..') {
        continue;
    }
    echo '<a href=/images/books/' . $title . '/' . $file . '"><a href=/images/books/' . $title . '/' . $file . '"><img class="bookcover" src=/images/books/' . $title . '/' . $file . '></a></a><br>';
    echo '<br>';
}
?>


    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>

</body>

</html>