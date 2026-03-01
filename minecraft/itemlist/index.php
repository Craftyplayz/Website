<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Images</title>
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
    <div class="grid">
        <?php
        $dir = __DIR__;
        $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
        
        $files = scandir($dir);
        
        foreach ($files as $file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, $extensions)) {
                echo '<img src="' . htmlspecialchars($file) . '" alt="">';
            }
        }
        ?>
    </div>
</body>
</html>