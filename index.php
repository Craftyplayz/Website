<?php
// Old short links: /?url=melon -> the address in shorten.json (until /go/ replaces them)
if (isset($_GET['url']) && is_string($_GET['url'])) {
    $links = json_decode((string)@file_get_contents(__DIR__ . '/shorten.json'), true);
    if (is_array($links) && isset($links[$_GET['url']])) {
        header('Location: ' . $links[$_GET['url']], true, 302);
        exit;
    }
}

$site_page = ['description' => "Crafty's Website"];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
require INC . '/slideshow.php';
$slides = slideshow_slides();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <?php slideshow_head($slides); ?>
    <script src="/konami.js" defer></script>
</head>

<body>
    <?php site_header(); ?>

    <main class="hero" id="main">
        <?php slideshow_stage($slides); ?>
        <div class="hero-card">
            <h1>Hi, I'm Crafty!</h1>
            <p>Web Developer, Avid Gamer And Technical Minecraft Player</p>
        </div>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
