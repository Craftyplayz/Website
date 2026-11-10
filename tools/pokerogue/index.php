<?php
$site_page = [
    'components' => true,
    'ui' => true,
    'css' => ['/assets/css/pages/tools-pokerogue.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <link rel="preconnect" href="https://raw.githubusercontent.com">
</head>

<body>
    <?php site_header(); ?>

    <main class="page prg" id="main" data-data="<?= h(asset('/tools/pokerogue/data.json')) ?>">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">PokeRogue Save Editor</h1>
        <p class="page-lede">Open a PokeRogue save to see and edit your starters, candies, eggs and vouchers.</p>

        <section id="prgStart">
            <div class="drop" id="prgDrop">Drop a .prsv save here or <button class="btn small" id="prgPick">choose a file</button></div>
            <p class="muted prg-small">Get the file in PokeRogue from Menu → Manage Data → Export Data. Your save stays in this browser and is never uploaded.</p>
        </section>

        <section id="prgApp" hidden>
            <div class="stat-row" id="prgStats"></div>
            <div class="bar prg-bar">
                <input class="field prg-q" id="prgQ" type="search" placeholder="Search  ( / )" autocomplete="off" aria-label="Search Pokémon">
                <div class="stabs" id="prgFilter">
                    <button class="on" data-f="all">All</button><button data-f="caught">Caught</button><button data-f="missing">Not caught</button><button data-f="shiny">Shiny</button>
                </div>
                <span class="muted" id="prgCount"></span>
                <button class="btn small" id="prgOpen">Open another</button>
                <button class="btn small primary" id="prgDownload">Download save</button>
            </div>
            <div class="note prg-putback" id="prgPutBack" hidden>
                <b>Put it back:</b> in PokeRogue open Menu → Manage Data → Import Data and pick the downloaded file. Keep your original file as a backup. Logged-in saves sync to the server, so the game replaces your account data.
            </div>
            <div class="prg-grid" id="prgGrid"></div>
            <p class="prg-more"><button class="btn" id="prgMore" hidden>Show more</button></p>

            <div class="grid prg-cards">
                <section class="card">
                    <h2>Vouchers</h2>
                    <form class="form" id="prgVouchers"></form>
                </section>
                <section class="card">
                    <h2>Eggs <span class="n" id="prgEggN"></span></h2>
                    <p class="muted prg-small">Waves left until each egg hatches. Set 1 to hatch after your next wave.</p>
                    <div class="rows prg-eggs" id="prgEggs"></div>
                </section>
            </div>

            <details class="prg-raw">
                <summary>Raw save data</summary>
                <pre class="tree prg-pre" id="prgRaw"></pre>
            </details>
        </section>

        <input type="file" id="prgFile" accept=".prsv,.json,.txt" hidden>
        <aside class="drawer" id="prgDrawer" aria-label="Pokémon details" aria-hidden="true" tabindex="-1">
            <button class="close" id="prgClose" aria-label="Close" title="Close">&times;</button>
            <div id="prgDetail"></div>
        </aside>
        <dialog class="dlg" id="prgEdit" aria-label="Edit"></dialog>

        <p class="muted prg-small">Names and sprites from PokeAPI; egg moves from the PokeRogue source. PokeRogue is made by Pagefault Games; this tool isn't affiliated with it.</p>
    </main>

    <?php site_footer(null, false); ?>
    <script src="<?= h(asset('/tools/pokerogue/prsv.js')) ?>" defer></script>
    <script src="<?= h(asset('/tools/pokerogue/app.js')) ?>" defer></script>
</body>

</html>
