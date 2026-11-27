<?php
$site_page = ['components' => true];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

$groups = [
    'Farm and mounts' => ['Pig' => 'Porkchop', 'Cow' => 'Beef', 'Chicken' => 'Chicken', 'Sheep' => 'Mutton', 'Rabbit' => 'Rabbit', 'Horse' => 'Saddle', 'Donkey' => 'Hay block', 'Mule' => 'Golden carrot', 'Skeleton horse' => 'Bone block', 'Zombie horse' => 'Green carpet', 'Llama' => 'White carpet', 'Trader llama' => 'Blue carpet', 'Camel' => 'Cactus', 'Camel husk' => 'Dead bush', 'Goat' => 'Goat horn', 'Mooshroom' => 'Red mushroom'],
    'Pets and wild animals' => ['Wolf' => 'Cooked beef', 'Cat' => 'String', 'Ocelot' => 'Cocoa beans', 'Parrot' => 'Feather', 'Fox' => 'Sweet berries', 'Panda' => 'Bamboo', 'Polar bear' => 'Ice', 'Bee' => 'Honeycomb', 'Bat' => 'Gray dye', 'Frog' => 'Lily pad', 'Armadillo' => 'Armadillo scute', 'Sniffer' => 'Torchflower seeds', 'Strider' => 'Warped fungus', 'Allay' => 'Amethyst shard', 'Happy ghast' => 'Dried ghast'],
    'Water' => ['Cod' => 'Cod', 'Salmon' => 'Salmon', 'Tropical fish' => 'Tropical fish', 'Pufferfish' => 'Pufferfish', 'Squid' => 'Ink sac', 'Glow squid' => 'Glow ink sac', 'Dolphin' => 'Heart of the sea', 'Turtle' => 'Turtle scute', 'Axolotl' => 'Axolotl bucket', 'Tadpole' => 'Tadpole bucket', 'Nautilus' => 'Nautilus shell', 'Zombie nautilus' => 'Dead brain coral', 'Guardian' => 'Prismarine shard', 'Elder guardian' => 'Sponge', 'Drowned' => 'Kelp'],
    'Villagers and golems' => ['Villager' => 'Emerald', 'Wandering trader' => 'Emerald block', 'Iron golem' => 'Iron block', 'Snow golem' => 'Snow block', 'Copper golem' => 'Copper ingot'],
    'Undead' => ['Zombie' => 'Rotten flesh', 'Zombie villager' => 'Golden apple', 'Husk' => 'Sand', 'Giant' => 'Enchanted golden apple', 'Skeleton' => 'Bone', 'Stray' => 'Packed ice', 'Bogged' => 'Brown mushroom', 'Parched' => 'Charcoal', 'Wither skeleton' => 'Coal', 'Phantom' => 'Phantom membrane', 'Zombified piglin' => 'Gold nugget', 'Zoglin' => 'Crimson fungus'],
    'Monsters' => ['Creeper' => 'Gunpowder', 'Spider' => 'Spider eye', 'Cave spider' => 'Fermented spider eye', 'Slime' => 'Slime ball', 'Silverfish' => 'Stone', 'Witch' => 'Redstone', 'Enderman' => 'End stone', 'Endermite' => 'Chorus fruit', 'Shulker' => 'Shulker shell', 'Breeze' => 'Breeze rod', 'Creaking' => 'Resin clump', 'Sulfur cube' => 'Yellow dye'],
    'Nether and raids' => ['Blaze' => 'Blaze rod', 'Ghast' => 'Ghast tear', 'Magma cube' => 'Magma cream', 'Piglin' => 'Gold ingot', 'Piglin brute' => 'Gold block', 'Hoglin' => 'Cooked porkchop', 'Pillager' => 'White banner', 'Vindicator' => 'Iron ingot', 'Evoker' => 'Totem of undying', 'Illusioner' => 'Enchanted book', 'Ravager' => 'Leather', 'Vex' => 'Iron nugget'],
    'Bosses' => ['Wither' => 'Nether star', 'Ender dragon' => 'Dragon egg', 'Warden' => 'Echo shard'],
    'Boats' => ['Acacia boat' => 'Acacia boat', 'Acacia chest boat' => 'Acacia chest boat', 'Birch boat' => 'Birch boat', 'Birch chest boat' => 'Birch chest boat', 'Cherry boat' => 'Cherry boat', 'Cherry chest boat' => 'Cherry chest boat', 'Dark oak boat' => 'Dark oak boat', 'Dark oak chest boat' => 'Dark oak chest boat', 'Jungle boat' => 'Jungle boat', 'Jungle chest boat' => 'Jungle chest boat', 'Mangrove boat' => 'Mangrove boat', 'Mangrove chest boat' => 'Mangrove chest boat', 'Oak boat' => 'Oak boat', 'Oak chest boat' => 'Oak chest boat', 'Pale oak boat' => 'Pale oak boat', 'Pale oak chest boat' => 'Pale oak chest boat', 'Poplar boat' => 'Poplar boat', 'Poplar chest boat' => 'Poplar chest boat', 'Spruce boat' => 'Spruce boat', 'Spruce chest boat' => 'Spruce chest boat', 'Bamboo raft' => 'Bamboo raft', 'Bamboo chest raft' => 'Bamboo chest raft'],
    'Minecarts' => ['Minecart' => 'Minecart', 'Chest minecart' => 'Chest minecart', 'Furnace minecart' => 'Furnace minecart', 'Hopper minecart' => 'Hopper minecart', 'TNT minecart' => 'TNT minecart', 'Command block minecart' => 'Command block minecart', 'Spawner minecart' => 'Spawner'],
    'Other non-living' => ['Armor stand' => 'Armor stand', 'End crystal' => 'End crystal', 'Item frame' => 'Item frame', 'Glow item frame' => 'Glow item frame', 'Painting' => 'Painting', 'TNT' => 'TNT', 'Egg' => 'Egg', 'Snowball' => 'Snowball', 'Ender pearl' => 'Ender pearl', 'Arrow' => 'Arrow', 'Spectral arrow' => 'Spectral arrow', 'Firework rocket' => 'Firework rocket', 'Wind charge' => 'Wind charge', 'Splash potion' => 'Splash potion', 'Lingering potion' => 'Lingering potion', 'Experience bottle' => 'Experience bottle', 'Eye of ender' => 'Ender eye', 'Fireball' => 'Fire charge', 'Small fireball' => 'Blaze powder', 'Dragon fireball' => 'Dragon breath', 'Wither skull' => 'Wither rose', 'Shulker bullet' => 'Popped chorus fruit', 'Llama spit' => 'White dye', 'Experience orb' => 'Lapis lazuli', 'Leash knot' => 'Lead'],
];
$icons = $_SERVER['DOCUMENT_ROOT'] . '/minecraft/tools/item-list/minecraft_';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <style>
        .disguise .rowi { display: grid; grid-template-columns: 1fr 24px 1fr; }
        .disguise .rowi img { image-rendering: pixelated; }
    </style>
</head>

<body>
    <?php site_header(); ?>

    <main class="page disguise" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Disguise Items</h1>
        <p class="page-lede">Rename the item to <code class="mono">transform entity</code> in an anvil, then wear it in the helmet slot.</p>

        <div class="masonry">
            <?php foreach ($groups as $group => $rows): ?>
                <section class="card">
                    <h2><?= h($group) ?> <span class="n"><?= count($rows) ?></span></h2>
                    <div class="rows">
                        <?php foreach ($rows as $disguise => $item): $id = strtolower(str_replace(' ', '_', $item)); ?>
                            <div class="rowi">
                                <span><?= h($disguise) ?></span>
                                <?php if (is_file($icons . $id . '.png')): ?><img src="/minecraft/tools/item-list/minecraft_<?= h($id) ?>.png" alt="" width="24" height="24" loading="lazy" decoding="async"><?php else: ?><span></span><?php endif; ?>
                                <span><?= h($item) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
