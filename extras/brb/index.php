<?php
$site_page = ['description' => 'Be right back screen'];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
require INC . '/slideshow.php';
$slides = slideshow_slides();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <?php slideshow_head($slides); ?>
    <script src="/assets/js/konami.js" defer></script>
</head>

<body>
    <?php site_header(); ?>

    <main class="hero" id="main">
        <?php slideshow_stage($slides); ?>
        <div class="hero-card">
            <h1>Be Right Back</h1>
            <p>The stream isn't ending, but I'm gone for now</p>
        </div>
    </main>
</body>

</html>
