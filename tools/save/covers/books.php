<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Covers</title>
    <link rel="stylesheet" href="css/homepage.css">
    <!-- <meta http-equiv="refresh" content="300"> -->
</head>

<body>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>


    <div class="title">
        <h1>Book Covers</h1>
    </div>
<div class="bookcovers">
<?php
$dir = $_SERVER['DOCUMENT_ROOT'] . '/images/books/';
$files = scandir($dir);

$items = [];
foreach ($files as $file) {
    if ($file == '.' || $file == '..') {
        continue;
    }
    $filename = $file; // Save the original filename
    $file = preg_replace('/([a-z])([A-Z])/', '$1 $2', $file); // Add space before capital letters
    $firstLetter = strtoupper(substr($file, 0, 1));
    if (!isset($items[$firstLetter])) {
        $items[$firstLetter] = [];
    }
    $items[$firstLetter][] = ['filename' => $filename, 'file' => $file]; // Save both the original filename and modified filename
}

foreach (range('A', 'Z') as $letter) {
    if (isset($items[$letter])) {
        echo '<div class="letter">';
        echo $letter . ': <br>';
        foreach ($items[$letter] as $item) {
            $filename = $item['filename']; // Retrieve the original filename
            $file = $item['file']; // Retrieve the modified filename
            echo "<a href='/tools/save/covers/book?title=" . $filename . "'>" . $file . "</a><br>";
        }
        echo '</div><br>';
    }
}
?>
</div>


    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>

</body>

</html>