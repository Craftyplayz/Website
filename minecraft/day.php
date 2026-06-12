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
        <h1>Day calculator</h1>
    </div>

    <h2>Enter a number:</h2>
<input type="number" id="inputNumber">
<button onclick="calculate()">Calculate</button>
<p id="result"></p>

<script>
function calculate() {
    var input = document.getElementById("inputNumber").value;
    var result = (parseFloat(input) * 24000) + 1000;
    var formattedResult = "/time set " + result.toFixed(2);
    document.getElementById("result").innerText = formattedResult;
    
    // Copy result to clipboard
    var dummyElement = document.createElement("textarea");
    dummyElement.value = formattedResult;
    document.body.appendChild(dummyElement);
    dummyElement.select();
    document.execCommand("copy");
    document.body.removeChild(dummyElement);
}
</script>

    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>

</body>

</html>