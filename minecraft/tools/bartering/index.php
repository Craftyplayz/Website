<?php
$site_page = [
    'components' => true,
    'ui' => true,
    'css' => ['/assets/css/pages/minecraft-forms.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <style>
        .barter-icon { width: 24px; height: 24px; image-rendering: pixelated; vertical-align: middle; margin-right: 8px; }
        .barter-icon[hidden] { display: none; }
        .barter .stat-row { margin-top: 20px; }
        .barter .table-wrap { margin-top: 16px; }
    </style>
</head>

<body>
    <?php site_header(); ?>
    <main class="page barter" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Piglin Bartering</h1>
        <p class="page-lede">Enter how many piglins your farm has to see what it produces and how many hoppers each item needs.</p>

        <div class="card form-card">
            <div class="form">
                <label>Piglins
                    <input class="field" type="number" id="piglins" inputmode="numeric" min="1" max="1000000" step="1" value="768">
                </label>
            </div>
        </div>

        <div class="stat-row">
            <div class="stat-card"><b id="s-items">0</b><span>items per hour</span></div>
            <div class="stat-card"><b id="s-hoppers">0</b><span>hoppers in total</span></div>
            <div class="stat-card"><b id="s-gold">0</b><span>gold ingots used per hour</span></div>
        </div>

        <div class="card">
            <h2>Items <span class="n">each piglin barters 10 times a minute</span></h2>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Item</th><th class="num">Per ingot</th><th class="num">Items per hour</th><th class="num">Hoppers</th></tr></thead>
                <tbody id="rows"></tbody>
            </table></div>
            <p class="muted">Hoppers in parallel needed for each item, at 9,000 items per hour per hopper line.</p>
        </div>
    </main>
    <?php site_footer(null, false); ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const { $, debounce, num } = UI;
            const WEIGHT = 459, PER_HOUR = 600, LINE = 9000;
            // [name, icon id, min, max, weight]
            const LOOT = [
                ['Soul Speed book', 'enchanted_book', 1, 1, 5], ['Iron boots (Soul Speed)', 'iron_boots', 1, 1, 8],
                ['Splash potion (Fire Resistance)', 'splash_potion', 1, 1, 8], ['Potion (Fire Resistance)', 'potion', 1, 1, 8],
                ['Water bottle', 'glass_bottle', 1, 1, 10], ['Obsidian', 'obsidian', 1, 1, 40], ['Fire charge', 'fire_charge', 1, 1, 40],
                ['Crying obsidian', 'crying_obsidian', 1, 3, 40], ['Ender pearl', 'ender_pearl', 2, 4, 10], ['String', 'string', 3, 9, 20],
                ['Leather', 'leather', 2, 4, 40], ['Nether quartz', 'quartz', 5, 12, 20], ['Soul sand', 'soul_sand', 2, 8, 40],
                ['Nether brick', 'nether_brick', 2, 8, 40], ['Iron nugget', 'iron_nugget', 10, 36, 10], ['Spectral arrow', 'spectral_arrow', 6, 12, 40],
                ['Blackstone', 'blackstone', 8, 16, 40], ['Gravel', 'gravel', 8, 16, 40],
            ];
            const fmt = (n, d = 2) => n.toLocaleString('en-GB', { maximumFractionDigits: d });
            $('#rows').innerHTML = LOOT.map(([name, id]) =>
                `<tr><td><img class="barter-icon" src="/minecraft/tools/item-list/minecraft_${id}.png" alt="" width="24" height="24" onerror="this.hidden=true">${UI.esc(name)}</td>` +
                '<td class="num"></td><td class="num"></td><td class="num"></td></tr>').join('');
            const trs = [...$('#rows').children];

            function calc() {
                const el = $('#piglins'), p = Math.floor(Number(el.value));
                const ok = p >= 1 && p <= 1e6;
                el.setAttribute('aria-invalid', !ok);
                if (!ok) return;
                history.replaceState(null, '', p === 768 ? location.pathname : '?piglins=' + p);
                let items = 0, hoppers = 0;
                LOOT.forEach(([, , min, max, w], i) => {
                    const per = (min + max) / 2 * w / WEIGHT, hour = per * PER_HOUR * p, h = Math.ceil(hour / LINE);
                    items += hour; hoppers += h;
                    const c = trs[i].children;
                    c[1].textContent = fmt(per, 3); c[2].textContent = num(Math.round(hour)); c[3].textContent = num(h);
                });
                $('#s-items').textContent = num(Math.round(items));
                $('#s-hoppers').textContent = num(hoppers);
                $('#s-gold').textContent = num(p * PER_HOUR);
            }

            const q = Number(new URLSearchParams(location.search).get('piglins'));
            if (q >= 1) $('#piglins').value = Math.floor(q);
            $('#piglins').addEventListener('input', debounce(calc, 120));
            calc();
        });
    </script>
</body>

</html>
