<?php
$site_page = ['components' => true, 'css' => ['/assets/css/pages/extras-pets.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

$pets = [];
foreach (scandir(SITE_ROOT . '/media/pets/') as $folder) {
    if (in_array($folder, ['.', '..', '!videos'], true) || !is_dir(SITE_ROOT . '/media/pets/' . $folder)) continue;
    $photos = array_diff(glob(SITE_ROOT . '/media/pets/' . $folder . '/*.webp'), [SITE_ROOT . '/media/pets/' . $folder . '/thumb.webp']);
    $pets[$folder] = count($photos);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>

    <main class="page" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">People's Pets</h1>
        <p class="page-lede">Pick a pet to see all their photos.</p>

        <div class="pets">
            <?php foreach ($pets as $folder => $count): ?>
                <a class="pet-card" href="/extras/pets/pet/?pet=<?= rawurlencode($folder) ?>">
                    <img src="/media/pets/<?= rawurlencode($folder) ?>/thumb.webp" width="300" height="300" alt="" decoding="async">
                    <span><?= h(ucwords(str_replace('-', ' ', $folder))) ?> <small><?= $count ?> photos</small></span>
                </a>
            <?php endforeach; ?>
        </div>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
