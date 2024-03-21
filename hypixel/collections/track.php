<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hypixel Collection</title>
    <meta http-equiv="refresh" content="60">
    <meta http-equiv="cache-control" content="no-cache" />
    <link rel="stylesheet" href="/css/hypixel/collection.css">
</head>

<body>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>
    <div class="title">
        <?php

$profileIds = array(
    '22159541fde841e6a104593e1cb3a456',
    '39ac5666450b4635873573217a72f143',
    'dd5b7e595e7846a983d11f9cafa0cd42',
    'faccdbb77a1f4ed6a5d442dca531089b',
    '8fc496c53ce64a939b377d8d1af1f1cb'
);

$trackedItem = $_GET['item'];
$itemName = $_GET['name'];

echo '<table style="width: 10%;">';

// Check if the profile parameter is set to 'ironman'
if ($_GET['profile'] == 'ironman') {
    foreach ($profileIds as $key => $profileId) {
        $profileUrl = 'https://api.hypixel.net/v2/skyblock/profiles?key=' . $hypixelkeyapp . '&uuid=' . $profileId;
        $playerdata = json_decode(file_get_contents($profileUrl), true);

        foreach ($playerdata['profiles'] as $profile) {
            $selectedName = ($key == 2) ? 'Banana' : 'Blueberry';

            if ($profile['cute_name'] == $selectedName) {
                $collected = shortenNumber($profile['members'][$profileId]['collection'][$trackedItem] ?? 0);

                $data[] = [
                    'player_id' => $profile['members'][$profileId]['player_id'],
                    'collected' => $collected,
                    'total' => $profile['members'][$profileId]['collection'][$trackedItem]
                ];
            }
        }
    }
} else {
    foreach ($profileIds as $key => $profileId) {
        $profileUrl = 'https://api.hypixel.net/v2/skyblock/profiles?key=' . $hypixelkeyapp . '&uuid=' . $profileId;
        $playerdata = json_decode(file_get_contents($profileUrl), true);

        foreach ($playerdata['profiles'] as $profile) {
            if ($profile['cute_name'] == 'Mango') {
                $collected = shortenNumber($profile['members'][$profileId]['collection'][$trackedItem] ?? 0);

                $data[] = [
                    'player_id' => $profile['members'][$profileId]['player_id'],
                    'collected' => $collected,
                    'total' => $profile['members'][$profileId]['collection'][$trackedItem]
                ];
            }
        }
    }
}

        usort($data, function ($a, $b) {
            return $b['total'] - $a['total'];
        });

        // Display the sorted data
        foreach ($data as $item) {
            echo '<tr>';
            echo '<td><img style="height: 30px;" src="https://www.mc-heads.net/head/' . $item['player_id'] . '/left"></td>';
            echo '<td><span title="' . $item['total'] . '">' . $item['collected'] . '</span></td>';
            echo '</tr>';
            $totalTrackedItems += $item['total'];
        }

        echo '</table>';
        echo $itemName . ' Collection: <span title="' . $totalTrackedItems . '">' . shortenNumber($totalTrackedItems) . '</span>';

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
        $collectionUrl = 'https://api.hypixel.net/v2/resources/skyblock/collections';
        $collectionData = json_decode(file_get_contents($collectionUrl), true);

        foreach ($collectionData['collections'] as $collection) {
            foreach ($collection['items'] as $itemId => $item) {
                if ($itemId == $trackedItem) {

                    $lastTier = end($item['tiers']);
                    $nextTier = null;

                    foreach ($item['tiers'] as $tier) {
                        if ($totalTrackedItems < $tier['amountRequired']) {
                            $nextTier = $tier;
                            break;
                        }
                    }
                    $amountRequiredLastTier = shortenNumber($lastTier['amountRequired']);
                    $percentageToLastTier = number_format(($totalTrackedItems / $lastTier['amountRequired']) * 100, 1);
                    if ($nextTier !== $lastTier) {
                        if ($nextTier) {
                            $amountRequired = shortenNumber($nextTier['amountRequired']);
                            $percentageToNextTier = number_format(($totalTrackedItems / $nextTier['amountRequired']) * 100, 1);
                            echo '<p>Tier ' . $nextTier['tier'] . ': ' . $amountRequired . ' (' . $percentageToNextTier . '%)</p>';
                            echo '<progress value="' . $percentageToNextTier . '" max="100"></progress>';
                        }
                    }
                    echo '<p><span title="' . $lastTier['tier'] . '">Final Tier' . '</span>: ' . $amountRequiredLastTier . ' (' . $percentageToLastTier . '%)</p>';
                    echo '<progress value="' . $percentageToLastTier . '" max="100"></progress><br>';
                    if ($trackedItem == "MELON") {
                        echo '<a href="https://www.craftyplayz.com/hypixel/collections/melonwar">The Great Melon War</a><br>';
                    }
                }
            }
        }
        ?>
        <a href="/hypixel/collections/?profile=<?php echo $_GET['profile']; ?>">
        <button id="timer" onmouseover="showBackText()"
            onmouseout="hideBackText()">1:00</button></a>
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
                button.textContent = 'Back';
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