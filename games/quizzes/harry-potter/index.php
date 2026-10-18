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
        <h1>Harry Potter Quizes</h1>
    </div>

    <div class="title">
        <a href="/games/quizzes/harry-potter/easy/">
            <h2>Easy (Not Functional)</h2>
        </a>
        <a href="/games/quizzes/harry-potter/medium/">
            <h2>Normal (Not Functional)</h2>
        </a>
        <a href="/games/quizzes/harry-potter/hard/">
            <h2>Hard (Not Functional)</h2>
        </a>
        <a href="/games/quizzes/harry-potter/extrahard/">
            <h2>Extra Hard (Work in progress)</h2>
        </a>
    </div>

    </main>
    <?php site_footer(); ?>

</body>

</html>