<?php
// Old shop pages include this instead of header.php. The shop now uses the normal site header,
// which adds the shop tabs and logo itself (see "Captainalism Shop" in data/site.json).
include_once $_SERVER['DOCUMENT_ROOT'] . '/php/header.php';
echo '<link rel="stylesheet" href="/shop/styles.css">' . "\n";
