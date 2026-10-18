<?php
$site_page = ['components' => true, 'css' => ['/assets/css/pages/extras-pets.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
// no pet picked (or one that does not exist): back to the gallery
$pet = basename((string)($_GET['pet'] ?? ''));  // folder name only, no ../
if ($pet === '' || !is_dir(SITE_ROOT . '/media/pets/' . $pet)) {
    header('Location: /extras/pets/', true, 302);
    exit;
}

$dir = SITE_ROOT . '/media/pets/' . $pet;
// full-size originals live in raw/ (Raw/ for one pet) with their own extension (.jpg, .dng, .mp4 ...)
$raw = [];
foreach (['raw', 'Raw'] as $sub) {
    foreach (is_dir("$dir/$sub") ? scandir("$dir/$sub") : [] as $f) {
        if ($f[0] !== '.') $raw[pathinfo($f, PATHINFO_FILENAME)] = "$sub/$f";
    }
}
$photos = [];
foreach (scandir($dir) as $f) {
    if (pathinfo($f, PATHINFO_EXTENSION) !== 'webp' || $f === 'thumb.webp') continue;
    $name = pathinfo($f, PATHINFO_FILENAME);
    $photos[$f] = isset($raw[$name]) ? $raw[$name] : $f;
}
$base = '/media/pets/' . rawurlencode($pet) . '/';
$title = ucwords(str_replace('-', ' ', $pet));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>

    <main class="page" id="main">
        <nav class="crumbs" aria-label="Breadcrumb"><a href="/">Home</a> <span class="crumbs-sep">›</span> <a href="/extras/">Extras</a> <span class="crumbs-sep">›</span> <a href="/extras/pets/">Pets</a> <span class="crumbs-sep">›</span> <span aria-current="page"><?= h($title) ?></span></nav>
        <h1 class="page-title"><?= h($title) ?> Photos</h1>
        <p class="page-lede"><?= count($photos) ?> photos. Click one to open the full-size original.</p>

        <div class="pet-photos">
            <?php foreach ($photos as $thumb => $full): ?>
                <a href="<?= h($base . implode('/', array_map('rawurlencode', explode('/', $full)))) ?>"><img src="<?= h($base . rawurlencode($thumb)) ?>" width="300" height="300" alt="<?= h($title) ?>" loading="lazy" decoding="async"></a>
            <?php endforeach; ?>
        </div>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
