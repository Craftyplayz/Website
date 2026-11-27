<?php
$site_page = [
    'components'  => true,
    'ui'          => true,
    'css'         => ['/assets/css/pages/games-reaction-test.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>

    <main class="page reaction" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Reaction Test</h1>
        <p class="page-lede">Click as soon as the panel turns green. Your result is the average of five tries.</p>

        <section class="rt-wrap" aria-label="Reaction test">
            <button class="rt-panel" id="rt-panel" type="button" data-state="idle">
                <span class="rt-big" id="rt-big">Click or press Space to start</span>
                <span class="rt-small" id="rt-small">Five tries. Wait for the green, then react.</span>
            </button>

            <ol class="rt-tries" id="rt-tries" aria-label="Your tries"></ol>

            <div class="card rt-result" id="rt-result" hidden>
                <h2>Your result</h2>
                <div class="tiles">
                    <div class="tile"><b id="rt-avg">0</b><span>average (ms)</span></div>
                    <div class="tile"><b id="rt-best">0</b><span>best try (ms)</span></div>
                    <div class="tile"><b id="rt-verdict">-</b><span id="rt-verdict-note"></span></div>
                </div>
                <p class="muted" id="rt-pb"></p>
                <form class="form rt-form" id="rt-form" autocomplete="off">
                    <label>Name for the leaderboard
                        <input class="field" id="rt-name" maxlength="20" required enterkeyhint="send">
                    </label>
                    <div class="actions">
                        <button class="btn primary" id="rt-submit" type="submit">Add to leaderboard</button>
                    </div>
                </form>
                <p class="note" id="rt-msg" role="status" hidden></p>
            </div>
        </section>

        <h2 class="sec-h">Leaderboard <span class="n">top 20</span></h2>
        <p class="note" id="rt-board-status">Loading the leaderboard...</p>
        <div class="table-wrap" id="rt-board" hidden></div>
        <p class="muted rt-foot">Each name keeps its best average. Under 200 ms is fast, up to 300 ms is typical, and over 300 ms is slow. Results depend on your screen and input device.</p>
    </main>

    <?php site_footer(null, false); ?>
    <script src="<?= asset('/games/reaction-test/reaction-test.js') ?>" defer></script>
</body>

</html>
