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
        <h1 class="page-title">Random Image</h1>
        <p class="page-lede">Opens prnt.sc screenshots one after another. Switching back to this tab opens the next one.</p>

        <div class="card tl-card">
            <div class="tl-form">
                <label>Two letters
                    <input class="field" type="text" id="letters" maxlength="2" placeholder="ab">
                </label>
                <label>Starting count
                    <input class="field" type="number" id="startCount" value="7100" placeholder="Starting count">
                </label>
            </div>
            <button class="btn primary tl-big" id="open" type="button">Generate and Open Link</button>
            <p class="tl-status muted" id="status" aria-live="polite"></p>
        </div>
    </main>

    <?php site_footer(null, false); ?>

    <script>
        let counter = document.getElementById("startCount").value;
        let shouldOpenOnReturn = false;
        let openCount = 0;
        let lastOpenTime = 0;
        let tabBlock = false;
        const status = document.getElementById("status");

        function openLink() {
            if (tabBlock) {
                status.textContent = "Tab opening is paused for a few seconds.";
                return;
            }

            const letters = document.getElementById("letters").value;
            if (letters.length !== 2) {
                status.textContent = "Please enter exactly 2 letters.";
                return;
            }

            const now = Date.now();

            // count tabs opened less than 10 seconds apart
            if (now - lastOpenTime <= 10000) {
                openCount++;
            } else {
                openCount = 1;
            }

            lastOpenTime = now;

            if (openCount >= 15) {
                tabBlock = true;
                status.textContent = "Too many tabs opened! Pausing for a few seconds.";
                setTimeout(() => {
                    tabBlock = false;
                    openCount = 0;
                    status.textContent = "";
                }, 3000);
            } else {
                const url = `https://prnt.sc/${letters}${counter}`;
                window.open(url, '_blank');
                status.textContent = "Opened " + url;
                counter++;
            }
        }

        document.getElementById("open").addEventListener("click", openLink);

        // open the next one when the user comes back to this tab
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'hidden') {
                shouldOpenOnReturn = true;
            } else if (document.visibilityState === 'visible' && shouldOpenOnReturn) {
                openLink();
                shouldOpenOnReturn = false;
            }
        });

        document.getElementById("startCount").addEventListener("change", function() {
            counter = document.getElementById("startCount").value;
        });
    </script>
</body>

</html>
