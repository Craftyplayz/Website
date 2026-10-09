<?php
$url = $_SERVER['REQUEST_URI'];
$path = parse_url($url, PHP_URL_PATH);
$gameid = rtrim(str_replace('/steam/newgame/', '', $path), '/');
/* query steam api for the selected game */

$url = 'https://store.steampowered.com/api/appdetails?appids=' . $gameid;
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
if(curl_errno($ch)) {
    echo 'Error:' . curl_error($ch);
} else {
    curl_close($ch);
    $data = json_decode($response, true);
    if(isset($data[$gameid]['success']) && $data[$gameid]['success'] === false) {
        echo 'Error: ' . $data[$gameid]['message'];
    } else {
        $gametitle = $data[$gameid]['data']['name'];
        
    }
}
global $gametitle;
global $gameid;
?>