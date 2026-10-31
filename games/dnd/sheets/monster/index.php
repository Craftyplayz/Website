<?php
$site_page = ['components' => true, 'ui' => true, 'css' => ['/assets/css/pages/games-dnd-sheet.css', '/assets/css/pages/games-dnd-monster.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
$sources = json_decode((string)@file_get_contents(__DIR__ . '/monsters.json'), true)['sources'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php // no site header or footer here, like the player sheet: the stat block and its dice use the whole screen (owner's choice) ?>

    <main class="page hb mon" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="hb-sr">Monster Sheet</h1>

        <div class="bar mon-bar">
            <button type="button" class="btn" data-act="new">New monster</button>
            <details class="mon-menu">
                <summary class="btn primary">Import monster</summary>
                <div class="mon-menuw">
                    <button type="button" data-act="find">From the monster list</button>
                    <button type="button" data-act="file">From a file</button>
                </div>
            </details>
            <button type="button" class="btn" data-act="export">Export</button>
        </div>
        <div class="mon-wrap" id="mon"><noscript><p class="err">This sheet needs JavaScript.</p></noscript></div>
        <p class="muted hb-hint">The encounter is saved in this browser only. Use Export to back it up or move it to another device.</p>

        <details class="mon-credit">
            <summary>Monster sources and licences</summary>
            <ul>
                <?php foreach ($sources as $s): ?>
                    <li><a href="<?= h($s['url']) ?>" target="_blank" rel="noopener"><?= h($s['title']) ?></a> (<?= h($s['licence']) ?>). <?= h($s['copyright']) ?></li>
                <?php endforeach; ?>
            </ul>
            <p>Open Game Content is used under the <a href="ogl.txt">Open Game License 1.0a</a>. Monster data from <a href="https://www.dnd5eapi.co/" target="_blank" rel="noopener">dnd5eapi.co</a> and <a href="https://open5e.com/" target="_blank" rel="noopener">Open5e</a>.</p>
        </details>

        <dialog class="dlg mon-dlg" id="mon-dlg" aria-label="Import a monster">
            <h2>Import a monster</h2>
            <input class="field" type="search" id="mon-q" placeholder="Goblin" aria-label="Search monsters by name" autocomplete="off">
            <p class="note" id="mon-status">Loading the monster list...</p>
            <div class="mon-results" id="mon-results"></div>
            <div class="actions"><button type="button" class="btn" data-act="close">Close</button></div>
        </dialog>
        <div id="hb-rolls" aria-live="polite"></div>
        <input type="file" id="mon-file" accept=".json,application/json" hidden>
    </main>

    <script src="<?= h(asset('/games/dnd/sheets/monster/monster.js')) ?>" defer></script>
</body>

</html>
