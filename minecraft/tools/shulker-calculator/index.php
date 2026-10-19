<?php
$site_page = [
    'components' => true,
    'ui' => true,
    'css' => ['/assets/css/pages/minecraft-data.css', '/assets/css/pages/minecraft-shulker.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
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
        <h1 class="page-title">Shulker Schematic Calculator</h1>
        <p class="page-lede">Drop a schematic to get its item list and how many copies fit in a shulker box.</p>

        <label class="drop" id="drop">
            <input type="file" id="file" accept=".litematic,.schem,.schematic,.nbt" hidden>
            <b>Choose a schematic</b> <span class="muted">or drop it here · .litematic, .schem, .schematic, .nbt</span>
        </label>
        <p class="err hide" id="err" role="alert"></p>

        <div class="data-cards hide" id="out">
            <section class="card">
                <h2>Fit <span class="n" id="filename"></span></h2>
                <div class="stabs" id="slots" role="group" aria-label="Container">
                    <button type="button" class="on" data-slots="27" aria-pressed="true">Shulker box</button>
                    <button type="button" data-slots="36" aria-pressed="false">Inventory</button>
                    <button type="button" data-slots="54" aria-pressed="false">Double chest</button>
                </div>
                <div class="tiles">
                    <div class="tile"><b id="copies">0</b><span id="copies-label">copies per shulker box</span></div>
                    <div class="tile"><b id="boxes">0</b><span>shulker boxes for one copy</span></div>
                    <div class="tile"><b id="blocks">0</b><span>items in total</span></div>
                    <div class="tile"><b id="dims">-</b><span>size</span></div>
                </div>
                <div class="progress sc-meter"><i id="meter"></i></div>
                <p class="muted sc-note" id="fitnote"></p>
            </section>

            <section class="card">
                <h2>Materials <span class="n" id="types"></span> <button class="btn primary small" id="export" type="button">Export CSV</button></h2>
                <div class="table-wrap">
                    <table class="data-table sc-table">
                        <thead><tr><th>Item</th><th class="num">Count</th><th class="num">Stacks</th></tr></thead>
                        <tbody id="rows"></tbody>
                    </table>
                </div>
                <p class="muted sc-note">Read in your browser, never uploaded. Chest contents and entities aren't counted.</p>
            </section>
        </div>
    </main>
    <?php site_footer(); ?>
    <script src="<?= h(asset('/minecraft/tools/shulker-calculator/schematic.js')) ?>"></script>
    <script>
        // ui.js is deferred
        document.addEventListener('DOMContentLoaded', () => {
            const { $, esc, num } = UI;
            const ICONS = '/minecraft/tools/item-list/';
            let parsed = null, fileBase = 'schematic', slots = 27;

            const label = (id) => id.replace(/^[^:]*:/, '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
            function stacksText(n, size) {
                const full = Math.floor(n / size), rest = n % size;
                return (full ? num(full) + ' × ' + size : '') + (full && rest ? ' + ' : '') + (rest ? num(rest) : '');
            }

            function renderFit() {
                const f = Schematic.fit(parsed.counts, slots), name = $('#slots .on').textContent.toLowerCase();
                $('#copies').textContent = num(f.copies);
                $('#copies-label').textContent = 'copies per ' + name;
                $('#boxes').textContent = num(Math.ceil(f.perCopy / 27));
                $('#meter').style.width = (f.copies ? f.used / slots * 100 : Math.min(100, f.perCopy / slots * 100)) + '%';
                $('#fitnote').textContent = f.copies
                    ? `${num(f.copies)} ${f.copies === 1 ? 'copy uses' : 'copies use'} ${f.used} of ${slots} slots. One copy needs ${f.perCopy}.`
                    : `One copy needs ${num(f.perCopy)} slots, more than a ${name} holds.`;
            }

            function render() {
                const list = [...parsed.counts].sort((a, b) => b[1] - a[1]);
                $('#types').textContent = num(list.length) + ' types';
                $('#blocks').textContent = num(list.reduce((a, [, n]) => a + n, 0));
                $('#dims').textContent = parsed.dims ? parsed.dims.join('×') : parsed.regions + ' regions';
                $('#rows').innerHTML = list.map(([id, n]) => {
                    const mc = id.startsWith('minecraft:'), size = Schematic.stackSize(id);
                    const icon = `<img src="${ICONS}${mc ? 'minecraft_' + esc(id.slice(10)) : 'canUse_unknown'}.png" width="24" height="24" alt="" loading="lazy" decoding="async">`;
                    return `<tr><td><span class="sc-item" title="${esc(id)}">${icon}<span>${esc(label(id))}</span></span></td>` +
                        `<td class="num">${num(n)}</td><td class="num">${stacksText(n, size)}</td></tr>`;
                }).join('');
                renderFit();
                $('#out').classList.remove('hide');
            }

            function fail(msg) {
                $('#err').textContent = msg;
                $('#err').classList.remove('hide');
                $('#out').classList.add('hide');
            }

            async function load(file) {
                if (!file) return;
                $('#err').classList.add('hide');
                $('#drop b').textContent = 'Reading ' + file.name + '…';
                try {
                    parsed = await Schematic.readFile(await file.arrayBuffer());
                    if (!parsed.counts.size) throw Error('No blocks found in it.');
                    fileBase = file.name.replace(/\.[^.]+$/, '');
                    $('#filename').textContent = file.name;
                    render();
                    if (parsed.legacy) UI.toast('Old numeric block IDs: only the common ones are named, the rest show as legacy:block_<id>.', 'note');
                } catch (e) {
                    fail(`Couldn't read ${file.name}: ${e.message}`);
                }
                $('#drop b').textContent = 'Choose another schematic';
            }

            const drop = $('#drop');
            $('#file').addEventListener('change', (e) => load(e.target.files[0]));
            drop.addEventListener('dragover', (e) => { e.preventDefault(); drop.classList.add('over'); });
            drop.addEventListener('dragleave', () => drop.classList.remove('over'));
            drop.addEventListener('drop', (e) => { e.preventDefault(); drop.classList.remove('over'); load(e.dataTransfer.files[0]); });

            $('#slots').addEventListener('click', (e) => {
                const b = e.target.closest('button');
                if (!b) return;
                for (const x of $('#slots').children) { x.classList.toggle('on', x === b); x.setAttribute('aria-pressed', x === b); }
                slots = +b.dataset.slots;
                renderFit();
            });

            // missing textures (modded or brand-new items) fall back to the unknown icon
            $('#rows').addEventListener('error', (e) => {
                if (e.target.tagName === 'IMG' && !e.target.src.endsWith('canUse_unknown.png')) e.target.src = ICONS + 'canUse_unknown.png';
            }, true);

            $('#export').addEventListener('click', () => {
                const rows = [['Item', 'ID', 'Count', 'Stack size'], ...[...parsed.counts].sort((a, b) => b[1] - a[1]).map(([id, n]) => [label(id), id, n, Schematic.stackSize(id)])];
                const csv = rows.map((r) => r.map((v) => '"' + String(v).replace(/"/g, '""') + '"').join(',')).join('\r\n');
                const a = document.createElement('a');
                a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
                a.download = fileBase + '-materials.csv';
                a.click();
                URL.revokeObjectURL(a.href);
            });
        });
    </script>
</body>

</html>
