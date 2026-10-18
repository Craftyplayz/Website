<?php
$site_page = ['legacy' => true];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <style>
        .folder-list {
            max-width: 700px;
            margin: 20px auto;
        }

        details {
            margin: 10px 0;
            border: 1px solid #444;
            border-radius: 8px;
            padding: 8px 12px;
            background: #222;
            color: #fff;
        }

        summary {
            cursor: pointer;
            font-weight: bold;
            font-size: 1.05rem;
            outline: none;
        }

        a {
            display: block;
            margin: 4px 0 4px 12px;
            text-decoration: none;
            color: #9cf;
            font-size: 0.95rem;
        }

        a:hover {
            color: #fff;
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <?php site_header(); ?>
    <main class="page page-legacy" id="main">

    <div class="title">
        <h1>Template</h1>
    </div>

    <div class="folder-list">
        <?php
        $baseDir = __DIR__;
        $items = scandir($baseDir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            $path = $baseDir . '/' . $item;

            if (is_dir($path)) {
                echo "<details><summary>" . htmlspecialchars(ucwords(str_replace('-', ' ', $item))) . "</summary>";

                $files = scandir($path);
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') continue;
                    $filePath = htmlspecialchars($item . '/' . $file . '/');
                    $fileName = htmlspecialchars(ucwords(str_replace('-', ' ', $file)));
                    echo "<a href=\"$filePath\" target=\"_blank\">$fileName</a>";
                }

                echo "</details>";
            }
        }
        ?>
    </div>

    </main>
    <?php site_footer(); ?>

</body>

</html>
