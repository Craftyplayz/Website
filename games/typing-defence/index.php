<?php
$site_page = [
    'components' => true,
    'ui' => true,
    'css' => ['/assets/css/pages/games-typing-defence.css'],
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

    <main class="page td" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Typing Defence</h1>
        <p class="page-lede">Type the falling words before they land. Three lives, and the words get faster.</p>

        <div class="td-hud tiles" aria-live="off">
            <div class="tile"><b id="td-score">0</b><span>Score</span></div>
            <div class="tile"><b id="td-level">1</b><span>Level</span></div>
            <div class="tile"><b id="td-lives" aria-label="3 lives">3</b><span>Lives</span></div>
            <div class="tile"><b id="td-wpm">0</b><span>Words per minute</span></div>
        </div>

        <div class="td-field" id="td-field">
            <div class="td-words" id="td-words" aria-hidden="true"></div>
            <input class="td-input" id="td-input" type="text" autocomplete="off" autocapitalize="none" autocorrect="off" spellcheck="false" enterkeyhint="done" aria-label="Type the falling words here">
            <div class="td-screen" id="td-start">
                <button class="btn primary" id="td-go" type="button" disabled>Start game</button>
                <p class="muted">Type a word to destroy it. Backspace undoes, Esc pauses. Three lives.</p>
            </div>
            <div class="td-screen" id="td-pause" hidden>
                <p><b>Paused</b></p>
                <p class="muted">Press Esc or tap here to carry on.</p>
            </div>
            <div class="td-screen td-over" id="td-ended" hidden>
                <p><b>Game over</b></p>
            </div>
        </div>

        <section class="card td-result" id="td-result" hidden aria-label="Result">
            <h2>Game over <span class="n" id="td-best"></span></h2>
            <div class="tiles">
                <div class="tile"><b id="td-r-score">0</b><span>Score</span></div>
                <div class="tile"><b id="td-r-wpm">0</b><span>Words per minute</span></div>
                <div class="tile"><b id="td-r-level">1</b><span>Level reached</span></div>
            </div>
            <form class="form" id="td-form" autocomplete="off">
                <label>Name for the leaderboard <input class="field" id="td-name" maxlength="20" required></label>
                <p class="err" id="td-form-err" hidden></p>
                <p class="ok" id="td-form-ok" hidden></p>
                <div class="actions">
                    <button class="btn primary" id="td-again" type="button">Play again</button>
                    <button class="btn" id="td-save" type="submit">Save score</button>
                </div>
            </form>
        </section>

        <h2 class="sec-h">Leaderboard <span class="n">top 20</span></h2>
        <p class="note" id="td-board-status">Loading the leaderboard...</p>
        <div class="table-wrap" id="td-board-wrap" hidden>
            <table class="table">
                <thead><tr><th>Rank</th><th>Name</th><th class="num">Score</th><th class="num">WPM</th><th>Date</th></tr></thead>
                <tbody id="td-board"></tbody>
            </table>
        </div>
    </main>

    <?php site_footer(null, false); ?>
    <script src="<?= asset('/games/typing-defence/typing-defence.js') ?>" defer></script>
</body>

</html>
