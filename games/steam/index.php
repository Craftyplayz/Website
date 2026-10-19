<?php
$site_page = ['config' => true, 'components' => true, 'css' => ['/assets/css/pages/games-steam.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
require __DIR__ . '/steam.php';

$profile = steam_profile();
$games = steam_games();
$recent = steam_recent();
$progress = steam_progress(steam_achievements($games));
$items = steam_items(array_merge(array_slice(array_keys($games), 0, 6), array_column($recent, 'appid')));
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
$state = $profile ? (int)$profile['personastate'] : 0;
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
        <h1 class="page-title">Steam Stats</h1>
        <p class="page-lede">My Steam profile, playtime and achievements, live from Steam.</p>
        <?php steam_nav('/games/steam/'); ?>

        <?php if (!$games): ?>
            <p class="err">Couldn't load the game list from Steam. Try again in a minute.</p>
        <?php endif; ?>

        <?php if ($profile): ?>
            <section class="card section steam-profile">
                <img class="steam-avatar" src="<?= h($profile['avatarfull']) ?>" alt="" width="96" height="96">
                <div class="grow">
                    <h2><a class="steam-name" href="/games/steam/profile/" title="Full profile"><?= h($profile['personaname']) ?></a>
                        <?php if ($badges): ?><span class="steam-level" title="Steam level"><?= (int)$badges['player_level'] ?></span><?php endif; ?>
                    </h2>
                    <p class="steam-state<?= !empty($profile['gameextrainfo']) ? ' ingame' : ($state ? ' online' : '') ?>">
                        <?php if (!empty($profile['gameextrainfo'])): ?>
                            Playing <a href="/games/steam/game/?game=<?= (int)$profile['gameid'] ?>"><?= h($profile['gameextrainfo']) ?></a>
                        <?php else: ?>
                            <?= isset(STEAM_STATES[$state]) ? STEAM_STATES[$state] : 'Online' ?><?php if (!$state && !empty($profile['lastlogoff'])): ?> · last seen <?= mb_strtolower(steam_ago($profile['lastlogoff'])) ?><?php endif; ?>
                        <?php endif; ?>
                    </p>
                    <?php if ($badges): $span = $badges['player_xp_needed_to_level_up'] + $badges['player_xp'] - $badges['player_xp_needed_current_level']; ?>
                        <div class="progress" role="progressbar" aria-label="XP to next level" aria-valuenow="<?= $badges['player_xp'] - $badges['player_xp_needed_current_level'] ?>" aria-valuemin="0" aria-valuemax="<?= $span ?>"><i style="width: <?= $span ? round(($badges['player_xp'] - $badges['player_xp_needed_current_level']) / $span * 100) : 0 ?>%"></i></div>
                        <small class="muted"><?= number_format($badges['player_xp']) ?> XP · <?= number_format($badges['player_xp_needed_to_level_up']) ?> to level <?= $badges['player_level'] + 1 ?></small>
                    <?php endif; ?>
                </div>
                <div class="kv">
                    <?php if (!empty($profile['timecreated'])): ?>
                        <span>Member since</span><span><?= date('F Y', $profile['timecreated']) ?></span>
                    <?php endif; ?>
                    <?php if ($badges): ?>
                        <span>Badges</span><span><?= count($badges['badges']) ?></span>
                    <?php endif; ?>
                    <span>Profile</span><span><a target="_blank" rel="noopener" href="<?= h($profile['profileurl']) ?>">steamcommunity.com</a></span>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($games): ?>
            <div class="tiles section">
                <div class="tile"><b><?= number_format(count($games)) ?></b><span>Games owned</span></div>
                <div class="tile"><b><?= number_format($played) ?></b><span>Games played</span></div>
                <div class="tile"><b><?= number_format(round($minutes / 60)) ?></b><span>Hours played</span></div>
                <?php if ($progress): ?>
                    <div class="tile"><b><?= number_format($unlocked) ?></b><span>Achievements</span></div>
                    <div class="tile"><b><?= $perfect ?></b><span>Perfect games</span></div>
                    <div class="tile"><b><?= $avg ?>%</b><span>Average completion</span></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($platformTotal): ?>
            <section class="card section">
                <h2>Playtime by platform</h2>
                <div class="steam-split" role="img" aria-label="<?= h(implode(', ', array_map(function ($k, $v) use ($platformTotal) {
                    return $k . ' ' . round($v / $platformTotal * 100) . '%';
                }, array_keys($platforms), $platforms))) ?>">
                    <?php $i = 0; foreach ($platforms as $name => $min): if ($min): ?>
                        <i class="s<?= $i ?>" style="width: <?= $min / $platformTotal * 100 ?>%"></i>
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

        <?php if ($recent): ?>
            <section class="card section">
                <h2>Played in the past 2 weeks <span class="n"><?= steam_hours(array_sum(array_column($recent, 'playtime_2weeks'))) ?></span></h2>
                <div class="rows">
                    <?php foreach ($recent as $g): $id = (int)$g['appid']; $p = isset($progress[$id]) ? $progress[$id] : null; ?>
                        <a class="rowi steam-row" href="/games/steam/game/?game=<?= $id ?>">
                            <img src="<?= h(steam_art($id, $items, 'small_capsule')) ?>" alt="" width="231" height="87" decoding="async">
                            <div class="grow">
                                <b><?= h($g['name']) ?></b>
                                <small><?= steam_hours($g['playtime_2weeks']) ?> this fortnight · <?= steam_hours($g['playtime_forever']) ?> total</small>
                            </div>
                            <?php if ($p): ?><small><?= $p['done'] ?> / <?= $p['total'] ?></small><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($games): ?>
            <h2 class="steam-h">Most played <a class="steam-more" href="/games/steam/library/">Full library →</a></h2>
            <div class="steam-grid section">
                <?php $i = 0; foreach (array_slice($games, 0, 6, true) as $id => $g) steam_card($g, $items, isset($progress[$id]) ? $progress[$id] : null, $i++ > 2); ?>
            </div>
        <?php endif; ?>

        <?php if ($workshop): ?>
            <section class="card section">
                <h2>Workshop uploads <span class="n"><?= count($workshop) ?></span></h2>
                <div class="rows">
                    <?php foreach ($workshop as $f): ?>
                        <a class="rowi steam-row" target="_blank" rel="noopener" href="https://steamcommunity.com/sharedfiles/filedetails/?id=<?= h($f['publishedfileid']) ?>">
                            <?php if (!empty($f['preview_url'])): ?><img class="sq" src="<?= h($f['preview_url']) ?>" alt="" width="48" height="48" loading="lazy" decoding="async"><?php endif; ?>
                            <div class="grow">
                                <b><?= h($f['title']) ?></b>
                                <small><?= h(isset($f['app_name']) ? $f['app_name'] : '') ?> · <?= date('M Y', $f['time_created']) ?></small>
                            </div>
                            <small><?= number_format($f['subscriptions']) ?> subscribers</small>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <?php site_footer(null, false); ?>
    <script>
        // a game without Steam art keeps its empty art box instead of a broken-image icon
        document.addEventListener('error', function (e) {
            if (e.target.closest && e.target.closest('.steam-art, .steam-row')) e.target.style.visibility = 'hidden';
        }, true);
    </script>
</body>

</html>
