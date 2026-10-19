<?php
$site_page = [
    'components' => true,
    'ui' => true,
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

// Found by scanning craftyplayz.com for A and _minecraft._tcp SRV records. Add new servers here.
$servers = [
    ['id' => 'play', 'name' => 'Survival', 'address' => 'play.craftyplayz.com'],
    ['id' => 'unlimited', 'name' => 'Unlimited', 'address' => 'unlimited.craftyplayz.com'],
    ['id' => 'creative','name' => 'Creative', 'address' => 'creative.craftyplayz.com'],
    ['id' => 'cobblemon', 'name' => 'Cobblemon', 'address' => 'cobblemon.craftyplayz.com'],
    ['id' => 'origins', 'name' => 'Origins', 'address' => 'origins.craftyplayz.com'],
    ['id' => 'bedrock', 'name' => 'Bedrock', 'address' => 'bedrock.craftyplayz.com', 'bedrock' => true],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
<style>
    .srv-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; margin-bottom: 16px; }
    .srv {
        display: flex; align-items: center; gap: 12px; width: 100%; padding: 12px; text-align: left;
        background: var(--card); border: 1px solid var(--line); border-radius: var(--radius-lg);
        color: var(--text); font: inherit; cursor: pointer; transition: border-color var(--fast);
    }
    .srv:hover, .srv.on { border-color: var(--accent); }
    .srv img, .det-head img { width: 48px; height: 48px; flex-shrink: 0; border-radius: 6px; image-rendering: pixelated; background: var(--sunken); }
    .srv .grow { flex: 1; min-width: 0; }
    .srv b { display: block; }
    .srv small { display: block; overflow: hidden; color: var(--muted); font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
    .srv .count { color: var(--muted); font-size: 13px; white-space: nowrap; }

    .dot { display: inline-block; width: 9px; height: 9px; margin-right: 6px; border-radius: 50%; background: #666; vertical-align: 1px; }
    .dot.up { background: var(--accent); box-shadow: 0 0 6px var(--accent); }
    .dot.down { background: #d9534f; }

    .det-head { display: flex; flex-wrap: wrap; align-items: center; gap: 14px; margin-bottom: 14px; }
    .det-head img { width: 64px; height: 64px; }
    .det-head h2 { margin: 0; font-size: 22px; }
    .det-head .grow { flex: 1; min-width: 180px; }

    /* MOTD in the style of the in-game server list */
    .motd {
        margin: 0 0 14px; padding: 10px 12px; overflow-x: auto;
        background: #101010; border: 1px solid var(--line); border-radius: var(--radius);
        color: #aaa; font: 15px/1.4 var(--mono); white-space: pre;
    }
    .motd .b { font-weight: 700; } .motd .i { font-style: italic; } .motd .u { text-decoration: underline; } .motd .s { text-decoration: line-through; }
    .motd .u.s { text-decoration: underline line-through; }

    .det-cols { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px; margin-top: 12px; }
    .det-cols .card { background: var(--card2); }
    .det-cols h3 { margin: 0 0 8px; font-size: 15px; }
    .players { display: flex; flex-wrap: wrap; gap: 6px; }
    .players span { display: inline-flex; align-items: center; gap: 6px; padding: 3px 8px 3px 3px; background: var(--card); border-radius: 6px; font-size: 13px; }
    .players img { width: 22px; height: 22px; border-radius: 3px; image-rendering: pixelated; }
    .bar-label { display: flex; justify-content: space-between; margin: 12px 0 4px; color: var(--muted); font-size: 13px; }
    pre.raw { max-height: 400px; overflow: auto; padding: 10px; background: var(--sunken); border-radius: var(--radius); font-size: 12px; }
    details summary { cursor: pointer; color: var(--muted); }
</style>
</head>

<body>
    <?php site_header(); ?>
    <main class="page" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Server Status</h1>
        <p class="page-lede">Live status of my Minecraft servers. Click one for everything it reports. <span class="muted" id="checked"></span></p>

        <div class="srv-grid" id="list"></div>
        <div class="card hide" id="detail"></div>
        <p class="muted" style="font-size:13px">Data from <a href="https://mcsrvstat.us/" target="_blank" rel="noopener">mcsrvstat.us</a>, cached for 5 minutes.</p>
    </main>

    <?php site_footer(null, false); ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const { $, esc, copy, ago, num } = UI;
    const SERVERS = <?= json_encode($servers) ?>;
    const DEFAULT_ICON = (s) => 'https://api.mcsrvstat.us/icon/' + encodeURIComponent(s.address);
    const status = {}; // id -> API response, or {error}
    let current = null;

    const url = (s) => 'https://api.mcsrvstat.us/' + (s.bedrock ? 'bedrock/3/' : '3/') + encodeURIComponent(s.address);

    // § formatting codes -> safe HTML (MOTDs come from the server, so never trust the API's html field)
    const COLORS = { 0: '#000', 1: '#00a', 2: '#0a0', 3: '#0aa', 4: '#a00', 5: '#a0a', 6: '#fa0', 7: '#aaa', 8: '#555', 9: '#55f', a: '#5f5', b: '#5ff', c: '#f55', d: '#f5f', e: '#ff5', f: '#fff', g: '#ddd605' };
    function motdHtml(raw) {
        let out = '', color = null, fmt = new Set();
        const parts = String(raw).split(/§([0-9a-gk-or]|#[0-9a-f]{6})/i);
        for (let i = 0; i < parts.length; i++) {
            if (i % 2) {
                const c = parts[i].toLowerCase();
                if (c[0] === '#' || COLORS[c]) { color = c[0] === '#' ? c : COLORS[c]; fmt = new Set(); }
                else if (c === 'r') { color = null; fmt = new Set(); }
                else if ('lmno'.includes(c)) fmt.add({ l: 'b', m: 's', n: 'u', o: 'i' }[c]);
            } else if (parts[i]) {
                out += '<span class="' + [...fmt].join(' ') + '"' + (color ? ' style="color:' + color + '"' : '') + '>' + esc(parts[i]) + '</span>';
            }
        }
        return out;
    }

    const isUp = (d) => d && d.online === true;
    const players = (d) => isUp(d) && d.players ? num(d.players.online) + ' / ' + num(d.players.max) : '';

    function renderList() {
        $('#list').innerHTML = SERVERS.map((s) => {
            const d = status[s.id];
            const state = !d ? '' : isUp(d) ? 'up' : 'down';
            const label = !d ? 'Checking…' : isUp(d) ? 'Online' : d.error ? 'Check failed' : 'Offline';
            return `<button class="srv${current === s.id ? ' on' : ''}" data-id="${esc(s.id)}">
                <img src="${esc(d && d.icon || DEFAULT_ICON(s))}" alt="" loading="lazy">
                <span class="grow"><b>${esc(s.name)}</b><small>${esc(s.address)}</small><small><span class="dot ${state}"></span>${label}${s.bedrock ? ' · Bedrock' : ''}</small></span>
                <span class="count">${players(d)}</span>
            </button>`;
        }).join('');
    }

    const tile = (label, value) => value === undefined || value === null || value === '' ? '' : `<div class="tile"><b>${esc(value)}</b><span>${esc(label)}</span></div>`;
    const kv = (rows) => '<div class="kv">' + rows.filter((r) => r[1] !== undefined && r[1] !== '').map(([k, v]) => `<span>${esc(k)}</span><span class="mono">${esc(v)}</span>`).join('') + '</div>';
    const yes = (b) => (b ? 'yes' : 'no');

    function renderDetail() {
        const box = $('#detail');
        const s = SERVERS.find((x) => x.id === current);
        if (!s) { box.classList.add('hide'); return; }
        const d = status[s.id];
        box.classList.remove('hide');
        if (!d) { box.innerHTML = '<p class="muted">Checking ' + esc(s.address) + '…</p>'; return; }
        if (d.error) { box.innerHTML = '<div class="err">Could not reach the status API: ' + esc(d.error) + '</div>'; return; }

        const up = isUp(d);
        const addr = d.hostname || s.address;
        const p = d.players || {};
        const pct = up && p.max ? Math.min(100, (p.online / p.max) * 100) : 0;
        const dbg = d.debug || {};
        const list = (arr) => arr.map((x) => `<span class="badge">${esc(x.name)}${x.version ? ' <span class="muted">' + esc(x.version) + '</span>' : ''}</span>`).join('');

        let html = `<div class="det-head">
            <img src="${esc(d.icon || DEFAULT_ICON(s))}" alt="">
            <div class="grow"><h2>${esc(s.name)}</h2><span class="mono">${esc(addr)}</span></div>
            <span class="badge ${up ? 'g' : 'r'}"><span class="dot ${up ? 'up' : 'down'}"></span>${up ? 'Online' : 'Offline'}</span>
            <button class="btn small" id="copyaddr">Copy address</button>
        </div>`;

        if (up && d.motd && d.motd.raw) html += `<div class="motd">${d.motd.raw.map(motdHtml).join('\n')}</div>`;
        if (!up) html += '<p class="muted">The server did not answer. It may be stopped or restarting.</p>';

        if (up) {
            html += '<div class="tiles">' +
                tile('Players', players(d)) +
                tile('Version', d.version) +
                tile('Protocol', d.protocol ? d.protocol.version : '') +
                tile('Software', d.software) +
                tile('Game mode', d.gamemode) +
                tile('Map', d.map && d.map.clean) +
                '</div>';
            html += `<div class="bar-label"><span>Player slots</span><span>${pct.toFixed(pct < 1 && pct > 0 ? 2 : 0)}% full</span></div><div class="progress"><i style="width:${pct}%"></i></div>`;
        }

        html += '<div class="det-cols">';
        if (up) {
            html += `<div class="card"><h3>Players online <span class="muted">${num(p.online || 0)}</span></h3>` +
                (p.list && p.list.length
                    ? '<div class="players">' + p.list.map((pl) => `<span><img src="https://mc-heads.net/avatar/${esc(pl.uuid || pl.name)}/22" alt="" loading="lazy">${esc(pl.name)}</span>`).join('') + '</div>'
                    : '<p class="muted">' + (p.online ? 'The server hides its player list.' : 'Nobody is on right now.') + '</p>') + '</div>';
            if (d.mods && d.mods.length) html += `<div class="card"><h3>Mods <span class="muted">${d.mods.length}</span></h3><div class="badges">${list(d.mods)}</div></div>`;
            if (d.plugins && d.plugins.length) html += `<div class="card"><h3>Plugins <span class="muted">${d.plugins.length}</span></h3><div class="badges">${list(d.plugins)}</div></div>`;
            if (d.info && d.info.clean) html += `<div class="card"><h3>Info</h3><div class="motd">${d.info.raw.map(motdHtml).join('\n')}</div></div>`;
        }
        html += `<div class="card"><h3>Connection</h3>${kv([
            ['Address', s.address],
            ['IP', d.ip ? d.ip + (d.port ? ':' + d.port : '') : ''],
            ['Edition', s.bedrock ? 'Bedrock' : 'Java'],
            ['Server ID', d.serverid],
            ['SRV record', yes(dbg.srv)],
            ['Query', yes(dbg.query)],
            ['EULA blocked', d.eula_blocked === undefined ? '' : yes(d.eula_blocked)],
            ['Checked', dbg.cachetime ? ago(dbg.cachetime * 1000) : ''],
            ['Next refresh', dbg.cacheexpire ? ago(dbg.cacheexpire * 1000) : ''],
        ])}</div>`;
        if (dbg.dns && dbg.dns.records) html += `<div class="card"><h3>DNS</h3>${kv(dbg.dns.records.map((r) => [r.type, r.type === 'SRV' ? r.target + ':' + r.port : r.address || r.cname || '']))}</div>`;
        html += '</div>';

        html += `<details style="margin-top:12px"><summary>Raw API response</summary><pre class="raw">${esc(JSON.stringify(d, null, 2))}</pre></details>`;
        box.innerHTML = html;
        $('#copyaddr').onclick = (e) => copy(addr, e.currentTarget);
    }

    function select(id, scroll) {
        current = SERVERS.some((s) => s.id === id) ? id : null;
        history.replaceState(null, '', current ? '#' + current : location.pathname);
        renderList();
        renderDetail();
        if (scroll && current) $('#detail').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    async function load(s) {
        try {
            const r = await fetch(url(s));
            if (!r.ok) throw new Error('HTTP ' + r.status);
            status[s.id] = await r.json();
        } catch (e) {
            status[s.id] = { error: e.message };
        }
        renderList();
        if (current === s.id) renderDetail();
    }

    function refresh() {
        SERVERS.forEach(load);
        $('#checked').textContent = 'Updated ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + '.';
    }

    $('#list').addEventListener('click', (e) => {
        const b = e.target.closest('.srv');
        if (b) select(current === b.dataset.id ? null : b.dataset.id, true);
    });

    select(location.hash.slice(1), false);
    refresh();
    setInterval(refresh, 5 * 60 * 1000); // the API caches for 5 minutes, so polling faster gains nothing
});
</script>
</body>

</html>
