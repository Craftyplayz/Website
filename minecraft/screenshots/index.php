<?php
// The screenshot gallery, or one screenshot at full size with ?id=<file>
$id = isset($_GET['id']) ? basename((string)$_GET['id']) : '';
$site_page = [
    'title' => $id !== '' ? 'Screenshot' : 'Screenshots',
    'description' => 'Screenshots from my years of playing Minecraft',
    'components' => true,
    'css' => ['/assets/css/pages/minecraft-gallery.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

$dir = '/media/minecraft/';
// "2020-11-22_21.19.19" -> "22 Nov 2020", or '' when the name isn't a Minecraft screenshot date
function shot_date($name)
{
    $d = DateTime::createFromFormat('!Y-m-d', substr($name, 0, 10));
    return $d ? $d->format('j M Y') : '';
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
        <?php if ($id !== ''): ?>
            <?php
            $name = pathinfo($id, PATHINFO_FILENAME);
            // full-size versions are always .png; screenshots-sizes/ holds WebP copies of them
            $raw = $dir . 'screenshots-raw/' . rawurlencode($name . '.png');
            $manifest = json_decode((string)@file_get_contents(SITE_ROOT . $dir . 'screenshots-sizes/manifest.json'), true);
            $info = is_array($manifest) && isset($manifest[$name]) ? $manifest[$name] : null;
            $date = shot_date($name);
            ?>
            <h1 class="page-title">Screenshot</h1>
            <p class="page-lede"><?= h($date !== '' ? $date : $name) ?></p>
            <figure class="shot-full">
                <?php if ($info): ?>
                    <?php
                    $srcset = [];
                    foreach ($info['sizes'] as $w) $srcset[] = $dir . 'screenshots-sizes/' . $w . '/' . rawurlencode($name) . '.webp ' . $w . 'w';
                    ?>
                    <img src="<?= h($dir . 'screenshots-sizes/' . $info['sizes'][0] . '/' . rawurlencode($name) . '.webp') ?>"
                        srcset="<?= h(implode(', ', $srcset)) ?>" sizes="(max-width: 1280px) 100vw, 1248px"
                        width="<?= (int)$info['w'] ?>" height="<?= (int)$info['h'] ?>" alt="Screenshot <?= h($date) ?>">
                <?php else: ?>
                    <img src="<?= h($raw) ?>" alt="Screenshot <?= h($date) ?>">
                <?php endif; ?>
            </figure>
            <p class="shot-actions">
                <a class="btn" href="/minecraft/screenshots/">&larr; All screenshots</a>
                <a class="btn" href="<?= h($raw) ?>">Open original PNG</a>
            </p>
        <?php else: ?>
            <?php $images = array_reverse(array_values(array_diff(scandir(SITE_ROOT . $dir . 'screenshots') ?: [], ['.', '..']))); ?>
            <h1 class="page-title">Minecraft Screenshots</h1>
            <p class="page-lede">Screenshots from my years of playing Minecraft. <?= count($images) ?> so far, newest first. Click one for full quality.</p>

            <div class="shots">
                <?php foreach ($images as $image):
                    $date = shot_date($image); ?>
                    <a class="shot" href="/minecraft/screenshots/?id=<?= h(rawurlencode($image)) ?>">
                        <img src="<?= h($dir . 'screenshots/' . rawurlencode($image)) ?>" width="600" height="338" alt="Screenshot <?= h($date) ?>" loading="lazy" decoding="async">
                        <?php if ($date !== ''): ?><span><?= h($date) ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    <?php site_footer(); ?>
</body>

</html>
