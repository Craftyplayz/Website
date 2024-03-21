<!DOCTYPE html>
<html lang="en">

<head>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>
        <?php
        $game = $_GET['game'];
        $url = 'http://api.steampowered.com/ISteamUserStats/GetSchemaForGame/v2/?key=' . $steamkey . '&appid=' . $game . '&format=json&include_appinfo=1';
        /* list all data */
        $json = file_get_contents($url);
        $data = json_decode($json, true);
        $gametitle = $data['game']['name'];
        /* echo $gametitle Stats */
        echo $gametitle . ' Stats';
        ?>
    </title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/css/global.css">
    <link rel="stylesheet" href="/css/steam.css">
</head>

<body>

    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    ?>

    <?php
    $game = $_GET['game'];
    $url = 'https://store.steampowered.com/api/appdetails?appids=' . $game;
    $json = file_get_contents($url);
    $data = json_decode($json, true);
    $gameinfo = $data[$game]['data'];
    echo '<div class="info">';
    echo '<a href="' . $gameinfo['website'] . '"><h1>' . $gameinfo['name'] . '</h1></a>';
    echo '<h2>' . $gameinfo['short_description'] . '</h2>';
    echo '<h3>Release Date: ' . $gameinfo['release_date']['date'] . '</h3>';
    echo '<h3>Price: £' . $gameinfo['price_overview']['final'] / 100 . '</h3>';
    echo '<h3>Metacritic Score: ' . $gameinfo['metacritic']['score'] . '%</h3>';
    echo '<h3>Metacritic Link: <a href="' . $gameinfo['metacritic']['url'] . '">' . $gameinfo['metacritic']['url'] . '</a></h3>';
    echo '<h3>Steam Link: <a href="https://store.steampowered.com/app/' . $game . '">' . $gameinfo['name'] . '</a></h3>';
    echo '</div>';
    ?>

    <?php
    $game = $_GET['game'];
    $urla = 'http://api.steampowered.com/ISteamUserStats/GetPlayerAchievements/v0001/?appid=' . $game . '&key=' . $steamkey . '&steamid=' . $steamid . '&format=json&l=en';
    /* list all data */
    $json = file_get_contents($urla);
    $data = json_decode($json, true);
    $achievementsdata = $data['playerstats']['achievements'];
    echo '<table>';
    foreach ($achievementsdata as $achievement) {
        echo '<tr>';
        echo '<td>' . $achievement['name'] . '</td>';
        if ($achievement['achieved'] == 1) {
            echo '<td style="color: green;">Achieved</td>';
            echo '<td>' . $achievement['description'] . '</td>';
            /* echo time since unlock */
            $time = time() - $achievement['unlocktime'];
            $days = floor($time / 86400);
            $hours = floor(($time % 86400) / 3600);
            echo '<td>';
            if ($days > 0) {
                echo $days . ' days ';
            }
            if ($hours > 0) {
                echo $hours . ' hours ';
            }
            echo 'ago</td>';
        } else {
            echo '<td style="color: red;">Not Achieved</td>';
            echo '<td>' . $achievement['description'] . '</td>';
            echo '<td></td>';
        }
        echo '</tr>';
    }
    echo '</table>';
    ?>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>
</body>

</html>