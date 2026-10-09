<?php
// File path to the JSON file
$jsonFilePath = __DIR__ . '/thegame.json';

// Load data from the JSON file
if (!file_exists($jsonFilePath)) {
    $data = [
        'last_loss_time' => time(), // Initialize with current time
        'best_time' => 0 // Default best time
    ];
    file_put_contents($jsonFilePath, json_encode($data));
} else {
    $data = json_decode(file_get_contents($jsonFilePath), true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $timeSinceLastLoss = time() - $data['last_loss_time']; // Calculate time since last loss before updating
    $data['last_loss_time'] = time(); // Update last loss time

    // Update best time if current time is better
    if ($timeSinceLastLoss > $data['best_time']) {
        $data['best_time'] = $timeSinceLastLoss;
    }

    // Save updated data back to the JSON file
    file_put_contents($jsonFilePath, json_encode($data));

    // Refresh the page to show updated times
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}


// Calculate current time since last loss
$currentTime = time() - $data['last_loss_time'];

function formatTime($seconds)
{
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;

    $timeParts = [];
    if ($days > 0) {
        $timeParts[] = "{$days}d";
    }
    if ($hours > 0 || !empty($timeParts)) { // Include hours if there are days
        $timeParts[] = "{$hours}h";
    }
    if ($minutes > 0 || !empty($timeParts)) { // Include minutes if there are hours/days
        $timeParts[] = "{$minutes}m";
    }
    $timeParts[] = "{$seconds}s"; // Always include seconds

    return implode(' ', $timeParts);
}
?>


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
    <!--#header--><!--#/header-->


    <div class="title">
        <h1>The Game</h1><br>
        <h1>Time Since Last Loss: <?= formatTime($currentTime); ?></h1>
        <h1>Best Time: <?= formatTime($data['best_time']); ?></h1>
        <form method="POST">
            <button type="submit" id="myButton">I Lost!</button>
        </form>
    </div>


    <!--#footer--><!--#/footer-->

</body>

</html>