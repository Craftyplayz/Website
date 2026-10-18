<?php
$site_page = ['components' => true, 'css' => ['/assets/css/pages/tools.css']];
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
        <h1 class="page-title">QR Code Generator</h1>
        <p class="page-lede">Type a link or any text and get a QR code for it.</p>

        <div class="card tl-card">
            <form class="tl-form" id="qrForm">
                <label>URL or text
                    <input class="field" type="text" id="urlInput" placeholder="https://example.com">
                </label>
                <button class="btn primary" type="submit">Generate QR Code</button>
            </form>
            <p class="tl-out">
                <img class="tl-qr" id="qrCodeImage" src="/media/fake-updates/rickroll.png" width="240" height="240" alt="QR Code">
            </p>
        </div>
    </main>

    <?php site_footer(null, false); ?>

    <script>
        document.getElementById('qrForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const url = document.getElementById('urlInput').value;
            document.getElementById('qrCodeImage').src = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(url)}`;
        });
    </script>
</body>

</html>
