<?php
$site_page = ['legacy' => true];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>
    <main class="page page-legacy" id="main">

    <div class="title">
        <h1>Random Insult Generator</h1>
<h2>
<?php
// API URL
$url = "https://evilinsult.com/generate_insult.php?lang=en&type=json";

// Get the API response
$response = file_get_contents($url);

// Decode JSON into array
$data = json_decode($response, true);

// Check and echo the insult
if (isset($data['insult'])) {
    echo $data['insult'];
} else {
    echo "No insult found.";
}
?>

</h2>
    </div>

    </main>
    <?php site_footer(); ?>

</body>

</html>