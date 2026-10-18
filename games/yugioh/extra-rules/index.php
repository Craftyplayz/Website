<?php require dirname(__DIR__) . '/guide.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>

    <main class="page ygo" id="main">
        <?php ygo_top('Optional house rules to make the series more interesting.'); ?>

        <section class="card section">
            <h2>Match rules</h2>
            <p class="muted">Decide these at the start. They're always in effect.</p>
            <ul>
                <li>You can only pick your winning card from cards the loser played during that match.</li>
                <li>You can pick 1 Monster card and 1 Spell or Trap card.</li>
                <li>You pick one starter deck (Yugi, Joey, Kaiba or Pegasus).</li>
            </ul>
        </section>

        <section class="card section">
            <h2>Player rules</h2>
            <p class="muted">Optional, usable at any time. You can only use one of these.</p>
            <ul>
                <li>Instead of taking a card, open 2 extra packs next round.</li>
                <li>Take 2 cards from the loser's deck, but the loser can take one from yours (not the same card you took).</li>
                <li>Pick 10 cards, then roll a d10 three times and take the cards the numbers land on.</li>
            </ul>
        </section>
    </main>

    <?php site_footer(); ?>
</body>

</html>
