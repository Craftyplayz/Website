<?php
$site_page = [
    'components' => true,
    'ui' => true,
    'css' => ['/assets/css/pages/games-pubgames-around-the-clock.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <script src="<?= h(asset('/games/pub-games/around-the-clock/clock.js')) ?>" defer></script>
</head>

<body>
    <?php site_header(); ?>

    <main class="page atc" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Around the Clock</h1>
        <p class="page-lede">Hit 1 to 20 in order, then the bull. Track every dart for one player or up to eight.</p>

        <!-- Setup -->
        <form class="card form form-card atc-form" data-view="setup" novalidate>
            <h2>New game</h2>
            <div class="atc-names"></div>
            <div><button class="btn small" type="button" data-act="add">Add player</button></div>
            <fieldset>
                <legend>Rules</legend>
                <label>Which hits count
                    <select class="field" name="hitMode">
                        <option value="any">Any single, double or treble</option>
                        <option value="doubles">Doubles only</option>
                        <option value="trebles">Trebles only</option>
                    </select>
                </label>
                <label class="check"><input type="checkbox" name="skip"> Skip ahead: a double jumps 2 numbers, a treble 3</label>
                <label>Finish on
                    <select class="field" name="finish">
                        <option value="either">Either bull</option>
                        <option value="outer">Outer bull (25) only</option>
                        <option value="bull">Bullseye (50) only</option>
                    </select>
                </label>
                <div class="cols">
                    <label>Order
                        <select class="field" name="order">
                            <option value="up">1 to 20, then bull</option>
                            <option value="down">20 to 1, then bull</option>
                            <option value="bullfirst">Bull, then 1 to 20</option>
                            <option value="random">Shuffled</option>
                        </select>
                    </label>
                    <label>Hits needed per number
                        <select class="field" name="req">
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                        </select>
                    </label>
                </div>
                <label class="check"><input type="checkbox" name="equal" checked> Equal turns: later players in the round still throw after someone finishes</label>
            </fieldset>
            <div class="actions"><button class="btn primary" type="submit">Start game</button></div>
        </form>

        <!-- Play -->
        <section class="atc-play" data-view="play" hidden>
            <section class="card atc-banner ok" hidden aria-live="polite">
                <h2 class="atc-win-title"></h2>
                <p class="atc-win-sub"></p>
                <div class="actions">
                    <button class="btn primary" type="button" data-act="again">Play again</button>
                    <button class="btn" type="button" data-act="new">Change players or rules</button>
                </div>
            </section>

            <section class="card atc-turn">
                <h2><span class="atc-who"></span> <span class="n atc-dartno"></span></h2>
                <div class="atc-target" aria-live="polite" aria-atomic="true"></div>
                <p class="muted atc-sub"></p>
                <div class="atc-btns">
                    <button class="btn atc-btn" type="button" data-m="1"></button>
                    <button class="btn atc-btn" type="button" data-m="2"></button>
                    <button class="btn atc-btn" type="button" data-m="3"></button>
                </div>
                <button class="btn atc-btn atc-miss" type="button" data-m="0">Miss</button>
                <p class="atc-msg" aria-live="polite" hidden></p>
                <p class="muted atc-thisturn"></p>
                <details class="atc-other">
                    <summary>Dart landed on another number</summary>
                    <p class="muted">Counts as a thrown dart. Numbers out of order do not advance you.</p>
                    <div class="atc-seg"></div>
                </details>
                <div class="actions">
                    <button class="btn" type="button" data-act="undo" disabled>Undo last dart</button>
                    <button class="btn" type="button" data-act="new">New game</button>
                </div>
            </section>

            <section class="card">
                <h2>Players <span class="n atc-players-n"></span></h2>
                <div class="atc-players"></div>
            </section>
        </section>

        <!-- Rules -->
        <details class="card atc-rules">
            <summary>Rules</summary>
            <ul class="atc-rules-now-wrap">
                <li class="atc-hide-setup">This game:
                    <ul class="atc-rules-now"></ul>
                </li>
            </ul>
            <ol>
                <li>Throw in turn, three darts each. The order is set by the closest dart to the bull.</li>
                <li>Everyone starts on 1 and works up to 20, then finishes on the bull.</li>
                <li>Any part of the target number counts: single, double or treble. Other numbers do nothing, even the next one.</li>
                <li>Each hit moves you on, and your next dart in the same turn goes for the next target. Three hits in a turn is the best you can do.</li>
                <li>Finish with the first dart of a turn and you stop there. The darts used are what count.</li>
                <li>When someone finishes, players still to throw in that round get their turn. Fewest darts wins; if level it is a tie and you shoot off at the bull.</li>
                <li>A dart that bounces out still counts as thrown. A dart that misses the board is a miss.</li>
            </ol>
            <h3>Variants you can switch on</h3>
            <ul>
                <li>Skip ahead: a double moves you on 2 numbers, a treble 3. A jump stops at the last target, so you always have to hit the bull.</li>
                <li>Doubles only or trebles only: only that ring scores. There is no treble bull.</li>
                <li>Bull first, 20 down to 1, shuffled order, or 2 or 3 hits per number.</li>
                <li>Bull: either bull, only the outer bull (25), or only the bullseye (50).</li>
            </ul>
            <p class="muted">Sources and the full list of house rules are in the project notes (docs/around-the-clock.md).</p>
        </details>

        <dialog class="dlg" id="atc-new" aria-labelledby="atc-new-t">
            <h2 id="atc-new-t">Start a new game?</h2>
            <p>The current game will be cleared.</p>
            <div class="actions">
                <button class="btn primary" type="button" data-act="confirm-new">New game</button>
                <button class="btn" type="button" data-act="close">Keep playing</button>
            </div>
        </dialog>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
