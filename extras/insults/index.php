<?php
$site_page = ['components' => true, 'css' => ['/assets/css/pages/extras-random.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

$response = @file_get_contents('https://evilinsult.com/generate_insult.php?lang=en&type=json', false, stream_context_create(['http' => ['timeout' => 5]]));
$data = $response ? json_decode($response, true) : null;
// the API sends HTML entities (&quot;), so decode before escaping
$insult = isset($data['insult']) ? html_entity_decode($data['insult'], ENT_QUOTES, 'UTF-8') : null;
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
        <h1 class="page-title">Random Insult Generator</h1>
        <p class="page-lede">A new insult every time you load the page.</p>

        <div class="card xr-card">
            <?php if ($insult !== null): ?>
                <p class="xr-line"><?= h($insult) ?></p>
            <?php else: ?>
                <p class="err">No insult found. Try again in a moment.</p>
            <?php endif; ?>
        </div>

        <p class="xr-actions"><a class="btn primary" href="/extras/insults/">Another insult</a></p>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
