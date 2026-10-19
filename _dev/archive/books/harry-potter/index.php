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
        <h1>Harry potter characters info</h1>
        <form action="/books/harry-potter/character/" method="get">
            <label for="myInput">Search for character</label>
            <input list="characters" id="myInput" name="character" placeholder="Enter character">
            <input type="submit" value="Search">
        </form>
    </div>
    <datalist id="characters">
        <?php
        $json = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/books/harry-potter/json/characters.json');
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
        </main>
    <?php site_footer(); ?>

</body>
</html>