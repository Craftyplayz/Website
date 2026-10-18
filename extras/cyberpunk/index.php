<?php
$site_page = ['components' => true, 'css' => ['/assets/css/pages/extras-random.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
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
        <h1 class="page-title">I wanna play Cyberpunk 2077 but I have a question</h1>

        <div class="card xr-card">
            <p class="xr-cp">In order to understand the story do I need to play
                <?php for ($year = 2076; $year > 1; $year--) echo 'Cyberpunk ' . $year . ', '; ?>
                and Cyberpunk 1 or can I just play Cyberpunk 2077 and figure it out as I go?
            </p>
        </div>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
