<?php
$site_page = [
    'components' => true,
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
// Playtime ranks on the server: [rank, total hours, perks new at this rank]; perks stack upwards
$ranks = [
    ['Default', 0, ['1 home', '/tpa, /spawn, /msg, /mail, /afk, /playtime', 'Free heads from /headdb', 'Veinminer', 'Use elevators']],
    ['Rank 2', 1, ['2 homes']],
    ['Rank 3', 4, ['3 homes']],
    ['Rank 4', 12, ['4 homes']],
    ['Rank 5', 24, ['5 homes', 'Craft and dye elevators']],
    ['Rank 6', 48, ['6 homes']],
    ['Rank 7', 72, ['7 homes']],
    ['Rank 8', 96, ['8 homes', '/workbench (portable crafting table)']],
    ['Rank 9', 120, ['9 homes']],
    ['Rank 10', 168, ['10 homes', '/hat']],
    ['Rank 11', 216, ['11 homes']],
    ['Rank 12', 264, ['12 homes']],
];
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
        <p class="page-lede">Ranks are earned automatically from your total playtime on the server. Hours never reset, and each rank keeps all the perks of the ranks below it.</p>

        <section class="card">
            <h2>Ranks and perks <span class="n"><?= count($ranks) ?> ranks</span></h2>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Rank</th><th class="num">Total hours</th><th>New perks</th></tr></thead>
                <tbody>
<?php foreach ($ranks as [$rank, $hours, $perks]): ?>
                    <tr><td><?= h($rank) ?></td><td class="num"><?= $hours ?> <?= $hours === 1 ? 'hour' : 'hours' ?></td><td><?= implode('<br>', array_map('h', $perks)) ?></td></tr>
<?php endforeach ?>
                </tbody>
            </table></div>
            <p class="muted">Type <span class="mono">/rank</span> in game to see your progress.</p>
        </section>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
