<?php
// Dice sound mockups: twenty throw-and-roll sound designs (sounds.js) to listen to and try on real dice, so the owner can pick one
$site_page = ['components' => true, 'css' => ['/assets/css/pages/games-dnd-sheet-dice.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
$sets = ['ember' => 'Ember Cage', 'frost' => 'Frostbound', 'arcane' => 'Arcane Orrery', 'clockwork' => 'Clockwork', 'storm' => 'Stormcaller',
    'wildwood' => 'Wildwood', 'abyss' => 'Abyssal Tide', 'starfall' => 'Starfall', 'venom' => 'Viper Venom', 'spectral' => 'Spectral'];
$v = fn($f) => filemtime(__DIR__ . '/' . $f);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <meta name="robots" content="noindex">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
</head>

<body>
    <?php site_header(); ?>

    <main class="page dd" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Dice Sound Mockups</h1>
        <p class="page-lede">Twenty ideas for the sound when dice are thrown and while they roll. Listen to each, try it on real dice, and pick the one you like.</p>

        <div class="bar dd-bar">
            <label>Dice to try them on <select class="field" id="ds-set">
                <?php foreach ($sets as $k => $n): ?><option value="<?= h($k) ?>"><?= h($n) ?></option><?php endforeach; ?>
            </select></label>
        </div>

        <div class="dd-grid" id="ds-grid"><p class="note">Loading sounds...</p></div>
    </main>

    <?php site_footer(null, false); ?>

    <script type="module">
        import mocks from './sounds.js?v=<?= $v('sounds.js') ?>';
        const $ = (s, r = document) => r.querySelector(s), grid = $('#ds-grid');
        const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
        grid.innerHTML = mocks.map((m, i) => `<section class="card dd-card">
            <h2>${i + 1}. ${esc(m.name)}</h2><p class="muted dd-sound">${esc(m.blurb)}</p>
            <div class="actions"><button type="button" class="btn" data-play="throw" data-i="${i}">Play throw</button>
            <button type="button" class="btn" data-play="roll" data-i="${i}">Play a roll</button>
            <button type="button" class="btn" data-dice="${i}">Roll dice with it</button></div></section>`).join('');
        // the page's own audio for listening without dice, built like the dice engine's: a compressor and a short noise buffer
        let A = null;
        const audio = () => {
            if (!A) { const ac = new (window.AudioContext || window.webkitAudioContext)(), out = ac.createDynamicsCompressor(); out.connect(ac.destination);
                const noise = ac.createBuffer(1, ac.sampleRate * 0.4, ac.sampleRate), d = noise.getChannelData(0); for (let i = 0; i < d.length; i++) d[i] = Math.random() * 2 - 1;
                A = { ac, out, noise }; }
            if (A.ac.state === 'suspended') A.ac.resume();
            return A;
        };
        // a pretend roll: the throw, then bumps that come quicker and softer, a few of them dice on dice
        const HITS = [[0.38, 1, 1], [0.52, 0.8, 1], [0.6, 0.55, 0], [0.7, 0.6, 1], [0.79, 0.45, 1], [0.86, 0.3, 0], [0.93, 0.32, 1], [0.99, 0.2, 1], [1.04, 0.14, 1], [1.08, 0.08, 1]];
        // the dice engine and the chosen set, loaded on first use
        let engine = null; const sets = {};
        const load = () => (engine ||= import('../../dice3d.js?v=<?= filemtime(__DIR__ . '/../../dice3d.js') ?>').catch((e) => { engine = null; throw e; }));
        const setOf = (k) => (sets[k] ||= import(`../sets/${k}.js`).then((m) => m.default));
        document.addEventListener('click', async (e) => {
            const p = e.target.closest('[data-play]'), d = e.target.closest('[data-dice]');
            if (p) {
                const m = mocks[+p.dataset.i], a = audio(), t = a.ac.currentTime + 0.05;
                m.throw({ ...a, t, count: 3 });
                if (p.dataset.play === 'roll') for (const [at, vol, table] of HITS) m.hit({ ...a, t: t + at, vol, onTable: !!table });
            }
            if (d) {
                const m = mocks[+d.dataset.dice], k = $('#ds-set').value;
                d.disabled = true;
                try {
                    const [eng, set] = await Promise.all([load(), setOf(k)]);
                    // the set as it is, with only its throw and rolling sounds swapped for this mockup
                    await eng.throwDice([[20, 1], [6, 2]], { style: { ...set, key: 'set-' + k, sound: { ...set.sound, throw: m.throw, hit: m.hit } }, sound: true });
                } catch (err) { d.textContent = "Couldn't roll. Try again"; }
                d.disabled = false;
            }
        });
    </script>
</body>

</html>
