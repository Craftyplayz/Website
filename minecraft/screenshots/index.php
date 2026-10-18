<?php
// The screenshot gallery, or one screenshot at full size with ?id=<file>
$id = isset($_GET['id']) ? basename((string)$_GET['id']) : '';
$site_page = [
    'title' => $id !== '' ? 'Screenshot' : 'Screenshots',
    'description' => 'Screenshots from my years of playing Minecraft',
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
        <?php if ($id !== ''): ?>
            <?php
            // full-size versions are always .png
            $file = pathinfo($id, PATHINFO_FILENAME) . '.png';
            ?>
            <img src="/media/minecraft/screenshots-raw/<?= h(rawurlencode($file)) ?>" alt="Screenshot <?= h($file) ?>" class="screenshotfull">
            <p style="text-align: center; margin: 12px 0;"><a href="/minecraft/screenshots/"><button>Back to all screenshots</button></a></p>
        <?php else: ?>
            <div class="title">
                <h1>Minecraft Screenshots</h1>
                <h2>Some screenshots from my years of playing minecraft</h2>
                <h2>Click any image to view full quality</h2>
            </div>

            <div class="screenshots">
                <?php
                $images = array_reverse(array_values(array_diff(scandir($_SERVER['DOCUMENT_ROOT'] . '/media/minecraft/screenshots') ?: [], ['.', '..'])));
                foreach ($images as $image) {
                    echo '<a href="/minecraft/screenshots/?id=' . h(rawurlencode($image)) . '">' .
                        '<div class="screenshot"><img src="/media/minecraft/screenshots/' . h(rawurlencode($image)) . '" alt="Screenshot" class="screenshot-image" loading="lazy" decoding="async"></div></a>' . PHP_EOL;
                }
                ?>
            </div>
        <?php endif; ?>
    </main>
    <?php site_footer(); ?>
</body>

</html>
