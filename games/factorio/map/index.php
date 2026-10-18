<?php
// Full-screen embed of one map; ?map= must be a folder name on assets.craftyplayz.com (no slashes or quotes)
$map = preg_replace('/[^A-Za-z0-9_-]/', '', isset($_GET['map']) ? (string)$_GET['map'] : '');
?>
<iframe src="https://assets.craftyplayz.com/factorio/<?= htmlspecialchars($map, ENT_QUOTES) ?>/" width="100%" height="100%" frameborder="0">Server Currently Offline, Please check back later</iframe>
