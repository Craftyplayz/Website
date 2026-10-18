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



<?php


$profileIds = array(
    '22159541fde841e6a104593e1cb3a456',
    '39ac5666450b4635873573217a72f143',
    'dd5b7e595e7846a983d11f9cafa0cd42'
);

foreach ($profileIds as $profileId) {
    $profileUrl = 'https://api.hypixel.net/v2/skyblock/profiles?key=' . $hypixelkeyapp . '&uuid=' . $profileId;
    $playerdata = json_decode(file_get_contents($profileUrl), true);

    foreach ($playerdata['profiles'] as $profile) {
        if ($profile['cute_name'] == 'Mango') {
            foreach ($profile['members'][$profileId]['player_data']['crafted_generators'] as $generator) {
                /* count how many of each generator */
                $processedGeneratorsList[$generator]++;
                echo $generator . '<br>';
            }
        }
    }
}

$json_url = "https://api.hypixel.net/v2/resources/skyblock/items";
$json_data = file_get_contents($json_url);
$data = json_decode($json_data, true);

function getskin($base64Value) {
    $jsonData = json_decode(base64_decode($base64Value), true);
    $skinId = explode('/', $jsonData['textures']['SKIN']['url'])[4];
    return "<img src=\"https://www.mc-heads.net/head/{$skinId}/left\">";
}


echo "<table>";
$processedGeneratorsList = [];
$highestTiersList = [];

function filterAndSortItems($items) {
    $filteredItems = [];
    foreach ($items as $item) {
        if ($item['generator'] == $item['generator']) {
            $filteredItems[] = $item;
        }
    }
    usort($filteredItems, function($a, $b) {
        return $a['generator_tier'] <=> $b['generator_tier'];
    });
    return $filteredItems;
}

$filteredItems = filterAndSortItems($data['items']);

$minionTypes = [];
foreach ($filteredItems as $item) {
    $minionType = $item['generator'];
    $tier = $item['generator_tier'];
    
    if (!isset($minionTypes[$minionType]) || $tier > $minionTypes[$minionType]) {
        $minionTypes[$minionType] = $tier;
    }
}

foreach ($minionTypes as $minionType => $highestTier) {
    echo "<tr>";
    echo "<td>{$minionType}</td>";
    echo "<td>{$highestTier}</td>";
    echo "</tr>";
}

echo "</table>";
?>

<?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>

</body>

</html>