<?php
$site_page = [
    'components' => true,
    'ui' => true,
    'css' => ['/assets/css/pages/games-pubgames-9-ball.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <script src="<?= h(asset('/games/pub-games/9-ball/9ball.js')) ?>" defer></script>
</head>

<body>
    <?php site_header(); ?>

    <main class="page nb" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">9-Ball Pool</h1>
        <p class="page-lede">Track a match of 9-ball on one phone: shots, fouls, push-outs and racks.</p>

        <!-- Setup -->
        <form class="card form form-card nb-setup" novalidate>
            <h2>New match</h2>
            <div class="cols">
                <label>Player 1 <input class="field" name="p1" maxlength="20" value="Player 1" autocomplete="off"></label>
                <label>Player 2 <input class="field" name="p2" maxlength="20" value="Player 2" autocomplete="off"></label>
            </div>
            <div class="cols">
                <label>Race to (racks) <input class="field" name="target" type="number" inputmode="numeric" min="1" max="99" value="7"></label>
                <label>First break
                    <select class="field" name="first"><option value="1">Player 1</option><option value="2">Player 2</option></select>
                </label>
            </div>
            <fieldset>
                <legend>Who breaks next</legend>
                <label class="check"><input type="radio" name="breakFmt" value="alternate" checked> Alternate breaks</label>
                <label class="check"><input type="radio" name="breakFmt" value="winner"> Winner breaks</label>
            </fieldset>
            <label class="check"><input type="checkbox" name="threeFoul" checked> Three-foul rule (three fouls in a row lose the rack)</label>
            <label class="check"><input type="checkbox" name="pushOut" checked> Allow a push-out after the break</label>
            <details class="nb-variants">
                <summary>Rule variations</summary>
                <div class="nb-var-body">
                    <label>9 on the break
                        <select class="field" name="nineBreak"><option value="win">Wins the rack (WPA)</option><option value="spot">Is spotted, does not win</option></select>
                    </label>
                    <label>Ball in hand after a foul
                        <select class="field" name="bih"><option value="anywhere">Anywhere (WPA)</option><option value="line">Behind the head string</option></select>
                    </label>
                    <label>Fouls that count
                        <select class="field" name="foulsMode"><option value="all">All fouls (WPA)</option><option value="cue">Cue-ball fouls only (scratch, cue ball off the table)</option></select>
                    </label>
                    <label class="check"><input type="checkbox" name="callNine"> Call the 9 (an uncalled 9 is spotted)</label>
                    <label class="check"><input type="checkbox" name="threePoint"> Pro 3-point break (three balls potted or past the head string)</label>
                </div>
            </details>
            <div class="actions">
                <button class="btn primary" type="submit">Start match</button>
                <button class="btn" type="button" data-act="cancel" hidden>Back to the match</button>
            </div>
        </form>

        <!-- Match, drawn by 9ball.js -->
        <div class="nb-play" hidden></div>

        <details class="card nb-rules">
            <summary>Rules of 9-ball (WPA)</summary>
            <h3>Racking and breaking</h3>
            <ul>
                <li>Diamond rack with the 1 at the apex and the 9 in the centre. The cue ball starts in hand behind the head string and must hit the 1 first.</li>
                <li>A legal break pots a ball or drives at least four balls to a rail. Anything else is a foul.</li>
                <li>The 9 potted on a legal break wins the rack. If the break was a foul, the 9 is spotted instead.</li>
                <li>The breaker keeps shooting if a ball goes down. Players alternate breaks, or the winner breaks, depending on the event.</li>
            </ul>
            <h3>Shots</h3>
            <ul>
                <li>The cue ball must hit the lowest-numbered ball on the table first, then a ball must be potted or touch a rail.</li>
                <li>After a legal hit any ball can go down. The 9 wins the rack on any legal shot, including a combination or carom.</li>
                <li>If nothing is potted the turn passes, with no ball in hand.</li>
            </ul>
            <h3>Push-out</h3>
            <ul>
                <li>Allowed only on the shot straight after the break, and it must be announced first.</li>
                <li>The cue ball need not hit anything. The opponent then shoots from there or passes the shot back.</li>
                <li>A 9 potted on a push-out is spotted.</li>
            </ul>
            <h3>Fouls</h3>
            <ul>
                <li>Scratch, wrong ball first, no rail after contact, ball off the table, push or double hit, touching a ball, feet off the floor.</li>
                <li>The opponent gets ball in hand anywhere. Balls potted on a foul stay down, but the 9 is spotted.</li>
                <li>Three fouls in a row by one player lose the rack. A warning is due after the second.</li>
            </ul>
            <h3>House rules</h3>
            <ul>
                <li>Bars often play slop (luck counts), winner breaks, ball in hand behind the line and no push-out, and may make you call the 9. The match options above cover these.</li>
            </ul>
            <p class="muted">Full notes, rule sets (Matchroom, APA, BCA, VNEA, bar rules) and sources are in <code>docs/9-ball.md</code> in the site repository.</p>
        </details>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
