<?php
$site_page = [
    'components' => true,
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
// Playtime ranks on the server: [rank, total hours]
$ranks = [['Default', 0], ['Rank 2', 1], ['Rank 3', 4], ['Rank 4', 12], ['Rank 5', 24], ['Rank 6', 48], ['Rank 7', 72], ['Rank 8', 96], ['Rank 9', 120], ['Rank 10', 168], ['Rank 11', 216], ['Rank 12', 264]];
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
        <h1 class="page-title">Ranks</h1>
        <p class="page-lede">Ranks are earned automatically from your total playtime on the server. Hours never reset.</p>

        <div class="grid">
            <section class="card">
                <h2>Playtime needed <span class="n"><?= count($ranks) ?> ranks</span></h2>
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>Rank</th><th class="num">Total hours</th></tr></thead>
                    <tbody>
<?php foreach ($ranks as [$rank, $hours]): ?>
                        <tr><td><?= h($rank) ?></td><td class="num"><?= $hours ?> <?= $hours === 1 ? 'hour' : 'hours' ?></td></tr>
<?php endforeach ?>
                    </tbody>
                </table></div>
                <p class="muted">Type <span class="mono">/rank</span> in game to see your progress.</p>
            </section>
        </div>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
