<?php
$site_page = ['components' => true, 'ui' => true, 'css' => ['/assets/css/pages/tools.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

$steps = [
    'Allow signed scripts' => 'Set-ExecutionPolicy AllSigned',
    'Install Chocolatey' => "Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://community.chocolatey.org/install.ps1'))",
    'Install the apps' => implode("\n", array_map(fn($p) => "choco install $p -y", [
        'wiztree', 'googlechrome', 'python', '7zip', 'googledrive', 'vscode', 'spotify', 'putty', 'winscp',
        'vortex', 'discord', 'qbittorrent-enhanced', 'steam', 'epicgameslauncher', 'modernflyouts', 'tor',
        'razer-synapse-3', 'lghub', 'pia', 'minecraft-launcher', 'msedgeredirect', 'microsoft-windows-terminal',
        'powertoys', 'files', 'autohotkey', 'autoruns', 'everything',
    ])),
];
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
        <h1 class="page-title">Install Script</h1>
        <p class="page-lede">Copy each command and run all three, in order, in an admin PowerShell.</p>

        <div class="card tl-card">
            <?php $i = 0; foreach ($steps as $title => $cmd): $i++; ?>
                <div class="tl-step">
                    <h2><?= $i ?>. <?= h($title) ?> <button class="btn small" type="button" data-copy="cmd<?= $i ?>">Copy</button></h2>
                    <pre class="tl-code" id="cmd<?= $i ?>"><?= h($cmd) ?></pre>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <?php site_footer(null, false); ?>

    <script>
        document.addEventListener('click', e => {
            const b = e.target.closest('[data-copy]');
            if (b) UI.copy(document.getElementById(b.dataset.copy).textContent, b);
        });
    </script>
</body>

</html>
