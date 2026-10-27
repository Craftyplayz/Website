<?php
$site_page = [
    'components' => true,
    'css' => ['/assets/css/pages/minecraft-enchanting.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

// Same list as ITEMS in enchanting.js (id => [name, icon]); PHP draws the picker so it shows before JS runs.
$ench_items = [
    'helmet' => ['Helmet', 'netherite_helmet'], 'chestplate' => ['Chestplate', 'netherite_chestplate'],
    'leggings' => ['Leggings', 'netherite_leggings'], 'boots' => ['Boots', 'netherite_boots'],
    'turtle_shell' => ['Turtle shell', 'turtle_helmet'], 'elytra' => ['Elytra', 'elytra'], 'shield' => ['Shield', 'shield'],
    'sword' => ['Sword', 'netherite_sword'], 'axe' => ['Axe', 'netherite_axe'], 'pickaxe' => ['Pickaxe', 'netherite_pickaxe'],
    'shovel' => ['Shovel', 'netherite_shovel'], 'hoe' => ['Hoe', 'netherite_hoe'], 'bow' => ['Bow', 'bow'],
    'crossbow' => ['Crossbow', 'crossbow'], 'trident' => ['Trident', 'trident'], 'mace' => ['Mace', 'mace'],
    'fishing_rod' => ['Fishing rod', 'fishing_rod'], 'shears' => ['Shears', 'shears'],
    'flint_and_steel' => ['Flint and steel', 'flint_and_steel'], 'brush' => ['Brush', 'brush'],
    'carrot_on_a_stick' => ['Carrot on a stick', 'carrot_on_a_stick'],
    'warped_fungus_on_a_stick' => ['Warped fungus on a stick', 'warped_fungus_on_a_stick'],
];
$ench_current = isset($ench_items[$_GET['item'] ?? '']) ? $_GET['item'] : 'helmet';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>

    <main class="page ench" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Enchanting Guide</h1>
        <p class="page-lede">Pick an item to see its best enchants and the cheapest order to put them on with an anvil (Java Edition).</p>

        <div class="ench-items" id="ench-items" role="group" aria-label="Item">
            <?php foreach ($ench_items as $id => [$name, $icon]) : ?>
                <button type="button" data-item="<?= h($id) ?>" aria-pressed="<?= $id === $ench_current ? 'true' : 'false' ?>">
                    <img src="/minecraft/tools/item-list/minecraft_<?= h($icon) ?>.png" alt="" width="32" height="32" onerror="this.hidden=true">
                    <span><?= h($name) ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <h2 class="sec-h" id="ench-item-name"><?= h($ench_items[$ench_current][0]) ?></h2>
        <noscript><p class="err">This calculator needs JavaScript.</p></noscript>

        <div class="stat-row">
            <div class="stat-card"><b id="ench-total">-</b><span>levels in total</span></div>
            <div class="stat-card"><b id="ench-steps-n">-</b><span>anvil steps</span></div>
            <div class="stat-card" id="ench-max-card"><b id="ench-max">-</b><span>most expensive step</span></div>
            <div class="stat-card"><b id="ench-xp">-</b><span>XP points, each step from level 0</span></div>
        </div>

        <div class="ench-cols">
            <section class="card ench-set-card">
                <h2>Enchants</h2>
                <div id="ench-set"></div>
            </section>
            <section class="card ench-order-card">
                <h2>Anvil order</h2>
                <p class="err" id="ench-warn" hidden>A step costs 40 levels or more, so survival anvils refuse it. Untick an enchant to bring it down.</p>
                <ol class="ench-order" id="ench-order"></ol>
                <p class="muted ench-note">Every book starts as a single max-level book and the item starts unenchanted and unrepaired. Each anvil use raises the next cost, so this order keeps those uses low.</p>
            </section>
        </div>
    </main>

    <?php site_footer(null, false); ?>
    <script src="<?= h(asset('/minecraft/tools/enchanting/enchanting.js')) ?>" defer></script>
</body>

</html>
