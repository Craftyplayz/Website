<?php
$site_page = [];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php page_head(); ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: #fff;
            padding: 16px;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, 64px);
            gap: 8px;
        }
        img {
            width: 64px;
            height: 64px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <?php site_header(); ?>
    <main class="page" id="main">
    <h1 class="page-title">Item List</h1>
    <p class="page-lede">Every Minecraft item texture. Hover for the name.</p>
    <div class="grid">
        <?php
        $dir = __DIR__;
        $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
        
        $files = scandir($dir);
        
        foreach ($files as $file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, $extensions)) {
                $name = ucwords(str_replace('_', ' ', preg_replace('/^minecraft_/', '', pathinfo($file, PATHINFO_FILENAME))));
                echo '<img src="' . htmlspecialchars($file) . '" alt="' . htmlspecialchars($name) . '" title=' . '"' . htmlspecialchars($name) . '" loading="lazy" decoding="async">';
            }
        }
        ?>
    </div>
    <?php site_footer(); ?>
</body>
</html>