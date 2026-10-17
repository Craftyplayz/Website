<?php
$site_page = [
    'title' => 'Minecraft',
    'description' => 'Minecraft tools, server projects, screenshots and skins',
    'legacy' => true,
    'css' => ['/assets/css/pages/minecraft.css'],
];
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

        <div class="about">
            <h1>I play alot of Minecraft</h1>
            <a style="color: white;" href="#explore"><h2>Tools and projects</h2></a>
            <a style="color: white;" href="/minecraft/screenshots/"><h2>View some screenshots</h2></a>
            <a style="color: white;" href="/minecraft/skins/"><h2>View my Skins</h2></a>
        </div>

        <div class="slideshow">
            <?php
            $isMobile = preg_match('/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $_SERVER['HTTP_USER_AGENT'] ?? '');
            $imageFolder = $isMobile ? '/media/slideshow/mobile/' : '/media/slideshow/desktop/';
            $images = array_values(array_diff(scandir($_SERVER['DOCUMENT_ROOT'] . $imageFolder) ?: [], ['.', '..']));
            shuffle($images);
            foreach ($images as $i => $image) {
                // only the first slide is needed straight away
                echo '<img src="' . h($imageFolder . $image) . '" alt=""' . ($i ? ' loading="lazy"' : '') . '>' . PHP_EOL;
            }
            ?>
        </div>

        <script>
            const images = document.querySelectorAll('.slideshow img');
            let currentIndex = 0;

            function changeImage() {
                images[currentIndex].style.opacity = 0;
                currentIndex = (currentIndex + 1) % images.length;
                images[currentIndex].style.opacity = 1;
                setTimeout(changeImage, 5000);
            }

            if (images.length) changeImage();
        </script>

        <div class="page" id="explore">
            <?php site_hub('minecraft'); ?>
        </div>

        <script src="/konami.js"></script>
    </main>
    <?php site_footer(); ?>
</body>

</html>
