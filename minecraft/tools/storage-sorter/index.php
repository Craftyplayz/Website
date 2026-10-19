<?php
$site_page = [
    'components' => true,
    'ui' => true,
    'css' => ['/assets/css/pages/minecraft-storage-sorter.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

// every survival item by its in-game name; Item List's textures give the IDs that exist
$items = array_values(array_filter(array_map('trim', file(__DIR__ . '/items.txt') ?: [])));
$ids = [];
foreach (scandir(__DIR__ . '/../item-list') ?: [] as $f) {
    if (preg_match('/^minecraft_(.+)\.png$/', $f, $m)) $ids[] = $m[1];
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
        <h1 class="page-title">Storage Sorter</h1>
        <p class="page-lede">Paste your sorted item list to see which of the <?= number_format(count($items)) ?> survival items aren't categorised yet.</p>

        <div class="ss">
            <section class="card">
                <h2>Your sorted items</h2>
                <textarea class="field ss-input" id="sheet" aria-label="Your sorted items" spellcheck="false" placeholder="Paste from Google Sheets: one item per row, extra columns are fine"></textarea>
                <p class="muted ss-hint">Names match the in-game names, ignoring case. Categories and counts in other columns are ignored.</p>
                <button class="btn primary" id="run" type="button">Find unsorted items</button>
            </section>

            <section class="card hide" id="dupes">
                <h2>Listed more than once <span class="n" id="dupecount"></span></h2>
                <div class="ss-list" id="dupelist"></div>
            </section>

            <section class="card hide" id="results">
                <h2>Unsorted items <span class="n" id="count"></span> <button class="btn small" id="copylist" type="button">Copy list</button></h2>
                <div class="ss-bar">
                    <input class="field" type="search" id="filter" placeholder="Filter items" aria-label="Filter unsorted items" autocomplete="off">
                    <span class="muted" id="matched"></span>
                </div>
                <div class="ss-list" id="list"></div>
                <button class="btn ss-give" id="give" type="button">Make /give commands</button>
            </section>

            <section class="card hide" id="commands">
                <h2>/give commands <span class="n" id="cmdcount"></span></h2>
                <p class="muted ss-hint" id="cmdhint"></p>
                <div class="ss-cmds" id="cmdlist"></div>
            </section>
        </div>
    </main>
    <?php site_footer(); ?>
    <script src="<?= h(asset('/minecraft/tools/shulker-calculator/schematic.js')) ?>"></script>
    <script src="<?= h(asset('/minecraft/tools/storage-sorter/sorter.js')) ?>"></script>
    <script>
        const ALL = <?= json_encode($items, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
        const IDS = new Set(<?= json_encode($ids, JSON_HEX_TAG) ?>);
        // ui.js is deferred
        document.addEventListener('DOMContentLoaded', () => {
            const { $, $$, esc, num, copy, debounce } = UI;
            let unsorted = [], commands = [];
            const icon = (name) => `/minecraft/tools/item-list/minecraft_${encodeURIComponent(Sorter.nameToId(name, IDS).slice(10))}.png`;
            const plural = (n, word) => num(n) + ' ' + word + (n === 1 ? '' : 's');

            $('#run').addEventListener('click', () => {
                const raw = $('#sheet').value;
                if (!raw.trim()) return UI.toast('Paste your sorted item list first.', 'err');
                const r = Sorter.compare(ALL, raw);
                unsorted = r.unsorted;

                $('#dupes').classList.toggle('hide', !r.duplicates.length);
                $('#dupecount').textContent = plural(r.duplicates.length, 'item');
                $('#dupelist').innerHTML = r.duplicates.map(([name, n]) => `<div class="rowi"><span class="grow">${esc(name)}</span><small>×${n}</small></div>`).join('');

                $('#count').textContent = plural(unsorted.length, 'item');
                $('#matched').textContent = `${num(r.matched)} of ${num(ALL.length)} sorted`;
                $('#list').innerHTML = unsorted.length
                    ? unsorted.map((n) => `<div class="rowi"><img src="${icon(n)}" width="24" height="24" alt="" loading="lazy" decoding="async"><span class="grow">${esc(n.replace(/[​-‍﻿]/g, ''))}</span></div>`).join('')
                    : '<p class="ok">Everything is sorted.</p>';
                $('#filter').value = '';
                $('#give').disabled = !unsorted.length;
                $('#results').classList.remove('hide');
                $('#commands').classList.add('hide');
                $('#results').scrollIntoView({ behavior: 'smooth', block: 'start' });
            });

            $('#filter').addEventListener('input', debounce((e) => {
                const q = e.target.value.trim().toLowerCase();
                let shown = 0;
                for (const el of $$('#list .rowi')) {
                    const hit = !q || el.textContent.toLowerCase().includes(q);
                    el.classList.toggle('hide', !hit);
                    shown += hit;
                }
                $('#count').textContent = q ? `${num(shown)} of ${plural(unsorted.length, 'item')}` : plural(unsorted.length, 'item');
            }, 80));

            // textures for brand-new items fall back to the unknown icon
            $('#list').addEventListener('error', (e) => {
                if (e.target.tagName === 'IMG' && !e.target.src.endsWith('canUse_unknown.png')) e.target.src = '/minecraft/tools/item-list/canUse_unknown.png';
            }, true);

            $('#copylist').addEventListener('click', (e) => copy($$('#list .rowi:not(.hide)').map((el) => el.textContent).join('\n'), e.currentTarget));

            $('#give').addEventListener('click', () => {
                commands = Sorter.giveCommands(unsorted, IDS, Schematic.stackSize);
                $('#cmdcount').textContent = plural(commands.length, 'chest');
                $('#cmdhint').textContent = `One full stack of each of the ${plural(unsorted.length, 'item')}, packed into shulker boxes. Too long for chat: paste into a command block (needs operator).`;
                $('#cmdlist').innerHTML = commands.map((c, i) => `<div class="ss-cmd">
                    <div class="ss-cmd-head"><span class="muted">${commands.length > 1 ? `Chest ${i + 1} of ${commands.length}` : 'Command'}</span><button class="btn small" type="button" data-i="${i}">Copy</button></div>
                    <pre>${esc(c)}</pre></div>`).join('');
                $('#commands').classList.remove('hide');
                $('#commands').scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
            $('#cmdlist').addEventListener('click', (e) => {
                const b = e.target.closest('button[data-i]');
                if (b) copy(commands[b.dataset.i], b);
            });
        });
    </script>
</body>

</html>
