<?php
$site_page = ['legacy' => true];
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
        <h1>Stonecrest World downloads</h1>
        <a href="https://craftyplayz.co.uk/stonecrest/stonecrest-trimmed.zip">Trimmed World - 1.6GB</a><br>
        <a href="https://craftyplayz.co.uk/stonecrest/stonecrest-untrimmed.zip">Untrimmed world - 12.2GB</a><br>
        <br>
        <br>
        <p>Any chunks that were inhabited for less then 2 minutes were deleted in the trimmed version.<br>Trimmed using MCA Selector<br>Any issues feel free to contact me on discord - craftyplayz</p>
    </div>



    </main>
    <?php site_footer(); ?>

</body>

</html>