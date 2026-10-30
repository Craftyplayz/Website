<?php
$site_page = ['components' => true];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

// Add a clip: drop clip-N.mp4 + a clip-N.webp poster (ffmpeg -ss 1 -frames:v 1 -vf scale=540:-2) here, then list it; w/h = video size
$clips = [
    ['file' => 'clip-1', 'title' => 'Clip 1', 'w' => 1080, 'h' => 1920],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <style>
        /* Funny Clips: grid of videos, portrait ones stay phone-sized */
        .clp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 280px), 1fr)); gap: 16px; align-items: start; }
        .clp-card { margin: 0; }
        .clp-card video { display: block; width: 100%; height: auto; max-height: 80vh; border-radius: var(--radius-lg); background: var(--sunken); }
        .clp-card figcaption { margin-top: 8px; font-size: 14px; font-weight: 600; }
    </style>
</head>

<body>
    <?php site_header(); ?>

    <main class="page" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Funny Clips</h1>
        <p class="page-lede">Short clips worth a laugh. Press play to watch.</p>

        <div class="clp-grid">
            <?php foreach ($clips as $c): ?>
                <figure class="clp-card">
                    <video controls playsinline preload="none" width="<?= $c['w'] ?>" height="<?= $c['h'] ?>" poster="<?= h($c['file']) ?>.webp" style="aspect-ratio: <?= $c['w'] ?> / <?= $c['h'] ?>">
                        <source src="<?= h($c['file']) ?>.mp4" type="video/mp4">
                    </video>
                    <figcaption><?= h($c['title']) ?></figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
