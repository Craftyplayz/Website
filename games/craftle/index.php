<?php
$site_page = [
    'components' => true,
    'ui' => true,
    'css' => ['/assets/css/pages/games-craftle.css'],
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

    <main class="page craftle" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Craftle</h1>
        <p class="page-lede">Guess the item from its crafting recipe. Every wrong guess reveals more of the grid.</p>

        <section class="card cr-card" aria-label="Puzzle">
            <h2><span id="cr-mode">Daily puzzle</span> <span class="n" id="cr-left"></span></h2>
            <p class="note" id="cr-status">Loading recipes...</p>

            <div class="cr-craft" id="cr-craft" hidden>
                <div class="cr-grid" id="cr-grid" role="img" aria-label="Crafting grid"></div>
                <span class="cr-arrow" aria-hidden="true">&rarr;</span>
                <div class="cr-slot cr-result" id="cr-result" aria-label="Result"></div>
            </div>

            <form class="cr-form" id="cr-form" autocomplete="off" hidden>
                <div class="cr-combo">
                    <input class="field" id="cr-input" type="text" role="combobox" aria-expanded="false" aria-controls="cr-list" aria-autocomplete="list" aria-label="Item name" placeholder="Type an item name" enterkeyhint="go" spellcheck="false">
                    <ul class="cr-list" id="cr-list" role="listbox" hidden></ul>
                </div>
                <div class="actions">
                    <button class="btn primary" id="cr-go" type="submit">Guess</button>
                    <button class="btn" id="cr-practice" type="button">Play practice puzzle</button>
                </div>
            </form>

            <div class="cr-end" id="cr-end" hidden>
                <p class="cr-verdict" id="cr-verdict"></p>
                <div class="actions">
                    <button class="btn primary" id="cr-again" type="button">Play practice puzzle</button>
                    <button class="btn" id="cr-copy" type="button">Copy result</button>
                    <button class="btn" id="cr-daily" type="button" hidden>Back to daily puzzle</button>
                </div>
            </div>

            <ol class="cr-guesses" id="cr-guesses" aria-label="Your guesses"></ol>
        </section>

        <div class="stat-row cr-stats" id="cr-stats" hidden>
            <div class="stat-card"><b id="cr-s-streak">0</b><span>Current streak</span></div>
            <div class="stat-card"><b id="cr-s-best">0</b><span>Best streak</span></div>
            <div class="stat-card"><b id="cr-s-played">0</b><span>Daily puzzles played</span></div>
        </div>
        <p class="muted cr-foot" id="cr-foot"></p>
    </main>

    <?php site_footer(null, false); ?>
    <script src="<?= asset('/games/craftle/craftle.js') ?>" defer></script>
</body>

</html>
