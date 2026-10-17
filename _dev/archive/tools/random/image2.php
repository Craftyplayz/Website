<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Untitled</title>
    <meta http-equiv="refresh" content="-1">
</head>

<body>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>

    <div class="title">
        <h1>Random image</h1>
    </div>

    <body>
    <input type="text" id="letters" maxlength="2" placeholder="Enter 2 letters">
    <input type="number" id="startCount" value="7100" placeholder="Starting count">
    <button onclick="openLink()" style="height: 500px; width: 2000px">Generate and Open Link</button>


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
            if (now - lastOpenTime <= 5000) {
                openCount++;
            } else {
                openCount = 1; // Reset count if more than 5 seconds passed
            }

            lastOpenTime = now;

            if (openCount >= 5) {
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
    
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>

</body>

</html>