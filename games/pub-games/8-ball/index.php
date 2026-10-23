<?php
$site_page = [
    'components' => true,
    'ui' => true,
    'css' => ['/assets/css/pages/games-pubgames-8-ball.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

$eb_fouls = [
    'in-off' => 'In-off (cue ball potted)',
    'wrong' => 'Wrong ball hit first',
    'no-hit' => 'No ball hit',
    'cushion' => 'No cushion after contact',
    'off' => 'Ball off the table',
    'push' => 'Push or double hit',
    'touch' => 'Touched a ball',
    'feet' => 'Both feet off the floor',
    'early' => 'Shot before the balls stopped',
    'jump' => 'Jump shot',
    'baulk' => 'Played from outside baulk',
    'break' => 'Illegal break',
    'black-off' => 'Black off the table (loses the frame)',
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <script src="<?= h(asset('/games/pub-games/8-ball/8ball.js')) ?>" defer></script>
</head>

<body>
    <?php site_header(); ?>

    <main class="page eb" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">8-Ball Pool</h1>
        <p class="page-lede">Keep track of a pub frame of reds and yellows: whose visit, fouls, two shots and the black.</p>

        <!-- Setup -->
        <form class="card form form-card eb-setup" novalidate>
            <h2>New match</h2>
            <div class="cols">
                <label>Player or team 1 <input class="field" name="p1" maxlength="20" autocomplete="off" placeholder="Sam"></label>
                <label>Player or team 2 <input class="field" name="p2" maxlength="20" autocomplete="off" placeholder="Alex"></label>
            </div>
            <div class="cols">
                <label>Race to
                    <select class="field" name="race">
                        <?php foreach ([1, 2, 3, 4, 5, 7, 9, 11] as $n): ?><option value="<?= $n ?>" <?= $n === 3 ? 'selected' : '' ?>><?= $n ?> <?= $n === 1 ? 'frame' : 'frames' ?></option><?php endforeach; ?>
                    </select>
                </label>
                <fieldset>
                    <legend>Breaks first</legend>
                    <label class="check"><input type="radio" name="breaker" value="0" checked> Player 1</label>
                    <label class="check"><input type="radio" name="breaker" value="1"> Player 2</label>
                </fieldset>
            </div>
            <label>Rule set
                <select class="field" name="set">
                    <option value="pub">English pub rules (two visits, free shot)</option>
                    <option value="world">WEPF World Rules (two visits, no free shot)</option>
                    <option value="blackball">WPA Blackball (free shot, one visit)</option>
                    <option value="intl">International Rules (ball in hand anywhere)</option>
                </select>
                <small class="muted eb-set-help"></small>
            </label>
            <fieldset>
                <legend>House rules</legend>
                <label>Black potted on the break
                    <select class="field" name="blackOnBreak">
                        <option value="rerack">Re-rack, same player breaks</option>
                        <option value="respot">Re-spot the black and carry on</option>
                        <option value="win">Wins the frame</option>
                        <option value="lose">Loses the frame</option>
                    </select>
                </label>
                <label class="check"><input type="checkbox" name="twoOnBlack" checked> Two visits carry on to the black</label>
                <label class="check"><input type="checkbox" name="callBlack"> Call the black's pocket</label>
                <label class="check"><input type="checkbox" name="inOffBlack"> An in-off while on the black loses</label>
            </fieldset>
            <div class="actions"><button class="btn primary" type="submit">Start match</button></div>
        </form>

        <!-- Board -->
        <section class="eb-board" aria-label="Frame" hidden>
            <p class="eb-msg note" tabindex="-1" aria-live="polite"></p>

            <section class="card eb-over" hidden>
                <h2 class="eb-over-title"></h2>
                <p class="eb-over-reason"></p>
                <div class="actions">
                    <button class="btn primary" type="button" data-act="next">Next frame</button>
                    <button class="btn primary" type="button" data-act="rematch">Play again</button>
                    <button class="btn" type="button" data-act="undo"><?= site_icon('undo') ?> Undo</button>
                </div>
            </section>

            <div class="eb-score">
                <div class="eb-player"><b class="eb-name"></b><span class="eb-frames"></span><span class="eb-tag-row badges"></span></div>
                <div class="eb-player"><b class="eb-name"></b><span class="eb-frames"></span><span class="eb-tag-row badges"></span></div>
            </div>
            <p class="muted eb-race"></p>

            <div class="bar eb-turn"><p class="eb-status" aria-live="polite"></p></div>

            <section class="eb-table" aria-label="Balls on the table">
                <div class="eb-row eb-row-red"><span class="eb-lbl">Reds <span class="eb-n"></span></span><div class="eb-balls"></div></div>
                <div class="eb-row eb-row-yellow"><span class="eb-lbl">Yellows <span class="eb-n"></span></span><div class="eb-balls"></div></div>
                <div class="eb-row eb-row-black"><span class="eb-lbl">Black</span><div class="eb-balls"><button class="eb-slot" type="button" data-act="black" aria-label="Pot the black"><span class="eb-ball black" aria-hidden="true"></span></button></div></div>
            </section>

            <div class="actions eb-actions">
                <button class="btn primary" type="button" data-act="miss">Miss / end visit</button>
                <button class="btn" type="button" data-act="foul">Foul</button>
                <button class="btn eb-black-btn" type="button" data-act="black"><span class="eb-ball black mini" aria-hidden="true"></span> Pot the black</button>
                <button class="btn" type="button" data-act="undo"><?= site_icon('undo') ?> Undo</button>
            </div>
            <p class="muted eb-hint">Tap a red or yellow each time one goes down for the player at the table.</p>

            <details class="card eb-more">
                <summary>Frame log and match</summary>
                <ol class="rows eb-log"></ol>
                <div class="actions">
                    <button class="btn small" type="button" data-act="rerack">Re-rack (stalemate)</button>
                    <button class="btn small" type="button" data-act="rerack" data-id="swap">Re-rack, other player breaks</button>
                    <button class="btn small" type="button" data-act="new-ask">New match</button>
                </div>
            </details>
        </section>

        <details class="card eb-rules">
            <summary>Rules</summary>
            <div class="eb-your-rules" hidden>
                <h3>Your table</h3>
                <ul></ul>
            </div>
            <h3>Rack and break</h3>
            <ul>
                <li>Black in the middle of the third row, reds and yellows mixed, apex towards the breaker.</li>
                <li>Break from behind the baulk line. A legal break pots a ball or sends at least 2 balls to a cushion (World Rules: 4).</li>
                <li>Pot a ball on the break and you carry on. An illegal break is re-racked and the opponent breaks with the foul penalty.</li>
                <li>Black on the break: re-rack and break again in pub, World and Blackball rules; re-spotted in International Rules.</li>
            </ul>
            <h3>Colours</h3>
            <ul>
                <li>The table is open until someone legally pots a red or a yellow; that colour is theirs for the frame.</li>
                <li>Pub rules count a ball potted on the break; World, Blackball and International leave the table open after the break.</li>
                <li>Every shot must hit one of your own colour first (any colour on an open table, never the black until your colours are down).</li>
            </ul>
            <h3>Fouls</h3>
            <ul>
                <li>In-off, wrong ball first, no ball hit, a ball off the table, push or double hit, jump shot, touching a ball, both feet off the floor, shooting before the balls stop.</li>
                <li>Potting the opponent's ball: a foul in pub and World rules (allowed on a free shot), allowed alongside your own in Blackball, loss of turn in International.</li>
                <li>No cushion after contact is a foul in World, Blackball and International rules; most pubs don't play it.</li>
            </ul>
            <h3>After a foul</h3>
            <ul>
                <li>Pub rules: two visits. The first shot is a free shot: hit any ball, and anything potted counts. The cue ball may be placed behind the baulk line.</li>
                <li>Pot on the first visit and you carry on; when you miss, you still have the second visit.</li>
                <li>World Rules: two visits, no free shot. Blackball: free shot then one visit. International: one visit, ball in hand anywhere.</li>
            </ul>
            <h3>The black</h3>
            <ul>
                <li>Clear your colour, then pot the black to win.</li>
                <li>You lose the frame if you pot the black before your colours are down, pot it with a foul (black and in-off together), or knock it off the table.</li>
                <li>Stalemate: re-rack and the same player breaks again.</li>
            </ul>
            <h3>American 8-ball is different</h3>
            <p class="muted">Numbered solids and stripes, apex ball on the foot spot, you call the pocket for the 8, and every foul gives one visit with ball in hand anywhere. No two visits, no free shot.</p>
        </details>

        <!-- Dialogs -->
        <dialog class="dlg" id="eb-foul" aria-labelledby="eb-foul-title">
            <h2 id="eb-foul-title">What was the foul?</h2>
            <div class="eb-picks">
                <?php foreach ($eb_fouls as $id => $label): ?><button class="btn" type="button" data-act="foul-pick" data-id="<?= h($id) ?>"><?= h($label) ?></button><?php endforeach; ?>
            </div>
            <div class="actions"><button class="btn" type="button" data-act="dialog-close">Cancel</button></div>
        </dialog>

        <dialog class="dlg" id="eb-black" aria-labelledby="eb-black-title">
            <h2 id="eb-black-title">The black went down</h2>
            <p class="eb-black-lead"></p>
            <div class="eb-picks">
                <button class="btn primary" type="button" data-act="black-pick" data-id="clean">Clean pot</button>
                <button class="btn" type="button" data-act="black-pick" data-id="foul">With a foul (in-off, wrong ball first)</button>
                <button class="btn" type="button" data-act="black-pick" data-id="wrong">In the wrong pocket</button>
            </div>
            <div class="actions"><button class="btn" type="button" data-act="dialog-close">Cancel</button></div>
        </dialog>

        <dialog class="dlg" id="eb-new" aria-labelledby="eb-new-title">
            <h2 id="eb-new-title">Start a new match?</h2>
            <p>The current score and frame are cleared.</p>
            <div class="actions">
                <button class="btn primary" type="button" data-act="new">Start new match</button>
                <button class="btn" type="button" data-act="dialog-close">Keep playing</button>
            </div>
        </dialog>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
