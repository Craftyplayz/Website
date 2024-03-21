<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Random Number Generator</title>
    <link rel="stylesheet" href="/css/tools.css">
</head>
<body>
<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
?>


<div class="title">
    <h1>Random Number Generator</h1>
</div>

<div class="tool">
    <h3>Minimum Number</h3>
    <h4>Default 1</h4>
    <input type="number" id="number" placeholder="Minimum"><br><br>
    <h3>Maximum Number</h3>
    <input type="number" id="number2" placeholder="Maximum"><br><br>
    <button id="generate" onclick="generate()" >Generate</button>
</div>


<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
?>

</body>
</html>