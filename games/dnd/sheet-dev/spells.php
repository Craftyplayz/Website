<?php
// SRD 5.1 spells for the homebrew sheet, from dnd5eapi.co (SRD 5.1, CC-BY-4.0).
//   spells.php?list=1      -> [[index, name, level], ...]
//   spells.php?i=fireball  -> one spell, trimmed to the fields the sheet uses
// Non-SRD spells come from extra-spells.json (stats); their text is fetched from dnd5e.wikidot.com when picked.
// Cached on disk for 30 days (SRD text doesn't change); a failed refresh serves the last copy.
const SRD_API = 'https://www.dnd5eapi.co/api/2014/spells';
const SRD_TTL = 2592000;

function srd_out($code, $data)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header($code === 200 ? 'Cache-Control: public, max-age=86400' : 'Cache-Control: no-store');
    echo is_string($data) ? $data : json_encode($data);
    exit;
}

// GET $url, trim it, cache the trimmed JSON. Returns [json|null, status]
function srd_get($url, $trim, $html = false)
{
    $dir = sys_get_temp_dir() . '/craftyplayz-srd';
    // bump the html tag when the wikidot parser changes, to drop its old cached text
    $file = $dir . '/' . md5($url . ($html ? '#html2' : '')) . '.json';
    if (is_file($file) && time() - filemtime($file) < SRD_TTL) return [file_get_contents($file), 200];
    // a recent 404 is remembered so made-up names can't hammer the API
    if (is_file($file . '.miss') && time() - filemtime($file . '.miss') < 86400) return [null, 404];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; CraftyPlayz homebrew sheet; +https://craftyplayz.com/games/dnd/sheet/)',
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $json = $body ? ($html ? ['html' => $body] : json_decode($body, true)) : null;
    if ($status === 200 && is_array($json)) {
        $out = json_encode($trim($json));
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        @file_put_contents($file, $out, LOCK_EX);
        return [$out, 200];
    }
    if (is_file($file)) return [file_get_contents($file), 200];
    if ($status === 404) { if (!is_dir($dir)) @mkdir($dir, 0755, true); @file_put_contents($file . '.miss', '404', LOCK_EX); }
    return [null, $status === 404 ? 404 : 502];
}

$extra = json_decode((string)@file_get_contents(__DIR__ . '/extra-spells.json'), true) ?: [];

if (isset($_GET['list'])) {
    [$out, $code] = srd_get(SRD_API, function ($j) {
        return array_map(function ($s) { return [(string)$s['index'], (string)$s['name'], (int)$s['level']]; }, (array)($j['results'] ?? []));
    });
    if (!$out) srd_out(502, ['error' => "Couldn't reach the spell list. Try again in a minute."]);
    $list = array_merge(json_decode($out, true), array_map(function ($s) { return ['wd:' . $s['index'], $s['name'], $s['level']]; }, $extra));
    usort($list, function ($a, $b) { return strcasecmp($a[1], $b[1]); });
    srd_out(200, $list);
}

$i = (string)($_GET['i'] ?? '');
if (substr($i, 0, 3) === 'wd:') {
    foreach ($extra as $s) if ('wd:' . $s['index'] === $i) {
        $url = 'https://dnd5e.wikidot.com/spell:' . $s['index'];
        // the paragraphs after the stat block, minus the class list; a failed fetch still returns the stats
        [$text] = srd_get($url, function ($j) {
            if (!preg_match('~<div id="page-content">(.*?)<div class="content-separator"[^>]*>\s*</div>\s*</div>~s', $j['html'], $m)) return ['desc' => [], 'higher_level' => []];
            $body = preg_replace('~^.*?<strong>Duration:</strong>.*?</p>~', '', preg_replace('~\s+~', ' ', $m[1]));
            $body = preg_replace('~</(p|li|tr|h\d)>|<br\s*/?>~i', "\n", preg_replace('~</t[dh]>~i', ' | ', $body));
            $desc = $higher = [];
            foreach (explode("\n", html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8')) as $line) {
                $line = trim(preg_replace('~\s*\|\s*$~', '', trim($line)));
                if ($line === '' || strpos($line, 'Spell Lists.') === 0) continue;
                if (strpos($line, 'At Higher Levels.') === 0) $higher[] = trim(substr($line, 17));
                else $desc[] = $line;
            }
            return ['desc' => $desc, 'higher_level' => $higher];
        }, true);
        srd_out(200, ['wd' => $url, 'index' => $i] + $s + (json_decode((string)$text, true) ?: []));
    }
    srd_out(404, ['error' => 'Unknown spell.']);
}
if (!preg_match('/^[a-z0-9-]{1,80}$/', $i)) srd_out(400, ['error' => 'Unknown spell.']);
[$out, $code] = srd_get(SRD_API . '/' . $i, function ($j) {
    $keep = ['index', 'name', 'level', 'desc', 'higher_level', 'range', 'components', 'material', 'ritual', 'duration', 'concentration', 'casting_time', 'attack_type'];
    $s = array_intersect_key($j, array_flip($keep));
    $s['school'] = $j['school']['name'] ?? '';
    if (!empty($j['dc']['dc_type']['name'])) $s['save'] = $j['dc']['dc_type']['name'];
    $s['damage'] = array_map(function ($d) {
        return ['type' => $d['damage_type']['name'] ?? '', 'slot' => $d['damage_at_slot_level'] ?? null, 'char' => $d['damage_at_character_level'] ?? null];
    }, isset($j['damage']['damage_type']) || isset($j['damage']['damage_at_slot_level']) || isset($j['damage']['damage_at_character_level']) ? [$j['damage']] : (array)($j['damage'] ?? []));
    return $s;
});
$out ? srd_out(200, $out) : srd_out($code, ['error' => $code === 404 ? 'That spell isn\'t in the SRD.' : "Couldn't reach the spell list. Try again in a minute."]);
