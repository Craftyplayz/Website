<?php
$site_page = ['components' => true, 'ui' => true, 'css' => ['/assets/css/pages/games-factorio.css']];
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
        <h1 class="page-title">Factorio Command Generator</h1>
        <p class="page-lede">Builds the FactorioMaps render command for a save. If you don't know what this is, you won't need it.</p>

        <form class="card fcmd" id="form">
            <div class="fcmd-fields">
                <label>Save name <input class="field" type="text" id="savename" autocomplete="off"></label>
                <label>Seed <input class="field" type="text" id="seed" autocomplete="off"></label>
            </div>
            <label class="fcmd-check"><input type="checkbox" id="dayonly" checked> <span><b>Day only</b> <small>Renders daytime only. Rendering both takes twice as long.</small></span></label>
            <label class="fcmd-check"><input type="checkbox" id="hd"> <span><b>High definition</b> <small>Uses 4× the space. Only for small maps.</small></span></label>
            <label class="fcmd-check"><input type="checkbox" id="defaultstart"> <span><b>Default start</b> <small>The map opens at the start of the save.</small></span></label>
            <label class="fcmd-check"><input type="checkbox" id="timelapse"> <span><b>Timelapse</b> <small>Removes <code>_autosave-</code> and <code>_*</code> from the command.</small></span></label>
        </form>

        <section class="card section">
            <h2>Command <button class="btn primary small n-btn" type="button" id="copy">Copy</button></h2>
            <pre class="fcmd-out" id="output"></pre>
            <p class="muted">Open <code>D:\!Git\Website\Code\Factorio\L0laapk3_FactorioMaps_4.4.0</code>, type <code>cmd</code> in the address bar, then paste the command.</p>
        </section>
    </main>

    <?php site_footer(); ?>
    <script>
        // Generates the command from the form values.
        function generate() {
            var savename = document.getElementById('savename').value;
            var seedname = document.getElementById('seed').value;
            var command = 'python auto.py ' + savename + ' _autosave-' + seedname + '_* --no-tags --factorio="D:\\SteamLibrary\\steamapps\\common\\Factorio\\bin\\x64\\factorio.exe"';

            if (document.getElementById('dayonly').checked) command += ' --dayonly';
            if (document.getElementById('hd').checked) command += ' --hd';
            if (document.getElementById('defaultstart').checked) command += ' --default-timestamp=0';
            if (document.getElementById('timelapse').checked) command = command.replace('_autosave-', '').replace('_*', '');

            document.getElementById('output').textContent = command;
        }
        document.getElementById('form').addEventListener('input', generate);
        document.getElementById('form').addEventListener('submit', function (e) { e.preventDefault(); });
        document.getElementById('copy').addEventListener('click', function () {
            UI.copy(document.getElementById('output').textContent, this).then(function (ok) {
                if (!ok) UI.toast('Could not copy. Select the command and copy it by hand.', 'err');
            });
        });
        generate();
    </script>
</body>

</html>
