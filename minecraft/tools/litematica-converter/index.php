<?php
$site_page = [
    'components' => true,
    'ui' => true,
    'css' => ['/assets/css/pages/minecraft-data.css'],
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
        <h1 class="page-title">Litematica Material Converter</h1>
        <p class="page-lede">Paste a Litematica material list to see stacks and shulker boxes, tick what you have, then copy it to Google Sheets.</p>

        <div class="data-cards">
            <section class="card">
                <h2>Material list</h2>
                <textarea class="field data-text" id="input" aria-label="Material list" placeholder="Paste Litematica material list here…" spellcheck="false"></textarea>
                <div class="actions">
                    <button class="btn primary" type="button" id="convert">Convert</button>
                </div>
            </section>

            <section class="card hide" id="result">
                <h2>Materials <span class="n" id="rowcount"></span> <button class="btn small" type="button" id="copy">Copy for Sheets</button></h2>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Item</th><th class="num">Total</th><th class="num">Missing</th><th class="num">Available</th><th class="num">Stacks</th><th class="num">Shulkers</th><th class="cb-cell">Have it?</th></tr></thead>
                        <tbody id="rows"></tbody>
                    </table>
                </div>
                <p class="muted lc-note">Stacks use real stack sizes (64, 16 or 1). Shulkers = stacks ÷ 27. "Have it?" copies as TRUE/FALSE.</p>
            </section>
        </div>
    </main>
    <?php site_footer(); ?>
    <script src="<?= h(asset('/minecraft/tools/shulker-calculator/schematic.js')) ?>"></script>
    <script>
        // ui.js is deferred
        document.addEventListener('DOMContentLoaded', () => {
            const { $, $$, esc, num, toast, copy } = UI;
            let rows = [];

            // Litematica prints display names ("Oak Planks"); the stack-size table wants IDs ("oak_planks")
            const stackOf = (name) => Schematic.stackSize(name.toLowerCase().replace(/[\s-]+/g, '_'));
            const toInt = (s) => parseInt(s.replace(/[,\s]/g, ''), 10) || 0;
            function stacksText(n, size) {
                const full = Math.floor(n / size), rest = n % size;
                return (full ? num(full) + ' × ' + size : '') + (full && rest ? ' + ' : '') + (rest ? num(rest) : '') || '0';
            }

            function parse(text) {
                const out = [];
                for (const line of text.split('\n')) {
                    if (!line.includes('|') || line.includes('Material List for schematic') || line.includes('+---')) continue;
                    const parts = line.split('|').map((x) => x.trim()).filter((x) => x.length);
                    if (parts.length !== 4 || parts[0] === 'Item') continue;
                    const [name, total, missing, available] = parts, size = stackOf(name), t = toInt(total);
                    const stacks = Math.ceil(t / size);
                    out.push({ name, total: t, missing: toInt(missing), available: toInt(available), size, stacks, shulkers: stacks / 27 });
                }
                return out;
            }

            function convert() {
                rows = parse($('#input').value);
                $('#result').classList.toggle('hide', !rows.length);
                if (!rows.length) return toast('No material rows found in that text.', 'err');
                const boxes = Math.ceil(rows.reduce((a, r) => a + r.stacks, 0) / 27);
                $('#rowcount').textContent = `${num(rows.length)} items · ${num(boxes)} shulker boxes`;
                $('#rows').innerHTML = rows.map((r, i) => `<tr>
                    <td>${esc(r.name)}</td>
                    <td class="num">${num(r.total)}</td>
                    <td class="num">${num(r.missing)}</td>
                    <td class="num">${num(r.available)}</td>
                    <td class="num">${stacksText(r.total, r.size)}</td>
                    <td class="num">${num(r.shulkers, 2)}</td>
                    <td class="cb-cell"><input type="checkbox" data-i="${i}" aria-label="Have ${esc(r.name)}"></td>
                </tr>`).join('');
            }

            function tsv() {
                const have = new Set($$('#rows input:checked').map((c) => +c.dataset.i));
                const head = ['Item', 'Total', 'Missing', 'Available', 'Stacks', 'Shulker boxes', 'Have it?'];
                return [head, ...rows.map((r, i) => [r.name, r.total, r.missing, r.available, r.stacks, r.shulkers.toFixed(2), have.has(i) ? 'TRUE' : 'FALSE'])]
                    .map((r) => r.join('\t')).join('\n');
            }

            $('#convert').addEventListener('click', convert);
            $('#copy').addEventListener('click', (e) => copy(tsv(), e.currentTarget));
        });
    </script>
</body>

</html>
