// Steam Profile (/games/steam/profile/): the History chart, the daily log's buttons and the CSV download.
(function () {
    const data = JSON.parse(document.getElementById('steam-data').textContent);
    const box = document.getElementById('chart');
    const note = document.getElementById('chart-note');
    const fmt = (n) => Math.round(n).toLocaleString();
    const day = (t, long) => new Date(t * 1000).toLocaleDateString(undefined, long ? { day: 'numeric', month: 'short', year: 'numeric' } : { month: 'short', year: 'numeric' });
    const since = data.since; // the server's date, so a time zone can't shift it a day
    const notes = {
        rebuilt: { achievements: 'Before ' + since + ': rebuilt from achievement unlock dates.' },
        rebuiltBadges: 'Before ' + since + ": rebuilt from badge dates, so it's approximate. A badge with several levels counts all its XP when it reached its latest one.",
        guess: 'Before ' + since + ": a guess, a straight line from 0 when the account was made. Steam keeps no history of it.",
    };
    // long estimated runs arrive packed as {t0, kind, vals, pts}; expand them to [time, value, kind] points
    const series = {};
    Object.keys(data.series).forEach((k) => {
        const s = data.series[k];
        series[k] = Array.isArray(s) ? s : s.vals.map((v, i) => [s.t0 + i * 86400, v, s.kind]).concat(s.pts);
    });
    const ESTIMATED = 'Estimated from purchase dates, achievement unlocks and last-played dates. Day-to-day detail is simulated.';
    const metricSel = document.getElementById('metric');
    const rangeSel = document.getElementById('range');
    let metric = metricSel ? metricSel.value : null;
    let range = rangeSel ? +rangeSel.value : 0;

    // round tick step: 1, 2 or 5 times a power of ten
    function step(span, n) {
        const raw = span / n || 1;
        const p = Math.pow(10, Math.floor(Math.log10(raw)));
        return [1, 2, 5, 10].map((m) => m * p).find((s) => s >= raw);
    }

    function draw() {
        if (!box || !metric) return;
        const all = series[metric] || [];
        const cut = range ? Date.now() / 1000 - range * 86400 : 0;
        const pts = all.filter((p) => p[0] >= cut);
        if (pts.length < 2) {
            box.innerHTML = '<p class="note">Not enough readings in this range yet. Try a longer time range.</p>';
            note.textContent = '';
            return;
        }
        const W = Math.max(280, box.clientWidth);
        const H = W < 500 ? 220 : 280;
        const L = 52, R = 12, T = 12, B = 26;
        const t0 = pts[0][0], t1 = pts[pts.length - 1][0];
        let lo = Math.min(...pts.map((p) => p[1])), hi = Math.max(...pts.map((p) => p[1]));
        const ys = step(hi - lo || Math.max(1, hi), 4);
        lo = Math.floor(lo / ys) * ys;
        hi = Math.max(lo + ys, Math.ceil(hi / ys) * ys);
        const x = (t) => L + (t - t0) / Math.max(1, t1 - t0) * (W - L - R);
        const y = (v) => T + (1 - (v - lo) / (hi - lo)) * (H - T - B);

        let svg = '';
        for (let v = lo; v <= hi + ys / 2; v += ys) svg += `<line class="grid" stroke="#3d3d3d" x1="${L}" x2="${W - R}" y1="${y(v)}" y2="${y(v)}"/><text fill="#9b9b9b" x="${L - 8}" y="${y(v) + 4}" text-anchor="end">${fmt(v)}</text>`;
        // date ticks: years, months or days, whichever gives about five labels
        const span = t1 - t0, n = Math.max(2, Math.floor((W - L - R) / 110));
        const d = new Date(t0 * 1000);
        d.setHours(0, 0, 0, 0);
        const unit = span > 3 * 365 * 86400 ? 'y' : span > 75 * 86400 ? 'm' : 'd';
        if (unit === 'y') d.setMonth(0, 1); else if (unit === 'm') d.setDate(1);
        const ticks = [];
        while (d.getTime() / 1000 <= t1) {
            if (d.getTime() / 1000 >= t0) ticks.push(d.getTime() / 1000);
            if (unit === 'y') d.setFullYear(d.getFullYear() + 1); else if (unit === 'm') d.setMonth(d.getMonth() + 1); else d.setDate(d.getDate() + 1);
        }
        const every = Math.ceil(ticks.length / n);
        ticks.filter((_, i) => i % every === 0).forEach((t) => {
            const label = new Date(t * 1000).toLocaleDateString(undefined, unit === 'y' ? { year: 'numeric' } : unit === 'm' ? { month: 'short', year: '2-digit' } : { day: 'numeric', month: 'short' });
            svg += `<text fill="#9b9b9b" x="${x(t)}" y="${H - 6}" text-anchor="middle">${label}</text>`;
        });

        // one path per run of points of the same kind, each joined to the next run's first point.
        // The fill/stroke attributes only matter if games-steam.css fails to load; the CSS overrides them.
        const runs = [];
        pts.forEach((p, i) => {
            const kind = p[2] || 'rec';
            if (!runs.length || runs[runs.length - 1].kind !== kind) {
                if (runs.length) runs[runs.length - 1].pts.push(p);
                runs.push({ kind, pts: [] });
            }
            runs[runs.length - 1].pts.push(p);
        });
        runs.forEach((r) => svg += `<polyline class="${r.kind}" fill="none" stroke="#3987e5" stroke-width="2" points="${r.pts.map((p) => x(p[0]).toFixed(1) + ',' + y(p[1]).toFixed(1)).join(' ')}"/>`);
        // shaded band behind the estimated stretch
        const est = pts.filter((p) => p[2] === 'est');
        if (est.length > 1) svg = `<rect class="band" fill="#2d4224" fill-opacity=".25" x="${x(est[0][0])}" y="${T}" width="${x(est[est.length - 1][0]) - x(est[0][0])}" height="${H - T - B}"/>` + svg;
        const rec = pts.filter((p) => !p[2]);
        if (rec.length <= 90) rec.forEach((p) => svg += `<circle class="dot" fill="#3987e5" cx="${x(p[0])}" cy="${y(p[1])}" r="3"/>`);
        svg += `<line class="cross" stroke="#555" y1="${T}" y2="${H - B}" hidden/><circle class="hover" fill="#66c0f4" r="5" hidden/><rect class="hit" fill="transparent" x="${L}" y="0" width="${W - L - R}" height="${H}"/>`;

        const label = data.labels[metric];
        box.innerHTML = (est.length > 1 ? `<span class="steam-est-tag" title="${ESTIMATED}" style="left: ${(x(est[0][0]) / W * 100).toFixed(2)}%">Estimated</span>` : '') + `<svg class="steam-line" viewBox="0 0 ${W} ${H}" width="${W}" height="${H}" role="img" aria-label="${label}: ${fmt(pts[0][1])} on ${day(t0, true)} to ${fmt(pts[pts.length - 1][1])} on ${day(t1, true)}">${svg}</svg><div class="steam-tip" hidden></div>`;

        const kinds = new Set(pts.map((p) => p[2] || 'rec'));
        document.querySelectorAll('.steam-legend [data-kind]').forEach((el) => el.hidden = !kinds.has(el.dataset.kind));
        note.textContent = kinds.has('guess') ? notes.guess : kinds.has('rebuilt') ? (notes.rebuilt[metric] || notes.rebuiltBadges) : '';

        const s = box.querySelector('svg'), tip = box.querySelector('.steam-tip');
        const cross = s.querySelector('.cross'), dot = s.querySelector('.hover');
        function show(i) {
            const p = pts[i], px = x(p[0]), py = y(p[1]);
            cross.setAttribute('x1', px); cross.setAttribute('x2', px); cross.removeAttribute('hidden');
            dot.setAttribute('cx', px); dot.setAttribute('cy', py); dot.removeAttribute('hidden');
            tip.innerHTML = `<b>${fmt(p[1])}</b> ${UIesc(label)}<br><span>${day(p[0], p[2] !== 'guess')}${{ guess: ' · guess', est: ' · estimated', rebuilt: ' · from unlock dates' }[p[2]] || ''}</span>`;
            tip.hidden = false;
            const scale = s.getBoundingClientRect().width / W;
            tip.style.left = Math.min(px * scale + 12, box.clientWidth - tip.offsetWidth) + 'px';
            tip.style.top = Math.max(0, py * scale - tip.offsetHeight - 10) + 'px';
        }
        function hide() {
            cross.setAttribute('hidden', ''); dot.setAttribute('hidden', ''); tip.hidden = true;
        }
        s.addEventListener('pointermove', (e) => {
            const r = s.getBoundingClientRect();
            const t = t0 + ((e.clientX - r.left) * W / r.width - L) / (W - L - R) * (t1 - t0);
            let best = 0;
            pts.forEach((p, i) => { if (Math.abs(p[0] - t) < Math.abs(pts[best][0] - t)) best = i; });
            show(best);
        });
        s.addEventListener('pointerleave', hide);
    }

    function UIesc(s) {
        return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    if (metricSel) metricSel.addEventListener('change', () => { metric = metricSel.value; mark(metric); draw(); });
    if (rangeSel) rangeSel.addEventListener('change', () => { range = +rangeSel.value; draw(); });
    draw();
    let w = box ? box.clientWidth : 0;
    window.addEventListener('resize', () => {
        if (box && box.clientWidth !== w) { w = box.clientWidth; draw(); }
    });

    // ---------- Played each day: bars by day (7-90 days), week (1 year) or month (all time), plus the top games ----------
    const pBars = document.getElementById('played-bars');
    const pRange = document.getElementById('played-range');
    const hours = (m) => (m < 60 ? Math.round(m) + ' min' : Math.round(m / 60).toLocaleString() + ' h');
    const iso = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    const pDays = new Map((data.played ? data.played.days : []).map((r) => [r[0], r[1]]));
    const estTo = data.played ? data.played.estTo : null;

    function drawPlayed() {
        if (!pBars || !pDays.size) return;
        const days = +pRange.value;
        const end = new Date();
        end.setHours(12, 0, 0, 0);
        const first = [...pDays.keys()].sort()[0];
        const start = days ? new Date(end.getTime() - (days - 1) * 864e5) : new Date(first + 'T12:00:00');
        const unit = !days ? 'month' : days > 90 ? 'week' : 'day';
        // one bucket per day / Monday-week / month from start to today
        const buckets = [];
        const keyOf = (d) => {
            if (unit === 'day') return iso(d);
            if (unit === 'month') return iso(d).slice(0, 7);
            const m = new Date(d);
            m.setDate(m.getDate() - ((m.getDay() + 6) % 7));
            return iso(m);
        };
        const byKey = new Map();
        for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
            const k = keyOf(d);
            if (!byKey.has(k)) {
                const b = { k, from: iso(d), min: 0, games: new Map(), est: true, any: false };
                byKey.set(k, b);
                buckets.push(b);
            }
            const b = byKey.get(k), day = iso(d), row = pDays.get(day);
            if (!estTo || day >= estTo) b.est = false;
            if (!row) continue;
            b.any = true;
            row.forEach(([id, m]) => { b.min += m; b.games.set(id, (b.games.get(id) || 0) + m); });
        }
        const max = Math.max(60, ...buckets.map((b) => b.min));
        pBars.style.gap = buckets.length > 60 ? '1px' : '';
        const name = (id) => (data.played.names[id] || ['App ' + id])[0];
        const label = (b) => unit === 'day' ? new Date(b.from + 'T12:00:00').toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
            : unit === 'week' ? 'Week of ' + new Date(b.k + 'T12:00:00').toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
            : new Date(b.k + '-01T12:00:00').toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
        pBars.innerHTML = buckets.map((b) => {
            const top = [...b.games].sort((a, c) => c[1] - a[1]).slice(0, 8).map(([id, m]) => '\n' + name(id) + ' ' + hours(m)).join('');
            const tip = UIesc(label(b) + ': ' + (b.min ? hours(b.min) : 'nothing played') + (b.est ? ' (estimated)' : '') + top);
            return `<div role="listitem" class="${b.est ? 'est' : ''}" title="${tip}" aria-label="${tip}"><i style="height: ${b.min / max * 100}%"></i></div>`;
        }).join('');
        document.getElementById('played-axis').innerHTML = `<span>${UIesc(label(buckets[0]))}</span><span>${unit === 'day' ? 'Today' : UIesc(label(buckets[buckets.length - 1]))}</span>`;

        // totals and the top games for the range
        const games = new Map(), seen = new Map();
        let total = 0;
        pDays.forEach((row, day) => {
            if (day < iso(start)) return;
            row.forEach(([id, m]) => {
                total += m;
                games.set(id, (games.get(id) || 0) + m);
                seen.set(id, (seen.get(id) || 0) + 1);
            });
        });
        document.getElementById('played-total').textContent = hours(total) + (days ? ' in the last ' + (days === 365 ? 'year' : days + ' days') : ' since ' + new Date(first + 'T12:00:00').toLocaleDateString(undefined, { month: 'short', year: 'numeric' }));
        const top = [...games].sort((a, c) => c[1] - a[1]).slice(0, 8);
        document.getElementById('played-top-h').hidden = !top.length;
        document.getElementById('played-top').innerHTML = top.map(([id, m], i) => `<a class="steam-rank" href="/games/steam/game/?game=${+id}">
            <span class="steam-rank-n">${i + 1}</span>
            <img src="${UIesc((data.played.names[id] || [, ''])[1])}" alt="" width="231" height="87" loading="lazy" decoding="async">
            <span class="grow"><b>${UIesc(name(id))}</b><span class="steam-rank-bar"><i style="width: ${m / top[0][1] * 100}%"></i></span></span>
            <span class="steam-rank-val">${hours(m)}<small>${seen.get(id)} day${seen.get(id) === 1 ? '' : 's'}</small></span></a>`).join('');
    }
    if (pRange) pRange.addEventListener('change', drawPlayed);
    drawPlayed();

    // the headline numbers double as chart buttons: pick that metric and bring the chart into view
    const metrics = document.querySelector('.steam-metrics');
    function mark(k) {
        const metrics = document.querySelector('.steam-metrics');
        if (metrics) metrics.querySelectorAll('button').forEach((b) => b.setAttribute('aria-pressed', b.dataset.k === k));
    }
    mark(metric);
    if (metrics) metrics.addEventListener('click', (e) => {
        const b = e.target.closest('button[data-k]');
        if (!b || !metricSel || ![...metricSel.options].some((o) => o.value === b.dataset.k)) return;
        metricSel.value = b.dataset.k;
        metricSel.dispatchEvent(new Event('change'));
        document.getElementById('history').scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
    });

    const all = document.getElementById('log-all');
    if (all) all.addEventListener('click', () => {
        document.querySelectorAll('.steam-log tr[hidden]').forEach((r) => r.hidden = false);
        all.remove();
    });
    document.querySelectorAll('[data-more]').forEach((b) => b.addEventListener('click', () => {
        document.querySelectorAll(b.dataset.more).forEach((r) => r.hidden = false);
        b.remove();
    }));

    const csv = document.getElementById('csv');
    if (csv) csv.addEventListener('click', () => {
        const keys = Object.keys(data.labels);
        const rows = [['day'].concat(keys).join(',')];
        Object.keys(data.history).forEach((d) => rows.push([d].concat(keys.map((k) => data.history[d][k] ?? '')).join(',')));
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([rows.join('\n') + '\n'], { type: 'text/csv' }));
        a.download = 'steam-history.csv';
        a.click();
        setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    });
})();
