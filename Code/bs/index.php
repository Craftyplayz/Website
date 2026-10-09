<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crafty Playz</title>
</head>
<body>
<!--#header--><!--#/header-->


<div class="title">
    <h1>Bs</h1>
</div>
<div class="title">
<?php
$dir = __DIR__;              // current folder
$items = scandir($dir);

foreach ($items as $item) {
    if ($item === '.' || $item === '..') continue;   // skip system entries

    // skip index.php file
    if ($item === 'index.php') continue;

    echo '<a href="' . htmlspecialchars($item) . '">' . htmlspecialchars($item) . '</a><br>';
}
?>

</div>



<!--#footer--><!--#/footer-->

</body>
</html>