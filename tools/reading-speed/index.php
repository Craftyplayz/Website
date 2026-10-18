<?php
$site_page = ['components' => true, 'css' => ['/assets/css/pages/tools.css', '/assets/css/pages/speedtest.css']];
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
        <h1 class="page-title">Reading Speed Test</h1>
        <p class="page-lede">Press Start, read the text, then press Stop to see your words per minute.</p>

        <div class="card st-card">
            <div class="tl-form">
                <label>Language
                    <select class="field" id="language"></select>
                </label>
                <label>Text
                    <select class="field" id="passage"></select>
                </label>
            </div>
            <p class="st-info muted" id="info"></p>

            <div class="st-actions">
                <button id="start" class="btn primary" type="button" disabled>Start</button>
                <div class="tiles">
                    <div class="tile"><b id="time">0:00.0</b><span>time</span></div>
                    <div class="tile"><b id="speed">0</b><span>words per minute</span></div>
                </div>
            </div>
        </div>

        <div class="card reading" id="reading">
            <div id="text"></div>
            <button id="stop" class="btn primary" type="button" disabled>Stop</button>
        </div>
    </main>

    <?php site_footer(null, false); ?>

    <script src="speedtest.js" defer></script>
</body>

</html>
