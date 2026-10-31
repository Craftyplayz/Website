<?php
$site_page = ['components' => true, 'ui' => true, 'css' => ['/assets/css/pages/minecraft-collection-hallway.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

// hallway.json and hallway-recipes.json are built by _dev/scripts/build-collection-hallway.js
$data = json_decode((string)file_get_contents(__DIR__ . '/hallway.json'), true) ?: ['entries' => []];
$data['recipes'] = asset('/minecraft/projects/collection/hallway/hallway-recipes.json');
$n = count($data['entries']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
</head>

<body>
    <?php site_header(); ?>

    <main class="page hw" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Collection Hallway</h1>
        <p class="page-lede">Walk the <?= number_format($n) ?> chests of the <a href="/minecraft/projects/collection/">collection</a> in 3D, find any item and check a schematic or your world save against the layout.</p>

        <div class="bar hw-bar">
            <button type="button" class="btn primary" id="hw-pick">Check a schematic</button>
            <input type="file" id="hw-file" accept=".schem,.litematic,.nbt,.mca" multiple hidden>
            <button type="button" class="btn" id="hw-world">Check a world folder</button>
            <input type="file" id="hw-dir" webkitdirectory hidden>
            <span class="muted" id="hw-checked" hidden></span>
            <button type="button" class="btn small" id="hw-clear" hidden>Clear</button>
            <span class="muted hw-help">A .schem, .litematic or structure .nbt made standing at -636 36 -626, or the world folder (the one with level.dat; save or close the world first). Everything is read in your browser, nothing is uploaded.</span>
        </div>
        <p class="note" id="hw-note" hidden></p>

        <div class="stat-row hw-stats" id="hw-stats" hidden>
            <button type="button" class="stat-card hw-f" data-f="complete" aria-pressed="false"><b data-c="complete">0</b><span><i class="hw-dot complete"></i>Complete</span></button>
            <button type="button" class="stat-card hw-f" data-f="partial" aria-pressed="false"><b data-c="partial">0</b><span><i class="hw-dot partial"></i>Not full</span></button>
            <button type="button" class="stat-card hw-f" data-f="wrong" aria-pressed="false"><b data-c="wrong">0</b><span><i class="hw-dot wrong"></i>Wrong item</span></button>
            <button type="button" class="stat-card hw-f" data-f="missing" aria-pressed="false"><b data-c="missing">0</b><span><i class="hw-dot missing"></i>Missing</span></button>
        </div>

        <div class="hw-view" id="hw-view">
            <canvas id="hw-c" tabindex="0" aria-label="3D view of the collection hallway. Click to look around, WASD to walk."></canvas>
            <div class="hw-hud">
                <div class="hw-search">
                    <input class="field" type="search" id="hw-q" placeholder="Search chests or type a number" aria-label="Search chests" autocomplete="off">
                    <div class="hw-step" id="hw-step" hidden>
                        <button type="button" class="btn small" data-step="-1" aria-label="Previous chest">&lsaquo;</button>
                        <span id="hw-stepn"></span>
                        <button type="button" class="btn small" data-step="1" aria-label="Next chest">&rsaquo;</button>
                    </div>
                    <ul class="hw-res" id="hw-res" role="listbox" aria-label="Matching chests"></ul>
                </div>
                <div class="hw-cross" aria-hidden="true"></div>
                <div class="hw-target" id="hw-target" hidden></div>
                <div class="hw-touch"><button type="button" class="btn" data-key="KeyW" aria-label="Walk forward">&#9650;</button><button type="button" class="btn" data-key="KeyS" aria-label="Walk back">&#9660;</button></div>
            </div>
            <p class="note hw-load" id="hw-load">Loading the 3D view...</p>
        </div>
        <div class="hw-strip">
            <canvas id="hw-map" aria-label="Map of the whole hallway. Click to teleport."></canvas>
            <div class="hw-strip-meta muted"><span>West end, chest 1</span><span id="hw-pos"></span><span>East end, chest <?= number_format($n) ?></span></div>
        </div>
        <p class="muted hw-keys">Click the view to look around. <kbd>W</kbd> <kbd>A</kbd> <kbd>S</kbd> <kbd>D</kbd> walk, <kbd>Space</kbd> and <kbd>Shift</kbd> go up and down, <kbd>Ctrl</kbd> runs, <kbd>Esc</kbd> lets go. Click a chest or its item frame for details.</p>

        <div class="grid hw-lists" id="hw-lists" hidden>
            <section class="card" id="hw-wrong" hidden><h2>Wrong items <span class="n" id="hw-wrong-n"></span></h2><div class="table-wrap tall"><table class="table"><thead><tr><th>Chest</th><th>Holds</th><th class="num">Count</th><th>Belongs in</th></tr></thead><tbody></tbody></table></div></section>
            <section class="card" id="hw-extra" hidden><h2>Chests outside the layout <span class="n" id="hw-extra-n"></span></h2><div class="table-wrap tall"><table class="table"><thead><tr><th>Position</th><th>Holds</th></tr></thead><tbody></tbody></table></div></section>
        </div>
        <noscript><p class="err">The hallway needs JavaScript.</p></noscript>
    </main>

    <aside class="drawer hw-drawer" id="hw-drawer" aria-label="Chest details" tabindex="-1" aria-hidden="true">
        <button type="button" class="close" id="hw-close" aria-label="Close" title="Close">&times;</button>
        <div id="hw-detail"></div>
    </aside>

    <?php site_footer(null, false); ?>
    <script>window.HW = <?= json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?>;</script>
    <script src="<?= h(asset('/minecraft/tools/shulker-calculator/schematic.js')) ?>" defer></script>
    <script src="<?= h(asset('/minecraft/projects/collection/hallway/check.js')) ?>" defer></script>
    <script type="module" src="<?= h(asset('/minecraft/projects/collection/hallway/hallway.js')) ?>"></script>
</body>

</html>
