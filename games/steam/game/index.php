<?php
$site_page = ['config' => true, 'legacy' => true];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
// appid goes into API URLs, so digits only
$game = preg_replace('/\D/', '', isset($_GET['game']) ? $_GET['game'] : '');
$url = 'http://api.steampowered.com/ISteamUserStats/GetSchemaForGame/v2/?key=' . $steamkey . '&appid=' . $game . '&format=json&include_appinfo=1';
$data = $game !== '' ? json_decode(@file_get_contents($url), true) : null;
$gametitle = isset($data['game']['gameName']) ? $data['game']['gameName'] : (isset($data['game']['name']) ? $data['game']['name'] : 'Steam Game');
$site_page['title'] = $gametitle . ' Stats';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <link rel="stylesheet" href="/assets/css/legacy.css">
    <link rel="stylesheet" href="/assets/css/pages/steam.css">
</head>

<body>

    <?php site_header(); ?>
    <main class="page page-legacy" id="main">

    <?php
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
    </main>
    <?php site_footer(); ?>
</body>

</html>