<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/php/steam.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $gametitle; ?> Stats</title>
    <link rel="stylesheet" href="/css/steamnew.css">
</head>
<body>

<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
?>

<div class="title">
    <h1><?php echo $gametitle; ?></h1>
</div>


<?php

// Define the URL to fetch the data from
$url = 'https://api.steampowered.com/ISteamUserStats/GetSchemaForGame/v2/?appid=' . $gameid . '&key=' . $steamkey;
// Fetch the data from the URL
$output = file_get_contents($url);
$data = json_decode($output, true);


// Define the filename to save the data to
$filename = $_SERVER['DOCUMENT_ROOT'] . '/steam/data/achievements/' . $gameid . '.json';

// Create the directory if it doesn't exist
if (!is_dir(dirname($filename))) {
    mkdir(dirname($filename), 0777, true);
}

// Open the file for writing
$file = fopen($filename, 'w');

// Write the data to the file
fwrite($file, $output);

// Close the file
fclose($file);

// Display a link to the saved data
echo '<a href="/steam/data/achievements/' . $gameid . '.json">' . $gametitle . '</a><br>';
?>

<?php

// Define the URL to fetch the data from
$url = 'https://api.steampowered.com/ISteamUserStats/GetPlayerAchievements/v0001/?key=' . $steamkey . '&appid=' . $gameid . '&steamid=' . $steamid . '&format=json';
// Fetch the data from the URL
$output = file_get_contents($url);

// Define the filename to save the data to
$filename = $_SERVER['DOCUMENT_ROOT'] . '/steam/data/achieved/' . $gameid . '.json';

// Create the directory if it doesn't exist
if (!is_dir(dirname($filename))) {
    mkdir(dirname($filename), 0777, true);
}

// Open the file for writing
$file = fopen($filename, 'w');

// Write the data to the file
fwrite($file, $output);

// Close the file
fclose($file);

// Display a link to the saved data
echo '<a href="/steam/data/achieved/' . $gameid . '.json">' . $gametitle . '</a>';

// Decode the JSON data
$data = json_decode($output, true);

$totalAchievements = 0;
$achievedAchievements = 0;

// Get the total number of achievements
$totalAchievements = count($data['playerstats']['achievements']);

// Get the number of achieved achievements
$achievedAchievements = 0;
foreach ($data['playerstats']['achievements'] as $achievement) {
    if ($achievement['achieved'] == 1) {
        $achievedAchievements++;
    }
}

$jsonFilePath = $_SERVER['DOCUMENT_ROOT'] . '/steam/data/achieved/count.json';

if (file_exists($jsonFilePath)) {
    $jsonData = file_get_contents($jsonFilePath);
    $data = json_decode($jsonData, true);

    $gameId = $gameid; // Replace with the desired game ID









/*     $totalAchievements = $totalAchievements; // Replace with the desired total achievements
    $achievedAchievements = $achievedAchievements; // Replace with the desired achieved achievements
 */







    if (isset($data['gameid'][$gameId])) {
        echo "";
    } else {
        $data['gameid'][$gameId] = [
            'totalAchievements' => $totalAchievements,
            'achievedAchievements' => $achievedAchievements
        ];
        echo "";
    }
    file_put_contents($jsonFilePath, json_encode($data));
}

?>


<div class="title">
    <h2>Achieved</h2>
</div>

<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
?>

</body>
</html>