<?php
    $folder = $_SERVER['DOCUMENT_ROOT'] . '/images/quotes';
    $images = scandir($folder);
    $image = $images[array_rand($images)];
    header ('Content-Type: image/png');
    readfile($folder . '/' . $image);
?>