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
    <!--#header--><!--#/header-->


    <div class="title">
        <h1>Games</h1>
<?php
$folders = glob('*/');
foreach ($folders as $folder) {
    $folder = rtrim($folder, '/');
    echo '<p><a href="' . $folder . '">' . $folder . '</a></p><br>';
}
?>
</div>
<!--#footer--><!--#/footer-->

</body>

</html>