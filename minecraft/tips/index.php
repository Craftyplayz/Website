<?php
$site_page = [
    'components' => true,
    'ui' => true,
    'css' => ['/assets/css/pages/minecraft-tips.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
$jvm = '-XX:+UseG1GC -Dsun.rmi.dgc.server.gcInterval=2147483646 -XX:+UnlockExperimentalVMOptions -XX:G1NewSizePercent=20 -XX:G1ReservePercent=20 -XX:MaxGCPauseMillis=50 -XX:G1HeapRegionSize=32M';
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
        <h1 class="page-title">Minecraft Tips</h1>
        <p class="page-lede">Settings and mods that make Minecraft run better.</p>

        <div class="grid">
            <section class="card">
                <h2>Performance tips</h2>
                <p class="muted">JVM arguments for smoother garbage collection. Paste them into your launcher's JVM arguments field.</p>
                <code class="jvm"><?= h(str_replace(' ', "\n", $jvm)) ?></code>
                <p><button class="btn primary" id="jvmargs" type="button">Copy JVM arguments</button></p>
            </section>

            <section class="card">
                <h2>Recommended mods</h2>
                <div class="rows">
                    <a class="rowi" href="https://modrinth.com/mod/sodium"><span class="grow">Sodium</span><small>Performance enhancements</small></a>
                </div>
            </section>
        </div>
    </main>
    <?php site_footer(); ?>
    <script>
        // ui.js is deferred
        document.addEventListener('DOMContentLoaded', () => UI.$('#jvmargs').addEventListener('click', e => UI.copy(<?= json_encode($jvm, JSON_HEX_TAG) ?>, e.currentTarget)));
    </script>
</body>

</html>
