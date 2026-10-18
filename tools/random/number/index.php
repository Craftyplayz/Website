<?php
$site_page = ['legacy' => true];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php page_head(); ?>
    <link rel="stylesheet" href="/assets/css/pages/tools.css">
</head>
<body>
<?php site_header(); ?>
    <main class="page page-legacy" id="main">


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


</main>
    <?php site_footer(); ?>

</body>
</html>