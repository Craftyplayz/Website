<?php
$site_page = ['legacy' => true];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php page_head(); ?>
    <!-- <meta http-equiv="refresh" content="3"> -->
</head>
<body>
<?php
site_header(); echo '<main class="page page-legacy" id="main">';
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
echo '</main>'; site_footer();
?>

</body>
</html>