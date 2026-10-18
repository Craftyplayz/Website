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
    <h1>Files for download</h1>
</div>
<div class="title">
<?php
    $directory = __DIR__;
    $excludedFiles = ['.', '..', 'index.php', 'jelly.php'];

    $files = scandir($directory);
    foreach ($files as $file) {
        if (!in_array($file, $excludedFiles) && is_file($directory . '/' . $file)) {
            echo '<a href="/private/downloads/' . rawurlencode($file) . '">' . $file . '</a><br>';
        }
    }
?>
</div>



<?php
echo '</main>'; site_footer();
?>

</body>
</html>