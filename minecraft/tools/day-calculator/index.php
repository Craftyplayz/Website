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

    </main>
    <?php site_footer(); ?>

</body>

</html>