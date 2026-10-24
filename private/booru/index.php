<?php
header('X-Robots-Tag: noindex, nofollow');

// Saved groups and presets (php/ is gitignored, so this never reaches GitHub)
$dataFile = $_SERVER['DOCUMENT_ROOT'] . '/php/booru_data.json';

if (isset($_GET['save'])) {
    header('Content-Type: application/json');
    $raw = $_SERVER['REQUEST_METHOD'] === 'POST' ? file_get_contents('php://input') : '';
    // Decode as objects (not arrays) so {} and numeric group names survive the round trip
    $data = json_decode($raw);
    if (strlen($raw) > 1048576 || stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0
        || !is_object($data) || !is_object($data->groups ?? null) || !is_object($data->presets ?? null)) {
        http_response_code(400);
        echo '{"ok":false}';
        exit;
    }
    $out = json_encode(['groups' => $data->groups, 'presets' => $data->presets]);
    echo json_encode(['ok' => @file_put_contents($dataFile, $out, LOCK_EX) !== false]);
    exit;
}

// Is this request coming from a PIA server? Same list and cache file as /tools/vpn-check/.
function booru_on_pia()
{
    $path = sys_get_temp_dir() . '/craftyplayz-pia/ips.json';
    $ips = is_file($path) ? json_decode((string)@file_get_contents($path), true) : null;
    if (!$ips || time() - filemtime($path) >= 86400) {
        $ch = curl_init('https://serverlist.piaservers.net/vpninfo/servers/v6');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 10, CURLOPT_ENCODING => '']);
        // the JSON is the first line; a signature follows it
        $json = json_decode(strtok((string)curl_exec($ch), "\n"), true);
        $fresh = [];
        foreach ($json['regions'] ?? [] as $r) foreach ($r['servers'] ?? [] as $list) foreach ($list as $s) if (!empty($s['ip'])) $fresh[$s['ip']] = $r['name'];
        if ($fresh) {
            @mkdir(dirname($path), 0755, true);
            @file_put_contents($path, json_encode($fresh), LOCK_EX);
            $ips = $fresh;
        }
    }
    if (!$ips) return null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (isset($ips[$ip])) return true;
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return false;
    // same /24 counts: the list only shows some of PIA's servers at a time
    $block = substr($ip, 0, strrpos($ip, '.') + 1);
    foreach ($ips as $k => $_) if (strpos($k, $block) === 0) return true;
    return false;
}

