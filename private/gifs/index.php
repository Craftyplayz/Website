<?php
// Meme GIF editor: upload a GIF, place text boxes per frame, save as a template for /gifs/<group>/<name>/<text>.gif
$site_page = ['title' => 'Meme GIFs', 'components' => true, 'ui' => true, 'css' => ['/assets/css/pages/private-gifs.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

$root = $_SERVER['DOCUMENT_ROOT'] . '/gifs';
$templates = [];
foreach (glob("$root/templates/*/*/template.json") ?: [] as $f) {
    $templates[basename(dirname($f, 2)) . '/' . basename(dirname($f))] = filemtime($f);
}
$fonts = array_map('basename', glob("$root/fonts/*.ttf") ?: []);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <meta name="robots" content="noindex, nofollow">
</head>

<body>
    <?php site_header(); ?>

    <main class="page" id="main">
        <h1 class="page-title">Meme GIFs</h1>
        <p class="page-lede">Upload a GIF, place the text, then share <span class="mono">/gifs/group/name/your_text.gif</span>.</p>

        <div class="gif-layout">
            <section class="card gif-side">
                <h2>Templates <span class="n"><?= count($templates) ?></span></h2>
                <div class="rows" id="list">
                    <?php foreach ($templates as $t => $mtime): ?>
                        <a class="rowi" href="?t=<?= h($t) ?>" data-t="<?= h($t) ?>"><span class="grow mono"><?= h($t) ?></span></a>
                    <?php endforeach; ?>
                </div>
                <div class="drop gif-drop" id="drop">
                    Drop a GIF here or <button class="btn small" id="pick" type="button">choose one</button>
                    <input type="file" id="file" accept="image/gif" class="hide">
                </div>
            </section>

            <section class="card gif-main hide" id="editor">
                <div class="gif-names">
                    <label>Group <input class="field" id="group" placeholder="atla" pattern="[a-z0-9-]+" maxlength="40"></label>
                    <label>Name <input class="field" id="name" placeholder="nowar" pattern="[a-z0-9-]+" maxlength="40"></label>
                </div>

                <canvas id="cv" class="gif-canvas" aria-label="Preview: click or drag to place the selected text on this frame"></canvas>

                <div class="gif-play">
                    <button class="btn small" id="play" type="button">Pause</button>
                    <input type="range" id="frame" min="0" value="0" aria-label="Frame">
                    <span class="muted mono" id="frameNo"></span>
                </div>

                <h2>Text</h2>
                <div class="stabs" id="boxTabs"></div>
                <div id="boxForm" class="gif-form">
                    <label class="gif-wide">Text <input class="field" id="bText" placeholder="There's no {thing} in {place = Ba Sing Se}."></label>
                    <p class="muted gif-wide">Plain words always show. <b>{thing}</b> is filled from the URL (blank if left out). <b>{place = Ba Sing Se}</b> uses "Ba Sing Se" when the URL leaves it out. Long text shrinks to fit the max width and the picture. <button class="btn small" id="addSlot" type="button">Add slot</button></p>
                    <label>Font <select class="field" id="bFont"><?php foreach ($fonts as $f): ?><option><?= h($f) ?></option><?php endforeach; ?></select></label>
                    <label>Size <input class="field" id="bSize" type="number" min="4" max="300"></label>
                    <label>Max width <input class="field" id="bMaxW" type="number" min="0"></label>
                    <label>Fill <input id="bColor" type="color"></label>
                    <label>Outline <input id="bStroke" type="color"></label>
                    <label>Outline width <input class="field" id="bStrokeW" type="number" min="0" max="20"></label>
                    <label class="gif-wide">Motion
                        <select class="field" id="bMotion">
                            <option value="step">Frame by frame: jump to each position and hold it</option>
                            <option value="smooth">Smooth: glide between positions at a steady rate</option>
                        </select>
                    </label>
                </div>

                <h2>URL parts <span class="n" id="slotCount"></span></h2>
                <p class="muted" id="slotHelp">Each slot is one part of the link, in this order. Type in the boxes to try it out; "-" in a link skips a part.</p>
                <div class="rows" id="slots"></div>

                <h2>Positions <span class="n" id="keyCount"></span></h2>
                <p class="muted">Click or drag on the picture to place the text on the current frame. Hidden hides it from that frame until the next position.</p>
                <div class="rows" id="keys"></div>
                <div class="gif-actions">
                    <button class="btn small" id="addBox" type="button">Add text</button>
                    <button class="btn small" id="delBox" type="button">Remove this text</button>
                </div>

                <div class="gif-save">
                    <button class="btn primary" id="save" type="button">Save</button>
                    <a class="mono gif-link" id="link" target="_blank" rel="noopener"></a>
                    <button class="btn small" id="copy" type="button">Copy link</button>
                </div>
            </section>
        </div>
    </main>

    <?php site_footer(null, false); ?>
    <script>
        const GIFS = <?= json_encode(['templates' => $templates, 'fonts' => $fonts]) ?>;
    </script>
    <script src="<?= asset('/private/gifs/editor.js') ?>" defer></script>
</body>

</html>
