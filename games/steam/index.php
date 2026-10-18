<?php
$site_page = ['config' => true, 'components' => true, 'css' => ['/assets/css/pages/games-steam.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
require __DIR__ . '/steam.php';

$owned = steam_json('http://api.steampowered.com/IPlayerService/GetOwnedGames/v0001/?key=' . $steamkey . '&steamid=' . $steamid . '&format=json&include_appinfo=1');
$recent = steam_json('http://api.steampowered.com/IPlayerService/GetRecentlyPlayedGames/v0001/?key=' . $steamkey . '&steamid=' . $steamid . '&format=json');
$games = isset($owned['response']['games']) ? $owned['response']['games'] : [];

// recently played games can be missing from the owned list (free weekends, family sharing)
$have = array_flip(array_column($games, 'appid'));
foreach (isset($recent['response']['games']) ? $recent['response']['games'] : [] as $g) {
    if (!isset($have[$g['appid']])) $games[] = $g;
}
usort($games, function ($a, $b) {
    return $b['playtime_forever'] <=> $a['playtime_forever'];
});

$counts = json_decode(@file_get_contents(__DIR__ . '/data/achieved/count.json'), true);
$counts = isset($counts['gameid']) ? $counts['gameid'] : [];
$skip = [730, 682130, 469820, 1085750, 459820, 1463120];
$shown = array_values(array_filter($games, function ($g) use ($skip) {
    return $g['playtime_forever'] >= 60 && !in_array($g['appid'], $skip);
}));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <link rel="preconnect" href="https://cdn.cloudflare.steamstatic.com">
</head>

<body>
    <?php site_header(); ?>

    <main class="page" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Steam Stats</h1>
        <p class="page-lede">My Steam library and achievements<?= $games ? ': ' . number_format(count($games)) . ' owned games, ' . count($shown) . ' played for an hour or more.' : '.' ?></p>

        <?php if (!$owned): ?>
            <p class="err">Couldn't load the game list from Steam. Try again in a minute.</p>
        <?php endif; ?>

        <div class="steam-grid">
            <?php foreach ($shown as $i => $g):
                $id = (int)$g['appid'];
                $link = '/games/steam/game/?game=' . $id;
                $c = isset($counts[$id]) ? $counts[$id] : null;
                $pct = $c && $c['totalAchievements'] ? round($c['achievedAchievements'] / $c['totalAchievements'] * 100) : 0;
                $last = isset($g['rtime_last_played']) ? (int)$g['rtime_last_played'] : 0;
            ?>
                <article class="card steam-game">
                    <a class="steam-art" href="<?= $link ?>" tabindex="-1" aria-hidden="true">
                        <img src="https://cdn.cloudflare.steamstatic.com/steam/apps/<?= $id ?>/header.jpg" alt="" width="460" height="215" <?= $i > 5 ? 'loading="lazy" ' : '' ?>decoding="async">
                    </a>
                    <h2><a href="<?= $link ?>"><?= h($g['name']) ?></a></h2>
                    <div class="kv">
                        <span>Playtime</span><span><?= number_format(round($g['playtime_forever'] / 60)) ?> hours</span>
                        <?php if (!empty($g['playtime_2weeks'])): ?>
                            <span>Past 2 weeks</span><span><?php $h2 = round($g['playtime_2weeks'] / 60); echo $h2 . ($h2 == 1 ? ' hour' : ' hours'); ?></span>
                        <?php endif; ?>
                        <?php if ($last): ?>
                            <span>Last played</span><span title="<?= date('j M Y, H:i', $last) ?>"><?= steam_ago($last) ?></span>
                        <?php endif; ?>
                        <?php if ($c && $c['totalAchievements']): ?>
                            <span>Achievements</span><span><?= $c['achievedAchievements'] ?> / <?= $c['totalAchievements'] ?> (<?= $pct ?>%)</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($c && $c['totalAchievements']): ?>
                        <div class="progress" role="progressbar" aria-label="Achievements" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><i style="width: <?= $pct ?>%"></i></div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </main>

    <?php site_footer(); ?>
    <script>
        // a game without Steam art keeps its empty art box instead of a broken-image icon
        document.addEventListener('error', function (e) {
            if (e.target.closest && e.target.closest('.steam-art')) e.target.remove();
        }, true);
    </script>
</body>

</html>
