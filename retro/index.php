<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retro Games</title>
    <link rel="stylesheet" href="/css/global.css">
    <link rel="stylesheet" href="/css/steamnew.css">
</head>

<body>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>
<style>
    .gamename {left: 180px;}
    .info {left: 180px;}
    .games {width: 70vw;}
</style>
<?php
$userprofileurl = 'https://retroachievements.org/API/API_GetUserProfile.php?u=CraftyPlayz&y=' . $retrokey . '';
$userprofilejson = file_get_contents($userprofileurl);
$userprofiledata = json_decode($userprofilejson, true);

$recentgamesurl = 'https://retroachievements.org/API/API_GetUserRecentlyPlayedGames.php?u=CraftyPlayz&y='. $retrokey . '&c=50';
$recentgamesjson = file_get_contents($recentgamesurl);
$recentgamesjson = json_decode($recentgamesjson, true);

$completionurl = 'https://retroachievements.org/API/API_GetUserCompletionProgress.php?u=CraftyPlayz&y='. $retrokey . '';
$completionjson = file_get_contents($completionurl);
$completiondata = json_decode($completionjson, true);

// Separate games based on "HighestAwardKind"
$highlightedGames = [];
$regularGames = [];

foreach ($completiondata['Results'] as $game) {
    if (in_array($game['HighestAwardKind'], ['beaten-softcore', 'beaten-hardcore'])) {
        $highlightedGames[] = $game;
    } else {
        $regularGames[] = $game;
    }
}

// Function to render game details
function renderGame($game) {
    $achievedAchievements = $game['NumAwarded'];
    $totalAchievements = $game['MaxPossible'];
    $percentage = $totalAchievements > 0 ? round(($achievedAchievements / $totalAchievements) * 100) : 0;

    // Determine which date to display
    $dateToShow = isset($game['HighestAwardKind']) && in_array($game['HighestAwardKind'], ['beaten-softcore', 'beaten-hardcore'])
        ? $game['HighestAwardDate']
        : $game['MostRecentAwardedDate'];
    $dateLabel = isset($game['HighestAwardKind']) && in_array($game['HighestAwardKind'], ['beaten-softcore', 'beaten-hardcore'])
        ? 'Beaten Date'
        : 'Last Achievement Awarded';

    // Convert the date to timestamp
    $timestamp = strtotime($dateToShow);

    // Get relative time (e.g., "27 days ago")
    $relativeTime = human_time_diff($timestamp, time());

    // Format the exact date for the title tag
    $formattedDate = isset($dateToShow) ? date("F j, Y, g:i a", $timestamp) : "N/A";

    // Generate image source URL
    $imageSrc = 'https://retroachievements.org' . $game['ImageIcon'];

    echo '<div class="games">' . PHP_EOL;
    echo '  <a href="/retro/game?game=' . $game['GameID'] . '"><img class="banner" src="' . $imageSrc . '" style="width: 160px; height: 160px; object-fit: cover;"></a>' . PHP_EOL;
    echo '  <a href="/retro/game?game=' . $game['GameID'] . '" style="width: 50%; color: white"><p class="gamename">' . $game['Title'] . '</p></a>' . PHP_EOL;
    echo '  <div class="info">' . PHP_EOL;
    echo '    <div class="item">' . PHP_EOL;
    echo '      <p>Console</p>' . PHP_EOL;
    echo '      <p>' . $game['ConsoleName'] . '</p>' . PHP_EOL;
    echo '    </div>' . PHP_EOL;
    echo '    <div class="item">' . PHP_EOL;
    echo '      <p>' . $dateLabel . '</p>' . PHP_EOL;
    echo '      <p title="' . $formattedDate . '">' . $relativeTime . '</p>' . PHP_EOL;
    echo '    </div>' . PHP_EOL;
    echo '    <div class="item">' . PHP_EOL;
    echo '      <p>Achievements ' . $achievedAchievements . '/' . $totalAchievements . ' (' . $percentage . '%)</p>' . PHP_EOL;
    echo '      <progress value="' . $percentage . '" max="100"></progress>' . PHP_EOL;
    echo '    </div>' . PHP_EOL;
    echo '  </div>' . PHP_EOL;
    echo '</div>' . PHP_EOL;
}

// Helper function to calculate human-readable time difference (in days only)
function human_time_diff($from, $to = null) {
    if (is_null($to)) {
        $to = time();
    }

    // Calculate the difference in days
    $diff = round(($to - $from) / 86400);  // 86400 seconds in a day

    // Return the difference in days, ensuring singular/plural form
    if ($diff == 1) {
        return $diff . ' day ago';  // Singular form for 1 day
    }
    return $diff . ' days ago';  // Plural form for other day counts
}

// Extract last played game and rich presence
$lastPlayedGameID = isset($userprofiledata['LastGameID']) ? $userprofiledata['LastGameID'] : null;
$richPresenceMessage = isset($userprofiledata['RichPresenceMsg']) ? $userprofiledata['RichPresenceMsg'] : 'No active rich presence';

// Fetch the last played game details
$lastPlayedGameDetails = null;
if ($lastPlayedGameID) {
    $lastPlayedGameUrl = 'https://retroachievements.org/API/API_GetGame.php?g=' . $lastPlayedGameID . '&y=' . $retrokey;
    $lastPlayedGameJson = file_get_contents($lastPlayedGameUrl);
    $lastPlayedGameDetails = json_decode($lastPlayedGameJson, true);
}

// Display "Last Played" above the completed games if there is data
if ($lastPlayedGameDetails) {
    echo '<div class="title"><h2>Last Played</h2></div>' . PHP_EOL;
    echo '<div class="last-played">' . PHP_EOL;
    // Render the last played game
    renderGame($lastPlayedGameDetails['Game']);
    echo '  <p>Rich Presence: ' . $richPresenceMessage . '</p>' . PHP_EOL;
    echo '</div>' . PHP_EOL;
}

// Display highlighted games
if (!empty($highlightedGames)) {
    echo '<div class="title"><h2>Completed Games</h2></div>' . PHP_EOL;
    foreach ($highlightedGames as $game) {
        renderGame($game);
    }
}

// Display regular games
if (!empty($regularGames)) {
    echo '<div class="title"><h2>In Progress Games</h2></div>'. PHP_EOL;
    foreach ($regularGames as $game) {
        renderGame($game);
    }
}
?>

    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>
</body>

</html>