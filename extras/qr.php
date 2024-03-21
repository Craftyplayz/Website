<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Qr Code generator</title>
    <meta http-equiv="refresh" content="-1">
</head>

<body>
    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/config.php';
    ?>



<div class="title">
    <h1>QR Code generator</h1>
    <input type="text" id="urlInput" placeholder="Enter URL">
    <button onclick="generateQRCode()">Generate QR Code</button><br><br>
    <img style="width: 25%;" id="qrCodeImage" src="\images\update\rickroll.png" alt="QR Code">
</div>

<script>
    function generateQRCode() {
        const url = document.getElementById("urlInput").value;
        const apiUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(url)}`;
        document.getElementById("qrCodeImage").src = apiUrl;
    }
</script>


    <?php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/php/footer.php';
    ?>

</body>

</html>