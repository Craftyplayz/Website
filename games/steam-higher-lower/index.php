<?php
$site_page = [
    'config' => true,
    'components' => true,
    'ui' => true,
    'css' => ['/assets/css/pages/games-steam-higher-lower.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
require dirname(__DIR__) . '/steam/steam.php';

const SHL_MIN = 60; // minutes; below an hour there are too many near-zero ties
$games = array_filter(steam_games(), function ($g) {
    return $g['playtime_forever'] >= SHL_MIN;
});
$items = $games ? steam_items(array_keys($games)) : [];
$list = [];
foreach ($games as $id => $g) {
    $list[] = ['n' => $g['name'], 'id' => (int)$id, 'm' => (int)$g['playtime_forever'], 'a' => steam_art($id, $items), 'b' => steam_art($id, [])];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <link rel="preconnect" href="https://shared.akamai.steamstatic.com">
</head>

<body>
    <?php site_header(); ?>

    <main class="page shl" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Higher or Lower: Steam Hours</h1>
        <p class="page-lede">Does the game on the right have more or fewer hours than the one on the left? Only games I've played for an hour or more.</p>

        <?php if (count($list) < 2): ?>
            <p class="err">Couldn't load the game list from Steam. Try again in a minute.</p>
        <?php else: ?>
            <div class="shl-score" aria-live="polite">
                <span>Streak <b id="streak">0</b></span>
                <span>Best <b id="best">0</b></span>
            </div>
            <div class="shl-duel">
                <section class="shl-card" id="left" aria-label="Known game"></section>
                <div class="shl-mid" id="mid">
                    <div class="shl-guess" id="guess">
                        <button class="btn primary" type="button" data-g="h">Higher (H)</button>
                        <button class="btn" type="button" data-g="l">Lower (L)</button>
                    </div>
                    <div class="shl-over" id="over" hidden>
                        <p id="msg"></p>
                        <button class="btn primary" type="button" id="again">Play again (Enter)</button>
                    </div>
                </div>
                <section class="shl-card" id="right" aria-label="Guess this game"></section>
            </div>
            <script>window.SHL = <?= json_encode($list, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
            <script src="<?= h(asset('/games/steam-higher-lower/higher-lower.js')) ?>" defer></script>
        <?php endif; ?>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
