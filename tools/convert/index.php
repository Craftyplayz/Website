<?php
$site_page = ['components' => true, 'ui' => true, 'css' => ['/assets/css/pages/tools-convert.css']];
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
        <h1 class="page-title">Unit Converter</h1>
        <p class="page-lede">Type a conversion or a sum, or pick units below. Click any result to copy it.</p>

        <section class="card cv-quick">
            <label for="cvQ" class="muted">Convert or calculate</label>
            <input class="field" id="cvQ" type="text" autocomplete="off" spellcheck="false"
                placeholder="5 ft to cm · 100 f in c · 2.5 gb to mb · 12*3+4">
            <div class="cv-out" id="cvOut" aria-live="polite"></div>
        </section>

        <section class="card">
            <div class="stabs" id="cvCats" role="tablist" aria-label="Category"></div>
            <div class="cv-in">
                <input class="field" id="cvV" type="text" inputmode="decimal" autocomplete="off" aria-label="Value" value="1">
                <select class="field" id="cvU" aria-label="From unit"></select>
            </div>
            <div class="rows cv-rows" id="cvRows"></div>
            <p class="muted cv-note" id="cvNote"></p>
        </section>
    </main>

    <?php site_footer(null, false); ?>
    <script src="<?= asset('/tools/convert/convert.js') ?>" defer></script>
</body>

</html>
