<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Steam Stats</title>
    <link rel="stylesheet" href="/css/global.css">
    <link rel="stylesheet" href="/css/steam.css">
</head>

<body>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>


    </header>

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

    // Sort the games based on playtime in descending order
    usort($games, function ($a, $b) {
        return $b['playtime_forever'] <=> $a['playtime_forever'];
    });

    // Output the games in a table
    $output = '<table>' . "\n";

    // Iterate through each game
    foreach ($games as $game) {
        // Skip games with playtime less than 600 minutes
        if ($game['playtime_forever'] < 60) {
            continue;
        }

        // Skip games with specific app IDs
        $skip = [730, 682130, 469820, 1085750, 459820, 1463120];
        if (in_array($game['appid'], $skip)) {
            continue;
        }


        $output .= '<tr>' . "\n";
        $output .= '<td>';
        $output .= '<img src="http://media.steampowered.com/steamcommunity/public/images/apps/' . $game['appid'] . '/' . $game['img_icon_url'] . '.jpg">';
        $output .= '</td>';
        $output .= '<td>';
        $output .= '<a class="link" href="https://craftyplayz.com/steam/game?game=' . $game['appid'] . '">' . $game['name'] . '</a>';
        $output .= '</td>';
        $output .= '<td>';
        if ($game['playtime_2weeks'] > 60) {
            $output .= round($game['playtime_forever'] / 60) . ' hours total / ' . round($game['playtime_2weeks'] / 60) . ' hours last 2 weeks';
        } else {
            $output .= round($game['playtime_forever'] / 60) . ' hours total';
        }
        $output .= '</td>';
        $output .= '<td>';
        $lastPlayed = $game['rtime_last_played'];
        $daysSinceLastPlayed = round((time() - $lastPlayed) / (60 * 60 * 24));
        if ($daysSinceLastPlayed == 0) {
            $output .= 'Last played today';
        } else {
            $output .= ' Last played ' . $daysSinceLastPlayed . ' days ago';
        }
        $output .= '</td>';
        $output .= '</tr>' . "\n";
    }

    $output .= '</table>';

    // Output the final HTML
    echo $output;
    ?>

    <div class="title">All games under 1 hour are excluded</div>

    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>

</body>

</html>