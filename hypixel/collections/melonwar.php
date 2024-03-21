<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Great Melon War</title>
    <meta http-equiv="refresh" content="60">
</head>

<body>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>

    <div class="title">

        <!-- https://api.hypixel.net/v2/skyblock/profiles?key=a71a6914-2a6e-4adc-bb02-4836e505dcfd&uuid=faccdbb77a1f4ed6a5d442dca531089b -->
        <?php

        /*             '22159541fde841e6a104593e1cb3a456'
                    'faccdbb77a1f4ed6a5d442dca531089b' */



        /* profile 221 = Mango 22159541fde841e6a104593e1cb3a456
        profile facc = papaya faccdbb77a1f4ed6a5d442dca531089b */


        /* calculate profile 221 */
        $craftyjson = file_get_contents('https://api.hypixel.net/v2/skyblock/profiles?key=a71a6914-2a6e-4adc-bb02-4836e505dcfd&uuid=22159541fde841e6a104593e1cb3a456');
        $craftyLocation = file_get_contents('https://api.hypixel.net/status?key=a71a6914-2a6e-4adc-bb02-4836e505dcfd&uuid=22159541fde841e6a104593e1cb3a456');
        $craftyLocationJson = json_decode($craftyLocation, true);
        $crafty = json_decode($craftyjson, true);
        foreach ($crafty['profiles'] as $profile) {
            if ($profile['cute_name'] == 'Mango') {
                $collectedCrafty = shortenNumber($profile['members']['22159541fde841e6a104593e1cb3a456']['collection']['MELON'] ?? 0);
                echo '<img style="height: 30px;" src="https://www.mc-heads.net/head/22159541-fde8-41e6-a104-593e1cb3a456/left"> ' . $collectedCrafty;
            }
        }
        if ($craftyLocationJson['session']['mode'] == 'garden') {
            echo ' Currently In Garden<br>';
        }
        else {
            echo '<br>';
        }
        $wafflejson = file_get_contents('https://api.hypixel.net/v2/skyblock/profiles?key=a71a6914-2a6e-4adc-bb02-4836e505dcfd&uuid=faccdbb77a1f4ed6a5d442dca531089b');
        $waffleLocation = file_get_contents('https://api.hypixel.net/status?key=a71a6914-2a6e-4adc-bb02-4836e505dcfd&uuid=faccdbb77a1f4ed6a5d442dca531089b');
        $waffleLocationJson = json_decode($waffleLocation, true);
        $waffle = json_decode($wafflejson, true);
        foreach ($waffle['profiles'] as $profile) {
            if ($profile['cute_name'] == 'Papaya') {
                $collectedWaffle = shortenNumber($profile['members']['faccdbb77a1f4ed6a5d442dca531089b']['collection']['MELON'] ?? 0);
                echo '<img style="height: 30px;" src="https://www.mc-heads.net/head/faccdbb77a1f4ed6a5d442dca531089b/left"> ' . $collectedWaffle;
            }
        }
        if ($waffleLocationJson['session']['mode'] == 'garden') {
            echo ' Currently In Garden<br>';
        }
        else {
            echo '<br>';
        }

        // Calculate and echo the difference
        $melonDifference = $collectedCrafty - $collectedWaffle;
        echo "Crafty has {$melonDifference} Million more melons than Waffle<br>";


        function shortenNumber($number)
        {
            if ($number >= 1000) {
                $suffix = ["", "k", "M", "B", "T"];
                $scale = floor(log10($number) / 3);
                $formattedNumber = sprintf('%s%s', number_format($number / pow(10, $scale * 3), 1), $suffix[$scale]);
                if (strpos($formattedNumber, '.0') !== false) {
                    $formattedNumber = str_replace('.0', '', $formattedNumber);
                }
                return $formattedNumber;
            }
            return $number;
        }
        ?>
        <button id="timer" onmouseover="showBackText()"
            onmouseout="hideBackText()">1:00</button>
        <br>
        <script>
            var isHovered = false;

            function startTimer(duration, display) {
                var timer = duration, minutes, seconds;
                setInterval(function () {
                    if (!isHovered) { // Only update the display when not hovered
                        minutes = parseInt(timer / 60, 10);
                        seconds = parseInt(timer % 60, 10);
                        minutes = minutes < 10 ? "0" + minutes : minutes;
                        seconds = seconds < 10 ? "0" + seconds : seconds;
                        display.textContent = minutes.substr(-1) + ":" + seconds;
                        if (--timer < 0) {
                            timer = duration;
                        }
                    }
                }, 1000);
            }

            function showBackText() {
                isHovered = true;

                var button = document.querySelector('#timer');
                button.textContent = 'THIS IS WAR';
            }

            function hideBackText() {
                isHovered = false;
            }

            window.onload = function () {
                var oneMinute = 60 * 1,
                    display = document.querySelector('#timer');
                startTimer(oneMinute, display);
            };
        </script>
    </div>

    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>

</body>

</html>