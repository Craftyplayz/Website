<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- <meta http-equiv="refresh" content="3"> -->
    <title>About Me</title>
</head>
<body>
<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
?>


<div class="title">
    <h1>Jellyfin api testing</h1>
    <h2>Kinda cringe</h2>
</div>

    <?php

    ?>
</p>


<?php
$url = 'https://jelly.bighudders.co.uk/Users/AuthenticateByName';
$data = array(
    'Username' => 'tomc',
    'Pw' => 'ThunderCunt69'
);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Accept: application/json',
    'Content-Type: application/json'
));
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
curl_close($ch);

// Handle the response
if ($response === false) {
    echo 'Error: ' . curl_error($ch);
} else {
    echo 'Response: ' . $response;
}
?>


<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
?>

</body>
</html>