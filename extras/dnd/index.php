<?php
$site_page = ['components' => true, 'ui' => true, 'css' => ['/assets/css/pages/dnd.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

// D&D Beyond has no public API. These are the unofficial endpoints its own character sheet uses:
// they only return public characters, need no key, and rate-limit heavy use, so every answer is cached.
const DND_CACHE = __DIR__ . '/cache';
const DND_CHAR_TTL = 600;     // 10 minutes
const DND_RULES_TTL = 86400;  // rule data barely changes

// GET a D&D Beyond JSON endpoint through a disk cache. Returns ['data' => array|null, 'time' => int, 'stale' => bool, 'status' => int].
// A failed refresh falls back to the last good copy, so a D&D Beyond outage doesn't blank the page.
function dnd_get($url, $file, $ttl, $trim = null)
{
    $path = DND_CACHE . '/' . $file;
    $cached = is_file($path) ? json_decode((string)@file_get_contents($path), true) : null;
    if ($cached && time() - filemtime($path) < $ttl) return ['data' => $cached, 'time' => filemtime($path), 'stale' => false, 'status' => 200];
    // a recent refusal (private, missing) is remembered too, so random IDs can't make us hammer D&D Beyond
    $miss = $path . '.miss';
    if (!$cached && is_file($miss) && time() - filemtime($miss) < $ttl) return ['data' => null, 'time' => 0, 'stale' => false, 'status' => (int)file_get_contents($miss)];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; CraftyPlayz character viewer; +https://craftyplayz.com/extras/dnd/)',
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $json = $body ? json_decode($body, true) : null;
    if ($status === 200 && !empty($json['success']) && isset($json['data'])) {
        $data = $trim ? $trim($json['data']) : $json['data'];
        if (!is_dir(DND_CACHE)) @mkdir(DND_CACHE, 0755, true);
        @file_put_contents($path, json_encode($data), LOCK_EX);
        return ['data' => $data, 'time' => time(), 'stale' => false, 'status' => 200];
    }
    if ($cached) return ['data' => $cached, 'time' => filemtime($path), 'stale' => true, 'status' => $status];
    if ($status >= 400 && $status < 500) { if (!is_dir(DND_CACHE)) @mkdir(DND_CACHE, 0755, true); @file_put_contents($miss, (string)$status, LOCK_EX); }
    return ['data' => null, 'time' => 0, 'stale' => false, 'status' => $status];
}

function dnd_character($id)
{
    return dnd_get('https://character-service.dndbeyond.com/character/v5/character/' . $id, 'char-' . $id . '.json', DND_CHAR_TTL);
}

// keep only the lookup tables the sheet uses (the full file is ~540 KB)
function dnd_trim_rules($r)
{
    $pick = function ($rows, $keys) {
        return array_map(function ($row) use ($keys) { return array_intersect_key($row, array_flip($keys)); }, (array)$rows);
    };
    $out = array_intersect_key($r, array_flip([
        'stats', 'abilitySkills', 'conditions', 'basicActions', 'naturalActions', 'levelProficiencyBonuses', 'levelExperiencePoints',
        'statModifiers', 'creatureSizes', 'senses', 'movements', 'damageTypes', 'damageAdjustments', 'privacyTypes', 'sharingTypes',
        'armorTypes', 'weaponCategories', 'currencyData', 'multiClassSpellSlots', 'pactMagicMultiClassSpellSlots', 'spellComponents',
        'spellRangeTypes', 'rangeTypes', 'challengeRatings', 'limitedUseResetTypes', 'initiativeScore', 'noArmorAcAmount', 'baseWeaponReach',
        'defaultAttunedItemCountMax', 'maxDeathsavesFail', 'maxDeathsavesSuccess', 'maxExhaustionLevel', 'coverTypes', 'restoreTypes',
        'proficiencyGroups', 'minimumHpTotal',
    ]));
    $out['alignments'] = $pick($r['alignments'] ?? [], ['id', 'name', 'description']);
    $out['lifestyles'] = $pick($r['lifestyles'] ?? [], ['id', 'name', 'description', 'cost']);
    $out['activationTypes'] = $pick($r['activationTypes'] ?? [], ['id', 'name']);
    $out['adjustmentTypes'] = $pick($r['adjustmentTypes'] ?? [], ['id', 'name']);
    $out['aoeTypes'] = $pick($r['aoeTypes'] ?? [], ['id', 'name']);
    $out['languages'] = $pick($r['languages'] ?? [], ['id', 'name']);
    $out['monsterTypes'] = $pick($r['monsterTypes'] ?? [], ['id', 'name']);
    $out['sources'] = $pick($r['sources'] ?? [], ['id', 'name', 'description']);
    $out['stats'] = $pick($r['stats'] ?? [], ['id', 'key', 'name', 'compendiumText']);
    return $out;
}

// characters offered in the picker: extras/dnd/characters.json (D&D Beyond won't list a user's characters without a login)
$list = json_decode((string)@file_get_contents(__DIR__ . '/characters.json'), true);
$list = array_values(array_filter(array_map('strval', is_array($list) ? $list : []), 'ctype_digit'));

// ?id= takes a character number or a pasted D&D Beyond link; without one the page shows the character chooser
$id = preg_match('/(\d{4,12})/', (string)($_GET['id'] ?? ''), $m) ? $m[1] : '';

$char = $id !== '' ? dnd_character($id) : null;
$d = $char ? $char['data'] : null;
$rules = $d ? dnd_get('https://character-service.dndbeyond.com/character/v5/rule-data', 'rules.json', DND_RULES_TTL, 'dnd_trim_rules') : null;

$picker = [];
foreach ($list as $lid) {
    $c = $lid === $id ? $char : dnd_character($lid);
    $cd = $c['data'];
    $picker[] = !$cd ? ['id' => $lid, 'name' => null, 'status' => $c['status']] : [
        'id' => $lid,
        'name' => $cd['name'] ?? 'Unnamed',
        'avatar' => $cd['decorations']['avatarUrl'] ?? null,
        'level' => array_sum(array_map(function ($cl) { return (int)($cl['level'] ?? 0); }, $cd['classes'] ?? [])),
        'summary' => trim(($cd['race']['fullName'] ?? '') . ' · ' . implode(' / ', array_map(function ($cl) {
            return ($cl['definition']['name'] ?? '') . ' ' . ($cl['level'] ?? '');
        }, $cd['classes'] ?? [])), ' ·'),
    ];
}

if ($d) $site_page['title'] = ($d['name'] ?? 'Character') . ' · D&D Character';
$payload = $d ? ['character' => $d, 'rules' => $rules['data'], 'fetched' => $char['time'], 'stale' => $char['stale'], 'list' => array_values(array_filter($picker, function ($p) { return $p['name'] !== null; })), 'id' => $id] : null;

// the chooser: one card per character in characters.json, plus a box for any other public character
function dnd_chooser($picker)
{
    echo '<div class="dnd-choose">';
    foreach ($picker as $p) {
        if ($p['name'] === null) {
            echo '<div class="dnd-card off"><span class="dnd-av"></span><span><b>Character ' . h($p['id']) . '</b><small>'
                . ($p['status'] === 403 ? 'Private on D&amp;D Beyond' : 'Couldn\'t load from D&amp;D Beyond') . '</small></span></div>';
            continue;
        }
        echo '<a class="dnd-card" href="?id=' . rawurlencode($p['id']) . '">'
            . ($p['avatar'] ? '<img class="dnd-av" src="' . h($p['avatar']) . '" width="64" height="64" alt="" referrerpolicy="no-referrer">' : '<span class="dnd-av"></span>')
            . '<span><b>' . h($p['name']) . '</b><small>' . h($p['summary']) . '</small><small>Level ' . (int)$p['level'] . '</small></span></a>';
    }
    echo '</div>';
    echo '<form class="dnd-other" method="get" action="/extras/dnd/"><label for="dnd-other">Open another public character</label>'
        . '<span><input id="dnd-other" class="field" name="id" placeholder="D&amp;D Beyond link or ID" required autocomplete="off"><button class="btn" type="submit">Open</button></span></form>';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <link rel="preconnect" href="https://www.dndbeyond.com">
    <?php if ($d): ?><script src="<?= h(asset('/extras/dnd/dnd.js')) ?>" defer></script><?php endif; ?>
</head>

<body>
    <?php site_header(); ?>

    <main class="page dnd" id="main">
        <?php if ($id === ''): ?>
            <?= site_breadcrumbs(site_current_path()) ?>
            <h1 class="page-title">D&amp;D Characters</h1>
            <p class="page-lede">Pick a character to open their sheet.</p>
            <?php dnd_chooser($picker); ?>
        <?php else: ?>
            <nav class="crumbs" aria-label="Breadcrumb"><a href="/">Home</a> <span class="crumbs-sep">›</span> <a href="/extras/">Extras</a> <span class="crumbs-sep">›</span> <a href="/extras/dnd/">D&amp;D Characters</a> <span class="crumbs-sep">›</span> <span aria-current="page"><?= h($d ? ($d['name'] ?? 'Unnamed') : $id) ?></span></nav>
            <?php if (!$d): ?>
                <h1 class="page-title">D&amp;D Character</h1>
                <?php if ($char['status'] === 403): ?>
                    <p class="err">That character is private. Set its privacy to Public on D&amp;D Beyond (character settings › Character Privacy) and reload.</p>
                <?php elseif (in_array($char['status'], [404, 409], true)): ?>
                    <p class="err">No D&amp;D Beyond character has the ID <?= h($id) ?>.</p>
                <?php else: ?>
                    <p class="err">Couldn't reach D&amp;D Beyond right now. Try again in a minute.</p>
                <?php endif; ?>
                <h2 class="dnd-h2">Pick another character</h2>
                <?php dnd_chooser($picker); ?>
            <?php else: ?>
                <div id="sheet" data-state="loading">
                    <h1 class="page-title"><?= h($d['name'] ?? 'Unnamed') ?></h1>
                    <p class="page-lede"><?= h(trim(($d['race']['fullName'] ?? '') . ' · ' . implode(' / ', array_map(function ($cl) {
                        return ($cl['definition']['name'] ?? '') . ' ' . ($cl['level'] ?? '');
                    }, $d['classes'] ?? [])), ' ·')) ?></p>
                    <noscript><p class="note">The full character sheet needs JavaScript.</p></noscript>
                </div>
                <script id="dnd-data" type="application/json"><?= json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) ?></script>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
