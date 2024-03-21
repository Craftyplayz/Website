<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hypixel Collection Tracker</title>
    <meta http-equiv="refresh" content="-1">
</head>

<body>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>
    <table>
        <?php


$profileIds = array(
    '22159541fde841e6a104593e1cb3a456',
    '39ac5666450b4635873573217a72f143',
    'dd5b7e595e7846a983d11f9cafa0cd42',
    'faccdbb77a1f4ed6a5d442dca531089b'
);

$profileType = isset($_GET['profile']) ? $_GET['profile'] : 'main';

$totalCollections = 0;
$allCollections = [];

foreach ($profileIds as $key => $profileId) {
    $profileUrl = 'https://api.hypixel.net/v2/skyblock/profiles?key=' . $hypixelkeyapp . '&uuid=' . $profileId;
    $playerdata = json_decode(file_get_contents($profileUrl), true);

    foreach ($playerdata['profiles'] as $profile) {
        if ($profileType == 'main') {
            $selectedName = 'Mango';
        } elseif ($profileType == 'ironman') {
            $selectedName = ($key == 3) ? 'Banana' : 'Blueberry';
        }

        if ($profile['cute_name'] == $selectedName) {
            foreach ($profile['members'][$profileId]['collection'] as $itemId => $item) {
                if (isset($allCollections[$itemId])) {
                    $allCollections[$itemId] += $item;
                } else {
                    $allCollections[$itemId] = $item;
                }
                $totalCollections += $item;
            }
        }
    }
}


        $collectionUrl = 'https://api.hypixel.net/v2/resources/skyblock/collections';
        $jsonData = file_get_contents($collectionUrl);
        $data = json_decode($jsonData, true);
        $categories = ['FARMING', 'MINING', 'COMBAT', 'FORAGING', 'FISHING', 'RIFT'];

        // Sort the collections into categories
        $sortedCollections = [];
        foreach ($allCollections as $itemId => $amount) {
            foreach ($categories as $category) {
                if (isset($data['collections'][$category]['items'][$itemId])) {
                    $sortedCollections[$category][$itemId] = $amount;
                    break;
                }
            }
        }

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

        echo "<div class='title'>Hypixel Collection Tracker<h2>Total: " . shortenNumber($totalCollections) . "</h2>";
        echo "<a href=\"/hypixel/collections/?profile=main\"><button>Main</button></a>";
        echo " ";
        echo "<a href=\"/hypixel/collections/?profile=ironman\"><button>Ironman</button></a>";
        // Echo each collection into a table
        echo "<table>";
        foreach ($sortedCollections as $category => $collections) {
            $items = [];
            foreach ($collections as $itemId => $amount) {
                $tiers = count($data['collections'][$category]['items'][$itemId]['tiers']);
                $maxTierAmount = $data['collections'][$category]['items'][$itemId]['tiers'][$tiers - 1]['amountRequired'];
                $neededAmount = max(0, $maxTierAmount - $amount);
                $textColor = ($neededAmount == 0) ? "green" : "#636262";
                $name = $data['collections'][$category]['items'][$itemId]['name'];
                $items[] = "<a href=\"/hypixel/collections/track?item=$itemId&name=$name&profile=$profileType\"><button style=\"background-color: $textColor;\">$name</button></a>";
            }
            $itemList = implode(', ', $items);
            echo "<tr><td>$category:</td><td>$itemList</td></tr>";
        }
        echo "</table>";
        ?>
    </table>

    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>

</body>

</html>