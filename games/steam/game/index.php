<?php
$site_page = ['config' => true, 'components' => true, 'css' => ['/assets/css/pages/games-steam.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
require dirname(__DIR__) . '/steam.php';
// appid goes into API URLs, so digits only
$game = preg_replace('/\D/', '', isset($_GET['game']) ? $_GET['game'] : '');
$schema = $game !== '' ? steam_json('http://api.steampowered.com/ISteamUserStats/GetSchemaForGame/v2/?key=' . $steamkey . '&appid=' . $game . '&format=json&l=en', 86400) : null;
$store = $game !== '' ? steam_json('https://store.steampowered.com/api/appdetails?appids=' . $game, 86400) : null;
$player = $game !== '' ? steam_json('http://api.steampowered.com/ISteamUserStats/GetPlayerAchievements/v0001/?appid=' . $game . '&key=' . $steamkey . '&steamid=' . $steamid . '&format=json&l=en') : null;

$info = isset($store[$game]['data']) ? $store[$game]['data'] : null;
$gametitle = $info ? $info['name'] : (isset($schema['game']['gameName']) ? $schema['game']['gameName'] : 'Steam Game');
$site_page['title'] = $gametitle . ' Stats';

// icons live in the schema, keyed by the achievement's API name
$icons = [];
foreach (isset($schema['game']['availableGameStats']['achievements']) ? $schema['game']['availableGameStats']['achievements'] : [] as $a) {
    $icons[$a['name']] = $a;
}
$achievements = isset($player['playerstats']['achievements']) ? $player['playerstats']['achievements'] : [];
$done = count(array_filter($achievements, function ($a) {
    return $a['achieved'] == 1;
}));
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
        <h1 class="page-title"><?= h($gametitle) ?></h1>
        <?php if ($info && !empty($info['short_description'])): ?>
            <p class="page-lede"><?= h(html_entity_decode($info['short_description'], ENT_QUOTES)) ?></p>
        <?php endif; ?>

        <?php if ($game === ''): ?>
            <p class="err">No game picked. Choose one from <a href="/games/steam/">Steam Stats</a>.</p>
        <?php elseif (!$info && !$achievements): ?>
            <p class="err">Couldn't load this game from Steam. Try again in a minute.</p>
        <?php endif; ?>

        <?php if ($info): ?>
            <section class="steam-head">
                <div class="steam-art"><img src="https://cdn.cloudflare.steamstatic.com/steam/apps/<?= $game ?>/header.jpg" alt="" width="460" height="215" decoding="async"></div>
                <div class="kv">
                    <?php if (!empty($info['release_date']['date'])): ?>
                        <span>Released</span><span><?= h($info['release_date']['date']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($info['price_overview']['final_formatted'])): ?>
                        <span>Price</span><span><?= h($info['price_overview']['final_formatted']) ?></span>
                    <?php elseif (!empty($info['is_free'])): ?>
                        <span>Price</span><span>Free</span>
                    <?php endif; ?>
                    <?php if (!empty($info['metacritic']['score'])): ?>
                        <span>Metacritic</span><span><a href="<?= h($info['metacritic']['url']) ?>"><?= (int)$info['metacritic']['score'] ?>%</a></span>
                    <?php endif; ?>
                    <span>Steam</span><span><a href="https://store.steampowered.com/app/<?= $game ?>">Store page</a></span>
                    <?php if (!empty($info['website'])): ?>
                        <span>Website</span><span><a href="<?= h($info['website']) ?>"><?= h(preg_replace('~^https?://(www\.)?|/$~', '', $info['website'])) ?></a></span>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($achievements): ?>
            <section class="card">
                <h2>Achievements <span class="n"><?= $done ?> / <?= count($achievements) ?></span></h2>
                <div class="progress section"><i style="width: <?= round($done / count($achievements) * 100) ?>%"></i></div>
                <div class="rows ach">
                    <?php foreach ($achievements as $a):
                        $got = $a['achieved'] == 1;
                        $meta = isset($icons[$a['apiname']]) ? $icons[$a['apiname']] : [];
                        $icon = $got ? (isset($meta['icon']) ? $meta['icon'] : '') : (isset($meta['icongray']) ? $meta['icongray'] : '');
                    ?>
                        <div class="rowi<?= $got ? '' : ' locked' ?>">
                            <?php if ($icon): ?><img src="<?= h($icon) ?>" alt="" width="40" height="40" loading="lazy" decoding="async"><?php endif; ?>
                            <div class="grow">
                                <b><?= h($a['name']) ?></b>
                                <small><?= h($a['description']) ?></small>
                            </div>
                            <?php if ($got): ?>
                                <small title="<?= date('j M Y, H:i', $a['unlocktime']) ?>"><?= steam_ago($a['unlocktime']) ?></small>
                            <?php else: ?>
                                <small>Locked</small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <?php site_footer(); ?>
</body>

</html>
