<?php
$site_page = ['components' => true, 'ui' => true, 'css' => ['/assets/css/pages/tools-text.css']];
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
        <h1 class="page-title">Text Tools</h1>
        <p class="page-lede">Count words, change case, and convert text to binary, hex, Base64, Morse and more. Edit any box and the others follow.</p>

        <section class="card tx-main">
            <h2><label for="txIn">Your text</label></h2>
            <textarea class="field tx-in" id="txIn" rows="8" placeholder="Type or paste text here"></textarea>
            <div class="tx-case" id="txCase" aria-label="Change case">
                <button type="button" class="btn small" data-case="upper">UPPERCASE</button>
                <button type="button" class="btn small" data-case="lower">lowercase</button>
                <button type="button" class="btn small" data-case="title">Title Case</button>
                <button type="button" class="btn small" data-case="sentence">Sentence case</button>
                <button type="button" class="btn small" data-case="camel">camelCase</button>
                <button type="button" class="btn small" data-case="snake">snake_case</button>
                <button type="button" class="btn small" data-case="kebab">kebab-case</button>
                <button type="button" class="btn small" data-case="reverse">Reverse</button>
                <button type="button" class="btn small" data-case="clear">Clear</button>
            </div>
            <div class="tiles" id="txCount"></div>
            <p class="muted tx-note">Your text stays in this browser so it's still here next time. Clear removes it.</p>
        </section>

        <div class="grid tx-grid" id="txFormats"></div>
    </main>

    <?php site_footer(null, false); ?>
    <script src="<?= asset('/tools/text/text.js') ?>" defer></script>
</body>

</html>
