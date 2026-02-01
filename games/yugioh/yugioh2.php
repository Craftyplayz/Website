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
        <h1>Yugioh Deck Builder Guide</h1>
        <p>Go to Pack Simulator and select 12 of the next pack in the list and open them all</p>
        <p>Once you have finioshed opening the packs click "Save" next to the binder you made to add the new cards to your deck</p>
        <p>Click Binders at the top then click export and export to banlist</p>
        <p> save the file to ProjectIgnis\lflists and Overwrite the old file</p>
        <p>restart EDOPro then go to Decks</p></p>
        <p>Click Ban List and select the banlist you just made</p>
        <p>Now make your deck from the list of cards avaliable</p>
        <p>Start your next Duel</p>
        <br>
        <p>The winner of the duel get to pick a card from the losers list of cards and add it to their deck (even if the loser wasnt playing that card)</p>
        <br>
        <p>Repeat</p>

<div style="display: flex; width: 100%;">
    <div style="width: 50%; padding-right: 10px;">
        <h2>Winner</h2>
        <p>In the binders section under "All Cards" search for the name of the card you picked</p>
        <p>Click the + to add the card to your deck</p>
    </div>

    <div style="width: 5px; background: white;"></div>

    <div style="width: 50%; padding-left: 10px;">
        <h2>Loser</h2>
        <p>In the binders section under "Your Binders" search for the card the winner chose and click the - to remove that card from your deck</p>
    </div>
</div>

<p><br><a href="/Code/yugioh2.php">Next</a></p>

    </div>

    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>

</body>

</html>