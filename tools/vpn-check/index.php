<?php
$site_page = ['components' => true];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

const PIA_LIST = 'https://serverlist.piaservers.net/vpninfo/servers/v6';
const PIA_TTL = 86400;

// PIA's server list as [ip => region name], cached for a day with a stale fallback
function pia_ips()
{
    $dir = sys_get_temp_dir() . '/craftyplayz-pia';
    $path = $dir . '/ips.json';
    $cached = is_file($path) ? json_decode((string)@file_get_contents($path), true) : null;
    if ($cached && time() - filemtime($path) < PIA_TTL) return $cached;

    $ch = curl_init(PIA_LIST);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; CraftyPlayz VPN check; +https://craftyplayz.com/tools/vpn-check/)',
    ]);
    $body = (string)curl_exec($ch);
    // the JSON is the first line; a signature follows it
    $json = json_decode(strtok($body, "\n"), true);
    $ips = [];
    foreach ($json['regions'] ?? [] as $r) {
        foreach ($r['servers'] ?? [] as $list) {
            foreach ($list as $s) if (!empty($s['ip'])) $ips[$s['ip']] = $r['name'];
        }
    }
    if ($ips) {
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        @file_put_contents($path, json_encode($ips), LOCK_EX);
        return $ips;
    }
    return $cached;
}

// exact match, else same /24 (PIA rents whole blocks, and the list only shows some servers at a time)
function pia_match($ip, $ips)
{
    if (isset($ips[$ip])) return ['exact', $ips[$ip]];
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return null;
    $block = substr($ip, 0, strrpos($ip, '.') + 1);
    foreach ($ips as $k => $region) if (strpos($k, $block) === 0) return ['block', $region];
    return null;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ips = pia_ips();
$match = $ips ? pia_match($ip, $ips) : null;
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
        <h1 class="page-title">VPN Check</h1>
        <p class="page-lede">Checks whether you're connecting through a Private Internet Access (PIA) server.</p>

        <div class="grid">
            <section class="card">
                <h2>Result</h2>
                <?php if (!$ips): ?>
                    <p class="err">Couldn't load PIA's server list. Try again in a minute.</p>
                <?php elseif ($match && $match[0] === 'exact'): ?>
                    <p class="ok">PIA is on. You're connected through <?= h($match[1]) ?>.</p>
                <?php elseif ($match): ?>
                    <p class="ok">PIA is probably on. Your IP is in the same block as PIA's <?= h($match[1]) ?> servers.</p>
                <?php else: ?>
                    <p class="err">PIA is off. Your IP isn't on PIA's server list.</p>
                <?php endif; ?>
                <details>
                    <summary>Show your IP</summary>
                    <p class="mono"><?= h($ip) ?></p>
                </details>
                <p class="muted">Matched against <?= number_format(count((array)$ips)) ?> servers from PIA's public list, refreshed daily. Reload the page after switching the VPN on or off.</p>
            </section>
        </div>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
