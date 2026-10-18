<?php require __DIR__ . '/guide.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>

    <main class="page ygo" id="main">
        <?php ygo_top('A progression series in EDOPro: everyone starts with the first pack and unlocks the rest one set at a time.'); ?>

        <section class="card section">
            <h2>First duel</h2>
            <ol>
                <li>Go to <a href="https://www.ygoprog.com/PackOpener">ygoprog.com/PackOpener</a> and make an account to save your card list.</li>
                <li>Select 12 of the first pack (Legend of Blue Eyes), then open them all.</li>
                <li>Next to "Save to Binder", click <b>New</b>, name it whatever you want, then click <b>Create</b>.</li>
                <li>Click <b>Save</b> to save your card list.</li>
                <li>At the top of the page, go to the <b>Binders</b> tab.</li>
                <li>Click <b>Select Binder</b> and select the binder you just made.</li>
                <li>Click <b>Export</b>, then <b>Export as banlist</b>.</li>
                <li>Put the downloaded file into the <code>ProjectIgnis\lflists</code> folder.</li>
                <li>Restart EDOPro, then go to <b>Decks</b>.</li>
                <li>Click <b>Ban List</b> and select the banlist you just made.</li>
                <li>Select <b>Limit</b> and set it to "Allowed".</li>
                <li>Make your deck from the list of cards available.</li>
                <li>Start your first duel.</li>
            </ol>
        </section>

        <?php ygo_trade(); ?>

        <p><a href="/games/yugioh/next-packs/">Next: opening the next packs →</a></p>
    </main>

    <?php site_footer(); ?>
</body>

</html>
