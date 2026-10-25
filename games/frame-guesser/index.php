<?php
$site_page = [
    'components' => true,
    'ui' => true,
    'css' => ['/assets/css/pages/games-frame-guesser.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
require __DIR__ . '/lib.php';

$index = fg_index(isset($_GET['refresh']));
$titles = $index['titles'];

// What the page needs to build the pickers: names only, no frame lists
$client = array_map(fn ($t) => [
    'name' => $t['name'],
    'groups' => array_map(fn ($g) => ['name' => $g['name'], 'episodes' => array_column($g['episodes'], 'name')], $t['groups']),
], $titles);

function fg_meta(array $t): string
{
    $eps = array_sum(array_map(fn ($g) => count($g['episodes']), $t['groups']));
    if ($t['groups'][0]['name'] === null) {
        return $eps . ' to choose from';
    }
    $n = count($t['groups']);
    return $n . ($n === 1 ? ' season' : ' seasons') . ' · ' . $eps . ($eps === 1 ? ' episode' : ' episodes');
}

// A frame from the middle of the first episode stands for the title on its card
function fg_cover(array $t): string
{
    $g = $t['groups'][0];
    $e = $g['episodes'][0];
    return fg_url(fg_frame_parts(['title' => $t['name'], 'season' => $g['name'], 'episode' => $e['name'], 'frame' => $e['frames'][intdiv(count($e['frames']), 2)]]));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <link rel="preconnect" href="https://assets.craftyplayz.com">
    <script src="<?= h(asset('/games/frame-guesser/frame-guesser.js')) ?>" defer></script>
</head>

<body>
    <?php site_header(); ?>

    <main class="page fg" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Frame Guesser</h1>
        <p class="page-lede">See one still frame from a show or film series and name the episode it comes from.</p>

        <!-- Pick titles -->
        <section class="fg-screen" id="seriesScreen">
            <?php if ($index['error']): ?>
                <p class="err">Couldn't reach the frame library. Try again in a few minutes.</p>
            <?php elseif (!$titles): ?>
                <p class="note">There's nothing to play yet. Each show needs its own folder of frames, laid out as Show / Season 1 / Episode 1 or Series / Film.</p>
            <?php else: ?>
                <?php if ($index['stale']): ?><p class="note">Showing the list of titles from <?= h(gmdate('j M Y', $index['built'])) ?>. The frame library couldn't be reached to update it.</p><?php endif; ?>
                <h2 class="sec-h">Choose what to play <span class="n" id="pickCount"><?= count($titles) === 1 ? '1 title' : count($titles) . ' titles' ?></span></h2>
                <div class="fg-titles" id="titleGrid">
                    <?php foreach ($titles as $i => $t): ?>
                        <button class="fg-title" type="button" aria-pressed="<?= count($titles) === 1 ? 'true' : 'false' ?>" data-name="<?= h($t['name']) ?>">
                            <img src="<?= h(fg_cover($t)) ?>" alt="" width="320" height="180"<?= $i >= 6 ? ' loading="lazy"' : '' ?> decoding="async">
                            <span class="fg-title-info"><b><?= h($t['name']) ?></b><small><?= h(fg_meta($t)) ?></small></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="actions fg-actions">
                    <button class="btn primary" type="button" id="toModes"<?= count($titles) === 1 ? '' : ' disabled' ?>>Choose a mode</button>
                    <?php if (count($titles) > 1): ?><button class="btn" type="button" id="pickAll">Select all</button><?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Pick a mode -->
        <section class="fg-screen" id="menuScreen" hidden>
            <button class="btn small" type="button" data-go="series">Change titles</button>
            <h2 class="sec-h">Choose a mode <span class="n" data-set-name></span></h2>
            <div class="fg-modes">
                <button class="fg-mode" type="button" data-mode="normal">
                    <b>Normal</b>
                    <span>Ten frames. Name the exact episode for 3 points, or just the right season for 1.</span>
                    <small>Best <span data-best="normal">0</span></small>
                </button>
                <button class="fg-mode" type="button" data-mode="survival">
                    <b>Survival</b>
                    <span>Keep going until you've missed three times. A miss is any guess that scores nothing.</span>
                    <small>Best <span data-best="survival">0</span></small>
                </button>
                <button class="fg-mode" type="button" data-mode="timed">
                    <b>Timed</b>
                    <span>90 seconds on the clock. Guess as many frames as you can; misses just don't score.</span>
                    <small>Best <span data-best="timed">0</span></small>
                </button>
            </div>
            <div class="fg-player">
                <label>Leaderboard name (optional)
                    <input class="field" id="playerName" maxlength="20" autocomplete="nickname" placeholder="Your name">
                </label>
                <button class="btn" type="button" data-go="board">Leaderboards</button>
            </div>
            <p class="muted fg-rules">Points: the exact episode or film scores 3, the right season but the wrong episode scores 1, anything else scores 0.</p>
        </section>

        <!-- Play -->
        <section class="fg-screen" id="quizScreen" hidden>
            <div class="fg-top">
                <button class="btn small" type="button" data-go="menu">Quit to modes</button>
                <span class="badge" id="modeTag"></span>
            </div>
            <div class="fg-play">
                <div class="fg-frame">
                    <img id="frameImg" alt="A frame from the show. Which episode is it from?" width="1280" height="720" fetchpriority="high">
                    <p class="err fg-frame-err" id="frameErr" hidden>Couldn't load this frame. <button class="btn small" type="button" id="frameRetry">Try again</button></p>
                </div>
                <div class="fg-side">
                    <div class="tiles fg-stats">
                        <div class="tile"><b id="scoreNum">0</b><span>Score</span></div>
                        <div class="tile" id="progressTile"><b id="progressNum">1 / 10</b><span id="progressLbl">Frame</span></div>
                        <div class="tile"><b id="bestNum">0</b><span>Best</span></div>
                    </div>
                    <p class="fg-step" id="stepText" aria-live="polite"></p>
                    <div class="fg-choices" id="choices"></div>
                    <div class="fg-result" id="result" aria-live="polite"></div>
                </div>
            </div>
        </section>

        <!-- Game over -->
        <section class="fg-screen" id="gameoverScreen" hidden>
            <div class="card fg-over">
                <p class="muted" id="overTitle">Game over</p>
                <b class="fg-big" id="overScore">0</b>
                <p class="muted" id="overLbl"></p>
                <p class="badge g" id="overRecord" hidden>New best score</p>
                <div id="overBoard"></div>
                <div class="actions">
                    <button class="btn primary" type="button" data-go="again">Play again</button>
                    <button class="btn" type="button" data-go="board">Leaderboard</button>
                    <button class="btn" type="button" data-go="menu">Change mode</button>
                </div>
            </div>
            <h2 class="sec-h">Your frames</h2>
            <div class="rows fg-rounds" id="overRounds"></div>
        </section>

        <!-- Leaderboards -->
        <section class="fg-screen" id="boardScreen" hidden>
            <button class="btn small" type="button" data-go="menu">Back to modes</button>
            <h2 class="sec-h">Leaderboards <span class="n" data-set-name></span></h2>
            <div class="stabs" id="boardTabs" role="group" aria-label="Game mode"></div>
            <div id="boardBody"></div>
        </section>

        <!-- One leaderboard run, frame by frame -->
        <section class="fg-screen" id="replayScreen" hidden>
            <button class="btn small" type="button" data-go="board">Back to leaderboard</button>
            <h2 class="sec-h" id="replayTitle">Replay</h2>
            <div class="rows fg-rounds" id="replayRounds"></div>
        </section>

        <noscript><p class="err">This game needs JavaScript.</p></noscript>
    </main>

    <script>window.FG = <?= json_encode(['titles' => $client, 'modes' => FG_MODES], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;</script>
    <?php site_footer(null, false); ?>
</body>

</html>
