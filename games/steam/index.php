<?php
$site_page = ['config' => true, 'components' => true, 'css' => ['/assets/css/pages/games-steam.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
require __DIR__ . '/steam.php';

$profile = steam_profile();
$games = steam_games();
$recent = steam_recent();
$progress = steam_progress(steam_achievements($games));
$items = steam_items(array_merge(array_slice(array_keys($games), 0, 12), array_column($recent, 'appid')));
$badges = steam_api('IPlayerService/GetBadges/v1', ['steamid' => $steamid], 86400);
$badges = isset($badges['response']) ? $badges['response'] : null;
$workshop = steam_api('IPublishedFileService/GetUserFiles/v1', ['steamid' => $steamid, 'appid' => 0, 'numperpage' => 12], 86400);
$workshop = isset($workshop['response']['publishedfiledetails']) ? $workshop['response']['publishedfiledetails'] : [];

$minutes = array_sum(array_column($games, 'playtime_forever'));
$played = count(array_filter($games, function ($g) {
    return $g['playtime_forever'] > 0;
}));
$unlocked = array_sum(array_column($progress, 'done'));
$perfect = count(array_filter($progress, function ($p) {
    return $p['done'] == $p['total'];
}));
$started = array_filter($progress, function ($p) {
    return $p['done'] > 0;
});
$avg = $started ? round(array_sum(array_column($started, 'pct')) / count($started)) : 0;
// Deck time is part of Linux time, so it's a note under the bar rather than its own slice
$platforms = ['Windows' => 0, 'Linux / SteamOS' => 0, 'macOS' => 0];
$deck = 0;
foreach ($games as $g) {
    $platforms['Windows'] += isset($g['playtime_windows_forever']) ? $g['playtime_windows_forever'] : 0;
    $platforms['Linux / SteamOS'] += isset($g['playtime_linux_forever']) ? $g['playtime_linux_forever'] : 0;
    $platforms['macOS'] += isset($g['playtime_mac_forever']) ? $g['playtime_mac_forever'] : 0;
    $deck += isset($g['playtime_deck_forever']) ? $g['playtime_deck_forever'] : 0;
}
$platformTotal = array_sum($platforms);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <link rel="preconnect" href="https://shared.akamai.steamstatic.com">
</head>

<body>
    <?php site_header(); ?>

    <main class="page steam" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <?php steam_hero(['title' => 'Steam Stats', 'lede' => 'My Steam profile, playtime and achievements, live from Steam.', 'current' => '/games/steam/']); ?>

        <?php if (!$games): ?>
            <p class="err">Couldn't load the game list from Steam. Try again in a minute.</p>
        <?php endif; ?>

        <?php if ($games): ?>
            <div class="steam-stats-row">
                <a class="steam-stat" href="/games/steam/library/"><b><?= number_format(count($games)) ?></b><span>Games owned</span><small><?= number_format($played) ?> played</small></a>
                <a class="steam-stat" href="/games/steam/library/"><b><?= number_format(round($minutes / 60)) ?></b><span>Hours played</span><small><?= steam_hours(array_sum(array_column($recent, 'playtime_2weeks'))) ?> in 2 weeks</small></a>
                <?php if ($progress): ?>
                    <a class="steam-stat" href="/games/steam/achievements/"><b><?= number_format($unlocked) ?></b><span>Achievements</span><small><?= $perfect ?> perfect games</small></a>
                    <a class="steam-stat steam-stat-ring" href="/games/steam/achievements/"><?= steam_ring($avg, 'Average completion ' . $avg . '%') ?><span>Average completion</span></a>
                <?php endif; ?>
                <?php if ($badges): $into = $badges['player_xp'] - $badges['player_xp_needed_current_level']; $span = $into + $badges['player_xp_needed_to_level_up']; ?>
                    <a class="steam-stat" href="/games/steam/profile/"><b><span class="steam-level"><?= (int)$badges['player_level'] ?></span></b><span>Steam level</span>
                        <span class="progress" role="progressbar" aria-label="XP to next level" aria-valuenow="<?= $into ?>" aria-valuemin="0" aria-valuemax="<?= $span ?>"><i style="width: <?= $span ? round($into / $span * 100) : 0 ?>%"></i></span>
                        <small><?= number_format($badges['player_xp_needed_to_level_up']) ?> XP to <?= $badges['player_level'] + 1 ?></small></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($recent): ?>
            <h2 class="steam-h">Played recently <span class="n"><?= steam_hours(array_sum(array_column($recent, 'playtime_2weeks'))) ?> in the past 2 weeks</span></h2>
            <div class="steam-banners section">
                <?php foreach ($recent as $i => $g): $id = (int)$g['appid']; $p = isset($progress[$id]) ? $progress[$id] : null; ?>
                    <a class="steam-banner" href="/games/steam/game/?game=<?= $id ?>">
                        <img src="<?= h(steam_art($id, $items, 'library_hero')) ?>" data-alt="<?= h(steam_art($id, $items)) ?>" alt="" width="1920" height="620" <?= $i > 2 ? 'loading="lazy" ' : '' ?>decoding="async">
                        <span class="steam-banner-text">
                            <b><?= h($g['name']) ?></b>
                            <small><?= steam_hours($g['playtime_2weeks']) ?> this fortnight · <?= steam_hours($g['playtime_forever']) ?> total</small>
                        </span>
                        <?php if ($p): ?><?= steam_ring($p['pct'], $p['done'] . ' of ' . $p['total'] . ' achievements') ?><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($games): ?>
            <h2 class="steam-h">Most played <a class="steam-more" href="/games/steam/library/">Full library →</a></h2>
            <div class="steam-caps section">
                <?php $i = 0; foreach (array_slice($games, 0, 12, true) as $id => $g) steam_card($g, $items, isset($progress[$id]) ? $progress[$id] : null, $i++ > 5); ?>
            </div>
        <?php endif; ?>

        <?php if ($platformTotal): ?>
            <section class="card section">
                <h2>Playtime by platform</h2>
                <div class="steam-split" role="img" aria-label="<?= h(implode(', ', array_map(function ($k, $v) use ($platformTotal) {
                    return $k . ' ' . round($v / $platformTotal * 100) . '%';
                }, array_keys($platforms), $platforms))) ?>">
                    <?php $i = 0; foreach ($platforms as $name => $min): if ($min): ?>
                        <i class="s<?= $i ?>" style="width: <?= $min / $platformTotal * 100 ?>%" title="<?= $name ?>: <?= steam_hours($min) ?>"></i>
                    <?php endif; $i++; endforeach; ?>
                </div>
                <div class="steam-legend">
                    <?php $i = 0; foreach ($platforms as $name => $min): if ($min): ?>
                        <span><i class="s<?= $i ?>"></i><?= $name ?> <b><?= steam_hours($min) ?></b></span>
                    <?php endif; $i++; endforeach; ?>
                    <?php if ($deck): ?><span class="muted">Steam Deck: <?= steam_hours($deck) ?> of the Linux time</span><?php endif; ?>
                    <?php if ($minutes - $platformTotal >= 60): ?><span class="muted"><?= steam_hours($minutes - $platformTotal) ?> that Steam doesn't split by platform</span><?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($workshop): ?>
            <h2 class="steam-h">Workshop uploads <span class="n"><?= count($workshop) ?></span></h2>
            <div class="steam-shots section">
                <?php foreach ($workshop as $f): ?>
                    <a class="steam-shot" target="_blank" rel="noopener" href="https://steamcommunity.com/sharedfiles/filedetails/?id=<?= h($f['publishedfileid']) ?>">
                        <?php if (!empty($f['preview_url'])): ?><img src="<?= h($f['preview_url']) ?>" alt="" width="320" height="180" loading="lazy" decoding="async"><?php endif; ?>
                        <span class="steam-banner-text">
                            <b><?= h($f['title']) ?></b>
                            <small><?= h(isset($f['app_name']) ? $f['app_name'] : '') ?> · <?= number_format($f['subscriptions']) ?> subscribers</small>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <?php site_footer(null, false); ?>
    <script src="<?= h(asset('/games/steam/steam.js')) ?>" defer></script>
</body>

</html>
