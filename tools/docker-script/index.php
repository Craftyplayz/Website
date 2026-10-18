<?php
$site_page = ['components' => true, 'ui' => true, 'css' => ['/assets/css/pages/tools.css']];
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
        <h1 class="page-title">Docker Install Script Maker</h1>
        <p class="page-lede">Turn a docker pull command into a docker run command with a volume. It's copied for you.</p>

        <div class="card tl-card">
            <form class="tl-form" id="dockerForm">
                <label>Docker pull command
                    <input class="field" type="text" id="command" placeholder="docker pull nginx">
                </label>
                <label>Volume name
                    <input class="field" type="text" id="volumeName" placeholder="data">
                </label>
                <button class="btn primary" type="submit">Generate Command</button>
            </form>
            <p class="tl-status err" id="status" hidden></p>
            <div class="tl-step tl-out" id="result" hidden>
                <h2>Command <button class="btn small" type="button" id="copy">Copy</button></h2>
                <pre class="tl-code" id="output"></pre>
            </div>
        </div>
    </main>

    <?php site_footer(null, false); ?>

    <script>
        document.getElementById('dockerForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const command = document.getElementById('command').value.trim();
            const volumeName = document.getElementById('volumeName').value.trim();
            const status = document.getElementById('status');

            status.hidden = command !== '' && volumeName !== '';
            if (!status.hidden) {
                status.textContent = 'Please enter both Docker pull command and volume name.';
                return;
            }

            const imageName = command.split(" ").pop(); // just the image name

            const dockerCommand = `sudo docker run -v ${volumeName}:/${volumeName} ${imageName}`;
            navigator.clipboard.writeText(dockerCommand).catch(() => {});
            document.getElementById('output').textContent = dockerCommand;
            document.getElementById('result').hidden = false;
        });
        document.getElementById('copy').addEventListener('click', e => UI.copy(document.getElementById('output').textContent, e.currentTarget));
    </script>
</body>

</html>
