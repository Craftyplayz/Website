<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Steam Stats</title>
    <link rel="stylesheet" href="/css/global.css">
    <link rel="stylesheet" href="/css/steamnew.css">
    <!-- <meta http-equiv="refresh" content="300"> -->
</head>

<body>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>

    <?php
    // Construct the API URL
    $url = 'http://api.steampowered.com/IPlayerService/GetOwnedGames/v0001/?key=' . $steamkey . '&steamid=' . $steamid . '&format=json&include_appinfo=1';
    /* 
    Retrieve the JSON data from the API 
    and decode it into an associative array
    */
    $json = file_get_contents($url);
    $data = json_decode($json, true);

    // Get the 'games' array from the response data
    $games = $data['response']['games'];
    $totalgames = $data['response']['game_count'];

    // Sort the games based on playtime in descending order
    usort($games, function ($a, $b) {
        return $b['playtime_forever'] <=> $a['playtime_forever'];
    });
    ?>

    <div class="title">
        <h1>Work in progress</h1>
        <h2>
            <?php echo $totalgames; ?> Owned Games
        </h2>
    </div>
    <?php
    // Iterate through each game
    foreach ($games as $game) {
        // Skip games with playtime less than 1 hour
        if ($game['playtime_forever'] < 60) {
            continue;
        }

        // Skip games with specific app IDs
        $skip = [730, 682130, 469820, 1085750, 459820, 1463120];
        if (in_array($game['appid'], $skip)) {
            continue;
        }

        $gamenewUrl = "/steam/gamenew/" . $game['appid'];

        $lastPlayedTimestamp = $game['rtime_last_played'];

        // Calculate the number of days since the game was last played
        $daysSinceLastPlayed = round((time() - $lastPlayedTimestamp) / (60 * 60 * 24));

        $currentYear = date("Y");

        // Format the last played time
        $lastPlayedTime = date("h:i A M jS", $lastPlayedTimestamp);
        if (date("Y", $lastPlayedTimestamp) != $currentYear) {
            $lastPlayedTime .= " " . date("Y", $lastPlayedTimestamp);
        }

        // Determine the string representation of the last played time
        if ($daysSinceLastPlayed <= 0) {
            $lastPlayed = 'Today';
        } elseif ($daysSinceLastPlayed == 1) {
            $lastPlayed = 'Yesterday';
        } else {
            $lastPlayed = $daysSinceLastPlayed . ' days ago';
        }

        $gamename = $game['name'];

        // Generate the URL for the game's image
        $imageSrc = 'https://cdn.cloudflare.steamstatic.com/steam/apps/' . $game['appid'] . '/header.jpg';

        // Use a different image URL for mobile devices
        if (isMobile()) {
            $imageSrc = 'https://cdn.cloudflare.steamstatic.com/steam/apps/' . $game['appid'] . '/library_600x900.jpg';
        }

        $achievedAchievements = 0;
        $totalAchievements = 0;
        $percentage = 0;

        $jsonFilePath = $_SERVER['DOCUMENT_ROOT'] . '/steam/data/achieved/count.json';

        if (file_exists($jsonFilePath)) {
            $jsonData = file_get_contents($jsonFilePath);
            $data = json_decode($jsonData, true);

            if ($data) {
                foreach ($data['gameid'] as $gameId => $achievements) {
                    if ($gameId == $game['appid']) {
                        $achievedAchievements = $achievements['achievedAchievements'];
                        $totalAchievements = $achievements['totalAchievements'];
                        $percentage = round(($achievedAchievements / $totalAchievements) * 100);
                    } else {
                        continue;
                    }
                }
            }
        } else {
            echo "JSON file not found at the specified path.";
        }


        echo '<div class="games">' . PHP_EOL;
        echo '    <a href="/steam/newgame/' . $game['appid'] . '"><img class="banner" src="' . $imageSrc . '"></a>' . PHP_EOL;
        echo '        <a href="/steam/newgame/' . $game['appid'] . '" style="width: 50%;"><p class="gamename">' . $gamename . '</p></a>' . PHP_EOL;
        echo '    <div class="info">' . PHP_EOL;
        echo '        <div class="item">' . PHP_EOL;
        echo '            <p>Playtime</p>' . PHP_EOL;
        echo '            <p>' . round($game['playtime_forever'] / 60) . ' hours</p>' . PHP_EOL;
        echo '        </div>' . PHP_EOL;
        echo '        <div class="item">' . PHP_EOL;
        echo '            <p>Last Played</p>' . PHP_EOL;
        echo '            <p title="' . $lastPlayedTime . '">' . $lastPlayed . '</p>' . PHP_EOL;
        echo '        </div>' . PHP_EOL;
        echo '        <div class="item">' . PHP_EOL;
        echo '            <p>Achievements ' . $achievedAchievements . '/' . $totalAchievements . ' (' . $percentage . '%)</p>' . PHP_EOL;
        echo '            <progress value="' . $percentage . '" max="100"></progress>' . PHP_EOL;
        echo '        </div>' . PHP_EOL;
        echo '        <div class="item stats">' . PHP_EOL;
        echo '            <a href="/steam/newgame/' . $game['appid'] . '"><button>Stats</button></a>' . PHP_EOL;
        echo '        </div>' . PHP_EOL;
        echo '    </div>' . PHP_EOL;
        echo '</div>' . PHP_EOL;
    }

    function isMobile()
    {
        return preg_match('/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i', $_SERVER["HTTP_USER_AGENT"]);
    }

    ?>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>
</body>

</html>