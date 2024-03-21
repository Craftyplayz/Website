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

    <div class="title">
        <h1>Harry potter characters info</h1>
        <form action="/extras/harrypotter/character.php" method="get">
            <label for="myInput">Search for character</label>
            <input list="characters" id="myInput" name="character" placeholder="Enter character">
            <input type="submit" value="Search">
        </form>
    </div>
    <datalist id="characters">
        <?php
        $json = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/extras/harrypotter/json/characters.json');
        $data = json_decode($json, true);

        foreach ($data as $character) {
            $fullname = $character['name'];
            $id = $character['id'];

            // Generate an <option> element for each character with their full name
            // and a comma-separated list of their alternate names
            echo "<option value='" . $fullname . "'>" . implode(", ", $character['alternate_names']) . "</option>";
        }

        // Close the <datalist> element
        echo "</datalist>";
        ?>
        <div class="title">
            <h1>Add a fact</h1>
            <h2><a href="https://forms.gle/LFuqnjQnQ3Qjc9v17">Google Form</a></h2>
        </div>
        <?php
        include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
        ?>

</body>
</html>