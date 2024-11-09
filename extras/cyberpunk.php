<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cyberpunk 2077</title>
    <meta http-equiv="refresh" content="-1">
</head>

<body>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>

    <div class="title">
        <h1>I wanna play Cyberpunk 2077 but i have a question</h1>
    </div>
    <p>In order to understand the story do i need to play
        <script>
            let year = 2076;
            let output = "";
            while (year > 1) {
                output += "Cyberpunk " + year + ", ";
                year--;
            }
            output += "Cyberpunk 2";
            document.write(output);
        </script>
        and Cyberpunk 1 or can I just play Cyberpunk 2077 and figure it out as I go?
    </p>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>

</body>

</html>