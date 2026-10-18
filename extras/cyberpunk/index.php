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
            output += "";
            document.write(output);
        </script>
        and Cyberpunk 1 or can I just play Cyberpunk 2077 and figure it out as I go?
    </p>
    </main>
    <?php site_footer(); ?>

</body>

</html>