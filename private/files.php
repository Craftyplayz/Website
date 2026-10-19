<?php
// Shared by the private folder listings (bs/, downloads/, scripts/): a card listing every file
// in the calling page's folder, newest first, with size and date. index.php is always left out.
$site_page = ['components' => true];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

function private_size($bytes)
{
    foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
        if ($bytes < 1024 || $unit === 'GB') return ($unit === 'B' ? $bytes : number_format($bytes, 1)) . ' ' . $unit;
        $bytes /= 1024;
    }
}

function private_files($dir, $title, $lede, $skip = [])
{
    $files = [];
    foreach (scandir($dir) as $f) {
        if ($f[0] === '.' || $f === 'index.php' || in_array($f, $skip, true) || !is_file($dir . '/' . $f)) continue;
        $files[$f] = filemtime($dir . '/' . $f);
    }
    arsort($files);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <meta name="robots" content="noindex, nofollow">
</head>

<body>
    <?php site_header(); ?>

    <main class="page" id="main">
        <nav class="crumbs" aria-label="Breadcrumb"><a href="/">Home</a> <span class="crumbs-sep">›</span> <a href="/private/">Private</a> <span class="crumbs-sep">›</span> <span aria-current="page"><?= h($title) ?></span></nav>
        <h1 class="page-title"><?= h($title) ?></h1>
        <p class="page-lede"><?= h($lede) ?></p>

        <?php if (!$files): ?>
            <p class="note">This folder is empty.</p>
        <?php else: ?>
            <section class="card">
                <h2>Files <span class="n"><?= count($files) ?></span></h2>
                <div class="rows">
                    <?php foreach ($files as $f => $time): ?>
                        <div class="rowi">
                            <div class="grow"><a href="<?= h(rawurlencode($f)) ?>"><?= h($f) ?></a></div>
                            <small title="<?= date('j M Y, H:i', $time) ?>"><?= private_size(filesize($dir . '/' . $f)) ?> · <?= date('j M Y', $time) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
<?php
}
