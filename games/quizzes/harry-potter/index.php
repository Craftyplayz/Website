<?php
$site_page = ['components' => true];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

$levels = [
    ['extrahard', 'Extra Hard', 'Type the answers yourself.', 'Work in progress', 'b'],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>

    <main class="page" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Harry Potter Quiz</h1>
        <p class="page-lede">Type the answers yourself. More levels are coming back later.</p>

        <div class="hub-grid">
            <?php foreach ($levels as [$dir, $name, $about, $status, $tone]): ?>
                <a class="hub-card" href="/games/quizzes/harry-potter/<?= $dir ?>/">
                    <b><?= $name ?></b>
                    <span><?= $about ?></span>
                    <div class="badges"><small class="badge <?= $tone ?>"><?= $status ?></small></div>
                </a>
            <?php endforeach; ?>
        </div>
    </main>

    <?php site_footer(); ?>
</body>

</html>
