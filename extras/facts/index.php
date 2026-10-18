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
        <h1>Random Fact</h1>
        <h2><?php
$url = "https://uselessfacts.jsph.pl/api/v2/facts/random";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

echo $data['text'] . "\n";
?></h2>
    </div>
<button class="button" onclick="location.href='/extras/facts/'" style="display: block; margin: 0 auto;">Random Fact</button>

    </main>
    <?php site_footer(); ?>

</body>

</html>