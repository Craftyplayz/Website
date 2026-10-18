<?php
$site_page = [
    'components' => true,
    'ui' => true,
    'css' => ['/assets/css/pages/minecraft-forms.css'],
];
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
        <h1 class="page-title">Day Calculator</h1>
        <p class="page-lede">Turn a day number into a <span class="mono">/time set</span> command. It's copied for you.</p>

        <div class="card form-card">
            <form class="form" id="dayform">
                <label>Day
                    <input class="field" type="number" id="inputNumber" step="any" required autofocus>
                </label>
                <button class="btn primary" type="submit">Calculate</button>
            </form>
            <div class="out hide" id="out">
                <div class="cmd"><code id="result"></code><button class="btn small" type="button" id="copy">Copy</button></div>
            </div>
        </div>
    </main>
    <?php site_footer(); ?>
    <script>
        // ui.js is deferred
        document.addEventListener('DOMContentLoaded', () => {
            const { $, copy } = UI;
            $('#dayform').addEventListener('submit', e => {
                e.preventDefault();
                const result = (parseFloat($('#inputNumber').value) * 24000) + 1000;
                $('#result').textContent = '/time set ' + result.toFixed(2);
                $('#out').classList.remove('hide');
                copy($('#result').textContent, $('#copy'));
            });
            $('#copy').addEventListener('click', e => copy($('#result').textContent, e.currentTarget));
        });
    </script>
</body>

</html>
