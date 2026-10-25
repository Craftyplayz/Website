<?php
$site_page = [
    'components' => true,
    'css' => ['/assets/css/pages/games-pubgames-501.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <script src="<?= h(asset('/games/pub-games/501/501.js')) ?>" defer></script>
</head>

<body>
    <?php site_header(); ?>

    <main class="page x01" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">501 / 301 Darts</h1>
        <p class="page-lede">Score a game of 501, 301 or any x01 for one to eight players, with busts, checkouts and undo.</p>

        <!-- Setup -->
        <section class="x01-setup">
            <form class="card form form-card" novalidate>
                <h2>New game</h2>
                <div class="cols">
                    <label>Start score
                        <select class="field" name="start">
                            <option value="501" selected>501</option>
                            <option value="301">301</option>
                            <option value="701">701</option>
                            <option value="custom">Custom</option>
                        </select>
                    </label>
                    <label>First to
                        <select class="field" name="legs">
                            <?php foreach ([1, 2, 3, 4, 5, 6, 7, 9, 11] as $n): ?><option value="<?= $n ?>"><?= $n ?> <?= $n === 1 ? 'leg' : 'legs' ?></option><?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <label class="x01-custom" hidden>Custom start score
                    <input class="field" name="custom" type="number" inputmode="numeric" min="2" max="1001" value="601">
                    <small class="x01-custom-err" hidden>Enter a whole number from 2 to 1001.</small>
                </label>
                <div class="cols">
                    <label>Starting
                        <select class="field" name="in">
                            <option value="straight">Straight in</option>
                            <option value="double">Double in</option>
                            <option value="master">Double/triple in</option>
                        </select>
                    </label>
                    <label>Finishing
                        <select class="field" name="out">
                            <option value="double">Double out</option>
                            <option value="master">Double/triple out</option>
                            <option value="straight">Straight out</option>
                        </select>
                    </label>
                </div>
                <label>Bull
                    <select class="field" name="bull">
                        <option value="split">Split: outer 25, inner 50</option>
                        <option value="flat">50/50: both 50, only inner is a double</option>
                    </select>
                </label>
                <label>Players
                    <select class="field" name="players">
                        <?php foreach (range(1, 8) as $n): ?><option value="<?= $n ?>"<?= $n === 2 ? ' selected' : '' ?>><?= $n ?><?= $n === 1 ? ' (practice)' : '' ?></option><?php endforeach; ?>
                    </select>
                </label>
                <?php foreach (range(1, 8) as $n): ?>
                <label data-name="<?= $n ?>"<?= $n > 2 ? ' hidden' : '' ?>>Player <?= $n ?> name (optional)
                    <input class="field" type="text" maxlength="20" autocomplete="off" placeholder="Player <?= $n ?>">
                </label>
                <?php endforeach; ?>
                <div class="actions">
                    <button class="btn primary" type="submit">Start game</button>
                    <button class="btn x01-cancel" type="button" hidden>Back to game</button>
                </div>
            </form>
        </section>

        <!-- Scorer (drawn by 501.js) -->
        <section class="x01-play" aria-live="off" hidden></section>

        <section class="card x01-rules">
            <h2>Rules</h2>
            <details>
                <summary>How the game works</summary>
                <ul>
                    <li>Count down from the start score to exactly zero. Three darts make a turn. Players take turns; the starter alternates each leg. Decide the first thrower with a bull-off: closest to the centre goes first.</li>
                    <li>Single is face value, double is 2x, treble is 3x. Outer bull is 25, inner bull is 50 and counts as a double. There is no treble bull.</li>
                    <li>Maximum turn is 180. The highest checkout is 170 (T20, T20, bull).</li>
                    <li>A dart that bounces out or misses the board scores 0 and still counts as a dart. It is not thrown again.</li>
                </ul>
            </details>
            <details>
                <summary>Finishing and busts</summary>
                <ul>
                    <li>With double out, the dart that reaches zero must be a double (the bull counts). Double/triple out: a double or a triple. Straight out: any dart.</li>
                    <li>It is a <b>bust</b> if a turn goes below zero, leaves 1 (double or double/triple out), or reaches zero without a valid finishing dart. The whole turn is void, your score goes back to what it was before the turn, and the darts you have not thrown are skipped.</li>
                    <li>Once someone checks out, the remaining darts are not thrown.</li>
                    <li>With double out, you can't finish 169, 168, 166, 165, 163, 162 or 159 in three darts, or anything over 170.</li>
                    <li>No three darts can score 163, 166, 169, 172, 173, 175, 176, 178 or 179, or anything over 180.</li>
                </ul>
            </details>
            <details>
                <summary>Rule variants on this page</summary>
                <ul>
                    <li><b>Straight in</b> (usual): every dart scores. <b>Double in</b>: nothing scores until a double (or bull) lands, often used with 301. <b>Double/triple in</b>: a double or triple opens. Darts before you are in score 0.</li>
                    <li><b>Double out</b> (PDC, WDF and most leagues), <b>double/triple out</b>, or <b>straight out</b> (beginners and children: only going below zero busts).</li>
                    <li><b>50/50 bull</b>: some pubs score the outer bull 50 too, with only the inner bull counting as a double.</li>
                    <li><b>First to</b> legs sets the match length. Sets are not scored here.</li>
                    <li>Bounce-outs, a dart sticking in another dart and darts thrown out of turn are settled by your league or house rules. Enter what the dart scored, using Miss for no score.</li>
                </ul>
                <p class="muted">Full notes, rule sets (PDC, WDF/BDO, leagues) and sources are in the <a href="https://github.com/Craftyplayz/Website/blob/main/docs/501.md" target="_blank" rel="noopener">rules document</a>.</p>
            </details>
        </section>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
