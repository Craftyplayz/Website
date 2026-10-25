<?php
$site_page = [
    'components' => true,
    'ui' => true,
    'css' => ['/assets/css/pages/games-kelly-pool.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

// numbers for the server-rendered pickers; everything else is drawn by kelly-pool.js
$kp_players = range(2, 15);
$kp_balls = range(1, 5);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <script src="<?= h(asset('/games/pub-games/kelly-pool/kelly-pool.js')) ?>" defer></script>
</head>

<body>
    <?php site_header(); ?>

    <main class="page kp" id="main" data-view="home">
        <?= site_breadcrumbs(site_current_path()) ?>

        <header class="page-hero kp-hero">
            <img class="page-hero-bg kp-hero-art" src="/media/games/kelly-pool/hero-960.webp"
                srcset="/media/games/kelly-pool/hero-960.webp 960w, /media/games/kelly-pool/hero-1920.webp 1920w"
                sizes="(max-width: 1312px) 100vw, 1280px" width="1920" height="800" alt="" fetchpriority="high" decoding="async">
            <div class="page-hero-body">
                <div class="grow">
                    <h1 class="page-title">Kelly Pool</h1>
                    <p class="page-lede kp-lede">Draw secret numbers, sink balls and be the last player standing.</p>
                    <p class="page-lede kp-mode-line" hidden></p>
                </div>
                <div class="kp-tools" hidden>
                    <button class="kp-icon" type="button" data-act="drawer" aria-controls="kp-history" aria-label="History" title="History"><?= site_icon('history') ?></button>
                    <button class="kp-icon" type="button" data-act="drawer" aria-controls="kp-rules" aria-label="Rules" title="Rules"><?= site_icon('info') ?></button>
                    <button class="kp-icon" type="button" data-act="dialog" aria-controls="kp-exit" aria-label="Leave game" title="Leave game"><?= site_icon('close') ?></button>
                </div>
            </div>
        </header>

        <!-- Home: choose a mode -->
        <section class="kp-view kp-home" data-view="home">
            <p class="note kp-nostore" hidden>This browser won't save the game if you leave the page.</p>
            <section class="card kp-resume" aria-labelledby="kp-resume-title" hidden>
                <h2 id="kp-resume-title">Game in progress</h2>
                <p class="muted kp-resume-meta"></p>
                <div class="actions">
                    <button class="btn primary" type="button" data-act="kp-resume">Resume game</button>
                    <button class="btn" type="button" data-act="kp-abandon">Abandon game</button>
                </div>
            </section>
            <div class="grid">
                <section class="card">
                    <h2>Last man standing</h2>
                    <p class="muted">Lose a ball every time one of yours goes down. Last player left wins.</p>
                    <div class="actions"><button class="btn primary" type="button" data-act="kp-mode" data-id="lms">Start last man standing</button></div>
                </section>
            </div>
            <div class="actions kp-home-more">
                <button class="btn" type="button" data-act="drawer" aria-controls="kp-rules">Read the rules</button>
                <button class="btn" type="button" data-act="drawer" aria-controls="kp-settings">Settings</button>
            </div>
            <section class="kp-guide" aria-labelledby="kp-guide-title">
                <h2 class="sec-h" id="kp-guide-title">How Kelly Pool works</h2>
                <div class="kp-guide-list">
                    <details open>
                        <summary>The basics</summary>
                        <ul>
                            <li>Everyone is dealt secret numbers on the phone, 1 to 5 each. Each number is a life. Keep your numbers to yourself.</li>
                            <li>Hit any ball you like. It doesn't have to be the lowest number, and you don't call shots.</li>
                            <li>Pot a ball and you shoot again. Miss and the turn passes.</li>
                            <li>Balls nobody was dealt are dead balls. Potting one changes nothing.</li>
                        </ul>
                        <p class="muted">The tracker doesn't track the shooting order unless you turn on "Show whose turn it is".</p>
                    </details>
                    <details>
                        <summary>When a ball goes down</summary>
                        <ul>
                            <li>The owner of the ball loses a life, whoever potted it. Pot your own number and you lose that life too, so leave your balls on the table.</li>
                            <li>Lose all your lives and you're out. You stop shooting for the rest of the game.</li>
                            <li>The last player with a life left wins.</li>
                        </ul>
                    </details>
                    <details>
                        <summary>Fouls</summary>
                        <ul>
                            <li>Fouls: potting the cue ball, knocking it off the table, or touching a ball.</li>
                            <li>After a foul the next player plays the table as it lies. If the cue ball went down or off the table, they get it in hand.</li>
                            <li>A ball potted on a foul stays down and counts like any other: its owner loses a life. <span class="badge">Tracker: tap it as normal</span></li>
                        </ul>
                    </details>
                    <details>
                        <summary>Edge cases</summary>
                        <ul>
                            <li><b>Several balls in one shot:</b> they all count. Tap each one.</li>
                            <li><b>Tie:</b> if the last players' final balls go down on the same shot, nobody wins. <span class="badge">Tracker: tap one, then choose "last ball went down too"</span></li>
                            <li><b>No peeking:</b> don't look at other players' numbers. Everyone's numbers are shown when the game ends.</li>
                        </ul>
                        <p class="muted">Other tables play other ways. The <a href="https://github.com/Craftyplayz/Website/blob/main/docs/kelly-pool.md" target="_blank" rel="noopener">rules document</a> lists them, with sources.</p>
                    </details>
                </div>
            </section>
        </section>

        <!-- Players and balls -->
        <form class="card form form-card kp-view kp-setup" data-view="setup" novalidate hidden>
            <h2>Players and balls</h2>
            <div class="kp-group" role="group" aria-labelledby="kp-lbl-players">
                <span class="kp-lbl" id="kp-lbl-players">Number of players</span>
                <div class="kp-picks">
                    <?php foreach ($kp_players as $n): ?><button class="kp-pick" type="button" data-act="kp-pick-players" data-id="<?= $n ?>" aria-pressed="false"><?= $n ?></button><?php endforeach; ?>
                </div>
            </div>
            <div class="kp-group" role="group" aria-labelledby="kp-lbl-balls">
                <span class="kp-lbl" id="kp-lbl-balls">Balls each</span>
                <div class="kp-picks kp-picks-balls">
                    <?php foreach ($kp_balls as $n): ?><button class="kp-pick" type="button" data-act="kp-pick-balls" data-id="<?= $n ?>" aria-pressed="false"><?= $n ?></button><?php endforeach; ?>
                </div>
                <small class="muted kp-limit" aria-live="polite"></small>
            </div>
            <p class="kp-summary"></p>
            <div class="actions kp-stick">
                <button class="btn primary" type="submit">Next</button>
                <button class="btn" type="button" data-act="kp-back">Back</button>
            </div>
        </form>

        <!-- Player names -->
        <form class="card form form-card kp-view kp-names" data-view="names" novalidate hidden>
            <h2>Player names</h2>
            <div class="kp-recent" hidden>
                <span class="kp-lbl">Recent names</span>
                <div class="kp-chips"></div>
            </div>
            <div class="kp-fields"></div>
            <div><button class="btn small" type="button" data-act="kp-shuffle"><?= site_icon('shuffle') ?> Shuffle order</button></div>
            <div class="actions kp-stick">
                <button class="btn primary" type="submit">Deal balls</button>
                <button class="btn" type="button" data-act="kp-back">Back</button>
            </div>
        </form>

        <!-- Pass and reveal: full screen, filled in by JS only while it shows -->
        <section class="kp-private" data-view="private" aria-label="Secret numbers" hidden>
            <div class="kp-private-top"></div>
            <div class="kp-private-mid" aria-live="polite"></div>
            <div class="actions kp-private-actions"></div>
        </section>

        <!-- Board -->
        <section class="kp-view kp-board" data-view="play" hidden>
            <div class="bar kp-turn" hidden>
                <p class="kp-turn-line" aria-live="polite"><b class="kp-turn-name"></b> to shoot</p>
                <div class="actions">
                    <button class="btn primary" type="button" data-act="kp-next">Next player</button>
                    <button class="btn" type="button" data-act="kp-next" data-id="back">Previous player</button>
                </div>
            </div>
            <div class="stat-row">
                <div class="stat-card"><b class="kp-st-in"></b><span>Players in</span><small class="kp-st-of"></small></div>
                <div class="stat-card"><b class="kp-st-table"></b><span>Balls on the table</span><small class="kp-st-potted"></small></div>
                <div class="stat-card kp-st-last-card" hidden><b class="kp-st-last"></b><span>Last ball</span><small class="kp-st-owner"></small></div>
            </div>
            <section class="card">
                <h2>Players <span class="n kp-players-n"></span></h2>
                <div class="kp-players"></div>
            </section>
            <section class="card kp-balls-card">
                <h2>Balls <span class="n kp-balls-n"></span></h2>
                <div class="kp-rack"></div>
            </section>
        </section>

        <!-- Game over -->
        <section class="kp-view kp-over" data-view="over" hidden>
            <section class="card kp-result">
                <h2 class="kp-over-title"></h2>
                <p class="muted kp-over-reason"></p>
            </section>
            <section class="card">
                <h2>Everyone's numbers</h2>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Player</th><th>Numbers</th><th>Result</th></tr></thead>
                        <tbody class="kp-over-rows"></tbody>
                    </table>
                </div>
            </section>
            <div class="actions">
                <button class="btn primary" type="button" data-act="kp-again">Play again</button>
                <button class="btn" type="button" data-act="kp-change">Change players</button>
                <button class="btn" type="button" data-act="kp-home">Back to Kelly Pool</button>
            </div>
        </section>

        <!-- Drawers -->
        <aside class="drawer kp-drawer" id="kp-history" aria-label="History" aria-hidden="true" tabindex="-1" inert>
            <button class="close" type="button" data-act="drawer-close" aria-label="Close">&times;</button>
            <h2>History</h2>
            <div class="actions"><button class="btn" type="button" data-act="kp-undo" disabled><?= site_icon('undo') ?> Undo last pot</button></div>
            <p class="muted kp-history-empty">No balls potted yet.</p>
            <ol class="rows kp-history-list"></ol>
        </aside>

        <aside class="drawer kp-drawer" id="kp-rules" aria-label="Rules" aria-hidden="true" tabindex="-1" inert>
            <button class="close" type="button" data-act="drawer-close" aria-label="Close">&times;</button>
            <h2>Rules</h2>
            <ol class="kp-rules-list">
                <li>Everyone is dealt secret numbers. Each one is a life.</li>
                <li>Take turns. Hit any ball you like.</li>
                <li>When one of your numbers goes down, you lose a life, whoever potted it. That includes you.</li>
                <li>A ball potted on a foul still counts.</li>
                <li>Lose all your lives and you're out for the rest of the game.</li>
                <li>The last player with a life left wins. If the last balls go down on the same shot, nobody wins.</li>
            </ol>
            <p class="muted">Play on one phone and pass it round. A game can't be shared between devices.</p>
        </aside>

        <aside class="drawer kp-drawer" id="kp-settings" aria-label="Settings" aria-hidden="true" tabindex="-1" inert>
            <button class="close" type="button" data-act="drawer-close" aria-label="Close">&times;</button>
            <h2>Settings</h2>
            <form class="form kp-settings-form">
                <div>
                    <label class="check"><input type="checkbox" name="pin"> Lock numbers with a PIN</label>
                    <small class="muted">Stops casual peeking. It isn't secure storage.</small>
                </div>
                <small class="muted kp-locked" hidden>This setting is locked until the current game ends.</small>
                <label>Hide numbers after
                    <select class="field" name="hideAfter">
                        <option value="10">10 seconds</option>
                        <option value="15">15 seconds</option>
                        <option value="30">30 seconds</option>
                    </select>
                </label>
                <label class="check"><input type="checkbox" name="vibrate"> Vibrate when a ball is potted</label>
                <div>
                    <label class="check"><input type="checkbox" name="turns"> Show whose turn it is</label>
                    <small class="muted">Adds a "to shoot" line and a Next player button to the board.</small>
                </div>
                <div><button class="btn small" type="button" data-act="kp-clear-names">Clear recent names</button></div>
            </form>
        </aside>

        <!-- Dialogs -->
        <dialog class="dlg" id="kp-outcome" aria-labelledby="kp-outcome-title">
            <h2 id="kp-outcome-title"></h2>
            <p class="kp-outcome-body"></p>
            <div class="actions"></div>
        </dialog>

        <dialog class="dlg" id="kp-exit" aria-labelledby="kp-exit-title">
            <h2 id="kp-exit-title">Leave this game?</h2>
            <p>The game is saved on this device, so you can resume it from the Kelly Pool page.</p>
            <div class="actions">
                <button class="btn primary" type="button" data-act="dialog-close">Keep playing</button>
                <button class="btn" type="button" data-act="kp-leave">Leave and save</button>
                <button class="btn" type="button" data-act="kp-end-ask">End game</button>
            </div>
        </dialog>

        <dialog class="dlg" id="kp-end" aria-labelledby="kp-end-title">
            <h2 id="kp-end-title">End this game for good?</h2>
            <p>Nobody wins and the game can't be resumed.</p>
            <div class="actions">
                <button class="btn primary" type="button" data-act="kp-end">End game</button>
                <button class="btn" type="button" data-act="dialog-close">Cancel</button>
            </div>
        </dialog>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
