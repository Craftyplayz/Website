<?php
// The header or footer as an HTML fragment, for plain .html pages.
// assets/js/site.js fetches it for <div id="site-header"> / <div id="site-footer"> placeholders:
//     /includes/header-fragment.php?part=header&path=/tests/speedtest/

require __DIR__ . '/bootstrap.php';

$path = isset($_GET['path']) ? (string)$_GET['path'] : '/';
if ($path === '' || $path[0] !== '/' || strlen($path) > 300) $path = '/';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=300');
header('X-Robots-Tag: noindex');

if (isset($_GET['part']) && $_GET['part'] === 'footer') {
    site_footer($path);
} else {
    // same stylesheets the PHP header adds (old pages still rely on /assets/css/legacy.css)
    echo '<link rel="stylesheet" href="' . h(asset('/assets/css/legacy.css')) . '">';
    echo '<link rel="stylesheet" href="' . h(asset('/assets/css/global.css')) . '">';
    site_header($path);
}
