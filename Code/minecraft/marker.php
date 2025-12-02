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
        <h1>Dynmap Marker creator</h1>
    </div>


    <style>
        /* Style for dropdown menus */
        select {
            color: white; /* Text color */
            background-color: black; /* Background color */
            padding: 5px; /* Padding for better appearance */
        }
    </style>




    <label for="label">Label:</label>
    <input type="text" id="label"><br><br>

    <label for="id">ID:</label>
    <input type="text" id="id"><br><br>

    <label for="set">Set:</label>
    <select id="set">
        <option value="Builds">Builds</option>
        <option value="Farms">Farms</option>
        <option value="Other">Other</option>
    </select><br><br>

    <label for="icon">Icon:</label>



    <select id="icon">
        <?php
        
        $dir    = $_SERVER['DOCUMENT_ROOT'] . '/images/markers/';
        $files1 = scandir($dir);
        foreach ($files1 as $file) {
            if ('.' !== $file[0]) {
                $file_name = substr($file, 0, -4);
                echo '<option value="' . $file_name . '">' . $file_name . '</option>';
            }
        }
        
        ?>
    </select><br><br>
<!-- 
<select id="icon">
    <option value="pirateflag">Pirate Flag</option>
    <option value="construction">Construction</option>
    <option value="hammer">Hammer</option>
    <option value="house">House</option>
    <option value="factory">Factory</option>
    <option value="diamond">Diamond</option>
    <option value="world">World</option>
    <option value="anchor">Anchor</option>
    <option value="gear">Gear</option>
    <option value="portal">Portal</option>
    <option value="cross">Cross</option>
    <option value="warning">Warning</option>
    <option value="zombie">Zombie Head</option>
    <option value="gold">Gold Ingot</option>
    <option value="oak">Oak Log</option>
</select><br><br> -->

    <label for="description">Description:</label>
    <input type="text" id="description"><br><br>

    <button onclick="generateCommands()">Generate Commands</button><br><br>

    <div id="output"></div>

    <script>
        function generateCommands() {
            var label = document.getElementById("label").value;
            var id = document.getElementById("id").value;
            var set = document.getElementById("set").value;
            var icon = document.getElementById("icon").value;
            var description = document.getElementById("description").value;

            var command1 = "/dmarker add \"" + label + "\" id:" + id + " set:" + set + " icon:" + icon;

            var command2 = "/dmarker appenddesc id:" + id + " set:" + set + " desc:\"" + description + "\"";

            var output = command1 + "<br>" + command2;

            document.getElementById("output").innerHTML = output;
        }
    </script>



    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>

</body>

</html>