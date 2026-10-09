<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Photos</title>
    <meta http-equiv="refresh" content="-1">
    <link rel="stylesheet" href="/css/gallery.css">
    <style>.gallery-image{height: 300px; width: 300px;} </style>
</head>

<body>
    <!--#header--><!--#/header-->


    <div class="title">
        <h1>People's Pets</h1>
        <h2>Click on an image to view more</h2>
    </div>
    <div class="gallery">


        <?php
        $folderPath = '/images/galleries/pets/';
        /* list names of all folders in the folder */
        $folders = scandir($_SERVER['DOCUMENT_ROOT'] . $folderPath);
        foreach ($folders as $folder) {
            $excludedFolders = ['.', '..', '!videos'];
            if (in_array($folder, $excludedFolders)) {
                continue;
            }

            echo '<div class="gallery-item" title="' . $folder . '">';
            echo '<a href="/photos/pet?pet=' . $folder . '"><img src="' . $folderPath . $folder . '/thumb" class="gallery-image" /></a>';
            echo '</div>';
        }
        ?>
<!-- <div class="title">
    <h1>If you want your pet added send me the photos</h1>
</div> -->
    </div>
    <!--#footer--><!--#/footer-->

</body>

</html>