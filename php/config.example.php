<?php
// Template for php/config.php (which is gitignored).
// Copy this file to config.php on the server and fill in the keys.
// Pages include config.php straight after header.php, so it also opens the
// <div class="content"> wrapper that footer.php closes.

session_start();
$timezone = $_SESSION['time'];
?>

<?php
/* steam */
global $steamkey;
global $steamid;
$steamkey = '';
$steamid = '';
?>

<?php
/* Retro Achievements */
global $retrokey;
$retrokey = '';
?>

<?php
/* Google Books */
global $googlebookskey;
$googlebookskey = '';
?>

<?php
/* Jellyfin */
global $jellyfinkey;
$jellyfinkey = '';
$jellyfinurl = '';
?>

<?php
/* Hypixel */
global $hypixelkey;
$hypixelkeyapp = '';
$hypixelkey = '';
$hypixelurl = '';
?>

<?php
/* apininja */
global $apininjakey;
$apininjakey = '';
?>

<?php
/* minecraftitems.xyz (used by /minecraft/tools/nbt-viewer/api.php) */
global $mcitemskey;
$mcitemskey = '';
?>

<?php
/* Putty */
$puttydir = '';
?>

<?php
$boorupassword = '';
?>

<div class="content">
