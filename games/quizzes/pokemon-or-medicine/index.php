<?php
// Pokémon or Medicine: is the name a Pokémon or a prescription drug?
// Pokémon come from the Pokémon Baby Names data; medicines.json is from the FDA drug directory (its "source" says how).
$site_page = [
    'components' => true,
    'ui' => true,
    'css' => ['/assets/css/pages/games-pokemon-or-medicine.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

$pom_poke = json_decode(file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/extras/pokemon-names/data.json'), true);
$pom_meds = json_decode(file_get_contents(__DIR__ . '/medicines.json'), true);
// [name, number] for a Pokémon (number picks its sprite), [name, 'b'|'g'] for a brand or generic medicine
$pom_pool = array_merge(
    array_map(fn ($p) => [$p['name'], $p['number']], $pom_poke),
    array_map(fn ($n) => [$n, 'b'], $pom_meds['brand']),
    array_map(fn ($n) => [$n, 'g'], $pom_meds['generic'])
);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>

    <main class="page pom" id="main" style="--pom-sheet: url('<?= h(asset('/extras/pokemon-names/sprites.webp')) ?>')">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Pokémon or Medicine</h1>
        <p class="page-lede">Is it a Pokémon or a prescription drug? 20 names a round. Keys: P for Pokémon, M for medicine, Enter for next.</p>

        <section class="card pom-card" aria-live="polite">
            <div class="pom-top">
                <span class="muted" id="pom-round"></span>
                <span class="muted" id="pom-score"></span>
            </div>

            <div id="pom-play">
                <p class="pom-name" id="pom-name"></p>
                <div class="pom-pick">
                    <button class="btn" type="button" data-pick="p">Pokémon</button>
                    <button class="btn" type="button" data-pick="m">Medicine</button>
                </div>
                <div class="pom-reveal" id="pom-reveal" hidden>
                    <i class="pom-spr" id="pom-spr" aria-hidden="true"></i>
                    <p class="pom-verdict" id="pom-verdict"></p>
                    <button class="btn primary" type="button" id="pom-next">Next name</button>
                </div>
            </div>

            <div id="pom-end" hidden>
                <div class="tiles">
                    <div class="tile"><b id="pom-final"></b><span>Correct</span></div>
                    <div class="tile"><b id="pom-best"></b><span>Best round</span></div>
                </div>
                <p id="pom-msg"></p>
                <div class="rows" id="pom-missed"></div>
                <div class="actions"><button class="btn primary" type="button" id="pom-again">Play again</button></div>
            </div>
        </section>

        <p class="muted pom-src"><?= number_format(count($pom_poke)) ?> Pokémon and <?= number_format(count($pom_meds['brand']) + count($pom_meds['generic'])) ?> medicines. Medicine names are from the US FDA drug directory.</p>
    </main>

    <?php site_footer(null, false); ?>
    <script>window.POM_POOL = <?= json_encode($pom_pool, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <script src="<?= h(asset('/games/quizzes/pokemon-or-medicine/pom.js')) ?>" defer></script>
</body>

</html>
