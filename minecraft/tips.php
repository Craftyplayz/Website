<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minecraft Tips</title>
</head>

<body>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>

    <div class="title">
        <h1>Additional projects</h1>
    </div>

    <div class="title left-section">
        <h1>Performance Tips</h1>
        <div>
        <p id="jvmargs" style="cursor: pointer">
    Copy JVM Arguments
</p>
<script>
    const textBox = document.getElementById('jvmargs');

    textBox.addEventListener('click', () => {
        const textToCopy = '-XX:+UseG1GC -Dsun.rmi.dgc.server.gcInterval=2147483646 -XX:+UnlockExperimentalVMOptions -XX:G1NewSizePercent=20 -XX:G1ReservePercent=20 -XX:MaxGCPauseMillis=50 -XX:G1HeapRegionSize=32M';
        const tempInput = document.createElement('input');
        tempInput.value = textToCopy;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand('copy');
        document.body.removeChild(tempInput);
        textBox.style.color = 'green';
        textBox.innerHTML = 'Copied! Paste it in your Minecraft Launcher';
    });
</script>
        </div>
    </div>

    <div class="title right-section">
        <h1>Reccomended Mods</h1>
        <div>
            <p><a href="https://modrinth.com/mod/sodium">Sodium - Performance Enhancements</p><br>
        </div>
    </div>

    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>
</body>

</html>