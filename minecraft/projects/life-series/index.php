<?php
$site_page = [
    'components' => true,
    'css' => ['/assets/css/pages/minecraft-life-series.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

// every season-N/session-N folder becomes a link, so new sessions show up on their own
$label = fn($name) => ucwords(str_replace('-', ' ', $name));
$seasons = [];
foreach (scandir(__DIR__) ?: [] as $season) {
    if ($season[0] === '.' || !is_dir(__DIR__ . '/' . $season)) continue;
    $seasons[$season] = array_values(array_filter(scandir(__DIR__ . '/' . $season) ?: [], fn($f) => $f[0] !== '.'));
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
        <h1 class="page-title">Life Series</h1>
        <p class="page-lede">Session transcripts from our Life Series server.</p>

        <div class="grid">
            <?php foreach ($seasons as $season => $sessions): ?>
                <section class="card">
                    <h2><?= h($label($season)) ?> <span class="n"><?= count($sessions) ?> sessions</span></h2>
                    <div class="rows">
                        <?php foreach ($sessions as $session): ?>
                            <a class="rowi" href="<?= h(rawurlencode($season) . '/' . rawurlencode($session) . '/') ?>"><?= h($label($session)) ?></a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    </main>
    <?php site_footer(); ?>
</body>

</html>
