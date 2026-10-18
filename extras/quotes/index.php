<?php
    $folder = $_SERVER['DOCUMENT_ROOT'] . '/media/quotes';
    $images = array_values(array_diff(scandir($folder), ['.', '..']));
    $image = $images[array_rand($images)];
    header ('Content-Type: image/png');
    readfile($folder . '/' . $image);
?>