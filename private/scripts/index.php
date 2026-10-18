<?php
$site_page = ['legacy' => true];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php page_head(); ?>
</head>
<body>
<?php
site_header(); echo '<main class="page page-legacy" id="main">';
?>

<div class="title">
    <h1>Scripts</h1>
</div>
<div class="title">
<?php
$dir = __DIR__;              // current folder
$items = scandir($dir);

foreach ($items as $item) {
    if ($item === '.' || $item === '..') continue;   // skip system entries

    // skip index.php file
    if ($item === 'index.php') continue;

    echo '<a href="' . htmlspecialchars($item) . '">' . htmlspecialchars($item) . '</a><br>';
}
?>

</div>



<?php
echo '</main>'; site_footer();
?>

</body>
</html>