<?php
$site_page = [
    'components' => true,
    'ui' => true,
    'css' => ['/assets/css/pages/minecraft-forms.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
$icons = [];
foreach (scandir(SITE_ROOT . '/assets/img/markers/') ?: [] as $file) {
    if ($file[0] !== '.') $icons[] = substr($file, 0, -4);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>
    <main class="page" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Dynmap Marker Creator</h1>
        <p class="page-lede">Fill in the marker, then paste the two commands in game.</p>

        <div class="card form-card">
            <form class="form" id="markerform">
                <label>Label
                    <input class="field" type="text" id="label">
                </label>
                <label>ID
                    <input class="field" type="text" id="id">
                </label>
                <label>Set
                    <select class="field" id="set">
                        <option value="Builds">Builds</option>
                        <option value="Farms">Farms</option>
                        <option value="Other">Other</option>
                    </select>
                </label>
                <label>Icon
                    <span class="row">
                        <img class="icon-preview" id="iconpreview" src="/assets/img/markers/<?= h(rawurlencode($icons[0] ?? '')) ?>.png" width="32" height="32" alt="">
                        <select class="field" id="icon">
                            <?php foreach ($icons as $icon): ?>
                                <option value="<?= h($icon) ?>"><?= h($icon) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </span>
                </label>
                <label>Description
                    <input class="field" type="text" id="description">
                </label>
                <button class="btn primary" type="submit">Generate commands</button>
            </form>
            <div class="out hide" id="output">
                <div class="cmd"><code></code><button class="btn small" type="button">Copy</button></div>
                <div class="cmd"><code></code><button class="btn small" type="button">Copy</button></div>
            </div>
        </div>
    </main>
    <?php site_footer(); ?>
    <script>
        // ui.js is deferred
        document.addEventListener('DOMContentLoaded', () => {
            const { $, $$, copy } = UI;
            $('#icon').addEventListener('change', e => $('#iconpreview').src = '/assets/img/markers/' + encodeURIComponent(e.target.value) + '.png');
            $('#markerform').addEventListener('submit', e => {
                e.preventDefault();
                const v = id => $('#' + id).value;
                const codes = $$('#output code');
                codes[0].textContent = '/dmarker add "' + v('label') + '" id:' + v('id') + ' set:' + v('set') + ' icon:' + v('icon');
                codes[1].textContent = '/dmarker appenddesc id:' + v('id') + ' set:' + v('set') + ' desc:"' + v('description') + '"';
                $('#output').classList.remove('hide');
            });
            $('#output').addEventListener('click', e => {
                const b = e.target.closest('button');
                if (b) copy(b.previousElementSibling.textContent, b);
            });
        });
    </script>
</body>

</html>
