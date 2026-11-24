<?php
$site_page = [
    'components' => true,
    'ui' => true,
    'css' => ['/assets/css/pages/minecraft-unlimitedsmp.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
require __DIR__ . '/stats.php';
[$stats_json, $stats_stale] = smp_stats();

$address = 'unlimited.craftyplayz.com';
$map_url = 'https://unlimitedmap.craftyplayz.com/';
$discord = 'https://discord.gg/xa7YSnvKzZ';
// Essentials rules.txt on the server
$rules = ['Be respectful', 'Be ethical', 'Use common sense'];
$features = [
    ['Castle Crafty shop', 'Buy blocks, enchanted books and special requests at the black tower.', '/shop/'],
    ['Casino', 'Minigames and gambling in the shop basement.', '/shop/#casino'],
    ['Bedrock players welcome', 'Join from phones, consoles and Windows on port 19132.', null],
    ['Vein miner', 'Mine a whole ore vein or tree at once.', null],
    ['Elevators', 'Jump or crouch on elevator blocks to change floors.', null],
    ['Brewing', 'Brew beer, wine and spirits with BreweryX.', null],
    ['Renamed items', 'Rename items in an anvil to turn them into cards, statues and more.', '/minecraft/models/'],
    ['Live map', 'See the whole world and who is where.', $map_url],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>

    <main class="page smp" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>

        <section class="page-hero">
            <div class="page-hero-body">
                <div class="grow">
                    <h1 class="page-title">UnlimitedSMP</h1>
                    <p class="page-lede">Join at <b class="mono"><?= h($address) ?></b> on Java or Bedrock. <span id="smp-updated"></span></p>
                </div>
                <div class="smp-join">
                    <button class="btn primary" id="smp-copy" type="button">Copy address</button>
                    <a class="btn" href="<?= h($discord) ?>" target="_blank" rel="noopener"><img src="/assets/img/icons/discord.svg" alt="" width="16" height="16"> Join the Discord</a>
                </div>
            </div>
        </section>

        <p class="err" id="smp-err" hidden></p>
        <div class="stat-row" id="smp-stats"></div>

        <div id="smp-live" hidden>
            <h2 class="sec-h" id="online">Online now <span class="n" id="smp-online-n"></span></h2>
            <div class="smp-players" id="smp-players"></div>

            <h2 class="sec-h" id="activity">Activity</h2>
            <div class="grid smp-charts">
                <section class="card">
                    <h2>Players online</h2>
                    <div class="stabs" id="smp-range" role="group" aria-label="Range"><button class="on" data-r="players24h" aria-pressed="true">24 hours</button><button data-r="players7d" aria-pressed="false">7 days</button></div>
                    <div id="smp-players-chart"></div>
                </section>
                <section class="card">
                    <h2>Server speed</h2>
                    <p class="muted">Ticks per second over 24 hours. 20 is perfect.</p>
                    <div id="smp-tps-chart"></div>
                </section>
                <section class="card smp-wide">
                    <h2>Busiest times</h2>
                    <p class="muted">Average players online over the last 30 days, in your time zone.</p>
                    <div id="smp-hours"></div>
                </section>
            </div>

            <h2 class="sec-h" id="leaderboards">Leaderboards <span class="n" id="smp-board-n"></span></h2>
            <div class="grid smp-boards" id="smp-boards"></div>

            <div id="smp-feed-wrap" hidden>
                <h2 class="sec-h" id="recent">Recent activity</h2>
                <section class="card"><div class="rows" id="smp-feed"></div></section>
            </div>
        </div>

        <h2 class="sec-h" id="map">Live map <a class="sec-more" href="<?= h($map_url) ?>" target="_blank" rel="noopener">Open full screen</a></h2>
        <div class="smp-map">
            <iframe src="<?= h($map_url) ?>" title="UnlimitedSMP live map" loading="lazy"></iframe>
        </div>

        <h2 class="sec-h" id="about">About the server</h2>
        <div class="grid">
            <section class="card">
                <h2>How to join</h2>
                <div class="kv">
                    <span>Java address</span><span class="mono"><?= h($address) ?></span>
                    <span>Bedrock address</span><span class="mono"><?= h($address) ?></span>
                    <span>Bedrock port</span><span class="mono">19132</span>
                    <span>Version</span><span id="smp-version">Latest</span>
                    <span>Difficulty</span><span>Hard</span>
                </div>
            </section>
            <section class="card">
                <h2>Rules</h2>
                <ol class="smp-rules">
                    <?php foreach ($rules as $r): ?><li><?= h($r) ?></li><?php endforeach; ?>
                </ol>
                <p class="muted">Ask in the <a href="<?= h($discord) ?>" target="_blank" rel="noopener">Discord</a> if you're not sure.</p>
            </section>
        </div>

        <h2 class="sec-h">What's on the server</h2>
        <div class="smp-feats">
            <?php foreach ($features as [$name, $text, $url]): ?>
                <?php if ($url): ?>
                    <a class="smp-feat" href="<?= h($url) ?>"<?= str_starts_with($url, 'http') ? ' target="_blank" rel="noopener"' : '' ?>><b><?= h($name) ?></b><span><?= h($text) ?></span></a>
                <?php else: ?>
                    <div class="smp-feat"><b><?= h($name) ?></b><span><?= h($text) ?></span></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <p class="muted smp-src">Stats come from the server itself and refresh every minute. Chat is never shown here.</p>
    </main>

    <?php site_footer(null, false); ?>

    <script>window.SMP = { stats: <?= json_encode($stats_json ? json_decode($stats_json) : null, JSON_HEX_TAG | JSON_HEX_AMP) ?>, stale: <?= $stats_stale ? 'true' : 'false' ?>, address: <?= json_encode($address) ?> };</script>
    <script src="<?= asset('/minecraft/unlimitedsmp/smp.js') ?>" defer></script>
</body>

</html>
