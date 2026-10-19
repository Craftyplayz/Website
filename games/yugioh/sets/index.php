<?php
require dirname(__DIR__) . '/guide.php';

$sets = [
    'Legend of Blue Eyes', 'Metal Raiders', 'Spell Ruler', "Pharaoh's Servant", 'Labyrinth of Nightmare', 'Legacy of Darkness',
    'Pharaonic Guardian', 'Magician\'s Force', 'Dark Crisis', 'Invasion of Chaos', 'Ancient Sanctuary', 'Soul of the Duelist',
    'Rise of Destiny', 'Tournament Packs 1–8', 'Dark Beginning 1 & 2', 'Retro Pack 1 & 2', 'Dark Legends',
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>

    <main class="page ygo" id="main">
        <?php ygo_top("Sets that don't suck balls."); ?>

        <section class="card section">
            <h2>Booster sets <span class="n"><?= count($sets) ?></span></h2>
            <ol class="ygo-sets">
                <?php foreach ($sets as $set): ?><li><?= h($set) ?></li><?php endforeach; ?>
            </ol>
        </section>

        <section class="card section">
            <h2>Also worth opening</h2>
            <ul>
                <li>All the collectible tins up to 2005</li>
                <li>All starter decks up to 2006, plus Yugi–Kaiba Reloaded</li>
            </ul>
        </section>
    </main>

    <?php site_footer(); ?>
</body>

</html>