if (isset($_GET['vpn'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode(['pia' => booru_on_pia()]);
    exit;
}

$saved = (object)['groups' => new stdClass(), 'presets' => new stdClass()];
if (is_file($dataFile)) {
    $d = json_decode((string)file_get_contents($dataFile));
    if (is_object($d)) $saved = $d;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Booru Query Builder</title>
    <style>
        :root {
            --bg: #f4f5f7; --panel: #fff; --text: #1d2025; --muted: #6b7280; --border: #d6d9de;
            --accent: #2563eb; --accent-text: #fff; --inc: #16a34a; --exc: #dc2626; --alt: #9333ea; --wild: #d97706;
            --chip: #eef2ff; --hover: #f1f5f9; --code: #0f172a; --code-text: #e2e8f0; --warn-bg: #fef3c7; --warn-text: #92400e;
            --grp: #0d9488; --grp-bg: #ccfbf1;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #111317; --panel: #1b1e24; --text: #e5e7eb; --muted: #9ca3af; --border: #2f343c;
                --accent: #3b82f6; --chip: #252a33; --hover: #262b34; --code: #0b0d10; --warn-bg: #3a2e10; --warn-text: #fcd34d;
                --grp: #2dd4bf; --grp-bg: #11332f;
            }
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: var(--bg); color: var(--text); }
        main { max-width: 900px; margin: 0 auto; padding: 24px 16px 160px; }
        header { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px; margin-bottom: 20px; }
        h1 { margin: 0 0 4px; font-size: 1.6rem; }
        .sub { color: var(--muted); margin: 0; font-size: .9rem; max-width: 560px; }
        .site { display: flex; align-items: center; gap: 8px; font-size: .9rem; }
        .site select { width: auto; font-weight: 600; }
        .head-tools { display: flex; flex-direction: column; align-items: flex-end; gap: 8px; }
        .presets { display: flex; gap: 6px; align-items: center; }
        .presets select { width: auto; max-width: 200px; }
        .new-group { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
        .new-group input { flex: 1 1 180px; width: auto; }
        section { background: var(--panel); border: 1px solid var(--border); border-radius: 10px; padding: 16px; margin-bottom: 14px; }
        section > h2 { margin: 0 0 4px; font-size: 1rem; display: flex; align-items: center; gap: 8px; }
        .hint { color: var(--muted); font-size: .82rem; margin: 0 0 10px; }
        .dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
        code { background: var(--chip); padding: 1px 5px; border-radius: 4px; font-size: .85em; }

        /* chip input */
        .chips { position: relative; display: flex; flex-wrap: wrap; gap: 6px; padding: 6px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg); min-height: 42px; cursor: text; }
        .chips:focus-within { border-color: var(--accent); }
        .chips input { flex: 1; min-width: 140px; border: 0; outline: 0; background: transparent; color: var(--text); font: inherit; padding: 4px; }
        .chip { display: inline-flex; align-items: center; gap: 6px; background: var(--chip); border-left: 3px solid var(--c, var(--accent)); border-radius: 6px; padding: 3px 4px 3px 8px; font-size: .88rem; }
        .chip button { border: 0; background: none; color: var(--muted); cursor: pointer; font-size: 1rem; line-height: 1; padding: 0 4px; }
        .chip button:hover { color: var(--exc); }
        .chip.grp { background: var(--grp-bg); border-left-color: var(--grp); color: var(--grp); font-weight: 600; }
        .chip.grp::before { content: "▣"; font-size: .75em; }
        .chip.missing { opacity: .6; text-decoration: line-through; }
        .grp-name { color: var(--grp); font-weight: 600; }
        .ac { position: absolute; left: -1px; right: -1px; top: 100%; z-index: 10; background: var(--panel); border: 1px solid var(--border); border-radius: 8px; margin-top: 4px; max-height: 280px; overflow-y: auto; box-shadow: 0 8px 24px rgba(0,0,0,.18); display: none; }
        .ac div { padding: 7px 10px; cursor: pointer; display: flex; justify-content: space-between; gap: 10px; font-size: .9rem; }
        .ac div span { color: var(--muted); font-size: .8rem; }
        .ac div.sel, .ac div:hover { background: var(--hover); }

        .group { display: flex; gap: 8px; align-items: flex-start; margin-bottom: 8px; }
        .group .chips { flex: 1; }
        .group-label { font-size: .75rem; color: var(--muted); padding-top: 12px; min-width: 52px; }

        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
        .f { display: flex; flex-direction: column; gap: 4px; font-size: .82rem; color: var(--muted); }
        input[type=text], input[type=number], select { font: inherit; color: var(--text); background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 8px 10px; width: 100%; }
        input:focus, select:focus { outline: 0; border-color: var(--accent); }
        .range { display: flex; gap: 6px; }

        .btn { font: inherit; border: 1px solid var(--border); background: var(--panel); color: var(--text); border-radius: 8px; padding: 8px 14px; cursor: pointer; text-decoration: none; }
        .btn:hover { background: var(--hover); }
        .btn.primary { background: var(--accent); border-color: var(--accent); color: var(--accent-text); }
        .btn.small { padding: 4px 10px; font-size: .82rem; }

        .ratings { display: flex; flex-wrap: wrap; gap: 8px; }
        .rating { font: inherit; border: 1px solid var(--border); background: var(--bg); color: var(--text); border-radius: 999px; padding: 6px 14px; cursor: pointer; text-transform: capitalize; }
        .rating[data-state=inc] { background: var(--inc); border-color: var(--inc); color: #fff; }
        .rating[data-state=exc] { background: var(--exc); border-color: var(--exc); color: #fff; text-decoration: line-through; }

        .out { position: fixed; left: 0; right: 0; bottom: 0; background: var(--panel); border-top: 1px solid var(--border); padding: 12px 16px; box-shadow: 0 -6px 20px rgba(0,0,0,.12); }
        .out-inner { max-width: 900px; margin: 0 auto; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        #query { flex: 1 1 300px; min-height: 40px; background: var(--code); color: var(--code-text); font-family: ui-monospace, Consolas, monospace; font-size: .9rem; padding: 10px 12px; border-radius: 8px; word-break: break-all; }
        #query:empty::before { content: "Add something above to build a query"; color: #64748b; }
        #warn { flex-basis: 100%; background: var(--warn-bg); color: var(--warn-text); font-size: .82rem; padding: 6px 10px; border-radius: 6px; }
        #warn:empty { display: none; }

        details summary { cursor: pointer; font-weight: 600; }
        table { border-collapse: collapse; width: 100%; font-size: .85rem; margin-top: 10px; }
        td { border-top: 1px solid var(--border); padding: 6px 4px; vertical-align: top; }
        td:first-child { white-space: nowrap; }
        .status { font-size: .78rem; color: var(--muted); }
        .btn.primary.novpn { background: var(--exc); border-color: var(--exc); }
        dialog { background: var(--panel); color: var(--text); border: 1px solid var(--border); border-radius: 10px; padding: 20px; max-width: min(400px, calc(100vw - 32px)); }
        dialog::backdrop { background: rgba(0,0,0,.5); }
        dialog h2 { margin: 0 0 8px; font-size: 1.1rem; }
        dialog p { margin: 0 0 16px; color: var(--muted); font-size: .9rem; }
        dialog .actions { display: flex; gap: 8px; justify-content: flex-end; flex-wrap: wrap; }
    </style>
</head>
<body>
<main>
    <header>
        <div>
            <h1>Booru Query Builder</h1>
            <p class="sub">Build search queries without remembering the syntax. Tag suggestions come from the Rule34 tag database. <span class="status" id="acStatus"></span></p>
        </div>
        <div class="head-tools">
            <div class="presets">
                <select id="presetSel" title="Load a saved search"><option value="">Saved searches…</option></select>
                <button class="btn small" id="presetSave" type="button">Save search</button>
                <button class="btn small" id="presetDel" type="button" title="Delete the selected saved search">Delete</button>
            </div>
            <span class="status" id="saveStatus"></span>
            <label class="site">Site
                <select id="site">
                    <option value="rule34">rule34.xxx</option>
                    <option value="gelbooru">Gelbooru</option>
                    <option value="danbooru">Danbooru</option>
                </select>
            </label>
        </div>
    </header>

    <section>
        <h2><span class="dot" style="background:var(--inc)"></span>Required tags</h2>
        <p class="hint">Posts must have <b>every</b> tag here. Press Enter, Tab, space or comma to add a tag. Type <code>group/name</code> to add a <span class="grp-name">saved group</span>.</p>
        <div class="chips" id="required" data-color="var(--inc)"></div>
    </section>

    <section>
        <h2><span class="dot" style="background:var(--alt)"></span>Alternative tags (OR)</h2>
        <p class="hint" id="altHint"></p>
        <div id="altGroups"></div>
        <button class="btn small" id="addGroup" type="button">+ Add another group</button>
    </section>

    <section>
        <h2><span class="dot" style="background:var(--exc)"></span>Exclude tags</h2>
        <p class="hint">Posts with <b>any</b> of these tags are hidden. <code>group/name</code> excludes every tag in a group.</p>
        <div class="chips" id="exclude" data-color="var(--exc)"></div>
    </section>

    <section>
        <h2><span class="dot" style="background:var(--wild)"></span>Wildcard tags</h2>
        <p class="hint">Use <code>*</code> for any text: <code>blue_*</code> (starts with), <code>*hair</code> (ends with), <code>ab*d</code>. Start with <code>-</code> to exclude, e.g. <code>-*_hat</code>. A <code>*</code> is added at the end if you leave it out.</p>
        <div class="chips" id="wildcard" data-color="var(--wild)"></div>
    </section>

    <section>
        <h2>Artist, character &amp; copyright</h2>
        <p class="hint" id="typedHint"></p>
        <div class="grid">
            <div class="f">Artist<div class="chips" id="artist" data-color="var(--accent)"></div></div>
            <div class="f">Character<div class="chips" id="character" data-color="var(--accent)"></div></div>
            <div class="f">Copyright / series<div class="chips" id="copyright" data-color="var(--accent)"></div></div>
        </div>
    </section>

    <section>
        <h2>Rating</h2>
        <p class="hint">Click once to <b style="color:var(--inc)">require</b>, twice to <b style="color:var(--exc)">exclude</b>, three times to reset. Only one rating can be required.</p>
        <div class="ratings" id="ratings"></div>
    </section>

    <section>
        <h2>Score &amp; dimensions</h2>
        <p class="hint">Leave a box empty to ignore it. Set min and max to the same number for an exact match.</p>
        <div class="grid">
            <label class="f">Score (min – max)<div class="range"><input type="number" id="scoreMin" placeholder="min"><input type="number" id="scoreMax" placeholder="max"></div></label>
            <label class="f">Width px (min – max)<div class="range"><input type="number" id="widthMin" placeholder="min" min="0"><input type="number" id="widthMax" placeholder="max" min="0"></div></label>
            <label class="f">Height px (min – max)<div class="range"><input type="number" id="heightMin" placeholder="min" min="0"><input type="number" id="heightMax" placeholder="max" min="0"></div></label>
        </div>
    </section>

    <section>
        <h2>Metadata</h2>
        <p class="hint">Search by uploader, favourites, pools, IDs, file hash or source. Wildcards work in <code>md5:</code> and <code>source:</code>.</p>
        <div class="grid">
            <label class="f">Uploader (user:)<input type="text" id="user" placeholder="username"></label>
            <label class="f"><span id="favLabel">Favourited by (fav:)</span><input type="text" id="fav"></label>
            <label class="f">Pool (pool:)<input type="text" id="pool" placeholder="456"></label>
            <label class="f">Post ID (id:)<input type="text" id="id" placeholder="12345"></label>
            <label class="f">Parent ID (parent:)<input type="text" id="parent" placeholder="12345"></label>
            <label class="f">File hash (md5:)<input type="text" id="md5" placeholder="abc123 or abc*"></label>
            <label class="f">Source (source:)<input type="text" id="source" placeholder="*pixiv*"></label>
        </div>
    </section>

    <section>
        <h2>Sorting</h2>
        <div class="grid">
            <label class="f">Sort by<select id="sort"></select></label>
            <label class="f" id="dirWrap">Direction
                <select id="dir">
                    <option value="desc">Descending (highest first)</option>
                    <option value="asc">Ascending (lowest first)</option>
                </select>
            </label>
            <label class="f" id="seedWrap" hidden>Random seed (optional)<input type="number" id="seed" placeholder="e.g. 3456"></label>
        </div>
    </section>

    <section>
        <h2><span class="dot" style="background:var(--grp)"></span>Saved groups</h2>
        <p class="hint">A group is a named list of tags. Type <code>group/name</code> in required, alternative or exclude tags to add every tag in it at once. Groups can include other groups.</p>
        <div id="groupList"></div>
        <div class="new-group">
            <input type="text" id="newGroupName" placeholder="New group name, e.g. eye_colours">
            <button class="btn small" id="newGroup" type="button">Create empty group</button>
            <button class="btn small" id="newGroupFromReq" type="button">Create from required tags</button>
        </div>
    </section>

    <section>
        <h2>Anything else</h2>
        <p class="hint">Raw text added to the end of the query as-is.</p>
        <input type="text" id="extra" placeholder="e.g. tag_a tag_b">
    </section>

    <section>
        <details>
            <summary>Syntax reference for <span id="refSite"></span></summary>
            <table id="refTable"></table>
            <p class="hint" style="margin-top:10px">Ratings and tags depend on how posts were tagged, so treat them as a guide.</p>
        </details>
    </section>
</main>

<div class="out">
    <div class="out-inner">
        <div id="query"></div>
        <button class="btn" id="copy" type="button">Copy</button>
        <button class="btn" id="reset" type="button">Clear all</button>
        <a class="btn primary" id="open" href="#" target="_blank" rel="noopener noreferrer">Search</a>
        <div id="warn"></div>
    </div>
</div>

<dialog id="vpnDlg">
    <h2>Connect to a VPN first</h2>
    <p>Your IP isn't one of PIA's servers. Turn on PIA, or search anyway without it.</p>
    <div class="actions">
        <button class="btn" id="vpnCancel" type="button">Cancel</button>
        <button class="btn" id="vpnOverride" type="button">Search without VPN</button>
    </div>
</dialog>

<script>
const $ = id => document.getElementById(id);
const esc = s => s.replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));

// Per-site syntax. rule34.xxx runs an older Gelbooru engine; Danbooru is its own thing.
const SITES = {
    rule34: {
        name: 'rule34.xxx',
        url: q => 'https://rule34.xxx/index.php?page=post&s=list&tags=' + encodeURIComponent(q || 'all'),
        or: groups => groups.map(l => `( ${l.join(' ~ ')} )`),
        orExample: '( blue_eyes ~ green_eyes )',
        ratings: ['safe', 'questionable', 'explicit'],
        typed: false,
        fav: { label: 'Favourited by user ID (fav:)', placeholder: '123' },
        sorts: [['score', 'Score'], ['id', 'Post ID'], ['updated', 'Last updated'], ['rating', 'Rating'],
                ['width', 'Width'], ['height', 'Height'], ['user', 'Uploader'], ['source', 'Source']],
        sort: (f, dir) => `sort:${f}:${dir}`,
        sortExample: 'sort:score:desc',
    },
    gelbooru: {
        name: 'Gelbooru',
        url: q => 'https://gelbooru.com/index.php?page=post&s=list&tags=' + encodeURIComponent(q || 'all'),
        or: groups => groups.map(l => `{${l.join(' ~ ')}}`),
        orExample: '{blue_eyes ~ green_eyes}',
        orNote: " Groups don't work with meta tags like <code>rating:</code> or with aliased tags.",
        ratings: ['general', 'sensitive', 'questionable', 'explicit'],
        typed: true,
        fav: { label: 'Favourited by user ID (fav:)', placeholder: '123' },
        sorts: [['score', 'Score'], ['id', 'Post ID'], ['updated', 'Last updated'], ['rating', 'Rating'],
                ['width', 'Width'], ['height', 'Height'], ['user', 'Uploader'], ['source', 'Source'], ['random', 'Random']],
        sort: (f, dir, seed) => f === 'random' ? (seed ? `sort:random:${seed}` : 'sort:random') : `sort:${f}:${dir}`,
        sortExample: 'sort:score:desc',
    },
    danbooru: {
        name: 'Danbooru',
        url: q => 'https://danbooru.donmai.us/posts?tags=' + encodeURIComponent(q),
        // A single group can use ~tags; several groups need parentheses so they don't merge into one.
        or: groups => groups.length === 1 ? [groups[0].map(t => '~' + t).join(' ')]
                                          : groups.map(l => `(${l.join(' or ')})`),
        orExample: '~blue_eyes ~green_eyes',
        orNote: ' With more than one group, <code>(a or b)</code> is used instead.',
        ratings: ['general', 'sensitive', 'questionable', 'explicit'],
        typed: false,
        fav: { label: 'Favourited by username (fav:)', placeholder: 'username' },
        sorts: [['score', 'Score'], ['id', 'Post ID'], ['favcount', 'Favourites'], ['random', 'Random']],
        sort: (f, dir) => {
            if (f === 'random') return 'order:random';
            if (f === 'id') return dir === 'asc' ? 'order:id' : 'order:id_desc';
            return dir === 'asc' ? `order:${f}_asc` : `order:${f}`;
        },
        sortExample: 'order:score',
        tagLimit: 2,
    },
};
let site = SITES.rule34;

// Saved groups { name: [tags] } and presets { name: state }, loaded from the server
const isObj = v => v && typeof v === 'object' && !Array.isArray(v);
const saved = <?= json_encode($saved, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
if (!isObj(saved.groups)) saved.groups = {};
if (!isObj(saved.presets)) saved.presets = {};
if (!Object.keys(saved.groups).length && !Object.keys(saved.presets).length) {
    try {
        const backup = JSON.parse(localStorage.getItem('booruSaved'));
        if (backup && isObj(backup.groups)) saved.groups = backup.groups;
        if (backup && isObj(backup.presets)) saved.presets = backup.presets;
    } catch (e) {}
}

let persistTimer;
function persist() {
    try { localStorage.setItem('booruSaved', JSON.stringify(saved)); } catch (e) {}
    clearTimeout(persistTimer);
    persistTimer = setTimeout(async () => {
        try {
            const res = await fetch('?save=1', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(saved) });
            if (!res.ok || !(await res.json()).ok) throw new Error();
            $('saveStatus').textContent = '';
        } catch (e) {
            $('saveStatus').textContent = "Couldn't save to the server. Groups and saved searches are only kept in this browser.";
        }
    }, 400);
}

const GROUP_RE = /^group\/(.+)$/;
const groupName = s => s.toLowerCase().trim().replace(/\s+/g, '_').replace(/[^a-z0-9_.\-()]/g, '');

// Replace group/name tokens with the group's tags (recursively, ignoring loops and unknown groups)
function expand(tokens, seen = new Set()) {
    const out = [];
    tokens.forEach(t => {
        const m = GROUP_RE.exec(t);
        if (!m) return out.push(t);
        const tags = saved.groups[m[1]];
        if (!tags || seen.has(m[1])) return;
        seen.add(m[1]);
        out.push(...expand(tags, seen));
        seen.delete(m[1]);
    });
    return [...new Set(out)];
}

const AC_URL = 'https://api.rule34.xxx/autocomplete.php?q=';
const acCache = new Map();

async function suggest(term) {
    if (acCache.has(term)) return acCache.get(term);
    try {
        const res = await fetch(AC_URL + encodeURIComponent(term));
        const data = await res.json();
        const list = data.map(d => {
            const m = /\((\d+)\)\s*$/.exec(d.label);
            return { value: d.value, count: m ? Number(m[1]).toLocaleString() : '' };
        });
        acCache.set(term, list);
        $('acStatus').textContent = '';
        return list;
    } catch (e) {
        $('acStatus').textContent = '(Tag suggestions unavailable right now.)';
        return [];
    }
}

// Chip input: a list of tags with Rule34 autocomplete. opts.groups allows group/name chips.
const chipInputs = [];
function chipInput(el, opts = {}) {
    const tags = [];
    const changed = () => (opts.onChange || update)();
    const color = el.dataset.color;
    const input = document.createElement('input');
    input.type = 'text';
    input.placeholder = opts.placeholder || 'Type a tag…';
    input.autocomplete = 'off';
    const ac = document.createElement('div');
    ac.className = 'ac';
    el.append(input, ac);
    el.addEventListener('click', e => { if (e.target === el) input.focus(); });

    let items = [], sel = -1, timer;
    const term = () => input.value.trim().replace(/^-/, '').replace(/\s+/g, '_');

    function render() {
        el.querySelectorAll('.chip').forEach(c => c.remove());
        tags.forEach((t, i) => {
            const chip = document.createElement('span');
            const g = GROUP_RE.exec(t);
            if (g) {
                const members = saved.groups[g[1]];
                chip.className = 'chip grp' + (members ? '' : ' missing');
                chip.textContent = g[1];
                chip.title = members ? (members.join(' ') || '(empty group)') : 'No saved group with this name';
            } else {
                chip.className = 'chip';
                if (color) chip.style.setProperty('--c', color);
                chip.textContent = t;
            }
            const x = document.createElement('button');
            x.type = 'button';
            x.textContent = '×';
            x.title = 'Remove';
            x.onclick = () => { tags.splice(i, 1); render(); changed(); };
            chip.append(x);
            el.insertBefore(chip, input);
        });
    }
    function add(raw) {
        let t = raw.trim().replace(/\s+/g, '_');
        if (opts.clean) t = opts.clean(t);
        if (opts.groups && /^group\//i.test(t)) t = 'group/' + groupName(t.slice(6));
        if (t && t !== 'group/' && !tags.includes(t)) tags.push(t);
        input.value = '';
        hide();
        render();
        changed();
    }
    function hide() { ac.style.display = 'none'; items = []; sel = -1; }
    function show() {
        ac.innerHTML = '';
        items.forEach((it, i) => {
            const row = document.createElement('div');
            row.className = i === sel ? 'sel' : '';
            row.innerHTML = '<b></b><span></span>';
            row.firstChild.textContent = it.value;
            row.lastChild.textContent = it.count;
            row.onmousedown = e => { e.preventDefault(); add(it.value); };
            ac.append(row);
        });
        ac.style.display = items.length ? 'block' : 'none';
    }

    input.addEventListener('input', () => {
        clearTimeout(timer);
        const t = term();
        if (opts.groups && /^group\//i.test(t)) {
            const prefix = groupName(t.slice(6));
            items = Object.keys(saved.groups).filter(n => n.startsWith(prefix)).sort()
                .map(n => ({ value: 'group/' + n, count: saved.groups[n].length + ' tags' }));
            sel = items.length ? 0 : -1;
            return show();
        }
        if (opts.noAutocomplete || t.length < 2) return hide();
        timer = setTimeout(async () => {
            const res = await suggest(t);
            if (term() !== t) return;
            items = res; sel = -1; show();
        }, 200);
    });
    input.addEventListener('keydown', e => {
        if (e.key === 'ArrowDown' && items.length) { e.preventDefault(); sel = (sel + 1) % items.length; show(); }
        else if (e.key === 'ArrowUp' && items.length) { e.preventDefault(); sel = (sel - 1 + items.length) % items.length; show(); }
        else if (e.key === 'Escape') hide();
        else if (e.key === 'Enter' || e.key === ',' || e.key === ' ' || (e.key === 'Tab' && (input.value.trim() || sel >= 0))) {
            if (sel >= 0) { e.preventDefault(); add(items[sel].value); }
            else if (input.value.trim()) { e.preventDefault(); add(input.value); }
        }
        else if (e.key === 'Backspace' && !input.value && tags.length) { tags.pop(); render(); changed(); }
    });
    input.addEventListener('paste', e => {
        const text = e.clipboardData.getData('text');
        if (!/[\s,]/.test(text.trim())) return;
        e.preventDefault();
        text.split(/[\s,]+/).filter(Boolean).forEach(add);
    });
    input.addEventListener('blur', () => { if (input.value.trim()) add(input.value); else hide(); });

    const api = {
        el, render,
        get: () => tags.slice(),
        set: list => { tags.length = 0; tags.push(...list); render(); },
    };
    chipInputs.push(api);
    return api;
}

// Re-draw every chip box so group chips reflect added, edited or deleted groups
function refreshChips() {
    for (let i = chipInputs.length - 1; i >= 0; i--) {
        if (chipInputs[i].el.isConnected) chipInputs[i].render();
        else chipInputs.splice(i, 1);
    }
}

const stripDash = t => t.replace(/^-+/, '');
const stripPrefix = p => t => t.replace(new RegExp('^' + p + ':'), '');
const fields = {
    required: chipInput($('required'), { groups: true }),
    exclude: chipInput($('exclude'), { groups: true, clean: stripDash }),
    wildcard: chipInput($('wildcard'), {
        noAutocomplete: true,
        placeholder: 'e.g. blue_* or *hair',
        clean: t => t.includes('*') ? t : t + '*',
    }),
    artist: chipInput($('artist'), { clean: stripPrefix('artist') }),
    character: chipInput($('character'), { clean: stripPrefix('character') }),
    copyright: chipInput($('copyright'), { clean: stripPrefix('copyright') }),
};

// Alternative (OR) groups
const altGroups = [];
function addAltGroup(list = []) {
    const row = document.createElement('div');
    row.className = 'group';
    const label = document.createElement('div');
    label.className = 'group-label';
    const box = document.createElement('div');
    box.className = 'chips';
    box.dataset.color = 'var(--alt)';
    const del = document.createElement('button');
    del.type = 'button';
    del.className = 'btn small';
    del.textContent = 'Remove';
    del.style.marginTop = '6px';
    row.append(label, box, del);
    $('altGroups').append(row);
    const g = chipInput(box, { groups: true, placeholder: 'Add options, or group/name…' });
    g.row = row;
    g.set(list);
    altGroups.push(g);
    del.onclick = () => {
        altGroups.splice(altGroups.indexOf(g), 1);
        row.remove();
        if (!altGroups.length) addAltGroup();
        relabel();
        update();
    };
    relabel();
    return g;
}
function relabel() { altGroups.forEach((g, i) => g.row.firstChild.textContent = 'Group ' + (i + 1)); }
$('addGroup').onclick = () => addAltGroup();

// Ratings: neutral -> inc -> exc -> neutral. State is kept per rating name across site switches.
let ratingState = {};
function renderRatings() {
    $('ratings').innerHTML = '';
    site.ratings.forEach(r => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'rating';
        b.textContent = r;
        b.dataset.state = ratingState[r] || '';
        b.onclick = () => {
            const next = { '': 'inc', inc: 'exc', exc: '' }[ratingState[r] || ''];
            if (next === 'inc') Object.keys(ratingState).forEach(k => { if (ratingState[k] === 'inc') ratingState[k] = ''; });
            ratingState[r] = next;
            renderRatings();
            update();
        };
        $('ratings').append(b);
    });
}

function applySite() {
    $('altHint').innerHTML = `Posts must have <b>at least one</b> tag from each group, e.g. <code>${esc(site.orExample)}</code>.` + (site.orNote || '') +
        ' Adding <code>group/name</code> makes every tag in that saved group an option.';
    $('typedHint').textContent = site.typed
        ? 'Typed tags. Each adds e.g. artist:name to the query.'
        : `${site.name} has no artist:/character:/copyright: prefixes, so these are added as normal tags.`;
    $('favLabel').textContent = site.fav.label;
    $('fav').placeholder = site.fav.placeholder;
    setOpenLabel();

    const current = $('sort').value;
    $('sort').innerHTML = '<option value="">Default order</option>' +
        site.sorts.map(([v, l]) => `<option value="${v}">${l}</option>`).join('');
    $('sort').value = site.sorts.some(([v]) => v === current) ? current : '';

    renderRatings();

    $('refSite').textContent = site.name;
    const rows = [
        ['tag1 tag2', 'Both tags required'],
        ['-tag', 'Exclude a tag'],
        [site.orExample, 'At least one of the tags'],
        ['blue_* / *hair', 'Wildcards'],
        [`rating:${site.ratings[0]} / -rating:explicit`, 'Require or exclude a rating: ' + site.ratings.join(', ')],
        ['score:>=100', 'Number filters, also width: and height:. Operators: > >= < <=, or none for exact'],
        ['user: fav: pool: id: md5: parent: source:', 'Metadata searches'],
        [site.sortExample, 'Sorting'],
    ];
    if (site.typed) rows.push(['artist: character: copyright:', 'Typed tags']);
    $('refTable').innerHTML = rows.map(([a, b]) => `<tr><td><code>${esc(a)}</code></td><td>${esc(b)}</td></tr>`).join('');
}

$('site').addEventListener('change', () => {
    site = SITES[$('site').value];
    applySite();
    update();
});

const textIds = ['scoreMin', 'scoreMax', 'widthMin', 'widthMax', 'heightMin', 'heightMax',
    'user', 'fav', 'pool', 'id', 'parent', 'md5', 'source', 'sort', 'dir', 'seed', 'extra'];
textIds.forEach(id => $(id).addEventListener('input', update));

function rangeParts(key, min, max) {
    min = min.trim(); max = max.trim();
    if (min && max && Number(min) === Number(max)) return [`${key}:${min}`];
    const out = [];
    if (min) out.push(`${key}:>=${min}`);
    if (max) out.push(`${key}:<=${max}`);
    return out;
}

function build() {
    const q = [];
    q.push(...expand(fields.required.get()));
    const groups = altGroups.map(g => expand(g.get())).filter(l => l.length);
    groups.filter(l => l.length === 1).forEach(l => q.push(l[0]));
    const multi = groups.filter(l => l.length > 1);
    if (multi.length) q.push(...site.or(multi));
    q.push(...expand(fields.exclude.get()).map(t => '-' + stripDash(t)));
    q.push(...fields.wildcard.get());
    ['artist', 'character', 'copyright'].forEach(k =>
        q.push(...fields[k].get().map(t => site.typed ? `${k}:${t}` : t)));

    const inc = site.ratings.find(r => ratingState[r] === 'inc');
    if (inc) q.push('rating:' + inc);
    else site.ratings.forEach(r => { if (ratingState[r] === 'exc') q.push('-rating:' + r); });

    q.push(...rangeParts('score', $('scoreMin').value, $('scoreMax').value));
    q.push(...rangeParts('width', $('widthMin').value, $('widthMax').value));
    q.push(...rangeParts('height', $('heightMin').value, $('heightMax').value));

    ['user', 'fav', 'pool', 'id', 'parent', 'md5', 'source'].forEach(k => {
        const v = $(k).value.trim().replace(/\s+/g, '_');
        if (v) q.push(`${k}:${v}`);
    });

    const sort = $('sort').value;
    if (sort) q.push(site.sort(sort, $('dir').value, $('seed').value.trim()));

    const extra = $('extra').value.trim();
    if (extra) q.push(extra);
    return q.join(' ');
}

function update() {
    const sort = $('sort').value;
    $('dirWrap').hidden = !sort || sort === 'random';
    $('seedWrap').hidden = !(sort === 'random' && site === SITES.gelbooru);
    const q = build();
    $('query').textContent = q;
    $('open').href = site.url(q);

    // Danbooru caps anonymous searches at 2 tags (rating: and order: don't count)
    let warn = '';
    if (site.tagLimit) {
        const counted = q.split(/\s+/).filter(t => t && !/^-?(rating|order):/.test(t) && !/^\(|\)$|^or$/.test(t));
        if (counted.length > site.tagLimit) warn = `Danbooru only allows ${site.tagLimit} tags per search unless you're logged in with an upgraded account. This query may be rejected.`;
    }
    const used = [fields.required, fields.exclude, ...altGroups].flatMap(f => f.get());
    const missing = [...new Set(used.map(t => (GROUP_RE.exec(t) || [])[1]).filter(n => n && !saved.groups[n]))];
    if (missing.length) warn = (warn ? warn + ' ' : '') + `No saved group called ${missing.join(', ')}, so it's left out of the query.`;
    $('warn').textContent = warn;
    save();
}

// The whole form as a plain object, used for saved searches and the in-browser draft
function getState() {
    const state = { site: $('site').value, alt: altGroups.map(g => g.get()), ratings: { ...ratingState } };
    Object.keys(fields).forEach(k => state[k] = fields[k].get());
    textIds.forEach(id => state[id] = $(id).value);
    return state;
}
function applyState(state) {
    if (SITES[state.site]) { $('site').value = state.site; site = SITES[state.site]; }
    applySite();
    Object.keys(fields).forEach(k => fields[k].set(state[k] || []));
    altGroups.splice(0).forEach(g => g.row.remove());
    (state.alt && state.alt.length ? state.alt : [[]]).forEach(l => addAltGroup(l));
    ratingState = { ...(state.ratings || {}) };
    renderRatings();
    textIds.forEach(id => $(id).value = state[id] != null ? state[id] : (id === 'dir' ? 'desc' : ''));
    update();
}

// Remember the current draft in this browser
const STORE = 'booruBuilder';
function save() {
    try { localStorage.setItem(STORE, JSON.stringify(getState())); } catch (e) {}
}
function load() {
    let state = null;
    try { state = JSON.parse(localStorage.getItem(STORE)); } catch (e) {}
    applyState(state || { site: 'rule34' });
}

// Saved searches
function renderPresets(selected = '') {
    $('presetSel').innerHTML = '<option value="">Saved searches…</option>' +
        Object.keys(saved.presets).sort().map(n => `<option>${esc(n)}</option>`).join('');
    $('presetSel').value = selected;
}
$('presetSel').addEventListener('change', () => {
    const p = saved.presets[$('presetSel').value];
    if (p) applyState(p);
});
$('presetSave').onclick = () => {
    const name = (prompt('Name for this search:', $('presetSel').value) || '').trim();
    if (!name) return;
    if (saved.presets[name] && name !== $('presetSel').value && !confirm(`Replace the saved search "${name}"?`)) return;
    saved.presets[name] = getState();
    renderPresets(name);
    persist();
};
$('presetDel').onclick = () => {
    const name = $('presetSel').value;
    if (!name) return alert('Pick a saved search to delete first.');
    if (!confirm(`Delete the saved search "${name}"?`)) return;
    delete saved.presets[name];
    renderPresets();
    persist();
};

// Saved groups editor
function groupsChanged() {
    refreshChips();
    update();
    persist();
}
function renderGroups() {
    $('groupList').innerHTML = '';
    Object.keys(saved.groups).sort().forEach(name => {
        const row = document.createElement('div');
        row.className = 'group';
        const label = document.createElement('div');
        label.className = 'group-label grp-name';
        label.textContent = name;
        const box = document.createElement('div');
        box.className = 'chips';
        box.dataset.color = 'var(--grp)';
        const del = document.createElement('button');
        del.type = 'button';
        del.className = 'btn small';
        del.textContent = 'Delete';
        del.style.marginTop = '6px';
        row.append(label, box, del);
        $('groupList').append(row);
        const ci = chipInput(box, {
            groups: true,
            placeholder: 'Add tags to this group…',
            onChange: () => { saved.groups[name] = ci.get(); groupsChanged(); },
        });
        ci.set(saved.groups[name]);
        del.onclick = () => {
            if (!confirm(`Delete the group "${name}"?`)) return;
            delete saved.groups[name];
            renderGroups();
            groupsChanged();
        };
    });
}
function createGroup(tags) {
    const name = groupName($('newGroupName').value);
    if (!name) return alert('Type a name for the group first.');
    if (saved.groups[name] && !confirm(`Replace the group "${name}"?`)) return;
    saved.groups[name] = tags;
    $('newGroupName').value = '';
    renderGroups();
    groupsChanged();
}
$('newGroup').onclick = () => createGroup([]);
$('newGroupFromReq').onclick = () => createGroup(fields.required.get());
$('newGroupName').addEventListener('keydown', e => { if (e.key === 'Enter') createGroup([]); });

$('copy').onclick = async () => {
    const q = build();
    try { await navigator.clipboard.writeText(q); } catch (e) {
        const r = document.createRange(); r.selectNodeContents($('query'));
        getSelection().removeAllRanges(); getSelection().addRange(r); document.execCommand('copy');
    }
    $('copy').textContent = 'Copied!';
    setTimeout(() => $('copy').textContent = 'Copy', 1200);
};
$('reset').onclick = () => {
    if (!confirm('Clear the whole query?')) return;
    applyState({ site: $('site').value });
    $('presetSel').value = '';
};

// VPN guard: the Search button only works on a PIA IP (null = couldn't check, treated as off)
let onVpn = <?= json_encode(booru_on_pia()) ?>;
function setOpenLabel() {
    $('open').textContent = onVpn ? 'Search ' + site.name : 'Connect with a VPN first';
    $('open').classList.toggle('novpn', !onVpn);
}
async function checkVpn() {
    try {
        const res = await fetch('?vpn=1', { cache: 'no-store' });
        onVpn = (await res.json()).pia;
    } catch (e) {
        onVpn = null;
    }
    setOpenLabel();
}
setInterval(() => { if (!document.hidden) checkVpn(); }, 10000);
document.addEventListener('visibilitychange', () => { if (!document.hidden) checkVpn(); });
$('open').addEventListener('click', e => {
    if (onVpn) return;
    e.preventDefault();
    $('vpnDlg').showModal();
});
$('vpnCancel').onclick = () => $('vpnDlg').close();
$('vpnOverride').onclick = () => {
    $('vpnDlg').close();
    window.open($('open').href, '_blank', 'noopener,noreferrer');
};

renderPresets();
renderGroups();
load();
</script>
</body>
</html>
