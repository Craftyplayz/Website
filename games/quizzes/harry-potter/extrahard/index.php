<?php
// Shared by every level: copy this file to medium/, hard/ and extrahard/ unchanged.
// Each level keeps its own assets/ (questions, answers, scripts.js), loaded by relative path, so the URL must end in /.
$site_page = ['components' => true, 'css' => ['/assets/css/pages/games-quiz.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>

    <main class="page quiz" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title"><?= h(site_page_title(site_current_path())) ?></h1>
        <p class="page-lede">Answer every question, then press Submit. <a href="/games/quizzes/harry-potter/">Other levels</a></p>

        <form id="quiz">
            <div id="content"></div>
            <button type="submit" class="btn primary submit">Submit</button>
        </form>

        <section class="card results" aria-live="polite">
            <h2>Your results</h2>
            <div class="tiles">
                <div class="tile"><b id="percent"></b><span>Score</span></div>
                <div class="tile"><b id="score"></b><span>Correct</span></div>
            </div>
            <p id="message"></p>
            <p><a href="">Try again</a> · <a href="/games/quizzes/harry-potter/">Other levels</a></p>
        </section>
    </main>

    <?php site_footer(); ?>
    <script src="https://code.jquery.com/jquery-3.3.1.min.js" defer></script>
    <script src="assets/js/scripts.js" defer></script>
</body>

</html>
