<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Random Insult</title>
    <meta http-equiv="refresh" content="-1">
</head>

<body>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>

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

    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>

</body>

</html>