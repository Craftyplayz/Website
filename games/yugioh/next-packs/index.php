<?php require dirname(__DIR__) . '/guide.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>

    <main class="page ygo" id="main">
        <?php ygo_top('After each duel, everyone opens the next pack in the list. Repeat until you run out of packs.'); ?>

        <section class="card section">
            <h2>Every round</h2>
            <ol>
                <li>Go to the Pack Simulator, select 12 of the next pack in the list and open them all.</li>
                <li>When you've finished opening the packs, click <b>Save</b> next to the binder you made to add the new cards.</li>
                <li>Click <b>Binders</b> at the top, then <b>Export</b> and <b>Export to banlist</b>.</li>
                <li>Save the file to <code>ProjectIgnis\lflists</code> and overwrite the old file.</li>
                <li>Restart EDOPro, then go to <b>Decks</b>.</li>
                <li>Click <b>Ban List</b> and select the banlist you just made.</li>
                <li>Make your deck from the list of cards available.</li>
                <li>Start your next duel.</li>
            </ol>
        </section>

        <?php ygo_trade(); ?>

        <p class="muted">Then repeat from the top for the next pack.</p>
    </main>

    <?php site_footer(); ?>
</body>

</html>
