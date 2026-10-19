<?php
$site_page = ['components' => true, 'css' => ['/assets/css/pages/extras-random.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

$ch = curl_init('https://uselessfacts.jsph.pl/api/v2/facts/random');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$data = $response ? json_decode($response, true) : null;
$fact = isset($data['text']) ? $data['text'] : null;
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
        <h1 class="page-title">Random Fact</h1>
        <p class="page-lede">A new useless fact every time you load the page.</p>

        <div class="card xr-card">
            <?php if ($fact !== null): ?>
                <p class="xr-line"><?= h($fact) ?></p>
            <?php else: ?>
                <p class="err">Couldn't get a fact right now. Try again in a moment.</p>
            <?php endif; ?>
        </div>

        <p class="xr-actions"><a class="btn primary" href="/extras/facts/">Random Fact</a></p>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
