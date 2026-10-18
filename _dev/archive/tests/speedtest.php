<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crafty Playz</title>
    <link rel="stylesheet" href="/assets/css/legacy.css">
    <script src="/tests/speedtest.js"></script>
</head>

<body>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>

    <style>
        .reading {
            width: 75%;
            margin: 0 auto;
            text-align: justify;
            background-color: peachpuff;
            color: black;
            padding: 10px 20px;
            display: none;
            line-height: 2.5;
        }

        /* if on phone reading width to 90% */
        @media only screen and (max-width: 600px) {
            .reading {
                width: 90%;
            }
        }

        .button {
            background-color: #4CAF50;
            border: none;
            color: white;
            padding: 10px 20px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
            margin: 4px 2px;
            cursor: pointer;
            /* display on ceenter */
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
    </style>
    <button id="start" class="button" onclick="timer(873)">Start</button>
    <p id="time">0:0.0</p>
    <p id="speed">000000 Words Per Minute</p>
    <div class="reading">
        <p id="text">
<br>
        </p>
    </div>
    <button id="stop" class="button" onclick="stop()">Stop</button>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>
</body>

</html>