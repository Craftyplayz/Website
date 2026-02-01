<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Untitled</title>
    <meta http-equiv="refresh" content="-1">
</head>

<body>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>

    <div class="title">
        <h1>Extra Rules</h1>
        <h2>Match Rules (Decide at start and are always in effect)</h2>
        <p>You can only pick your winning card from cards that the loser played during that match</p>
        <p>You can pick 1 Monster card and 1 Spell or Trap card</p>
        <p>You pick one starter deck (yugi, joey, kaiba or pegasus</p>
        <h1>Player Rules</h1>
        <h2>These can be used at any time and are optional (You can only use one of these)</h2>
        <p>Instead of taking a card you can open an additional 2 packs next round instead</p>
        <p>You can take 2 cards from the losers deck BUT the loser can take one from yours (They cant take the same card you took)</p>
        <p>You can pick 10 cards then roll a d10 3 times and take the cards that the numbers land on</p>

</div>

<p><br><a href="/Code/yugioh2.php">Next</a></p>

    </div>

    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>

</body>

</html>