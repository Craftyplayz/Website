<?php
$site_page = ['config' => true, 'components' => true, 'css' => ['/assets/css/pages/games-steam.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
require dirname(__DIR__) . '/steam.php';

$games = steam_games();
$ach = steam_achievements($games);
$progress = steam_progress($ach);
$started = array_filter($progress, function ($p) {
    return $p['done'] > 0;
});
$rarity = steam_rarity(array_keys($started));

$unlocks = [];
foreach ($ach as $id => $list) {
    foreach ($list as $a) {
        if (!$a['achieved']) continue;
        $a['appid'] = $id;
        $a['pct'] = isset($rarity[$id][$a['apiname']]) ? $rarity[$id][$a['apiname']] : null;
        $unlocks[] = $a;
    }
}
usort($unlocks, function ($a, $b) {
    return $b['unlocktime'] <=> $a['unlocktime'];
});
$recent = array_slice($unlocks, 0, 30);
$rarest = array_filter($unlocks, function ($a) {
    return $a['pct'] !== null;
});
usort($rarest, function ($a, $b) {
    return $a['pct'] <=> $b['pct'];
});
$rarest = array_slice($rarest, 0, 10);
$schema = steam_schema(array_unique(array_merge(array_column($recent, 'appid'), array_column($rarest, 'appid'))));

$perfect = array_filter($progress, function ($p) {
    return $p['done'] == $p['total'];
});
uasort($perfect, function ($a, $b) {
    return $b['last'] <=> $a['last'];
});
$almost = array_filter($progress, function ($p) {
    return $p['pct'] >= 80 && $p['done'] < $p['total'];
});
uasort($almost, function ($a, $b) {
    return $b['pct'] <=> $a['pct'];
});
$items = steam_items(array_merge(array_keys($perfect), array_keys($almost)));

// unlocks per month for the last 24 months, oldest first
$months = [];
for ($i = 23; $i >= 0; $i--) $months[date('Y-m', strtotime(date('Y-m-01') . " -$i months"))] = 0;
foreach ($unlocks as $a) {
    $m = date('Y-m', $a['unlocktime']);
    if (isset($months[$m])) $months[$m]++;
}
$peak = max(1, max($months));
$year = count(array_filter($unlocks, function ($a) {
    return $a['unlocktime'] >= strtotime(date('Y-01-01'));
}));

function steam_ach_row($a, $games, $schema)
{
    $meta = isset($schema[$a['appid']][$a['apiname']]) ? $schema[$a['appid']][$a['apiname']] : null;
    $game = isset($games[$a['appid']]) ? $games[$a['appid']]['name'] : '';
    ?>
    <div class="rowi">
        <?php if ($meta && !empty($meta['icon'])): ?><img src="<?= h($meta['icon']) ?>" alt="" width="40" height="40" loading="lazy" decoding="async"><?php endif; ?>
        <div class="grow">
            <b><?= h($a['name']) ?></b>
            <small><a href="/games/steam/game/?game=<?= (int)$a['appid'] ?>"><?= h($game) ?></a></small>
        </div>
        <?= steam_rarity_badge($a['pct']) ?>
        <small><?= steam_time($a['unlocktime']) ?></small>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>

    <main class="page steam" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Steam Achievements</h1>
        <p class="page-lede">Achievements across all my Steam games, with how rare each one is.</p>
        <?php steam_nav('/games/steam/achievements/'); ?>

        <?php if (!$progress): ?>
            <p class="err">Couldn't load achievements from Steam. Try again in a minute.</p>
        <?php else: ?>
            <div class="tiles section">
                <div class="tile"><b><?= number_format(count($unlocks)) ?></b><span>Unlocked</span></div>
                <div class="tile"><b><?= number_format($year) ?></b><span>Unlocked in <?= date('Y') ?></span></div>
                <div class="tile"><b><?= count($perfect) ?></b><span>Perfect games</span></div>
                <div class="tile"><b><?= $started ? round(array_sum(array_column($started, 'pct')) / count($started)) : 0 ?>%</b><span>Average completion</span></div>
                <?php if ($rarest): ?>
                    <div class="tile"><b><?= round($rarest[0]['pct'], 1) ?>%</b><span>Rarest unlock</span></div>
                <?php endif; ?>
            </div>

            <section class="card section">
                <h2>Unlocks per month <span class="n">last 24 months</span></h2>
                <?php
                $W = 720; $H = 150; $top = 14; $base = 128; $slot = $W / 24;
                ?>
                <svg class="steam-chart" viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="Achievements unlocked per month, peak <?= $peak ?>">
                    <line x1="0" x2="<?= $W ?>" y1="<?= $top ?>" y2="<?= $top ?>" stroke-dasharray="2 4" />
                    <text x="<?= $W ?>" y="<?= $top - 4 ?>" text-anchor="end"><?= $peak ?></text>
                    <line x1="0" x2="<?= $W ?>" y1="<?= $base + .5 ?>" y2="<?= $base + .5 ?>" />
                    <?php $i = 0; foreach ($months as $m => $n):
                        $x = $i * $slot + 1; $w = $slot - 2; $hgt = $n ? max(2, ($base - $top) * $n / $peak) : 0; $r = min(4, $hgt, $w / 2);
                        $label = date('M Y', strtotime($m . '-01')); ?>
                        <g>
                            <title><?= $label ?>: <?= $n ?> unlocked</title>
                            <?php if ($hgt): ?>
                                <path d="M<?= $x ?> <?= $base ?>V<?= $base - $hgt + $r ?>q0 -<?= $r ?> <?= $r ?> -<?= $r ?>h<?= $w - 2 * $r ?>q<?= $r ?> 0 <?= $r ?> <?= $r ?>V<?= $base ?>z" />
                            <?php endif; ?>
                            <rect x="<?= $i * $slot ?>" y="0" width="<?= $slot ?>" height="<?= $base ?>" fill="transparent" />
                            <?php if ($i % 3 == 0): ?><text x="<?= $i * $slot + 1 ?>" y="<?= $H - 4 ?>"><?= date('M y', strtotime($m . '-01')) ?></text><?php endif; ?>
                        </g>
                    <?php $i++; endforeach; ?>
                </svg>
                <details class="steam-table">
                    <summary>Show as a table</summary>
                    <table>
                        <tr><th>Month</th><th>Unlocked</th></tr>
                        <?php foreach (array_reverse($months, true) as $m => $n): ?><tr><td><?= date('F Y', strtotime($m . '-01')) ?></td><td><?= $n ?></td></tr><?php endforeach; ?>
                    </table>
                </details>
            </section>

            <div class="steam-cols section">
                <section class="card">
                    <h2>Recently unlocked</h2>
                    <div class="rows ach"><?php foreach ($recent as $a) steam_ach_row($a, $games, $schema); ?></div>
                </section>
                <div>
                    <?php if ($rarest): ?>
                        <section class="card section">
                            <h2>Rarest unlocks</h2>
                            <div class="rows ach"><?php foreach ($rarest as $a) steam_ach_row($a, $games, $schema); ?></div>
                        </section>
                    <?php endif; ?>
                    <?php if ($almost): ?>
                        <section class="card section">
                            <h2>Almost there <span class="n">80% or more</span></h2>
                            <div class="rows">
                                <?php foreach ($almost as $id => $p): $left = $p['total'] - $p['done']; ?>
                                    <a class="rowi steam-row" href="/games/steam/game/?game=<?= $id ?>">
                                        <img src="<?= h(steam_art($id, $items, 'small_capsule')) ?>" alt="" width="231" height="87" loading="lazy" decoding="async">
                                        <div class="grow">
                                            <b><?= h($games[$id]['name']) ?></b>
                                            <div class="progress"><i style="width: <?= round($p['pct']) ?>%"></i></div>
                                        </div>
                                        <small><?= $left ?> left</small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($perfect): ?>
                <section class="card section">
                    <h2>Perfect games <span class="n"><?= count($perfect) ?></span></h2>
                    <div class="rows steam-perfect">
                        <?php foreach ($perfect as $id => $p): ?>
                            <a class="rowi steam-row" href="/games/steam/game/?game=<?= $id ?>">
                                <img src="<?= h(steam_art($id, $items, 'small_capsule')) ?>" alt="" width="231" height="87" loading="lazy" decoding="async">
                                <div class="grow">
                                    <b><?= h($games[$id]['name']) ?></b>
                                    <small><?= $p['total'] ?> achievements</small>
                                </div>
                                <small>Completed <?= date('M Y', $p['last']) ?></small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <?php site_footer(null, false); ?>
    <script>
        document.addEventListener('error', function (e) {
            if (e.target.closest && e.target.closest('.steam-row')) e.target.style.visibility = 'hidden';
        }, true);
    </script>
</body>

</html>
