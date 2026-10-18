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
        <h1>Random image</h1>
    </div>

    <body>
    <input type="text" id="letters" maxlength="2" placeholder="Enter 2 letters">
    <input type="number" id="startCount" value="7100" placeholder="Starting count">
    <button onclick="openLink()" style="height: 500px; width: 2000px; max-width: 100%">Generate and Open Link</button>


    <script>
        let counter = document.getElementById("startCount").value;
        let shouldOpenOnReturn = false;
        let openCount = 0;
        let lastOpenTime = 0;
        let tabBlock = false;

        function openLink() {
            if (tabBlock) {
                console.log("Tab opening is paused for 10 seconds.");
                return;
            }

            const letters = document.getElementById("letters").value;
            if (letters.length !== 2) {
                alert("Please enter exactly 2 letters.");
                return;
            }

            const now = Date.now();

            // Check if 5 tabs were opened within 5 seconds
            if (now - lastOpenTime <= 10000) {
                openCount++;
            } else {
                openCount = 1; // Reset count if more than 5 seconds passed
            }

            lastOpenTime = now;

            if (openCount >= 15) {
                tabBlock = true;
                console.log("Too many tabs opened! Pausing for 10 seconds.");
                setTimeout(() => {
                    tabBlock = false;
                    openCount = 0; // Reset tab count after 10 seconds
                    console.log("Tab opening resumed.");
                }, 3000); // 10 seconds pause
            } else {
                const url = `https://prnt.sc/${letters}${counter}`;
                window.open(url, '_blank');
                counter++;
            }
        }

        // Detect tab visibility change
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'hidden') {
                shouldOpenOnReturn = true;
            } else if (document.visibilityState === 'visible' && shouldOpenOnReturn) {
                openLink();
                shouldOpenOnReturn = false;  // Reset the flag
            }
        });

        // Update counter when user changes the start count
        document.getElementById("startCount").addEventListener("change", function() {
            counter = document.getElementById("startCount").value;
        });
    </script>
    
    </main>
    <?php site_footer(); ?>

</body>

</html>