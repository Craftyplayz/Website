<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Private</title>
</head>

<body>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';

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

    <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php'; ?>
</body>

</html>
