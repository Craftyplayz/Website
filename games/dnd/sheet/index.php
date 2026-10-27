<?php
$site_page = ['components' => true, 'ui' => true, 'css' => ['/assets/css/pages/games-dnd-sheet.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>

    <main class="page hb" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Homebrew Character Sheet</h1>
        <p class="page-lede">A character sheet you can bend to any homebrew rules. Click a bonus to roll it (Shift for advantage, Ctrl for disadvantage).</p>

        <div class="bar hb-bar" id="hb-bar"></div>
        <div class="hb-sheet" id="hb-sheet"><noscript><p class="err">This sheet needs JavaScript.</p></noscript></div>
        <p class="muted hb-hint">Characters are saved in this browser only. Use Export to back one up or move it to another device.</p>

        <dialog class="dlg hb-dlg" id="hb-dlg" aria-label="Sheet settings"><div id="hb-dlg-body"></div></dialog>
        <input type="file" id="hb-file" accept=".json,application/json" hidden>
        <input type="file" id="hb-artfile" accept="image/*" hidden>
    </main>

    <?php site_footer(null, false); ?>
    <script src="<?= h(asset('/games/dnd/sheet/formula.js')) ?>" defer></script>
    <script src="<?= h(asset('/games/dnd/sheet/sheet.js')) ?>" defer></script>
</body>

</html>
