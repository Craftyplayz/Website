<?php
$site_page = ['legacy' => true];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <meta name="robots" content="noindex, nofollow">
</head>

<body>
    <?php
    site_header(); echo '<main class="page page-legacy" id="main">';

    // folder => description
    $sections = [
        'bs' => 'Discord exports',
        'transcripts' => 'Transcripts and recordings',
        'booru' => 'Booru',
        'jellyfin' => 'Jellyfin API testing',
        'kindle' => 'KOReader control panel',
        'downloads' => 'Files for download',
        'scripts' => 'Small test scripts',
    ];
    ?>

    <div class="title">
        <h1>Private</h1>
        <h2>Password-protected pages</h2>
    </div>
    <div class="title">
        <?php foreach ($sections as $dir => $label): ?>
            <?php if (is_dir(__DIR__ . '/' . $dir)): ?>
                <p><a href="/private/<?= $dir ?>/"><?= htmlspecialchars($label) ?></a></p><br>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <?php echo '</main>'; site_footer(); ?>
</body>

</html>
