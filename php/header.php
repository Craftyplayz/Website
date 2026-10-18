<?php
// Old pages include this inside <body>. The header now comes from /includes/ and data/site.json.
// New pages use /includes/bootstrap.php directly - see _templates/page.php.
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

if (empty($GLOBALS['site_head_done'])) {
    // old page styles first, so the new header styles win
    echo '<link rel="stylesheet" href="' . h(asset('/assets/css/legacy.css')) . '">' . "\n";
    echo '<link rel="stylesheet" href="' . h(asset('/assets/css/global.css')) . '">' . "\n";
    echo '<script src="' . h(asset('/assets/js/site.js')) . '" defer></script>' . "\n";
}
site_header();
