<?php
// Dice sets: ten 3D dice styles with moving parts and their own sounds, each in sets/<key>.js, thrown by the sheet's dice3d.js
$site_page = ['components' => true, 'css' => ['/assets/css/pages/games-dnd-sheet-dice.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
// key => [name, what it looks like, what it sounds like]; the look and sound live in sets/<key>.js
$sets = [
    'ember' => ['Ember Cage', 'A dark iron lattice over a molten core that churns, with sparks drifting up off each die.', 'Iron clanks with a crackle of embers; a whoosh of flame on the throw and a hiss as they settle.'],
    'frost' => ['Frostbound', 'Pale blue ice with silver filigree, frost creeping across the faces and snowflakes circling.', 'Glassy ice clinks and crunches; a cold gust on the throw and a wind-chime shimmer at rest.'],
    'arcane' => ['Arcane Orrery', 'Violet glass inside spinning gold rings, like a tiny orrery, with runes that pulse with light.', 'Bell-like tings tuned to a scale; a rising shimmer on the throw and a soft chord when they settle.'],
    'clockwork' => ['Clockwork', 'Brass faces etched with gears, with small gears turning around each die.', 'Ratchet clicks and metal ticks; a winding sound on the throw and a tick-tock that winds down to a ding.'],
    'storm' => ['Stormcaller', 'Slate-dark glass with lightning flickering across the faces and sparks arcing off the edges.', 'Sharp electric zaps on each hit; thunder on the throw and a distant rumble as they settle.'],
    'wildwood' => ['Wildwood', 'Living wood wrapped in vines, with leaves swaying on the faces and fireflies drifting round.', 'Hollow woody knocks; a rustle of leaves on the throw and a soft birdlike chirp at rest.'],
    'abyss' => ['Abyssal Tide', 'Deep-sea teal with light rippling across the faces and bubbles rising from each die.', 'Muffled underwater thuds and bubble pops; a wave swelling on the throw and a burst of bubbles at rest.'],
    'starfall' => ['Starfall', 'Midnight blue with a slowly turning galaxy inside, silver constellations and stars in orbit.', 'Soft glassy pings that ring out; a whoosh on the throw and a twinkling run of notes when they settle.'],
    'venom' => ['Viper Venom', 'A bone-white frame over toxic green liquid that bubbles, with drips falling from each die.', 'Dry bone clacks and wet gurgles; a hiss on the throw and a final hiss as they settle.'],
    'spectral' => ['Spectral', 'Ghostly see-through dice with a glowing wisp circling each one and leaving a trail.', 'Hollow, echoing knocks; a breathy rush on the throw and a low moan that fades at rest.'],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
</head>

<body>
    <?php site_header(); ?>

    <main class="page dd" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Dice Sets</h1>
        <p class="page-lede">Ten 3D dice sets for the Homebrew Sheet, each with moving parts and its own sounds. Roll one to see it.</p>

        <div class="bar dd-bar">
            <label class="check"><input type="checkbox" id="dd-sound" checked> Sound</label>
            <span class="muted" id="dd-status"></span>
        </div>

        <div class="dd-grid">
            <?php foreach ($sets as $key => [$name, $look, $sound]): ?>
                <section class="card dd-card" data-set="<?= h($key) ?>">
                    <img class="dd-prev" alt="" width="336" height="56">
                    <h2><?= h($name) ?></h2>
                    <p><?= h($look) ?></p>
                    <p class="muted dd-sound"><?= h($sound) ?></p>
                    <div class="actions">
                        <button type="button" class="btn" data-roll="20x1">Roll a d20</button>
                        <button type="button" class="btn" data-roll="4x1,6x1,8x1,10x1,12x1,20x1">Roll the set</button>
                        <span class="dd-out" aria-live="polite"></span>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    </main>

    <?php site_footer(null, false); ?>

    <script type="module">
        const $ = (s, r = document) => r.querySelector(s), status = $('#dd-status'), sound = $('#dd-sound');
        try { sound.checked = localStorage.getItem('dice-sets-sound') !== '0'; } catch (e) { }
        sound.addEventListener('change', () => { try { localStorage.setItem('dice-sets-sound', sound.checked ? '1' : '0'); } catch (e) { } });
        // three.js and cannon-es come from jsDelivr with the dice; each set is its own file
        let engine = null;
        // versioned, so a changed engine isn't kept from the browser's day-long cache
        const load = () => (engine ||= import('../dice3d.js?v=<?= filemtime(__DIR__ . '/../dice3d.js') ?>').catch((e) => { engine = null; throw e; }));
        const sets = {};
        const ver = <?= json_encode(array_map(fn($k) => @filemtime(__DIR__ . "/sets/$k.js") ?: 0, array_combine(array_keys($sets), array_keys($sets)))) ?>;
        const setOf = (key) => (sets[key] ||= import(`./sets/${key}.js?v=${ver[key]}`).then((m) => ({ ...m.default, key: 'set-' + key })));
        const cards = [...document.querySelectorAll('.dd-card')];
        // previews, one set at a time so the page stays responsive
        load().then(async (m) => {
            for (const c of cards) {
                try { $('.dd-prev', c).src = m.previewStyle(await setOf(c.dataset.set)); }
                catch (e) { $('.dd-prev', c).hidden = true; }
                await new Promise((r) => setTimeout(r, 0));
            }
        }, () => { status.textContent = "Couldn't load the 3D dice. Check your connection and reload."; });
        document.addEventListener('click', async (e) => {
            const b = e.target.closest('[data-roll]'); if (!b) return;
            const card = b.closest('.dd-card'), out = $('.dd-out', card);
            const groups = b.dataset.roll.split(',').map((g) => g.split('x').map(Number));
            out.textContent = 'Rolling...';
            try {
                const [m, st] = await Promise.all([load(), setOf(card.dataset.set)]);
                // a new roll takes the last one's dice off the table first
                m.clearTable(true);
                const res = await m.throwDice(groups, { style: st, sound: sound.checked });
                const vals = res.flatMap(([n, r]) => r.map((v) => (res.length > 1 ? `d${n} ${v}` : v)));
                out.textContent = res.length > 1 ? vals.join(' · ') : 'Rolled ' + vals[0];
            } catch (err) { out.textContent = "Couldn't roll. Try again."; }
        });
    </script>
</body>

</html>
