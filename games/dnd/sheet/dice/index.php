<?php
// Dice Sets: pick one of the ten 3D dice sets (sets/<key>.js) and a sound (the set's own, or one of sounds.js), then roll,
// or make a die land on a chosen number to hear how a roll ending on it sounds. The page's script is dice.js.
$site_page = ['components' => true, 'css' => ['/assets/css/pages/games-dnd-sheet-dice.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
// key => [name, what it looks like, what its own sound is like]; the look and sound live in sets/<key>.js
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
$v = fn($f) => filemtime(__DIR__ . '/' . $f);
$data = [
    'engine' => '../dice3d.js?v=' . filemtime(__DIR__ . '/../dice3d.js'),
    'sounds' => './sounds.js?v=' . $v('sounds.js'),
    // nat 1 and nat 20 sounds, a few to a file in natsounds/
    'nat' => array_map(fn($f) => './natsounds/' . basename($f) . '?v=' . filemtime($f), glob(__DIR__ . '/natsounds/*.js') ?: []),
    'sets' => array_map(fn($k, $s) => ['key' => $k, 'name' => $s[0], 'look' => $s[1], 'sound' => $s[2], 'v' => $v("sets/$k.js")], array_keys($sets), $sets),
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
        <p class="page-lede">Pick a dice set and a sound, then roll. You can also make a die land on a number to hear how a roll ending on it sounds.</p>

        <div class="bar dd-bar">
            <button type="button" class="btn primary" data-roll="20x1">Roll a d20</button>
            <button type="button" class="btn" data-roll="4x1,6x1,8x1,10x1,12x1,20x1">Roll the set</button>
            <span class="dd-land">
                <label>Land on <select class="field" id="dd-die" aria-label="Die">
                    <?php foreach ([4, 6, 8, 10, 12, 20, 100] as $d): ?><option value="<?= $d ?>"<?= $d === 20 ? ' selected' : '' ?>>d<?= $d ?></option><?php endforeach; ?>
                </select></label>
                <select class="field" id="dd-num" aria-label="Number to land on"></select>
                <button type="button" class="btn" id="dd-landgo">Roll it</button>
            </span>
            <label class="check"><input type="checkbox" id="dd-sound" checked> Sound</label>
            <span class="dd-out" id="dd-out" aria-live="polite"></span>
        </div>

        <h2 class="sec-h">Dice set <span class="n"><?= count($sets) ?></span></h2>
        <div class="dd-sets" role="group" aria-label="Dice set">
            <?php foreach ($sets as $k => [$name]): ?>
                <button type="button" class="dd-set" data-set="<?= h($k) ?>" aria-pressed="false"><img alt="" width="96" height="96"><span><?= h($name) ?></span></button>
            <?php endforeach; ?>
        </div>
        <p class="muted dd-setline" id="dd-setline"></p>

        <h2 class="sec-h">Sound <span class="n" id="dd-scount"></span></h2>
        <div class="stabs dd-cats" id="dd-cats" role="group" aria-label="Kind of sound"></div>
        <div class="dd-sounds" id="dd-sounds"><p class="note">Loading sounds...</p></div>

        <div class="dd-nats" id="dd-nats" hidden>
            <?php foreach ([1 => 'Nat 1 sound', 20 => 'Nat 20 sound'] as $n => $title): ?>
                <section>
                    <h2 class="sec-h"><?= $title ?> <span class="n" id="dd-n<?= $n ?>count"></span></h2>
                    <p class="muted dd-natline"><?= $n === 1 ? 'Plays when a d20 lands on 1 and blows up.' : 'Plays when a d20 lands on 20 and flares.' ?>
                        <button type="button" class="btn small" data-try="<?= $n ?>">Try it on a d20</button></p>
                    <div class="dd-sounds" id="dd-nat<?= $n ?>"></div>
                </section>
            <?php endforeach; ?>
        </div>
    </main>

    <?php site_footer(null, false); ?>

    <script type="application/json" id="dd-data"><?= json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <script type="module" src="<?= h('dice.js?v=' . $v('dice.js')) ?>"></script>
</body>

</html>
