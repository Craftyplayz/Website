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
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>

</body>

</html>