<?php
// Dice fairness test: eight panels roll the homebrew sheet's 3D dice (../dice3d.js) over and over; the chart adds up every result.
$site_page = ['components' => true, 'css' => ['/assets/css/pages/games-dnd-sheet-test.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
$dice = [4, 6, 8, 10, 12, 20, 100];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <meta name="robots" content="noindex">
</head>

<body>
    <?php // no site header or footer here: the sheet and its dice use the whole screen (owner's choice) ?>

    <main class="page dt" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Dice Fairness Test</h1>
        <p class="page-lede">Eight tables roll the Homebrew Sheet's 3D dice over and over; the chart adds up every result so you can see whether each face comes up as often as it should.</p>

        <div class="bar dt-bar">
            <label>Die <select class="field" id="dt-sides">
                <?php foreach ($dice as $d): ?><option value="<?= $d ?>"<?= $d === 20 ? ' selected' : '' ?>>d<?= $d ?></option><?php endforeach; ?>
            </select></label>
            <label>Dice per throw <span class="dt-range"><select class="field" id="dt-min" aria-label="Fewest dice per throw">
                <?php for ($n = 1; $n <= 6; $n++): ?><option<?= $n === 1 ? ' selected' : '' ?>><?= $n ?></option><?php endfor; ?>
            </select> to <select class="field" id="dt-max" aria-label="Most dice per throw">
                <?php for ($n = 1; $n <= 6; $n++): ?><option<?= $n === 1 ? ' selected' : '' ?>><?= $n ?></option><?php endfor; ?>
            </select></span></label>
            <label>Style <select class="field" id="dt-style">
                <option value="purple">Purple</option><option value="gold">Gold</option><option value="silver">Silver</option><option value="obsidian">Obsidian</option>
                <option value="ruby">Ruby</option><option value="wood">Wood</option><option value="marble">Marble</option><option value="ivory">Ivory</option>
            </select></label>
            <label>Speed <select class="field" id="dt-speed">
                <option value="1">1× (real time)</option><option value="2">2×</option><option value="4" selected>4×</option><option value="8">8×</option>
            </select></label>
            <button type="button" class="btn primary" id="dt-go">Start rolling</button>
            <button type="button" class="btn" id="dt-reset">Reset all counts</button>
            <span class="muted" id="dt-count"></span>
        </div>

        <div class="dt-panels">
            <?php for ($i = 0; $i < 8; $i++): ?>
                <figure class="dt-panel">
                    <iframe src="roller.html?v=<?= filemtime(__DIR__ . '/roller.html') ?>" title="Dice table <?= $i + 1 ?>" loading="eager"></iframe>
                    <figcaption>
                        <select class="field dt-pdie" data-i="<?= $i ?>" aria-label="Die for table <?= $i + 1 ?>">
                            <option value="">Same as above</option>
                            <?php foreach ($dice as $d): ?><option value="<?= $d ?>">d<?= $d ?></option><?php endforeach; ?>
                        </select>
                        <span class="muted" data-cap="<?= $i ?>">Table <?= $i + 1 ?></span>
                    </figcaption>
                </figure>
            <?php endfor; ?>
        </div>

        <section class="card dt-results">
            <h2>Results <span class="n" id="dt-n"></span></h2>
            <div class="stabs" id="dt-tabs" role="group" aria-label="Die to show"></div>
            <div class="dt-chart" id="dt-chart" role="img"></div>
            <p class="dt-verdict" id="dt-verdict"></p>
            <details class="dt-table"><summary>The numbers</summary><div class="table-wrap"><table class="table">
                <thead><tr><th>Face</th><th class="num">Rolled</th><th class="num">Expected</th><th class="num">Share</th></tr></thead>
                <tbody id="dt-rows"></tbody>
            </table></div></details>
            <p class="muted dt-hint">Counts are kept in this browser for each kind of die. The check is a chi-square test: it asks how often fair dice would land this unevenly by chance.</p>
        </section>
    </main>


    <script>
        (() => {
            const $ = (s) => document.querySelector(s);
            const frames = [...document.querySelectorAll('.dt-panel iframe')], caps = [...document.querySelectorAll('[data-cap]')], picks = [...document.querySelectorAll('.dt-pdie')];
            const per = frames.map(() => ({ n: 0, last: '' }));
            const KEY = 'dice-test-counts';
            let running = false, counts = {}, shown = 0;
            try { counts = JSON.parse(localStorage.getItem(KEY)) || {}; } catch (e) { }
            const store = () => { try { localStorage.setItem(KEY, JSON.stringify(counts)); } catch (e) { } };
            // a table's die: its own pick, or the page's
            const dieOf = (i) => +(picks[i].value || $('#dt-sides').value);
            const tally = (n) => (counts[n] ||= Array(n).fill(0));
            const total = (n) => (counts[n] || []).reduce((a, b) => a + b, 0);

            // chance of a chi-square value this big or bigger with k degrees of freedom (upper regularised gamma)
            function lnGamma(z) {
                const c = [76.18009172947146, -86.50532032941677, 24.01409824083091, -1.231739572450155, 0.1208650973866179e-2, -0.5395239384953e-5];
                let x = z, y = z, t = x + 5.5; t -= (x + 0.5) * Math.log(t); let s = 1.000000000190015;
                for (const v of c) s += v / ++y;
                return -t + Math.log((2.5066282746310005 * s) / x);
            }
            function chiP(x, k) {
                const a = k / 2, b = x / 2;
                if (b <= 0) return 1;
                if (b < a + 1) { let sum = 1 / a, d = sum, ap = a; for (let i = 0; i < 500; i++) { d *= b / ++ap; sum += d; if (Math.abs(d) < Math.abs(sum) * 1e-12) break; } return 1 - sum * Math.exp(-b + a * Math.log(b) - lnGamma(a)); }
                let f = b + 1 - a, c = 1e300, d = 1 / f, h = d;
                for (let i = 1; i < 500; i++) { const an = -i * (i - a); f += 2; d = an * d + f; c = f + an / c; d = 1 / d; const del = d * c; h *= del; if (Math.abs(del - 1) < 1e-12) break; }
                return Math.exp(-b + a * Math.log(b) - lnGamma(a)) * h;
            }

            function draw() {
                // a tab for every die on a table now or with counts; the chart shows the picked one
                const kinds = [...new Set([...frames.map((_, i) => dieOf(i)), ...Object.keys(counts).map(Number).filter(total)])].sort((a, b) => a - b);
                if (!kinds.includes(shown)) shown = dieOf(0);
                $('#dt-tabs').innerHTML = kinds.map((n) => `<button type="button" data-n="${n}" class="${n === shown ? 'on' : ''}" aria-pressed="${n === shown}">d${n} <span class="muted">${total(n).toLocaleString()}</span></button>`).join('');
                const f = tally(shown), n = total(shown), exp = n / f.length, max = Math.max(1, ...f, exp);
                $('#dt-n').textContent = n.toLocaleString() + ' d' + shown + ' rolled';
                $('#dt-count').textContent = running ? 'Rolling…' : Object.keys(counts).some((k) => total(+k)) ? 'Paused' : '';
                const every = f.length > 20 ? 10 : 1;
                $('#dt-chart').style.setProperty('--faces', f.length);
                $('#dt-chart').setAttribute('aria-label', `How often each face of a d${f.length} came up in ${n} rolls`);
                $('#dt-chart').innerHTML = `<div class="dt-expect" style="bottom: ${(exp / max) * 100}%" title="Expected: ${exp.toFixed(1)} each"><span>expected</span></div>`
                    + f.map((c, i) => `<div class="dt-col" title="${i + 1}: rolled ${c} times (${n ? ((c / n) * 100).toFixed(1) : 0}%)"><i style="height: ${(c / max) * 100}%"></i><span>${(i + 1) % every === 0 || every === 1 ? i + 1 : ''}</span></div>`).join('');
                $('#dt-rows').innerHTML = f.map((c, i) => `<tr><td>${i + 1}</td><td class="num">${c.toLocaleString()}</td><td class="num">${exp.toFixed(1)}</td><td class="num">${n ? ((c / n) * 100).toFixed(1) : '0.0'}%</td></tr>`).join('');
                const v = $('#dt-verdict');
                if (n < f.length * 5) { v.className = 'dt-verdict muted'; v.textContent = n ? `Roll at least ${(f.length * 5 - n).toLocaleString()} more d${shown} before the check means much.` : 'Press Start rolling to begin.'; return; }
                const chi = f.reduce((s, c) => s + (c - exp) ** 2 / exp, 0), p = chiP(chi, f.length - 1), pct = p * 100 < 1 ? (p * 100).toFixed(2) : Math.round(p * 100);
                v.className = 'dt-verdict ' + (p >= 0.05 ? 'ok' : p >= 0.001 ? 'note' : 'err');
                v.textContent = p >= 0.05 ? `Looks fair: fair dice land at least this unevenly ${pct}% of the time.`
                    : p >= 0.001 ? `A bit uneven: fair dice land this unevenly only ${pct}% of the time. Keep rolling to see if it evens out.`
                    : `Uneven: fair dice would almost never (${pct}% of the time) land like this.`;
            }
            const capt = () => caps.forEach((c, i) => { c.textContent = `${per[i].n.toLocaleString()} dice${per[i].last !== '' ? ' · last ' + per[i].last : ''}`; });

            const send = (fr, msg) => fr.contentWindow && fr.contentWindow.postMessage(msg, location.origin);
            const range = () => { const a = +$('#dt-min').value, b = +$('#dt-max').value; return [Math.min(a, b), Math.max(a, b)]; };
            const start = (i) => { const [min, max] = range(); send(frames[i], { cmd: 'start', sides: dieOf(i), style: $('#dt-style').value, speed: +$('#dt-speed').value, min, max }); };
            addEventListener('message', (e) => {
                if (e.origin !== location.origin) return;
                const i = frames.findIndex((fr) => fr.contentWindow === e.source);
                if (i < 0) return;
                if (e.data.ready) { if (running) start(i); return; }
                // only results for the die that table has now count
                if (!running || e.data.sides !== dieOf(i) || !Array.isArray(e.data.results)) return;
                const t = tally(e.data.sides);
                for (const v of e.data.results) t[v - 1]++;
                per[i].n += e.data.results.length; per[i].last = e.data.results.join(', ');
                store(); draw(); capt();
            });
            $('#dt-go').addEventListener('click', () => {
                running = !running;
                $('#dt-go').textContent = running ? 'Pause' : 'Start rolling';
                frames.forEach((fr, i) => (running ? start(i) : send(fr, { cmd: 'stop' })));
                draw();
            });
            const restart = () => { if (running) frames.forEach((_, i) => start(i)); per.forEach((p) => { p.last = ''; }); draw(); capt(); };
            for (const el of ['#dt-sides', '#dt-style', '#dt-speed', '#dt-min', '#dt-max']) $(el).addEventListener('change', restart);
            picks.forEach((s) => s.addEventListener('change', () => { if (running) start(+s.dataset.i); per[+s.dataset.i].last = ''; draw(); capt(); }));
            $('#dt-tabs').addEventListener('click', (e) => { const b = e.target.closest('[data-n]'); if (b) { shown = +b.dataset.n; draw(); } });
            $('#dt-reset').addEventListener('click', () => { if (!confirm('Clear the counts for every die?')) return; counts = {}; per.forEach((p) => { p.n = 0; p.last = ''; }); store(); draw(); capt(); });
            draw(); capt();
        })();
    </script>
</body>

</html>
