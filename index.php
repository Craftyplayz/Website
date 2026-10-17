<?php
// Old short links: /?url=melon -> the address in shorten.json (until /go/ replaces them)
if (isset($_GET['url']) && is_string($_GET['url'])) {
    $links = json_decode((string)@file_get_contents(__DIR__ . '/shorten.json'), true);
    if (is_array($links) && isset($links[$_GET['url']])) {
        header('Location: ' . $links[$_GET['url']], true, 302);
        exit;
    }
}

$site_page = [
    'description' => "Crafty's Website",
    'css' => ['/assets/css/pages/home.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

// Slideshow: every image in media/slideshow/desktop/, in a random order. Each slide lists its
// resized copies (from _dev/scripts/slideshow-sizes.js) smallest first, ending with the original.
// The page shows the smallest copy first, and slideshow.js upgrades it and preloads the next one.
$slideDir = '/media/slideshow/';
$manifest = json_decode((string)@file_get_contents(SITE_ROOT . $slideDir . 'manifest.json'), true);
if (!is_array($manifest)) $manifest = [];
$slides = [];
foreach (scandir(SITE_ROOT . $slideDir . 'desktop') ?: [] as $file) {
    if (!preg_match('/\.(webp|jpe?g|png)$/i', $file)) continue;
    $info = isset($manifest[$file]) ? $manifest[$file] : null;
    $name = preg_replace('/\.[^.]+$/', '', $file) . '.webp';
    $src = [];
    foreach ($info ? $info['sizes'] : [] as $w) $src[] = [$w, $slideDir . 'sizes/' . $w . '/' . rawurlencode($name)];
    $src[] = [$info ? $info['w'] : 0, $slideDir . 'desktop/' . rawurlencode($file)];
    $slides[] = ['a' => $info ? round($info['w'] / $info['h'], 3) : 1.78, 'src' => $src];
}
shuffle($slides);
$first = $slides ? $slides[0] : null;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <?php if ($first): ?>
    <link rel="preload" as="image" href="<?= h($first['src'][0][1]) ?>" fetchpriority="high">
    <?php endif; ?>
    <script src="<?= h(asset('/assets/js/slideshow.js')) ?>" defer></script>
    <script src="/konami.js" defer></script>
</head>

<body>
    <?php site_header(); ?>

    <main class="home-hero" id="main">
        <div class="hero-stage" id="hero-stage" aria-hidden="true">
            <?php if ($first): ?>
            <img class="on" src="<?= h($first['src'][0][1]) ?>" alt="" fetchpriority="high">
            <?php endif; ?>
        </div>
        <div class="hero-card">
            <h1>Hi, I'm Crafty!</h1>
            <p>Web Developer, Avid Gamer And Technical Minecraft Player</p>
        </div>
        <script type="application/json" id="hero-slides"><?= json_encode($slides, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
