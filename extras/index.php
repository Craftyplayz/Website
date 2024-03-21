<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crafty Playz</title>
</head>

<body>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>

    <div class="title">
        <h1>Additional projects</h1>
    </div>

    <div class="title left-section">
        <h1>Currently working</h1>
        <div>
            <p><a href="/update/">Fake pc updates and crashes</a></p><br>
            <p><a href="/tests/speedtest">Reading Speed test</a></p><br>
            <p><a href="/extas/rgb.php">RGB Strobe light</a></p><br>
            <p><a href="/extas/chaos.html">Fullscreen Twitch chat</a></p><br>
            <p><a href="/extras/trump">Random Trump Quote</a></p><br>
            <p><a href="/extras/insult">Random Insult</a></p><br>
            <p><a href="/extras/harrypotter/characters">Harry Potter character info</a></p><br>
            <p><a href="/steam/newindex">Improved steam stats showcase</a></p><br>
            <p><a href="/extras/qr">QR Code generator</a></p><br>
            <p><a href="/extras/livelaughlove">Live Laugh Love alternatives</a></p>
            <p><a href="/extras/santa">Secret Santa Generator</a></p><br>
        </div>
    </div>

    <div class="title right-section">
        <h1>Coming soon</h1>
        <div>
            <p>Harry Potter quiz</p><br>
            <a href="https://toneindicators.carrd.co/"><p>Tone indicator guide</p></a><br>
            <a href="https://www.nohello.com"><p>No Hello Guide</p></a><br>
            <p>Pet showcase pages</p><br>
        </div>
    </div>

    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>
</body>

</html>