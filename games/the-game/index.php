<?php
$site_page = ['components' => true, 'css' => ['/assets/css/pages/games-the-game.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
// File path to the JSON file
$jsonFilePath = __DIR__ . '/thegame.json';

// Load data from the JSON file
if (!file_exists($jsonFilePath)) {
    $data = [[
        'last_loss_time' => time(), // Initialize with current time
        'best_time' => 0 // Default best time
    ]];
    file_put_contents($jsonFilePath, json_encode($data, JSON_PRETTY_PRINT));
} else {
    $data = json_decode(file_get_contents($jsonFilePath), true);
}
if (!isset($data[1])) $data[1] = []; // a new file has no previous times yet

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
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>

    <main class="page thegame" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">The Game</h1>
        <p class="page-lede">You just lost it. Press the button every time you do.</p>

        <section class="card section">
            <div class="tiles">
                <div class="tile"><b><?= formatTime($currentTime); ?></b><span>Since last loss</span></div>
                <div class="tile"><b><?= formatTime($data[0]['best_time']); ?></b><span>Best time</span></div>
            </div>
            <form method="POST">
                <button class="btn primary" type="submit" id="myButton">I lost!</button>
            </form>
        </section>

        <?php if ($data[1]): ?>
            <section class="card">
                <h2>Previous times <span class="n"><?= count($data[1]) ?> losses</span></h2>
                <ol class="rows" reversed>
                    <?php foreach (array_reverse($data[1]) as $time) : ?>
                        <li class="rowi"><?= formatTime($time); ?></li>
                    <?php endforeach; ?>
                </ol>
            </section>
        <?php endif; ?>
    </main>

    <?php site_footer(); ?>
</body>
</html>
