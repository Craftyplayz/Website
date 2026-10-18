<?php
$site_page = [
    'title' => 'Minecraft',
    'description' => 'Minecraft tools, server projects, screenshots and skins',
];
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

    <main id="main">
        <section class="hero">
            <?php slideshow_stage($slides); ?>
            <div class="hero-card">
                <h1>I play a lot of Minecraft</h1>
                <p>Tools, server projects, screenshots and skins</p>
                <nav class="hero-links">
                    <a href="#explore">Tools and projects</a>
                    <a href="/minecraft/screenshots/">Screenshots</a>
                    <a href="/minecraft/skins/">Skins</a>
                </nav>
            </div>
        </section>

        <div class="page" id="explore">
            <?php site_hub('minecraft'); ?>
        </div>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
