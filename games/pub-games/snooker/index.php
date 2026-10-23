<?php
$site_page = [
    'components' => true,
    'ui' => true,
    'css' => ['/assets/css/pages/games-pubgames-snooker.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

// balls for the pot buttons; scoring and rules live in snooker.js
$snk_balls = ['red' => 1, 'yellow' => 2, 'green' => 3, 'brown' => 4, 'blue' => 5, 'pink' => 6, 'black' => 7];
$snk_best = [1, 3, 5, 7, 9, 11, 17, 19, 25, 35];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <script src="<?= h(asset('/games/pub-games/snooker/snooker.js')) ?>" defer></script>
</head>

<body>
    <?php site_header(); ?>

    <main class="page snk" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Snooker</h1>
        <p class="page-lede">Keep the score by the table: breaks, fouls, free balls, points remaining and snookers needed.</p>
        <p class="note snk-nostore" hidden>This browser won't save the match if you leave the page.</p>

        <!-- Setup -->
        <form class="card form form-card snk-setup" novalidate>
            <section class="snk-resume" aria-labelledby="snk-resume-title" hidden>
                <h2 id="snk-resume-title">Match in progress</h2>
                <p class="muted snk-resume-meta"></p>
                <div class="actions"><button class="btn" type="button" data-act="snk-resume">Resume match</button></div>
            </section>
            <h2>New match</h2>
            <div class="cols">
                <label>Player 1 <input class="field" name="p1" autocomplete="off" maxlength="24" placeholder="Player 1"></label>
                <label>Player 2 <input class="field" name="p2" autocomplete="off" maxlength="24" placeholder="Player 2"></label>
            </div>
            <div class="snk-group" role="group" aria-labelledby="snk-lbl-reds">
                <span class="snk-lbl" id="snk-lbl-reds">Reds</span>
                <div class="snk-picks">
                    <?php foreach ([15, 10, 6] as $n): ?><button class="snk-pick<?= $n === 15 ? ' on' : '' ?>" type="button" data-act="snk-reds" data-id="<?= $n ?>" aria-pressed="<?= $n === 15 ? 'true' : 'false' ?>"><?= $n ?></button><?php endforeach; ?>
                </div>
                <small class="muted">15 on a full-size table; 10 or 6 for shorter frames or a pub table.</small>
            </div>
            <div class="cols">
                <label>Match length
                    <select class="field" name="bestOf">
                        <?php foreach ($snk_best as $n): ?><option value="<?= $n ?>"<?= $n === 3 ? ' selected' : '' ?>><?= $n === 1 ? 'One frame' : 'Best of ' . $n ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label>Breaks first
                    <select class="field" name="breaker">
                        <option value="0">Player 1</option>
                        <option value="1">Player 2</option>
                    </select>
                </label>
            </div>
            <details class="snk-hr">
                <summary>House rules</summary>
                <div class="form">
                    <label>Rule set
                        <select class="field" name="preset">
                            <option value="wpbsa">WPBSA official</option>
                            <option value="league">League (no miss rule)</option>
                            <option value="pub">Pub / home (house rules)</option>
                            <option value="custom">Custom</option>
                        </select>
                    </label>
                    <label class="check"><input type="checkbox" name="freeBall" checked> Free ball when snookered after a foul</label>
                    <label class="check"><input type="checkbox" name="putBack" checked> After a foul, the opponent can put you back in</label>
                    <label class="check"><input type="checkbox" name="miss" checked> Miss rule: a third miss with a full ball view loses the frame</label>
                    <label class="check"><input type="checkbox" name="flat4"> Every foul is 4 points</label>
                    <div class="cols">
                        <label>Player 1 start (optional) <input class="field" name="h1" type="number" inputmode="numeric" min="0" max="200" value="0"></label>
                        <label>Player 2 start (optional) <input class="field" name="h2" type="number" inputmode="numeric" min="0" max="200" value="0"></label>
                    </div>
                    <small class="muted">A handicap start gives a player points at the start of every frame.</small>
                </div>
            </details>
            <div class="actions"><button class="btn primary" type="submit">Start match</button></div>
        </form>

        <!-- Scoreboard -->
        <section class="snk-play" aria-label="Scoreboard" hidden>
            <p class="muted snk-match"></p>
            <div class="snk-board">
                <?php foreach ([0, 1] as $i): ?>
                    <div class="snk-player">
                        <span class="snk-name"></span>
                        <b class="snk-score"></b>
                        <small><span class="snk-frames"></span> · high break <span class="snk-high"></span></small>
                        <span class="badge b snk-at" hidden>At the table</span>
                    </div>
                <?php endforeach; ?>
            </div>

            <p class="snk-on" aria-live="polite"></p>

            <section class="card snk-result" hidden>
                <h2 class="snk-result-title"></h2>
                <p class="muted snk-result-body"></p>
                <div class="actions">
                    <button class="btn primary" type="button" data-act="snk-next">Next frame</button>
                    <button class="btn" type="button" data-act="snk-new-ask">New match</button>
                    <button class="btn" type="button" data-act="snk-undo">Undo</button>
                </div>
            </section>

            <section class="card snk-choose" hidden>
                <h2>Re-spotted black</h2>
                <p class="muted">Scores are level. Toss a coin; the winner picks who plays first, cue ball in hand in the D. The next score or foul ends the frame.</p>
                <div class="actions">
                    <button class="btn" type="button" data-act="snk-respot" data-id="0"></button>
                    <button class="btn" type="button" data-act="snk-respot" data-id="1"></button>
                    <button class="btn" type="button" data-act="snk-undo">Undo</button>
                </div>
            </section>

            <div class="snk-notes" aria-live="polite" hidden></div>

            <div class="stat-row snk-stats">
                <div class="stat-card"><b class="snk-st-break"></b><span>Break</span></div>
                <div class="stat-card"><b class="snk-st-rem"></b><span>Points remaining</span></div>
                <div class="stat-card"><b class="snk-st-lead"></b><span>Lead</span><small class="snk-st-leader"></small></div>
                <div class="stat-card"><b class="snk-st-reds"></b><span>Reds left</span></div>
            </div>

            <div class="snk-actions">
                <div class="snk-balls" role="group" aria-label="Pot a ball">
                    <?php foreach ($snk_balls as $ball => $v): ?>
                        <button class="snk-ball snk-<?= $ball ?>" type="button" data-act="snk-pot" data-id="<?= $ball ?>" aria-label="<?= ucfirst($ball) ?>, <?= $v ?>">
                            <span class="snk-ball-n"><?= $ball === 'red' ? '' : $v ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="actions">
                    <button class="btn primary" type="button" data-act="snk-miss">End visit</button>
                    <button class="btn" type="button" data-act="snk-foul">Foul</button>
                    <button class="btn" type="button" data-act="snk-free" aria-pressed="false" hidden>Free ball</button>
                    <button class="btn" type="button" data-act="snk-undo" disabled>Undo</button>
                </div>
                <small class="muted">Tap a ball for each pot. End visit after a miss or safety.</small>
            </div>

            <div class="actions snk-more">
                <button class="btn small" type="button" data-act="snk-rerack">Re-rack</button>
                <button class="btn small" type="button" data-act="snk-concede-ask">Concede frame</button>
                <button class="btn small" type="button" data-act="snk-new-ask">New match</button>
            </div>
        </section>

        <!-- Rules (from docs/snooker.md) -->
        <details class="card snk-rules">
            <summary><h2>Rules</h2></summary>
            <p class="muted snk-house"></p>
            <h3>Order of play</h3>
            <ul>
                <li>Break off from the D. Pot a red (1), then any colour: yellow 2, green 3, brown 4, blue 5, pink 6, black 7. Repeat.</li>
                <li>Colours go back on their spots while reds are left (on the highest free spot if their own is taken). Reds stay down. Two or more reds in one shot all count.</li>
                <li>After the last red and its colour, clear the colours in order: yellow, green, brown, blue, pink, black.</li>
                <li>Your visit goes on until you miss or foul. The points from one visit are your break.</li>
                <li>With only the black left, the first score or foul ends the frame. If that leaves the scores level, the black is re-spotted: toss a coin, the winner picks who plays from the D, and the next score or foul wins.</li>
            </ul>
            <h3>Fouls</h3>
            <ul>
                <li>A foul gives your opponent at least 4 points, or the value of the ball on or the highest ball involved if that is more (so 4 to 7).</li>
                <li>Fouls: potting the white, missing everything, hitting or potting a ball not on first, a red and a colour together when on a red, push or jump shots, touching any ball with your hand or clothes, playing at reds twice in a row (7), using a ball off the table (7).</li>
                <li>After a foul your opponent plays from where the balls stopped, or puts you back in to play again.</li>
                <li>Free ball: if they're snookered after your foul, they can nominate any colour as the ball on. It scores as a red (or as the colour on) and is re-spotted. They can't snooker you behind it unless only pink and black are left.</li>
                <li>Miss rule: not trying your best to hit the ball on is a foul and a miss, and the balls can be put back. With a full ball in view, a third miss in a row loses the frame. Not called once either player needs snookers.</li>
                <li>Touching ball: play away from it without moving it.</li>
            </ul>
            <h3>Counting</h3>
            <ul>
                <li>Points remaining: reds × 8 + 27 while a red is on (each red with the black, then 27 for the colours).</li>
                <li>You need snookers when you're further behind than the points left. A maximum is 147, or 155 with a free ball at the start.</li>
                <li>Stalemate: both players can agree to re-rack. Scores in that frame are cancelled and the same player breaks.</li>
                <li>Concede when you can't win. Shake hands either way.</li>
            </ul>
            <h3>Variants</h3>
            <ul>
                <li>6-red and 10-red: the same rules with fewer reds (maximum 75 or 107), common on pub and home tables.</li>
                <li>Leagues often drop the miss rule; pubs often play every foul as 4 points with no put-back. Set these under House rules.</li>
                <li>Snooker Shoot Out: 10-minute frames, a shot clock, ball in hand after every foul, no miss rule, and a level frame goes to a blue-ball shoot-out. The clock isn't tracked here.</li>
            </ul>
        </details>

        <!-- Dialogs -->
        <dialog class="dlg" id="snk-foul" aria-labelledby="snk-foul-title">
            <form class="form" novalidate>
                <h2 id="snk-foul-title">Foul</h2>
                <div class="snk-pen">
                    <label>Penalty points <input class="field" name="pen" type="number" inputmode="numeric" min="4" max="7" required><small class="snk-pen-help"></small></label>
                    <div class="snk-picks" role="group" aria-label="Quick pick">
                        <?php foreach ([4, 5, 6, 7] as $n): ?><button class="snk-pick" type="button" data-act="snk-pen" data-id="<?= $n ?>" aria-pressed="false"><?= $n ?></button><?php endforeach; ?>
                    </div>
                </div>
                <p class="muted snk-flat" hidden>House rule: every foul is 4 points.</p>
                <label class="snk-foul-reds">Reds potted in the foul <input class="field" name="reds" type="number" inputmode="numeric" min="0" value="0"><small>They stay off the table.</small></label>
                <label class="check snk-foul-miss"><input type="checkbox" name="miss"> Foul and a miss, with a full ball in view</label>
                <div class="actions">
                    <button class="btn primary" type="button" data-act="snk-foul-go">Opponent plays</button>
                    <button class="btn" type="button" data-act="snk-foul-back">Put back in</button>
                    <button class="btn" type="button" data-act="dialog-close">Cancel</button>
                </div>
            </form>
        </dialog>

        <dialog class="dlg" id="snk-concede" aria-labelledby="snk-concede-title">
            <h2 id="snk-concede-title">Who concedes the frame?</h2>
            <div class="actions">
                <button class="btn" type="button" data-act="snk-concede" data-id="0"></button>
                <button class="btn" type="button" data-act="snk-concede" data-id="1"></button>
                <button class="btn" type="button" data-act="dialog-close">Cancel</button>
            </div>
        </dialog>

        <dialog class="dlg" id="snk-new" aria-labelledby="snk-new-title">
            <h2 id="snk-new-title">Start a new match?</h2>
            <p>The current match will be lost.</p>
            <div class="actions">
                <button class="btn primary" type="button" data-act="snk-new" data-id="same">Same players and rules</button>
                <button class="btn" type="button" data-act="snk-new" data-id="setup">Change setup</button>
                <button class="btn" type="button" data-act="dialog-close">Cancel</button>
            </div>
        </dialog>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
