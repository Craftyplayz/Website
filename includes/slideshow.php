<?php
// Full-screen slideshow used behind the hero card on the homepage, /minecraft/ and the BRB page.
// Every screenshot in media/minecraft/screenshots-raw/ is a slide. The raw PNGs are far too big
// to send, so _dev/scripts/slideshow-sizes.js makes WebP copies of each in screenshots-sizes/
// (960 = low-quality placeholder, up to 3840) plus manifest.json. The page paints the 960 copy of
// the first slide and /assets/js/slideshow.js upgrades it and keeps the next slide loading ahead.
//
//   $slides = slideshow_slides();
//   in <head>:  slideshow_head($slides);
//   in <body>:  slideshow_stage($slides);   (inside a positioned .hero)

// Every slide in a random order: ['a' => width / height, 'src' => [[width, url], ...]], smallest first.
function slideshow_slides()
{
    $dir = '/media/minecraft/';
    $manifest = json_decode((string)@file_get_contents(SITE_ROOT . $dir . 'screenshots-sizes/manifest.json'), true);
    if (!is_array($manifest)) $manifest = [];
    $slides = [];
    foreach (scandir(SITE_ROOT . $dir . 'screenshots-raw') ?: [] as $file) {
        if (!preg_match('/\.(png|webp|jpe?g)$/i', $file)) continue;
        $name = preg_replace('/\.[^.]+$/', '', $file);
        if (!isset($manifest[$name])) continue; // not resized yet: run slideshow-sizes.js
        $info = $manifest[$name];
        $src = [];
        foreach ($info['sizes'] as $w) $src[] = [$w, $dir . 'screenshots-sizes/' . $w . '/' . rawurlencode($name) . '.webp'];
        $slides[] = ['a' => round($info['w'] / $info['h'], 3), 'src' => $src];
    }
    shuffle($slides);
    return $slides;
}

function slideshow_head($slides)
{
    echo '<link rel="stylesheet" href="' . h(asset('/assets/css/pages/hero.css')) . '">' . "\n";
    if ($slides) echo '<link rel="preload" as="image" href="' . h($slides[0]['src'][0][1]) . '" fetchpriority="high">' . "\n";
    echo '<script src="' . h(asset('/assets/js/slideshow.js')) . '" defer></script>' . "\n";
}

function slideshow_stage($slides)
{
    echo '<div class="hero-stage" id="hero-stage" aria-hidden="true">';
    if ($slides) echo '<img class="on" src="' . h($slides[0]['src'][0][1]) . '" alt="" fetchpriority="high">';
    echo '</div>';
    echo '<script type="application/json" id="hero-slides">' . json_encode($slides, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>';
}
