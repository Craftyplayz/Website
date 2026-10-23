<?php
$site_page = [
    'components' => true,
    'css' => ['/assets/css/pages/games-pubgames-killer-darts.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <script src="<?= h(asset('/games/pub-games/killer-darts/killer.js')) ?>" defer></script>
</head>

<body>
    <?php site_header(); ?>

    <main class="page kd" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Killer Darts</h1>
        <p class="page-lede">Hit your own double to become a killer, take lives from rivals and be the last player standing.</p>

        <!-- Setup -->
        <form class="kd-form kd-view" data-view="setup" novalidate>
            <section class="card form">
                <h2>Players <span class="n">2 to 12</span></h2>
                <div class="kd-rows"></div>
                <div class="actions">
                    <button class="btn small" type="button" data-act="add">Add player</button>
                    <button class="btn small" type="button" data-act="random">Pick random numbers</button>
                </div>
                <small class="muted">Leave a number blank and it is picked at random from the numbers nobody has. Each player needs a different number.</small>
            </section>

            <section class="card form">
                <h2>Rules</h2>
                <div class="cols">
                    <label>Lives each <input class="field" name="lives" type="number" inputmode="numeric" min="1" max="9" value="3"></label>
                    <label>Hits to become a killer
                        <select class="field" name="need"><option value="1">1</option><option value="2">2</option><option value="3">3</option></select>
                    </label>
                </div>
                <label>Which darts count
                    <select class="field" name="count">
                        <option value="doubles">Doubles only (1 each)</option>
                        <option value="dt">Doubles (1) and trebles (2)</option>
                        <option value="triples">Trebles only (1 each)</option>
                        <option value="any">Any part of the number (single 1, double 2, treble 3)</option>
                    </select>
                </label>
                <label>When a killer hits their own number
                    <select class="field" name="self">
                        <option value="life">Loses a life</option>
                        <option value="scaled">Loses lives equal to the dart</option>
                        <option value="status">Loses killer status</option>
                        <option value="both">Loses a life and killer status</option>
                        <option value="heal">Gains lives (up to the start)</option>
                        <option value="none">Nothing happens</option>
                    </select>
                </label>
                <label>Bull
                    <select class="field" name="bull">
                        <option value="dead">Does nothing</option>
                        <option value="life">Gives the thrower a life (up to the start)</option>
                    </select>
                </label>
                <label class="check"><input type="checkbox" name="straight"> Everyone starts as a killer</label>
                <label class="check"><input type="checkbox" name="loseStatus"> Losing a life also loses killer status</label>
                <p class="err kd-setup-err" role="alert" hidden></p>
                <div class="actions"><button class="btn primary" type="submit">Start game</button></div>
            </section>
        </form>

        <!-- Play -->
        <section class="kd-view" data-view="play" hidden>
            <p class="kd-msg note" role="status" aria-live="polite"></p>
            <section class="card kd-win" hidden>
                <h2 class="kd-win-name"></h2>
                <p class="muted">Last player standing. Start a new game to play again.</p>
            </section>

            <section class="card kd-ctl">
                <div class="kd-turn">
                    <div class="grow">
                        <h2 class="kd-turn-name"></h2>
                        <p class="muted kd-turn-sub"></p>
                    </div>
                    <div class="actions">
                        <button class="btn" type="button" data-act="next">Next player</button>
                        <button class="btn" type="button" data-act="undo">Undo</button>
                    </div>
                </div>
                <div class="badges kd-darts"></div>
                <div class="stabs" role="group" aria-label="Ring">
                    <button type="button" data-mult="1">Single</button>
                    <button type="button" data-mult="2">Double</button>
                    <button type="button" data-mult="3">Treble</button>
                </div>
                <div class="kd-grid"></div>
                <div class="actions"><button class="btn" type="button" data-act="miss">Miss</button></div>
            </section>

            <section class="card">
                <h2>Players <span class="n kd-players-n"></span></h2>
                <div class="kd-players"></div>
            </section>

            <div class="actions"><button class="btn" type="button" data-act="new">New game</button></div>
        </section>

        <details class="card kd-rules">
            <summary>Rules</summary>
            <ol>
                <li>Each player owns one number on the board (thrown with the weaker hand, drawn, or chosen). No two players share one.</li>
                <li>Throw three darts a turn. Hit the double of your own number to become a killer.</li>
                <li>Once you are a killer, each double you hit on someone else's number takes a life from them. You can hit anyone, killer or not.</li>
                <li>Hit your own double as a killer and you pay the self-hit penalty. At zero lives you are out and your number is dead.</li>
                <li>Singles, and any dart that does not count, do nothing. The bull is nobody's number.</li>
                <li>The last player with a life left wins.</li>
            </ol>
            <p class="muted">The setup options change some of this. Once a game starts, the options in force are listed here.</p>
            <ul class="kd-active"></ul>
            <p class="muted">Full rules, variants and sources are in the <a href="https://github.com/Craftyplayz/Website/blob/main/docs/killer-darts.md" target="_blank" rel="noopener">rules document</a>.</p>
        </details>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
