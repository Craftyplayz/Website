<?php
$site_page = [
    'components' => true,
    'ui' => true,
    'css' => ['/assets/css/pages/minecraft-items.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

$extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
$items = [];
foreach (scandir(__DIR__) ?: [] as $file) {
    if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $extensions)) {
        $items[$file] = ucwords(str_replace('_', ' ', preg_replace('/^minecraft_/', '', pathinfo($file, PATHINFO_FILENAME))));
    }
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
        <h1 class="page-title">Item List</h1>
        <p class="page-lede">Every Minecraft item texture.</p>

        <div class="bar">
            <input class="field" type="search" id="q" placeholder="Filter items" aria-label="Filter items" autocomplete="off">
            <span class="muted" id="count"><?= number_format(count($items)) ?> items</span>
        </div>

        <div class="items" id="items">
            <?php foreach ($items as $file => $name): ?>
                <figure class="item"><img src="<?= h(rawurlencode($file)) ?>" width="64" height="64" alt="" loading="lazy" decoding="async"><figcaption><?= h($name) ?></figcaption></figure>
            <?php endforeach; ?>
        </div>
    </main>
    <?php site_footer(); ?>
    <script>
        // ui.js is deferred
        document.addEventListener('DOMContentLoaded', () => {
            const { $, $$, debounce } = UI;
            const items = $$('#items .item').map(el => [el, el.textContent.toLowerCase()]);
            const total = items.length;
            $('#q').addEventListener('input', debounce(e => {
                const q = e.target.value.trim().toLowerCase();
                let shown = 0;
                for (const [el, name] of items) {
                    const hit = !q || name.includes(q);
                    el.classList.toggle('hide', !hit);
                    shown += hit;
                }
                $('#count').textContent = q ? `${shown.toLocaleString()} of ${total.toLocaleString()} items` : `${total.toLocaleString()} items`;
            }, 80));
        });
    </script>
</body>

</html>
