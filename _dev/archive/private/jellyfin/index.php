<?php
// Checks the Jellyfin server with the API key from php/config.php ($jellyfinurl, $jellyfinkey).
// Nothing secret is printed: only the server's public info.
$site_page = ['components' => true, 'config' => true];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

$info = null;
$error = '';
if (empty($jellyfinurl) || empty($jellyfinkey)) {
    $error = 'Set $jellyfinurl and $jellyfinkey in php/config.php.';
} else {
    $ch = curl_init(rtrim($jellyfinurl, '/') . '/System/Info');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-Emby-Token: ' . $jellyfinkey],
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($response === false) $error = 'Could not reach the server: ' . curl_error($ch);
    elseif ($status !== 200) $error = 'The server answered with HTTP ' . $status . '. Check the API key.';
    else $info = json_decode($response, true);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <meta name="robots" content="noindex, nofollow">
</head>

<body>
    <?php site_header(); ?>

    <main class="page" id="main">
        <nav class="crumbs" aria-label="Breadcrumb"><a href="/">Home</a> <span class="crumbs-sep">›</span> <a href="/private/">Private</a> <span class="crumbs-sep">›</span> <span aria-current="page">Jellyfin</span></nav>
        <h1 class="page-title">Jellyfin API testing</h1>
        <p class="page-lede">Kinda cringe. Checks the server with the API key from the site config.</p>

        <?php if ($error): ?>
            <p class="err"><?= h($error) ?></p>
        <?php else: ?>
            <section class="card">
                <h2>Server <span class="n"><span class="badge g">Online</span></span></h2>
                <div class="kv">
                    <span>Name</span><span><?= h(isset($info['ServerName']) ? $info['ServerName'] : '?') ?></span>
                    <span>Version</span><span><?= h(isset($info['Version']) ? $info['Version'] : '?') ?></span>
                    <span>System</span><span><?= h(isset($info['OperatingSystemDisplayName']) ? $info['OperatingSystemDisplayName'] : (isset($info['OperatingSystem']) ? $info['OperatingSystem'] : '?')) ?></span>
                    <span>Address</span><span><a href="<?= h($jellyfinurl) ?>"><?= h(preg_replace('~^https?://~', '', $jellyfinurl)) ?></a></span>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
