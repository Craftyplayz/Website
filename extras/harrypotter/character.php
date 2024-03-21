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
    $json = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/extras/harrypotter/json/characters.json');
    $data = json_decode($json, true);
    $name = $_GET['character'];
    echo "<h1>" . $name . "</h1><br>";
    foreach ($data as $character) {
        if ($character['name'] == $name) {
            echo "<p>Character ID: " . $character['id'] . "</p><br>";
            echo "<p>Character Name: " . $character['name'] . "</p><br>";
            if (!empty($character['alternate_names']) && is_array($character['alternate_names'])) {
                echo "<p>Alternative names: " . implode(', ', $character['alternate_names']) . "</p><br>";
            }
            if (!empty($character['species'])) {
                echo "<p>Species: {$character['species']}</p><br>";
            }
            if (!empty($character['gender'])) {
                echo "<p>Gender: {$character['gender']}</p><br>";
            }
            if (!empty($character['house'])) {
                echo "<p>House: {$character['house']}</p><br>";
            }
            if (!empty($character['dateOfBirth'])) {
                echo "<p>Date of Birth: {$character['dateOfBirth']}</p><br>";
            }
            if (!empty($character['dateOfDeath'])) {
                echo "<p>Year of Death: {$character['dateOfDeath']}</p><br>";
            }
            echo "<p>Wizard: " . ($character['wizard'] ? 'yes' : 'no') . "</p><br>";
            if (!empty($character['ancestry'])) {
                echo "<p>Ancestry: {$character['ancestry']}</p><br>";
            }
            if (!empty($character['eyeColour'])) {
                echo "<p>Eye Colour: {$character['eyeColour']}</p><br>";
            }
            if (!empty($character['hairColour'])) {
                echo "<p>Hair Colour: {$character['hairColour']}</p><br>";
            }
if (!empty($character['wand'])) {
    echo "<p>Wands:</p><br>";
    echo "<ul>";
    foreach ($character['wand'] as $wand) {
        echo "<li>Wood: {$wand['wood']}</li>";
        echo "<li>Core: {$wand['core']}</li>";
        echo "<li>Length: {$wand['length']}</li>";
        echo "<br>";
    }
    echo "</ul>";
}
            if (!empty($character['patronus'])) {
                echo "<p>Patronus: {$character['patronus']}</p><br>";
            }
            echo "<p>Hogwarts Student: " . ($character['hogwartsStudent'] ? 'yes' : 'no') . "</p><br>";
            echo "<p>Hogwarts Staff: " . ($character['hogwartsStaff'] ? 'yes' : 'no') . "</p><br>";
            if (!empty($character['actor'])) {
                echo "<p>Actor: {$character['actor']}</p><br>";
            }
            if (!empty($character['alternateActor'])) {
                echo "<p>Alternate Actor: {$character['alternateActor']}</p><br>";
            }
            echo "<p>Alive: " . ($character['alive'] ? 'yes' : 'no') . "</p><br>";
        }
    }
    ?>
    <button onclick="history.back()">Go Back</button>
</body>

</html>