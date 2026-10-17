<?php
// The header markup lives in /php/header.html (shared with plain HTML pages).
// gtag, the menu buttons, footer text etc. are set up by /php/layout.js.
?>
<link rel="stylesheet" href="/css/global.css">
<script src="/php/layout.js" defer></script>
<?php readfile(__DIR__ . '/header.html'); ?>
