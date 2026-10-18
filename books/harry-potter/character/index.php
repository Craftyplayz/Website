<?php
$site_page = ['legacy' => true];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <meta http-equiv="refresh" content="5">
</head>

<body>
    <?php site_header(); ?>
    <main class="page page-legacy" id="main">
    <button onclick="history.back()">Go Back</button>
    </main>
    <?php site_footer(); ?>
</body>

</html>



