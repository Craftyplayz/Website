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
        <h1>Docker Install script maker</h1>

        <div>
        <label for="command">Docker Pull Command:</label>
        <input type="text" id="command" placeholder="Enter docker pull command">
    </div>
    <div>
        <label for="volumeName">Volume Name:</label>
        <input type="text" id="volumeName" placeholder="Enter volume name">
    </div>
    <button onclick="generateDockerCommand()">Generate Command</button>

    <script>
        function generateDockerCommand() {
            const command = document.getElementById('command').value.trim();
            const volumeName = document.getElementById('volumeName').value.trim();

            if (command === '' || volumeName === '') {
                alert('Please enter both Docker pull command and volume name.');
                return;
            }

            const imageName = command.split(" ").pop(); // Extracting just the image name
            const imageNameWithoutPrefix = imageName.split("/").pop(); // Extracting just the image name without prefix

            const dockerCommand = `sudo docker run -v ${volumeName}:/${volumeName} ${imageName}`;
            navigator.clipboard.writeText(dockerCommand);
            document.getElementById('output').value = dockerCommand;
        }
    </script>
    </main>
    <?php site_footer(); ?>

</body>

</html>