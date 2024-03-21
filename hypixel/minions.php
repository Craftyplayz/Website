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
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>

    <?php

    $profileIds = array(
        '22159541fde841e6a104593e1cb3a456',
        '39ac5666450b4635873573217a72f143',
        'dd5b7e595e7846a983d11f9cafa0cd42'
    );
// converts base 64 string to an image tag for a minecraft head
    function convertBase64ToUrl($base64Value) {
        $jsonData = json_decode(base64_decode($base64Value), true);
        $skinId = explode('/', $jsonData['textures']['SKIN']['url'])[4];
        return "<img src=\"https://www.mc-heads.net/head/{$skinId}/left\">";
    }
    

    foreach ($profileIds as $profileId) {
        $profileUrl = 'https://api.hypixel.net/v2/skyblock/profiles?key=' . $hypixelkeyapp . '&uuid=' . $profileId;
        $playerdata = json_decode(file_get_contents($profileUrl), true);

        foreach ($playerdata['profiles'] as $profile) {
            if ($profile['cute_name'] == 'Mango') {
                foreach ($profile['members'][$profileId]['player_data']['crafted_generators'] as $generator) {
                    echo $generator . '<br>';
                    $base64Value = $generator;
                    echo convertBase64ToUrl($base64Value);

                }
                /* echo $profile['members'][$profileId]['player_data']['crafted_generators']; */
            }
        }
    }

$base64Value = "eyJ0aW1lc3RhbXAiOjE1NTc5MzQwNzgwNTksInByb2ZpbGVJZCI6ImNiZGViZGRjODNhNTQ0OWFiZDFiOThhNzBjY2E0ZDhlIiwicHJvZmlsZU5hbWUiOiJDaGVja2lkb2lzIiwic2lnbmF0dXJlUmVxdWlyZWQiOnRydWUsInRleHR1cmVzIjp7IlNLSU4iOnsidXJsIjoiaHR0cDovL3RleHR1cmVzLm1pbmVjcmFmdC5uZXQvdGV4dHVyZS9jMzgxMmNiODZmZTIyOTcxZDBhZTU4Nzg5ZjE4YTFkMjA4MTE2Y2IyMDQzMjlhZmY3OTA1YWEzOTkzYjBkMGQ4In19fQ";


    ?>

    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>

</body>

</html>