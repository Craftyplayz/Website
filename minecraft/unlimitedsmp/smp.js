// UnlimitedSMP page: renders window.SMP.stats (from stats.php) and re-polls it every minute while the tab is visible.
document.addEventListener('DOMContentLoaded', () => {
    const { $, esc, copy, ago, num } = UI;
    let data = SMP.stats;
    let range = 'players24h';
    const boardTab = {};

    const head = (p, size) => `<img src="https://mc-heads.net/avatar/${esc(p.uuid || p.name)}/${size}" alt="" width="${size}" height="${size}" loading="lazy" decoding="async">`;
    const hours = (ms) => num(Math.round(ms / 3600000));
    const km = (cm) => num(cm / 100000, cm < 1e6 ? 1 : 0) + ' km';
    const date = (ms) => new Date(ms).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });

    // leaderboards: card → tabs → [label, field, format]
    const BOARDS = [
        ['Time played', [['Playtime', 'playtimeMs', (v) => hours(v) + ' h'], ['Sessions', 'sessions', num]]],
        ['Combat', [['Mob kills', 'mobKills', num], ['Player kills', 'playerKills', num], ['Deaths', 'deaths', num]]],
        ['Building and mining', [['Blocks mined', 'blocksMined', num], ['Blocks placed', 'blocksPlaced', num], ['Diamonds mined', 'diamondsMined', num]]],
        ['Travel and fun', [['Walked', 'walkedCm', km], ['Flown', 'flownCm', km], ['Jumps', 'jumps', num], ['Fish caught', 'fish', num], ['Villager trades', 'trades', num]]],
    ];

    const card = (value, label, small, title) => value === null || value === undefined || value === '' ? '' :
        `<div class="stat-card"${title ? ` title="${esc(title)}"` : ''}><b>${value}</b><span>${esc(label)}</span>${small ? `<small>${small}</small>` : ''}</div>`;

    function renderStats() {
        const s = data.status || {}, p = s.players || {}, perf = data.perf || {}, t = data.totals || {};
        $('#smp-stats').innerHTML =
            card(s.online ? num(p.online) + ' / ' + num(p.max) : 'Offline', 'Online now', s.online ? 'Server up' : 'Restarting or stopped') +
            card(t.players ? num(t.players) : '', 'Players all time') +
            card(t.playtimeMs ? hours(t.playtimeMs) : '', 'Hours played', '', t.playtimeMs ? num(t.playtimeMs / 3600000, 1) + ' h' : '') +
            card(typeof perf.tps === 'number' ? num(Math.min(perf.tps, 20), 1) : '', 'Ticks per second', perf.mspt ? num(perf.mspt, 1) + ' ms per tick' : '') +
            card(s.version ? esc(s.version) : '', 'Version');
        if (s.version) $('#smp-version').textContent = s.version;
        $('#smp-updated').textContent = data.updated ? 'Updated ' + ago(Date.parse(data.updated)) + '.' : '';
    }

    function renderPlayers() {
        const list = (data.status && data.status.players && data.status.players.list) || [];
        const n = (data.status && data.status.players && data.status.players.online) || 0;
        $('#smp-online-n').textContent = n ? num(n) : '';
        $('#smp-players').innerHTML = list.length
            ? list.map((p) => `<span class="smp-player">${head(p, 32)}<b>${esc(p.name)}</b></span>`).join('')
            : `<p class="muted">${n ? 'The server is hiding who is on.' : 'Nobody is on right now.'}</p>`;
    }

    // plain SVG line chart over [[ms, value], ...]; each point keeps its exact value in a <title>
    function line(points, max, fmtX, unit) {
        if (!points || points.length < 2) return '<p class="muted">Not enough history yet.</p>';
        const W = 600, H = 160, L = 30, B = 20, top = Math.max(max, ...points.map((p) => p[1])) || 1;
        const x0 = points[0][0], span = points[points.length - 1][0] - x0 || 1;
        const X = (t) => L + ((t - x0) / span) * (W - L - 4), Y = (v) => 4 + (1 - v / top) * (H - B - 8);
        const step = (W - L) / points.length;
        let svg = `<svg class="smp-line" viewBox="0 0 ${W} ${H}" role="img" aria-label="Line chart, highest ${num(Math.max(...points.map((p) => p[1])), 1)} ${unit}">`;
        [0, top / 2, top].forEach((v) => { svg += `<line class="grid" x1="${L}" x2="${W}" y1="${Y(v)}" y2="${Y(v)}"/><text x="${L - 6}" y="${Y(v) + 4}" text-anchor="end">${num(v, top < 10 ? 1 : 0)}</text>`; });
        [0, .5, 1].forEach((f) => { const t = x0 + span * f; svg += `<text x="${X(t)}" y="${H - 4}" text-anchor="${f ? f === 1 ? 'end' : 'middle' : 'start'}">${esc(fmtX(t))}</text>`; });
        svg += `<polyline points="${points.map((p) => X(p[0]).toFixed(1) + ',' + Y(p[1]).toFixed(1)).join(' ')}"/>`;
        svg += points.map((p) => `<rect class="hit" x="${(X(p[0]) - step / 2).toFixed(1)}" y="0" width="${step.toFixed(1)}" height="${H - B}"><title>${esc(fmtX(p[0], true))}: ${num(p[1], 1)} ${unit}</title></rect>`).join('');
        return svg + '</svg>';
    }
    const time = (t, full) => new Date(t).toLocaleString(undefined, full ? { weekday: 'short', hour: '2-digit', minute: '2-digit' } : { hour: '2-digit', minute: '2-digit' });
    const day = (t, full) => new Date(t).toLocaleString(undefined, full ? { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit' } : { weekday: 'short', day: 'numeric' });

    function renderCharts() {
        const h = data.history || {};
        $('#smp-players-chart').innerHTML = line(h[range], 2, range === 'players7d' ? day : time, 'players');
        $('#smp-tps-chart').innerHTML = line(h.tps24h && h.tps24h.map((p) => [p[0], Math.min(p[1], 20)]), 20, time, 'TPS');

        // weekday × hour grid in UTC (0 = Monday) → shifted into the viewer's time zone
        const grid = h.hours;
        if (!grid || grid.length !== 7) { $('#smp-hours').innerHTML = '<p class="muted">Not enough history yet.</p>'; return; }
        const off = Math.round(-new Date().getTimezoneOffset() / 60), local = new Array(168).fill(0);
        grid.forEach((row, d) => row.forEach((v, hr) => { local[(((d * 24 + hr + off) % 168) + 168) % 168] = v; }));
        const top = Math.max(...local) || 1, days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        let html = '<div class="smp-heat" role="img" aria-label="Average players by day and hour">';
        days.forEach((name, d) => {
            html += `<span class="smp-heat-day">${name}</span>`;
            for (let hr = 0; hr < 24; hr++) {
                const v = local[d * 24 + hr];
                html += `<i style="--a:${(v / top).toFixed(2)}" title="${name} ${String(hr).padStart(2, '0')}:00: ${num(v, 1)} players"></i>`;
            }
        });
        html += '<span></span>' + [0, 6, 12, 18].map((hr) => `<span class="smp-heat-hr" style="grid-column:${hr + 2} / span 6">${String(hr).padStart(2, '0')}:00</span>`).join('');
        $('#smp-hours').innerHTML = html + '</div>';
    }

    function renderBoards() {
        const players = data.players || [];
        $('#smp-board-n').textContent = players.length ? num(players.length) + ' players' : '';
        $('#smp-boards').innerHTML = BOARDS.map(([title, tabs], i) => {
            const on = boardTab[i] || 0, [, field, fmt] = tabs[on];
            const top = players.filter((p) => p[field] > 0).sort((a, b) => b[field] - a[field]).slice(0, 10);
            const best = top.length ? top[0][field] : 1;
            const rows = top.map((p, n) => `<div class="rank-row" title="First joined ${esc(p.firstSeen ? date(p.firstSeen) : 'unknown')}${p.lastSeen ? ', last seen ' + esc(ago(p.lastSeen)) : ''}">
                <span class="rank-n">${n + 1}</span>${head(p, 24)}
                <span class="grow"><b>${esc(p.name)}</b><span class="rank-bar"><i style="width:${(p[field] / best * 100).toFixed(1)}%"></i></span></span>
                <span class="rank-val">${fmt(p[field])}</span></div>`).join('');
            return `<section class="card"><h2>${esc(title)}</h2>
                <div class="stabs" data-board="${i}">${tabs.map(([label], t) => `<button data-tab="${t}" class="${t === on ? 'on' : ''}" aria-pressed="${t === on}">${esc(label)}</button>`).join('')}</div>
                ${rows ? `<div class="rank-list">${rows}</div>` : '<p class="muted">No one yet.</p>'}</section>`;
        }).join('');
    }

    function renderFeed() {
        const feed = data.activity || [];
        $('#smp-feed-wrap').hidden = !feed.length;
        $('#smp-feed').innerHTML = feed.slice(0, 15).map((a) => `<div class="rowi smp-${esc(a.type)}">${head(a, 24)}
            <span class="grow"><b>${esc(a.name)}</b> ${esc(a.text)}</span>
            <small title="${esc(new Date(a.t).toLocaleString())}">${esc(ago(a.t))}</small></div>`).join('');
    }

    function render() {
        $('#smp-err').hidden = !!data && !SMP.stale;
        if (!data) { $('#smp-err').textContent = "Couldn't load the live stats. Try again in a minute."; return; }
        if (SMP.stale) $('#smp-err').textContent = 'The server stats are not answering, so this is the last copy from ' + ago(Date.parse(data.updated)) + '.';
        $('#smp-live').hidden = false;
        renderStats(); renderPlayers(); renderCharts(); renderBoards(); renderFeed();
    }

    $('#smp-copy').addEventListener('click', (e) => copy(SMP.address, e.currentTarget));
    $('#smp-range').addEventListener('click', (e) => {
        const b = e.target.closest('button');
        if (!b) return;
        range = b.dataset.r;
        e.currentTarget.querySelectorAll('button').forEach((x) => { x.classList.toggle('on', x === b); x.setAttribute('aria-pressed', x === b); });
        renderCharts();
    });
    $('#smp-boards').addEventListener('click', (e) => {
        const b = e.target.closest('[data-tab]');
        if (!b) return;
        boardTab[b.parentElement.dataset.board] = +b.dataset.tab;
        renderBoards();
    });

    async function poll() {
        if (document.hidden) return;
        try {
            const r = await fetch('/minecraft/unlimitedsmp/stats.php', { cache: 'no-store' });
            if (!r.ok) throw new Error();
            data = await r.json();
            SMP.stale = r.headers.get('X-Stale') === '1';
        } catch { SMP.stale = !!data; }
        render();
    }
    render();
    setInterval(poll, 60 * 1000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
});
