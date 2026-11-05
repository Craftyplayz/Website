<?php
$site_page = [
    'components' => true,
    'ui' => true,
    'css' => ['/assets/css/pages/tools-fallout-shelter.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
// heavy scripts load on first use, with their cache-busting URLs
$fos_js = [];
foreach (['core', 'model', 'views', 'raw'] as $f) {
    $fos_js[$f] = asset('/tools/fallout-shelter/js/' . $f . '.js');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>

    <main class="page fos" id="main" data-js="<?= h(json_encode($fos_js)) ?>">
        <?= site_breadcrumbs(site_current_path()) ?>

        <section id="fosStart">
            <h1 class="page-title" id="fosTitle">Fallout Shelter Save Editor</h1>
            <p class="page-lede">Open your saves to see the vault as a map and edit it.</p>
            <div id="fosStartMsgs"></div>
            <div class="drop" id="fosDrop">Drop save files here or <button class="btn small" id="fosPick">choose files</button></div>
            <p class="muted fos-small fos-after-drop">Your files stay in this browser and are never uploaded. Saves are in <span class="mono" id="fosPath">%LOCALAPPDATA%\FalloutShelter\</span> <button class="btn small" id="fosCopyPath">Copy path</button></p>
        </section>

        <section id="fosApp" hidden>
            <header class="page-hero">
                <div class="page-hero-body"><div class="grow" id="fosHeroText"><p class="page-lede" id="fosLede"></p></div></div>
                <nav class="page-tabs" id="fosTabs" aria-label="Save sections"></nav>
            </header>
            <div class="stat-row" id="fosStats"></div>
            <div class="bar fos-bar">
                <div class="stabs fos-files" id="fosFiles" hidden></div>
                <input class="field fos-q" id="fosQ" type="search" placeholder="Search  ( / )" autocomplete="off" aria-label="Search this tab">
                <button class="btn small" id="fosUndo" disabled>Undo</button>
                <button class="btn small" id="fosRedo" disabled>Redo</button>
                <button class="btn small" id="fosChanges">Changes <span id="fosChangeN">0</span></button>
                <button class="btn small" id="fosAdd">Add files</button>
                <button class="btn small primary" id="fosDownload" disabled>Download 0 changed files</button>
            </div>
            <div id="fosMsgs"></div>
            <div class="note fos-putback" id="fosPutBack" hidden>
                <b>Put the files back</b>
                <ol>
                    <li>Quit the game.</li>
                    <li>Copy your whole Save Folder somewhere safe.</li>
                    <li>Check each download kept its exact name. Browsers can add "(1)".</li>
                    <li>Replace the file in <span class="mono">%LOCALAPPDATA%\FalloutShelter\</span>.</li>
                    <li>Start the game. If Steam reports a cloud conflict, choose the local files.</li>
                </ol>
            </div>
            <div id="fosView"></div>
        </section>

        <p class="muted fos-small fos-credit">Tested on the Steam Windows version only. Fallout Shelter and its artwork belong to Bethesda. This tool is not affiliated with Bethesda.</p>

        <input type="file" id="fosFile" multiple hidden>
        <aside class="drawer" id="fosDrawer" aria-label="Details" aria-hidden="true" tabindex="-1">
            <button class="close" id="fosDrawerClose" aria-label="Close" title="Close">&times;</button>
            <div id="fosDetail"></div>
        </aside>
        <dialog class="dlg fos-dlg" id="fosDlg"></dialog>
    </main>

    <?php site_footer(null, false); ?>
    <script src="<?= h(asset('/tools/fallout-shelter/js/app.js')) ?>" defer></script>
</body>

</html>
