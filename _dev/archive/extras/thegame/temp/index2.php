<?php
// File path to the JSON file
$jsonFilePath = __DIR__ . '/thegame2.json';

// Load data from the JSON file
if (!file_exists($jsonFilePath)) {
    $data = [
        'last_loss_time' => time(), // Initialize with current time
        'best_time' => 0 // Default best time
    ];
    file_put_contents($jsonFilePath, json_encode([$data], JSON_PRETTY_PRINT));
} else {
    $data = json_decode(file_get_contents($jsonFilePath), true);
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $timeSinceLastLoss = time() - $data[0]['last_loss_time']; // Time since last loss
    $data[0]['last_loss_time'] = time(); // Update last loss time

    // Update best time if current time is better
    if ($timeSinceLastLoss > $data[0]['best_time']) {
        $data[0]['best_time'] = $timeSinceLastLoss;
    }

    // Create new entry with the incremented key and value
    $newKey = '00000' . (count($data[1]) + 1); // Increment key
    $data[1][$newKey] = $timeSinceLastLoss; // Add new key-value pair

    // Save updated data back to the JSON file
    file_put_contents($jsonFilePath, json_encode($data, JSON_PRETTY_PRINT));

    // Refresh the page to show updated times
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Calculate current time since last loss
$currentTime = time() - $data[0]['last_loss_time'];

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
    if ($hours > 0 || !empty($timeParts)) {
        $timeParts[] = "{$hours}h";
    }
    if ($minutes > 0 || !empty($timeParts)) {
        $timeParts[] = "{$minutes}m";
    }
    $timeParts[] = "{$seconds}s";

    return implode(' ', $timeParts);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Game</title>
    <meta http-equiv="refresh" content="-1">
</head>
<body>
    <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php'; ?>
    <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php'; ?>

    <div class="title">
        <h1>The Game</h1><br>
        <h1>Time Since Last Loss: <?= formatTime($currentTime); ?></h1>
        <h1>Best Time: <?= formatTime($data[0]['best_time']); ?></h1>
        <form method="POST">
            <button type="submit" id="myButton">I Lost!</button>
        </form>
        <script>
            const password = 'thegame';

            function checkPassword() {
                const userPassword = prompt('Enter the password to press the button:');
                if (userPassword === password) {
                    alert('Access granted!');
                } else {
                    alert('Incorrect password');
                }
            }
        </script>
    </div>

    <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php'; ?>
</body>
</html>
