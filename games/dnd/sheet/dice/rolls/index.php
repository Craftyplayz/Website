<?php
// Quick Rolls: four buttons that throw the dice chosen on Dice Sets (choice.js) to land on a nat 1, a 10 or a nat 20, or flip the coin,
// for watching the exit animations without the rest of the Dice Sets page.
$site_page = ['components' => true];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
$v = fn($f) => filemtime(__DIR__ . '/' . $f);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <style>
        /* the dice table floats over the whole page, as on Dice Sets */
        #hb-throw { position: fixed; inset: 0; z-index: 1740; width: 100vw; height: 100vh; pointer-events: none; }
    </style>
</head>

<body>
    <?php site_header(); ?>

    <main class="page" id="main">
        <div class="bar" id="qr-bar">
            <button type="button" class="btn" data-land="1">Nat 1</button>
            <button type="button" class="btn" data-land="10">Roll a 10</button>
            <button type="button" class="btn" data-land="20">Nat 20</button>
            <button type="button" class="btn" data-coin>Flip a coin</button>
            <span class="muted" id="qr-out" aria-live="polite"></span>
        </div>
        <h1 class="page-title">Quick Rolls</h1>
        <p class="page-lede">Rolls the dice chosen on <a href="../">Dice Sets</a>, with its animations and sounds.</p>
    </main>

    <?php site_footer(null, false); ?>

    <script type="module">
        // the same engine and choice the Dice Sets page and the Homebrew Sheet use
        const engine = import('../../dice3d.js?v=<?= $v('../../dice3d.js') ?>'), choice = import('../choice.js?v=<?= $v('../choice.js') ?>');
        const out = document.getElementById('qr-out');
        document.getElementById('qr-bar').addEventListener('click', async (e) => {
            const b = e.target.closest('button'); if (!b) return;
            out.textContent = 'Rolling...';
            try {
                const [m, c] = await Promise.all([engine, choice]);
                const style = await c.styleFrom(c.readChoice() || { set: { key: 'purple', builtin: true } }, m);
                // a new roll takes the last one's dice off the table first, even mid-animation
                m.clearTable(true);
                const res = 'coin' in b.dataset ? await m.throwDice([['coin', 1]], { style }) : await m.throwDice([[20, 1]], { style, land: [[+b.dataset.land]] });
                const v = res[0][1][0];
                out.textContent = v === 'heads' ? 'Heads' : v === 'tails' ? 'Tails' : 'd20 ' + v;
            } catch (err) { out.textContent = "Couldn't roll. Try again."; }
        });
    </script>
</body>

</html>
