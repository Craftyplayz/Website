<?php
// Cross Quiz: a crossword where every clue is a trivia question from Open Trivia DB.
// PHP builds the puzzle from the seed (generator.php); cross-quiz.js makes it playable.
$site_page = [
    'components' => true,
    'ui' => true,
    'css' => ['/assets/css/pages/games-cross-quiz.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
require __DIR__ . '/generator.php';

$bank = cq_bank();
$o = cq_options($_GET, $bank);
$pool = cq_pool($bank, $o);
$puz = $bank['questions'] ? cq_build($pool, $o) : null;

$cat_names = array_column($bank['categories'], 'name', 'id');
$groups = [];
foreach ($bank['categories'] as $c) $groups[$c['group']][] = $c;
$picked = array_flip($o['c']);
$diff_names = [1 => 'Easy', 2 => 'Medium', 3 => 'Hard'];

$cat_label = !$o['c'] ? 'All categories'
    : (count($o['c']) === 1 ? $cat_names[$o['c'][0]] : count($o['c']) . ' categories');

// What the script needs: the answers (lightly hidden from view-source) and each clue's cells
$boot = null;
if ($puz) {
    $sol = implode('', array_map(fn ($l) => $l ?? '.', $puz['cells']));
    $boot = [
        'key' => $o['seed'] . cq_query($o, false),
        'link' => cq_query($o),
        'fresh' => cq_query($o, false),
        'cols' => $puz['cols'],
        'sol' => base64_encode(strrev($sol)),
        'clues' => array_map(fn ($c) => [
            'id' => $c['n'] . ($c['dir'] ? 'd' : 'a'),
            'n' => $c['n'],
            'dir' => $c['dir'],
            'cells' => $c['cells'],
            'o' => $c['o'],
            'q' => $c['q'],
            'e' => $c['e'],
            'meta' => ($cat_names[$c['c']] ?? '') . ' · ' . ($diff_names[$c['d']] ?? ''),
        ], array_merge($puz['clues'][0], $puz['clues'][1])),
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>

    <main class="page cq" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Cross Quiz</h1>
        <p class="page-lede">A crossword where every clue is a trivia question. Answer it, then write the answer in the grid.</p>

        <form class="cq-opts" method="get" action="./">
            <label>Size
                <select class="field" name="size">
                    <?php foreach (CQ_SIZES as $k => $s): ?>
                        <option value="<?= $k ?>" <?= $k === $o['size'] ? 'selected' : '' ?>><?= h($s['name']) ?> (about <?= $s['clues'] ?> clues)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Difficulty
                <select class="field" name="d">
                    <?php foreach (CQ_DIFFS as $k => $name): ?>
                        <option value="<?= $k ?>" <?= $k === $o['d'] ? 'selected' : '' ?>><?= h($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <details class="cq-cats">
                <summary class="field"><?= h($cat_label) ?></summary>
                <div class="cq-cats-box">
                    <p class="muted">Leave them all clear for every category.</p>
                    <?php foreach ($groups as $group => $cats): ?>
                        <fieldset>
                            <legend><?= h($group) ?></legend>
                            <?php foreach ($cats as $c): ?>
                                <label class="check"><input type="checkbox" name="c[]" value="<?= h($c['id']) ?>" <?= isset($picked[$c['id']]) ? 'checked' : '' ?>> <?= h($c['name']) ?> <small class="muted"><?= number_format($c['n']) ?></small></label>
                            <?php endforeach; ?>
                        </fieldset>
                    <?php endforeach; ?>
                </div>
            </details>
            <button class="btn primary" type="submit">New puzzle</button>
        </form>

        <?php if (!$bank['questions']): ?>
            <p class="err">The question list is missing, so there's no puzzle right now. Try again later.</p>
        <?php elseif (!$puz): ?>
            <p class="err">There aren't enough questions for those choices. Pick more categories or any difficulty.</p>
        <?php else: ?>
            <p class="muted cq-meta"><?= count($boot['clues']) ?> clues · <?= h(CQ_SIZES[$o['size']]['name']) ?> · <?= h(CQ_DIFFS[$o['d']]) ?> · <?= h($cat_label) ?> · Puzzle <span class="mono"><?= h($o['seed']) ?></span></p>

            <div class="cq-play">
                <div class="cq-board">
                    <div class="cq-now" id="cq-now" hidden>
                        <p class="cq-now-q"><b id="cq-now-n"></b> <span id="cq-now-q"></span> <span class="muted" id="cq-now-e"></span></p>
                        <p class="cq-now-meta muted" id="cq-now-meta"></p>
                        <div class="cq-opts-list" id="cq-choices" hidden></div>
                        <div class="actions">
                            <button class="btn small" type="button" data-act="choices">Show options</button>
                            <button class="btn small" type="button" data-act="reveal-letter">Reveal letter</button>
                            <button class="btn small" type="button" data-act="reveal-word">Reveal word</button>
                            <button class="btn small" type="button" data-act="check-word">Check word</button>
                        </div>
                    </div>

                    <div class="cq-grid-wrap">
                        <div class="cq-grid" id="cq-grid" style="--cols: <?= $puz['cols'] ?>; --rows: <?= $puz['rows'] ?>" role="grid" aria-label="Crossword, <?= $puz['cols'] ?> by <?= $puz['rows'] ?> squares">
                            <?php foreach ($puz['cells'] as $i => $l): ?>
                                <?php if ($l === null): ?><span class="cq-x"></span><?php else: ?><span class="cq-c" data-i="<?= $i ?>"><?php if (isset($puz['num'][$i])): ?><small><?= $puz['num'][$i] ?></small><?php endif; ?><b></b></span><?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <input class="cq-input" id="cq-input" type="text" autocomplete="off" autocapitalize="characters" autocorrect="off" spellcheck="false" aria-label="Type a letter" tabindex="-1">
                    </div>

                    <div class="cq-tools actions" id="cq-tools" hidden>
                        <span class="cq-time mono" id="cq-time" title="Time on this puzzle">0:00</span>
                        <button class="btn small" type="button" data-act="check-all">Check puzzle</button>
                        <button class="btn small" type="button" data-act="reveal-all">Reveal puzzle</button>
                        <button class="btn small" type="button" data-act="clear">Clear</button>
                        <button class="btn small" type="button" data-act="copy">Copy link</button>
                        <button class="btn small" type="button" data-act="print">Print</button>
                    </div>
                </div>

                <div class="cq-clues">
                    <?php foreach (['Across', 'Down'] as $dir => $title): ?>
                        <?php if ($puz['clues'][$dir]): ?>
                            <section>
                                <h2 class="sec-h"><?= $title ?></h2>
                                <ol class="cq-list">
                                    <?php foreach ($puz['clues'][$dir] as $c): ?>
                                        <li data-clue="<?= $c['n'] . ($dir ? 'd' : 'a') ?>"><b><?= $c['n'] ?></b> <span><?= h($c['q']) ?> <span class="muted">(<?= h($c['e']) ?>)</span></span></li>
                                    <?php endforeach; ?>
                                </ol>
                            </section>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <dialog class="dlg" id="cq-done">
                <h2>Puzzle solved</h2>
                <p id="cq-done-text"></p>
                <div class="actions">
                    <a class="btn primary" href="./<?= h(cq_query($o, false)) ?>">New puzzle</a>
                    <button class="btn" type="button" data-act="close">Close</button>
                </div>
            </dialog>
        <?php endif; ?>

        <p class="muted cq-credit">Questions from <a href="https://opentdb.com/" target="_blank" rel="noopener">Open Trivia DB</a>, shared under <a href="https://creativecommons.org/licenses/by-sa/4.0/" target="_blank" rel="noopener">CC BY-SA 4.0</a>.</p>
    </main>

    <?php site_footer(null, false); ?>
    <?php if ($boot): ?>
        <script>window.CQ = <?= json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;</script>
        <script src="<?= h(asset('/games/quizzes/cross-quiz/cross-quiz.js')) ?>" defer></script>
    <?php endif; ?>
</body>

</html>
