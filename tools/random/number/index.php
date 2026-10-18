<?php
$site_page = ['components' => true, 'css' => ['/assets/css/pages/tools.css']];
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
        <h1 class="page-title">Random Number Generator</h1>
        <p class="page-lede">Pick a whole number between a minimum and a maximum, both included.</p>

        <div class="card tl-card">
            <form class="tl-form" id="rngForm">
                <label>Minimum (default 1)
                    <input class="field" type="number" id="number" placeholder="1" step="1">
                </label>
                <label>Maximum
                    <input class="field" type="number" id="number2" placeholder="Maximum" step="1" required>
                </label>
                <button class="btn primary" type="submit" id="generate">Generate</button>
            </form>
            <p class="tl-num" id="result" aria-live="polite"></p>
        </div>
    </main>

    <?php site_footer(null, false); ?>

    <script>
        document.getElementById('rngForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const a = Math.ceil(Number(document.getElementById('number').value || 1));
            const b = Math.floor(Number(document.getElementById('number2').value));
            const lo = Math.min(a, b), hi = Math.max(a, b);
            document.getElementById('result').textContent = (lo + Math.floor(Math.random() * (hi - lo + 1))).toLocaleString();
        });
    </script>
</body>

</html>
