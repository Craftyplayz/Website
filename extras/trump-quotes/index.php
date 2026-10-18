<?php
$site_page = ['components' => true, 'css' => ['/assets/css/pages/extras-random.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

$curl = curl_init('https://api.tronalddump.io/random/quote');
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_TIMEOUT, 5);
$response = curl_exec($curl);
$quote = $response ? json_decode($response, true) : null;
if (!is_array($quote) || empty($quote['value'])) $quote = null;
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
        <h1 class="page-title">Random Donald Trump Quote</h1>
        <p class="page-lede">A new quote every time you load the page.</p>

        <div class="card xr-card">
            <img class="xr-mug" src="/media/memes/trump-mugshot.webp" width="160" height="160" alt="Donald Trump's mugshot">
            <?php if ($quote): ?>
                <blockquote class="xr-line"><?= h($quote['value']) ?></blockquote>
                <?php if (!empty($quote['created_at'])): ?>
                    <p class="xr-meta muted"><?= h(date('jS F Y', strtotime($quote['created_at']))) ?></p>
                <?php endif; ?>
                <?php if (!empty($quote['tags'])): ?>
                    <div class="badges"><?php foreach ((array)$quote['tags'] as $tag): ?><span class="badge"><?= h($tag) ?></span><?php endforeach; ?></div>
                <?php endif; ?>
            <?php else: ?>
                <p class="err">Couldn't get a quote right now. Try again in a moment.</p>
            <?php endif; ?>
        </div>

        <p class="xr-actions"><a class="btn primary" href="/extras/trump-quotes/">Another quote</a></p>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
