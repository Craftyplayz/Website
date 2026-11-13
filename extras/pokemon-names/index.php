<?php
$site_page = [
    'components' => true,
    'ui' => true,
    'css' => ['/assets/css/pages/extras-pokemon-names.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

// Every Pokémon with its baby-name score (0-10). Each entry also has a "reasoning" sentence,
// which is kept in data.json and in the page data but not shown (see SHOW_REASONING in pokemon-names.js).
$pbn = json_decode(file_get_contents(__DIR__ . '/data.json'), true);

$pbn_regions = [1 => 'Kanto', 2 => 'Johto', 3 => 'Hoenn', 4 => 'Sinnoh', 5 => 'Unova', 6 => 'Kalos', 7 => 'Alola', 8 => 'Galar', 9 => 'Paldea'];
$pbn_roman = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX'];
// Verdict bands, best first. Shared with the script through data-bands.
$pbn_bands = [
    ['id' => 'top', 'label' => 'Normal name', 'min' => 8, 'max' => 10],
    ['id' => 'good', 'label' => 'Unusual but fine', 'min' => 7, 'max' => 7],
    ['id' => 'mid', 'label' => 'Weird looks', 'min' => 5, 'max' => 6],
    ['id' => 'low', 'label' => 'Bully bait', 'min' => 3, 'max' => 4],
    ['id' => 'cruel', 'label' => 'Cruel', 'min' => 1, 'max' => 2],
    ['id' => 'ban', 'label' => 'Illegal', 'min' => 0, 'max' => 0],
];

function pbn_band($score, $bands)
{
    foreach ($bands as $b) {
        if ($score >= $b['min'] && $score <= $b['max']) return $b;
    }
    return end($bands);
}

function pbn_sprite_pos($n)
{
    $i = $n - 1;
    return '-' . (($i % 32) * 64) . 'px -' . (intdiv($i, 32) * 64) . 'px';
}

// Summary numbers
$total = count($pbn);
$sum = 0;
$best = $pbn[0];
$gen_scores = [];
$band_counts = [];
foreach ($pbn as $p) {
    $sum += $p['score'];
    if ($p['score'] > $best['score']) $best = $p;
    $gen_scores[$p['generation']][] = $p['score'];
    $bid = pbn_band($p['score'], $pbn_bands)['id'];
    $band_counts[$bid] = ($band_counts[$bid] ?? 0) + 1;
}
$avg = $sum / $total;
$best_gen = 1;
$best_gen_avg = 0;
foreach ($gen_scores as $g => $scores) {
    $a = array_sum($scores) / count($scores);
    if ($a > $best_gen_avg) {
        $best_gen_avg = $a;
        $best_gen = $g;
    }
}
$usable = 0;
$illegal = 0;
foreach ($pbn as $p) {
    if ($p['score'] >= 7) $usable++;
    if ($p['score'] === 0) $illegal++;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <link rel="preload" as="image" href="<?= h(asset('/extras/pokemon-names/sprites.webp')) ?>">
</head>

<body>
    <?php site_header(); ?>

    <main class="page pbn" id="main" data-bands="<?= h(json_encode($pbn_bands)) ?>" style="--pbn-sheet: url('<?= h(asset('/extras/pokemon-names/sprites.webp')) ?>')">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Pokémon Baby Names</h1>
        <p class="page-lede">Every Pokémon from #1 to #1025, scored from 0 to 10 on how well it would work as a child's name. Hover over or tap a Pokémon to see its verdict.</p>

        <div class="stat-row">
            <div class="stat-card"><b><?= number_format($total) ?></b><span>Pokémon rated</span><small>Generations I to IX</small></div>
            <div class="stat-card"><b><?= number_format($avg, 1) ?></b><span>Average score out of 10</span><small>Most score 2 to 4</small></div>
            <div class="stat-card"><b><?= h($best['name']) ?></b><span>Best baby name</span><small><?= (int) $best['score'] ?> out of 10</small></div>
            <div class="stat-card"><b><?= $usable ?></b><span>Score 7 or higher</span><small>Names you could use</small></div>
            <div class="stat-card"><b><?= $illegal ?></b><span>Illegal names</span><small>Symbols, digits or titles</small></div>
            <div class="stat-card"><b>Gen <?= $pbn_roman[$best_gen] ?></b><span>Best generation</span><small><?= $pbn_regions[$best_gen] ?>, average <?= number_format($best_gen_avg, 2) ?></small></div>
        </div>

        <div class="bar pbn-bar">
            <div class="pbn-row">
                <input class="field pbn-q" id="pbnQ" type="search" placeholder="Search by name or number  ( / )" autocomplete="off" aria-label="Search Pokémon">
                <select class="field pbn-sort" id="pbnSort" aria-label="Sort by">
                    <option value="num">Pokédex number</option>
                    <option value="score-desc">Score: best first</option>
                    <option value="score-asc">Score: worst first</option>
                    <option value="gen">Generation</option>
                    <option value="name">Name A to Z</option>
                    <option value="len-asc">Shortest name</option>
                    <option value="len-desc">Longest name</option>
                </select>
            </div>
            <div class="pbn-row">
                <span class="pbn-label">Generation</span>
                <div class="stabs" id="pbnGens">
                    <button type="button" class="on" aria-pressed="true" data-gen="0">All <span class="n"><?= $total ?></span></button>
                    <?php foreach ($pbn_roman as $g => $r): ?>
                        <button type="button" aria-pressed="false" data-gen="<?= $g ?>" title="<?= $pbn_regions[$g] ?>"><?= $r ?> <span class="n"><?= count($gen_scores[$g]) ?></span></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="pbn-row">
                <span class="pbn-label">Verdict</span>
                <div class="stabs" id="pbnBands">
                    <button type="button" class="on" aria-pressed="true" data-band="">Any <span class="n"><?= $total ?></span></button>
                    <?php foreach ($pbn_bands as $b): ?>
                        <button type="button" aria-pressed="false" data-band="<?= $b['id'] ?>" title="Score <?= $b['min'] === $b['max'] ? $b['min'] : $b['min'] . ' to ' . $b['max'] ?>"><?= h($b['label']) ?> <span class="n"><?= $band_counts[$b['id']] ?? 0 ?></span></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <p class="muted pbn-count" id="pbnCount" aria-live="polite">Showing all <?= number_format($total) ?> Pokémon</p>
        <div id="pbnList">
            <div class="pbn-grid">
                <?php foreach ($pbn as $p): $b = pbn_band($p['score'], $pbn_bands); ?>
                    <button type="button" class="pbn-tile" data-n="<?= (int) $p['number'] ?>">
                        <span class="pbn-top"><span class="mono pbn-num">#<?= str_pad($p['number'], 4, '0', STR_PAD_LEFT) ?></span><span class="pbn-score" style="--sc:hsl(<?= $p['score'] * 12 ?> 100% 50%)"><?= (int) $p['score'] ?></span></span>
                        <span class="pbn-spr" style="background-position:<?= pbn_sprite_pos($p['number']) ?>" aria-hidden="true"></span>
                        <span class="pbn-name"><?= h($p['name']) ?></span>
                        <span class="pbn-meter" style="--sc:hsl(<?= $p['score'] * 12 ?> 100% 50%)" aria-hidden="true"><i style="width:<?= $p['score'] * 10 ?>%"></i></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <p class="muted pbn-credit">Sprites from PokeAPI. Pokémon names are trademarks of Nintendo, Game Freak and The Pokémon Company.</p>
        <div class="pbn-tip" id="pbnTip" role="tooltip" hidden></div>
    </main>

    <script id="pbnData" type="application/json"><?= json_encode($pbn, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <script src="<?= h(asset('/extras/pokemon-names/pokemon-names.js')) ?>" defer></script>

    <?php site_footer(null, false); ?>
</body>

</html>
