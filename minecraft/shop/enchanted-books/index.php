<?php
$site_page = ['legacy' => true, 'css' => ['/minecraft/shop/styles.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>
    <main class="page page-legacy" id="main">

    <div class="title">
        <h1>Captainalism Enchanted Books</h1>
        <h2>Price List</h2>
        <h3>Mending (2 Diamonds)</h3><br>
        <h3>Unbreaking 3 (2 Diamonds)</h3><br>
        <h3>Protection 4 (2 Diamonds)</h3><br>
        <h3>Fortune 3 (2 Diamonds)</h3><br>
        <h3>Efficiency 5 (2 Diamonds)</h3><br>
        <h3>Depth Strider 3 (1 Diamond)</h3><br>
        <h3>Silk Touch (1 Diamond)</h3><br>
        <h3>Aqua Affinity (1 Diamond)</h3><br>
        <h3>Feather Falling 4 (1 Diamond)</h3><br>
        <h3>Sharpness 5 (1 Diamond)</h3><br>
        <h3>Smite 5 (1 Diamond)</h3><br>
        <h3>Respiration 3 (1 Diamond)</h3><br>
        <h3>Looting 3 (1 Diamond)</h3><br>
        <h3>Punch 2 (1 Diamond)</h3><br>
        <h3>Thorns 3 (1 Diamond)</h3><br>
        <h3>Blast Protection 4 (1 Diamond)</h3><br>
        <h3>Veinminer (1 Diamond)</h3><br>
    </div>

    </main>
    <?php site_footer(); ?>
</body>
</html>