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
    <h1>QR Code generator</h1>
    <input type="text" id="urlInput" placeholder="Enter URL">
    <button onclick="generateQRCode()">Generate QR Code</button><br><br>
    <img style="width: 25%;" id="qrCodeImage" src="/media/fake-updates/rickroll.png" alt="QR Code">
</div>

<script>
    function generateQRCode() {
        const url = document.getElementById("urlInput").value;
        const apiUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(url)}`;
        document.getElementById("qrCodeImage").src = apiUrl;
    }
</script>


    </main>
    <?php site_footer(); ?>

</body>

</html>