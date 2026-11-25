// Castle Crafty admin: renders /shop/admin/data (admin.json) and the monthly ledger (?ledger=YYYY-MM)
(function () {
    const { $, $$, esc, debounce, num, ago } = UI;
    const ICON = id => `/minecraft/tools/item-list/minecraft_${encodeURIComponent(id)}.png`;
    const ic = id => `<img class="ic" src="${ICON(id)}" alt="" width="16" height="16" loading="lazy" onerror="this.style.visibility='hidden'">`;
    const d2 = n => (n > 0 && n < 0.01 ? '<0.01' : (Math.round(n * 100) / 100).toLocaleString('en-GB'));
    const dia = n => `${d2(n)} diamond${Math.round(n * 100) === 100 ? '' : 's'}`;
    const date = t => new Date(t * 1000).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' });
    const short = t => new Date(t * 1000).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
    const md = t => esc(t || '').replace(/^⚠️ /, '').replace(/\*\*(.+?)\*\*/g, '<b>$1</b>').replace(/\n/g, '<br>');
    const TAG = { sale: 'Sale', theft: 'Theft', restock: 'Restock', loss: 'Missing stock', hopper: 'Hopper', low: 'Low stock', wrong: 'Wrong chest', short: 'Theft' };
    const tag = k => `<span class="tag t-${k === 'short' ? 'theft' : esc(k)}">${esc(TAG[k] || k)}</span>`;
    const ALERTS = ['loss', 'hopper', 'low', 'wrong'];
    const VIEWS = { overview: 'Overview', log: 'Log', alerts: 'Alerts', stock: 'Stock', chests: 'Chests', customers: 'Customers', prices: 'Prices' };
    let data, log = [], logMonth = null;

    // ---------- load ----------
    fetch('/shop/admin/data', { cache: 'no-store' }).then(async r => {
        const j = await r.json().catch(() => ({}));
        if (!r.ok) throw Error(j.error || `The data page answered ${r.status}.`);
        data = j;
        data.stale = !!r.headers.get('X-Stale');
        $('#status').hidden = true;
        renderAll();
        route();
    }).catch(e => {
        const s = $('#status');
        s.className = 'status err';
        s.textContent = `Couldn't load the shop data. ${e.message}`;
    });

    // ---------- views (#hash) ----------
    function route() {
        const v = VIEWS[location.hash.slice(1)] ? location.hash.slice(1) : 'overview';
        $$('.view').forEach(s => (s.hidden = s.dataset.view !== v));
        $$('#nav a').forEach(a => (a.getAttribute('data-view') === v ? a.setAttribute('aria-current', 'page') : a.removeAttribute('aria-current')));
        $('#title').textContent = VIEWS[v];
        document.title = `${VIEWS[v]} · Castle Crafty admin`;
        closePanel();
        if (v === 'log' && !logMonth && data.ledger && data.ledger.length) loadMonth(data.ledger[0].month);
    }
    window.addEventListener('hashchange', () => { if (data) { route(); $('#main').focus({ preventScroll: true }); window.scrollTo(0, 0); } });

    // the shop log and stock counter run every minute on the home server; say so loudly when one has stopped
    function staleCheck() {
        const now = Date.now() / 1000, log = now - data.updated, save = now - data.saved, mins = s => Math.round(s / 60);
        const msg = log > 300
            ? `<b>The shop log has stopped.</b> It last updated ${mins(log)} minutes ago, so sales, thefts and restocks aren't being recorded or posted to Discord. Visits from the last 6 hours are caught up when it starts again. On the server run: <code>cd /home/crafty/shop-stock && docker compose up -d</code>`
            : save > 900
                ? `<b>The stock counts are ${mins(save)} minutes old.</b> The stock counter or the Minecraft server's world saves may have stopped. On the server run: <code>cd /home/crafty/shop-stock && docker compose up -d</code>, and check the Minecraft server is running.`
                : '';
        $('#stale').innerHTML = msg;
        $('#stale').hidden = !msg;
    }
    function renderAll() {
        const t = data.takings;
        staleCheck();
        $('#updated').innerHTML = `Updated <span title="${esc(date(data.updated))}">${esc(ago(data.updated * 1000))}</span><br>Stock from the world save <span title="${esc(date(data.saved))}">${esc(ago(data.saved * 1000))}</span>${data.stale ? '<br><span class="low">Home server offline: last copy</span>' : ''}`;
        const kpi = (b, s) => `<div class="kpi"><b>${b}</b><span>${s}</span></div>`;
        $('#kpis').innerHTML = kpi(d2(t.today), 'diamonds today') + kpi(d2(t.week), 'last 7 days') + kpi(d2(t.all), 'all time') + (t.uncollected === null ? '' : kpi(d2(t.uncollected), 'uncollected'));
        const lows = data.products.filter(p => p.price && p.each !== null && p.stock !== null && p.stock < data.low && p.history.length && p.history.some(h => h[1] >= data.low));
        const wrong = data.chests.filter(c => c.misplaced.length);
        const sugg = data.products.filter(p => p.suggestion);
        const recentAlerts = data.events.filter(e => data.updated - e.t < 7 * 86400 && e.kind !== 'low');
        $('#n-alerts').textContent = recentAlerts.length || '';
        $('#n-low').textContent = lows.length || '';
        $('#n-wrong').textContent = wrong.length || '';
        $('#n-prices').textContent = sugg.length || '';
        overview(lows, wrong, sugg, recentAlerts);
        alertsView();
        stockView();
        chestsView();
        customersView();
        pricesView(sugg);
        logInit();
    }

    // ---------- overview ----------
    function overview(lows, wrong, sugg, recentAlerts) {
        chart(data.takings.daily);
        const att = [
            ...recentAlerts.slice(0, 5).map(e => `<li>${tag(e.kind)}<span class="grow">${md(e.text)}</span><small>${esc(ago(e.t * 1000))}</small></li>`),
            ...lows.slice(0, 5).map(p => `<li>${tag('low')}<span class="grow">${ic(p.id)}${esc(p.name)}: ${p.stock ? `${num(p.stock)} left` : 'sold out'}</span></li>`),
            wrong.length ? `<li>${tag('wrong')}<span class="grow">${num(wrong.length)} chest${wrong.length > 1 ? 's hold' : ' holds'} another item's stock</span><a href="#chests">Chests</a></li>` : '',
        ].filter(Boolean);
        $('#attention').innerHTML = att.length ? `<ul class="list">${att.join('')}</ul>` : '<p class="empty">Nothing needs attention.</p>';
        const best = data.products.filter(p => p.sold14).sort((a, b) => b.sold14 * (b.each || 0) - a.sold14 * (a.each || 0)).slice(0, 6);
        $('#best').innerHTML = best.length ? `<ul class="list">${best.map(p => `<li><span class="grow">${ic(p.id)}${esc(p.name)}</span><small>${num(p.sold14)} sold · ${d2(p.sold14 * (p.each || 0))} diamonds</small></li>`).join('')}</ul>` : '<p class="empty">No sales in the last 14 days.</p>';
        $('#ov-prices').innerHTML = sugg.length ? `<ul class="list">${sugg.slice(0, 5).map(p => `<li><span class="grow">${ic(p.id)}${esc(p.name)}</span><small>${esc(p.suggestion)}</small></li>`).join('')}</ul>` : '<p class="empty">Nothing to change yet.</p>';
        $('#latest').innerHTML = '<p class="empty">Loading…</p>';
    }
    function chart(daily) {
        const max = Math.max(1, ...daily.map(d => d[1]));
        const W = 900, H = 150, bw = W / daily.length;
        const total = daily.reduce((s, d) => s + d[1], 0);
        const bars = daily.map(([day, v], k) => {
            const h = Math.max(v ? 2 : 0, (v / max) * (H - 10));
            const label = `${new Date(day + 'T12:00').toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' })}: ${dia(v)}`;
            return `<rect x="${(k * bw + 1).toFixed(1)}" y="${(H - h).toFixed(1)}" width="${(bw - 2).toFixed(1)}" height="${h.toFixed(1)}"><title>${esc(label)}</title></rect>`;
        }).join('');
        $('#chart').innerHTML = `<svg class="bars" viewBox="0 0 ${W} ${H}" preserveAspectRatio="none" role="img" aria-label="Diamonds per day for 90 days, ${esc(dia(total))} in total">${bars}</svg>
            <div class="axis"><span>${esc(new Date(daily[0][0] + 'T12:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }))}</span><span>${esc(dia(total))} in 90 days · best day ${esc(dia(total ? max : 0))}</span><span>Today</span></div>`;
    }

    // ---------- log (permanent ledger) ----------
    const KINDS = { all: 'All', sale: 'Sales', theft: 'Thefts', restock: 'Restocks', alert: 'Alerts' };
    let kind = 'all', shown = 0, filtered = [];
    const PAGE = 200;
    const itemsText = items => { const l = (items || []).map(i => `${num(Math.abs(i.n))} ${i.name}`); return l.slice(0, 6).join(', ') + (l.length > 6 ? ` +${l.length - 6} more` : ''); };
    function entryRow(e, k) {
        let who = e.user ? esc(e.user) : '', what = '', amt = '';
        if (e.kind === 'sale') { what = esc(itemsText(e.items)) || 'paid, took nothing'; amt = e.earned ? `<span class="amt-pos">+${d2(e.earned)}</span>` : ''; }
        else if (e.kind === 'theft') { what = esc(itemsText(e.items) || 'took diamonds'); amt = `<span class="amt-neg">${d2(e.short)} short</span>`; }
        else if (e.kind === 'restock') {
            const add = (e.items || []).filter(i => i.n > 0), out = (e.items || []).filter(i => i.n < 0);
            what = [add.length ? `<span class="amt-pos">+</span> ${esc(itemsText(add))}` : '', out.length ? `<span class="amt-neg">−</span> ${esc(itemsText(out))}` : ''].filter(Boolean).join('<br>') || 'collected diamonds';
            amt = e.collected ? `<span class="amt-pos">${e.collected > 0 ? '+' : ''}${d2(e.collected)}</span>` : '';
        } else { what = md(e.text); if (!who && e.user) who = esc(e.user); }
        return `<tr data-i="${k}"><td class="when" title="${esc(date(e.t))}">${esc(short(e.t))}</td><td>${tag(e.kind)}</td><td>${who}</td><td class="items">${what}</td><td class="num">${amt}</td></tr>`;
    }
    function logInit() {
        $('#kinds').innerHTML = Object.entries(KINDS).map(([k, l]) => `<button type="button" data-k="${k}" aria-pressed="${k === kind}">${l}</button>`).join('');
        const months = data.ledger || [];
        $('#month').innerHTML = months.map(m => `<option value="${esc(m.month)}">${esc(new Date(m.month + '-01T12:00').toLocaleDateString('en-GB', { month: 'long', year: 'numeric' }))} · ${num(m.count)}</option>`).join('') || '<option>No entries yet</option>';
        if (months.length) loadMonth(months[0].month, true);
        else {
            $('#log-t').innerHTML = '<tbody><tr><td class="empty">Nothing logged yet. Sales, thefts and restocks appear here about 15 minutes after they happen.</td></tr></tbody>';
            $('#latest').innerHTML = '<p class="empty">Nothing logged yet.</p>';
        }
    }
    function loadMonth(m, quiet) {
        logMonth = m;
        if (!quiet) $('#log-t').innerHTML = '<tbody><tr><td class="empty">Loading…</td></tr></tbody>';
        fetch('/shop/admin/data?ledger=' + encodeURIComponent(m), { cache: 'no-store' }).then(async r => {
            const text = await r.text();
            if (!r.ok) { let msg = `The data page answered ${r.status}.`; try { msg = JSON.parse(text).error || msg; } catch (e) { } throw Error(msg); }
            return text;
        }).then(text => {
            if (m !== logMonth) return; // another month was picked meanwhile
            log = text.split('\n').filter(Boolean).map(l => { try { return JSON.parse(l); } catch (e) { return null; } }).filter(Boolean).reverse().sort((a, b) => b.t - a.t); // newest first; backfilled lines sit after newer ones in the file
            drawLog(true);
            if (m === (data.ledger[0] || {}).month) {
                $('#latest').innerHTML = log.length ? `<ul class="list">${log.slice(0, 8).map((e, k) => `<li><button type="button" data-latest="${k}">${tag(e.kind)}<span class="grow">${e.user ? `<b>${esc(e.user)}</b> ` : ''}${e.items && !ALERTS.includes(e.kind) ? esc(itemsText(e.items)) : md(e.text)}</span><small>${esc(ago(e.t * 1000))}</small></button></li>`).join('')}</ul>` : '<p class="empty">Nothing logged yet.</p>';
            }
        }).catch(e => { $('#log-t').innerHTML = `<tbody><tr><td class="low">Couldn't load that month. ${esc(e.message)}</td></tr></tbody>`; });
    }
    function drawLog(reset) {
        if (reset) {
            const q = $('#lq').value.trim().toLowerCase();
            filtered = log.map((e, k) => [e, k]).filter(([e]) => (kind === 'all' || (kind === 'alert' ? ALERTS.includes(e.kind) : e.kind === kind))
                && (!q || [e.user, e.text, ...(e.items || []).map(i => i.name + ' ' + i.id)].some(s => s && s.toLowerCase().includes(q))));
            shown = 0;
            $('#log-t').innerHTML = '<thead><tr><th>When</th><th>Type</th><th>Player</th><th>Items</th><th class="num">Diamonds</th></tr></thead><tbody></tbody>';
        }
        const next = filtered.slice(shown, shown + PAGE);
        $('#log-t tbody').insertAdjacentHTML('beforeend', next.map(([e, k]) => entryRow(e, k)).join('') || (shown ? '' : '<tr><td colspan="5" class="empty">Nothing matches.</td></tr>'));
        shown += next.length;
        $('#log-n').textContent = `${num(filtered.length)} of ${num(log.length)}`;
        $('#more').hidden = shown >= filtered.length;
        $('#more').textContent = `Show more (${num(filtered.length - shown)} left)`;
    }
    $('#month').addEventListener('change', e => loadMonth(e.target.value));
    $('#kinds').addEventListener('click', e => {
        const b = e.target.closest('button[data-k]');
        if (!b) return;
        kind = b.dataset.k;
        $$('#kinds button').forEach(x => x.setAttribute('aria-pressed', x === b));
        drawLog(true);
    });
    $('#lq').addEventListener('input', debounce(() => drawLog(true), 120));
    $('#more').addEventListener('click', () => drawLog(false));
    $('#log-t').addEventListener('click', e => { const tr = e.target.closest('tr[data-i]'); if (tr) { select(tr); openEntry(log[tr.dataset.i]); } });
    $('#latest').addEventListener('click', e => { const b = e.target.closest('[data-latest]'); if (b) openEntry(log[b.dataset.latest]); });

    function openEntry(e) {
        if (!e) return;
        const range = e.start && e.end && e.start !== e.end ? `${date(e.start)} – ${new Date(e.end * 1000).toLocaleTimeString('en-GB', { timeStyle: 'short' })}` : date(e.t);
        const kv = [['When', range], e.user && ['Player', e.user], e.owed !== undefined && ['Owed', dia(e.owed)], e.paidIn !== undefined && ['Paid in', dia(e.paidIn)],
            e.paidOut && ['Diamonds taken out', dia(e.paidOut)], e.short && ['Short by', dia(e.short)], e.collected && ['Diamonds collected', dia(e.collected)]].filter(Boolean);
        const sale = e.kind === 'sale' || e.kind === 'theft';
        const items = (e.items || []).length ? `<h3>Items</h3><div class="table-wrap"><table class="grid-t"><thead><tr><th>Item</th><th class="num">Amount</th>${sale ? '<th class="num">Diamonds</th>' : ''}<th class="num">Stock</th></tr></thead><tbody>
            ${e.items.map(i => `<tr><td>${ic(i.id)}${esc(i.name)}${i.price ? `<br><small class="dim">${esc(i.price)}</small>` : ''}</td><td class="num">${e.kind === 'restock' && i.n > 0 ? '+' : ''}${num(i.n)}</td>
            ${sale ? `<td class="num">${i.diamonds === null || i.diamonds === undefined ? 'no price' : d2(i.diamonds)}</td>` : ''}<td class="num">${i.before === null || i.before === undefined ? '' : `${num(i.before)} → ${num(i.after)}`}</td></tr>`).join('')}</tbody></table></div>` : '';
        const acts = (e.actions || []).length ? `<details><summary>CoreProtect actions (${num(e.actions.length)})</summary><div class="table-wrap"><table class="grid-t"><thead><tr><th>Time</th><th>Chest</th><th>Item</th><th class="num">Amount</th></tr></thead><tbody>
            ${e.actions.map(([t, x, y, z, id, n, a]) => `<tr><td>${esc(new Date(t * 1000).toLocaleTimeString('en-GB'))}</td><td class="mono">${x} ${y} ${z}</td><td>${esc(id.replace(/_/g, ' '))}</td><td class="num ${a ? 'amt-pos' : 'amt-neg'}">${a ? '+' : '−'}${num(n)}</td></tr>`).join('')}</tbody></table></div></details>` : '';
        openPanel(`<h2>${tag(e.kind)} ${e.user ? esc(e.user) : ''}</h2>${e.text && !sale && e.kind !== 'restock' ? `<p>${md(e.text)}</p>` : ''}
            <div class="kv">${kv.map(([k, v]) => `<span>${esc(k)}</span><span>${esc(v)}</span>`).join('')}</div>${items}
            ${(e.chests || []).length ? `<h3>Chests</h3><p class="mono">${e.chests.map(esc).join('<br>')}</p>` : ''}${acts}
            ${e.user ? `<p><button class="btn" data-player="${esc(e.user)}">Everything by ${esc(e.user)} this month</button></p>` : ''}
            <p class="dim mono">${esc(e.id)}</p>`);
    }

    // ---------- alerts ----------
    function alertsView() {
        $('#alerts-t').innerHTML = data.events.length ? `<thead><tr><th>When</th><th>Type</th><th>What happened</th></tr></thead><tbody>
            ${data.events.map(e => `<tr><td class="when" title="${esc(date(e.t))}">${esc(short(e.t))}</td><td>${tag(e.kind)}</td><td>${md(e.text)}</td></tr>`).join('')}</tbody>`
            : '<tbody><tr><td class="empty">No alerts yet.</td></tr></tbody>';
    }

    // ---------- stock ----------
    const SF = { shop: 'Shop items', low: 'Low or sold out', selling: 'Selling', all: 'Everything in the chests' };
    let sf = 'shop', ssort = { k: null, dir: 1 };
    function spark(h, w = 110, hgt = 24) {
        if (!h.length) return '';
        const now = data.updated, t0 = Math.min(h[0][0], now - 7 * 86400), pts = [...h, [now, h[h.length - 1][1]]];
        const max = Math.max(1, ...pts.map(p => p[1])), x = t => ((t - t0) / (now - t0)) * w, y = v => hgt - 2 - (v / max) * (hgt - 4);
        let d = `M${x(pts[0][0]).toFixed(1)},${y(pts[0][1]).toFixed(1)}`;
        for (let k = 1; k < pts.length; k++) d += `H${x(pts[k][0]).toFixed(1)}V${y(pts[k][1]).toFixed(1)}`;
        return `<svg class="spark" viewBox="0 0 ${w} ${hgt}" width="${w}" height="${hgt}" aria-hidden="true"><path d="${d}"/></svg>`;
    }
    function stockView() {
        $('#sfilter').innerHTML = Object.entries(SF).map(([k, l]) => `<button type="button" data-k="${k}" aria-pressed="${k === sf}">${l}</button>`).join('');
        drawStock();
    }
    const stacks = n => { const v = n / 64; return v === Math.floor(v) ? num(v) : v < 10 ? (Math.round(v * 10) / 10).toLocaleString('en-GB') : num(v); }; // 64 a stack
    const backTag = p => (p.backstock ? `<span class="tag t-back" title="In the stock room: ${num(p.backstock)} (${stacks(p.backstock)} stacks)">${num(p.backstock)} backstock</span>` : '');
    function drawStock() {
        data.products.forEach(p => { p.stacks = (p.stock || 0) / 64; p.value = p.each === null ? null : (p.stock || 0) * p.each; p.backValue = p.each === null || !p.backstock ? null : p.backstock * p.each; });
        const q = $('#sq').value.trim().toLowerCase();
        let rows = data.products.map((p, k) => [p, k]).filter(([p]) => {
            if (q && !(p.name + ' ' + p.id).toLowerCase().includes(q)) return false;
            if (sf === 'shop') return !!p.price;
            if (sf === 'low') return p.price && p.each !== null && p.stock < data.low;
            if (sf === 'selling') return p.sold14 > 0;
            return true;
        });
        if (ssort.k) rows.sort((a, b) => ((a[0][ssort.k] ?? -1) > (b[0][ssort.k] ?? -1) ? 1 : (a[0][ssort.k] ?? -1) < (b[0][ssort.k] ?? -1) ? -1 : 0) * ssort.dir);
        const th = (k, l, cls = '') => `<th class="${cls}" data-sort="${k}" aria-sort="${ssort.k === k ? (ssort.dir > 0 ? 'ascending' : 'descending') : 'none'}">${l}</th>`;
        $('#stock-t').innerHTML = `<thead><tr>${th('name', 'Item')}<th>Price</th>${th('stock', 'Stock', 'num')}${th('stacks', 'Stacks', 'num')}${th('value', 'Value', 'num')}${th('backstock', 'Backstock')}${th('sold14', 'Sold, 14 days', 'num')}${th('daysLeft', 'Days left', 'num')}<th>Trend</th><th>Suggestion</th></tr></thead><tbody>
            ${rows.map(([p, k]) => `<tr data-i="${k}"><td>${ic(p.id)}${esc(p.name)}</td><td class="dim price">${esc(p.price || '')}</td><td class="num ${!p.stock ? 'zero' : p.price && p.stock < data.low ? 'low' : ''}">${num(p.stock || 0)}</td><td class="num">${p.stock ? stacks(p.stock) : ''}</td><td class="num" title="Diamonds if all of it sells">${p.value ? d2(p.value) : ''}</td><td>${backTag(p)}</td>
            <td class="num">${p.sold14 ? num(p.sold14) : ''}</td><td class="num">${p.daysLeft !== null ? Math.round(p.daysLeft) : ''}</td><td>${spark(p.history)}</td><td class="dim">${esc(p.suggestion || '')}</td></tr>`).join('')
            || '<tr><td colspan="10" class="empty">Nothing matches.</td></tr>'}</tbody>`;
        const worth = rows.reduce((t, [p]) => t + (p.value || 0), 0), back = rows.reduce((t, [p]) => t + (p.backValue || 0), 0);
        $('#stock-n').textContent = `${num(rows.length)} items · on sale worth ${dia(worth)}${back ? ` · backstock worth ${dia(back)}` : ''}`;
    }
    $('#sq').addEventListener('input', debounce(drawStock, 80));
    $('#sfilter').addEventListener('click', e => { const b = e.target.closest('button[data-k]'); if (!b) return; sf = b.dataset.k; $$('#sfilter button').forEach(x => x.setAttribute('aria-pressed', x === b)); drawStock(); });
    $('#stock-t').addEventListener('click', e => {
        const h = e.target.closest('th[data-sort]');
        if (h) { ssort = { k: h.dataset.sort, dir: ssort.k === h.dataset.sort ? -ssort.dir : (h.dataset.sort === 'name' ? 1 : -1) }; return drawStock(); }
        const tr = e.target.closest('tr[data-i]');
        if (!tr) return;
        select(tr);
        const p = data.products[tr.dataset.i], hs = p.history;
        openPanel(`<h2>${ic(p.id)}${esc(p.name)}</h2>
            <div class="kv"><span>Price</span><span>${esc(p.price || 'not on the price list')}</span><span>In stock</span><span>${num(p.stock || 0)} (${stacks(p.stock || 0)} stacks)</span>${p.value !== null ? `<span>Value</span><span>${dia(p.value)} if it all sells</span>` : ''}<span>Backstock</span><span>${p.backstock ? `${num(p.backstock)} (${stacks(p.backstock)} stacks)${p.backValue ? `, worth ${dia(p.backValue)}` : ''}` : 'none in the stock room'}</span>
            <span>Sold, 14 days</span><span>${num(p.sold14)}</span>${p.daysLeft !== null ? `<span>Days of stock</span><span>about ${Math.round(p.daysLeft)}</span>` : ''}${p.suggestion ? `<span>Suggestion</span><span>${esc(p.suggestion)}</span>` : ''}</div>
            <h3>Stock history</h3>${hs.length ? spark(hs, 380, 130) + `<p class="dim">Highest ${num(Math.max(...hs.map(e => e[1])))}, lowest ${num(Math.min(...hs.map(e => e[1])))}, since ${esc(date(hs[0][0]))}</p>
            <details><summary>Every change (${num(hs.length)})</summary><table class="grid-t"><tbody>${hs.slice().reverse().map(([t, n]) => `<tr><td>${esc(date(t))}</td><td class="num">${num(n)}</td></tr>`).join('')}</tbody></table></details>` : '<p class="dim">No history yet.</p>'}
            <h3>Chests</h3>${chestList(p.id)}
            <p><button class="btn" data-item="${esc(p.name)}">Sales and restocks of ${esc(p.name)} this month</button></p>`);
    });
    const chestList = id => {
        const cs = data.chests.filter(c => c.items[id]), rs = (data.room || []).filter(c => c.items[id]);
        return (cs.length ? `<p class="mono">${cs.map(c => `${c.x} ${c.y} ${c.z}: ${num(c.items[id])}${c.main !== id ? ' (wrong chest)' : ''}`).join('<br>')}</p>` : '<p class="dim">Not in any shop chest.</p>')
            + (rs.length ? `<p class="dim">Stock room:</p><p class="mono">${rs.map(c => `${c.x} ${c.y} ${c.z}: ${num(c.items[id])}`).join('<br>')}</p>` : '');
    };

    // ---------- chests ----------
    const CF = { problems: 'Wrong item', used: 'In use', all: 'All, with empty' };
    let cf = 'used';
    function chestsView() {
        $('#cfilter').innerHTML = Object.entries(CF).map(([k, l]) => `<button type="button" data-k="${k}" aria-pressed="${k === cf}">${l}</button>`).join('');
        drawChests();
    }
    function drawChests() {
        const all = data.chests, byY = {};
        all.forEach((c, k) => {
            const used = Object.keys(c.items).length > 0;
            if ((cf === 'used' && !used) || (cf === 'problems' && !c.misplaced.length)) return;
            (byY[c.y] = byY[c.y] || []).push([c, k]);
        });
        const used = all.filter(c => Object.keys(c.items).length).length, wrong = all.filter(c => c.misplaced.length).length;
        $('#chests-n').textContent = `${num(used)} in use of ${num(all.length)}${wrong ? ` · ${num(wrong)} with the wrong item` : ''}`;
        const roomY = {};
        (data.room || []).forEach((c, k) => { if (cf === 'problems' || (cf === 'used' && !Object.keys(c.items).length)) return; (roomY[c.y] = roomY[c.y] || []).push([c, k]); });
        const tile = (c, attr) => { const ids = Object.entries(c.items).sort((a, b) => b[1] - a[1]), main = c.main || (ids[0] && ids[0][0]); return `<button type="button" class="chest${c.misplaced && c.misplaced.length ? ' wrong' : ''}${ids.length ? '' : ' empty'}" ${attr} title="${esc(`${c.x} ${c.y} ${c.z}` + (main ? ': ' + main.replace(/_/g, ' ') : ': empty'))}">${main ? `<img src="${ICON(main)}" alt="" width="24" height="24" loading="lazy">` : ''}<span>${ids.length ? num(ids.reduce((s, e) => s + e[1], 0)) : ''}</span></button>`; };
        const roomHtml = Object.keys(roomY).length ? `<h2 class="maph">Stock room <small>backstock, not for sale</small></h2>` + Object.keys(roomY).sort((a, b) => b - a).map(y => `<div class="level"><span>y ${esc(y)}</span><div class="chests">${roomY[y].sort((a, b) => a[0].x - b[0].x || a[0].z - b[0].z).map(([c, k]) => tile(c, `data-r="${k}"`)).join('')}</div></div>`).join('') : '';
        $('#map').innerHTML = (Object.keys(byY).length ? '<h2 class="maph">Shop</h2>' : '') + Object.keys(byY).sort((a, b) => b - a).map(y => `<div class="level"><span>y ${esc(y)}</span><div class="chests">
            ${byY[y].sort((a, b) => a[0].x - b[0].x || a[0].z - b[0].z).map(([c, k]) => {
                const ids = Object.entries(c.items).sort((a, b) => b[1] - a[1]), main = c.main || (ids[0] && ids[0][0]);
                return `<button type="button" class="chest${c.misplaced.length ? ' wrong' : ''}${ids.length ? '' : ' empty'}" data-c="${k}" title="${esc(`${c.x} ${c.y} ${c.z}` + (main ? ': ' + main.replace(/_/g, ' ') : ': empty'))}">${main ? `<img src="${ICON(main)}" alt="" width="24" height="24" loading="lazy">` : ''}<span>${ids.length ? num(ids.reduce((s, e) => s + e[1], 0)) : ''}</span></button>`;
            }).join('')}</div></div>`).join('') + roomHtml || `<p class="empty">${cf === 'problems' ? 'No chests hold the wrong item.' : 'No chests to show.'}</p>`;
    }
    $('#cfilter').addEventListener('click', e => { const b = e.target.closest('button[data-k]'); if (!b) return; cf = b.dataset.k; $$('#cfilter button').forEach(x => x.setAttribute('aria-pressed', x === b)); drawChests(); });
    $('#map').addEventListener('click', e => {
        const b = e.target.closest('[data-c],[data-r]');
        if (!b) return;
        const c = b.dataset.r !== undefined ? { ...data.room[b.dataset.r], misplaced: [], main: null } : data.chests[b.dataset.c], ids = Object.entries(c.items).sort((a, b) => b[1] - a[1]);
        openPanel(`<h2>${esc(c.type.replace(/_/g, ' '))}${b.dataset.r !== undefined ? ' <span class="tag t-back">stock room</span>' : ''}</h2><p class="mono">${c.x} ${c.y} ${c.z}</p>
            ${c.misplaced.length ? `<p class="low">Holds another item's stock: ${c.misplaced.map(id => esc(id.replace(/_/g, ' '))).join(', ')}</p>` : ''}
            <h3>Contents</h3>${ids.length ? `<table class="grid-t"><tbody>${ids.map(([id, n]) => `<tr><td>${ic(id)}${esc(id.replace(/_/g, ' '))}${id === c.main ? ' <span class="dim">(this chest\'s item)</span>' : ''}</td><td class="num">${num(n)}</td></tr>`).join('')}</tbody></table>` : '<p class="dim">Empty.</p>'}`);
    });

    // ---------- customers ----------
    let csort = { k: 'spent', dir: -1 };
    function customersView() {
        const th = (k, l, cls = '') => `<th class="${cls}" data-sort="${k}" aria-sort="${csort.k === k ? (csort.dir > 0 ? 'ascending' : 'descending') : 'none'}">${l}</th>`;
        const c = data.customers.map((x, k) => [x, k]).sort((a, b) => (a[0][csort.k] > b[0][csort.k] ? 1 : a[0][csort.k] < b[0][csort.k] ? -1 : 0) * csort.dir);
        $('#cust-t').innerHTML = c.length ? `<thead><tr>${th('name', 'Player')}${th('visits', 'Visits', 'num')}${th('spent', 'Spent', 'num')}${th('shorts', 'Paid short', 'num')}${th('shortBy', 'Short by', 'num')}${th('first', 'First visit')}${th('last', 'Last visit')}</tr></thead><tbody>
            ${c.map(([k, i]) => `<tr data-i="${i}"><td><b>${esc(k.name)}</b></td><td class="num">${num(k.visits)}</td><td class="num">${d2(k.spent)}</td><td class="num ${k.shorts ? 'low' : 'zero'}">${num(k.shorts)}</td><td class="num ${k.shortBy ? 'low' : 'zero'}">${k.shortBy ? d2(k.shortBy) : ''}</td>
            <td class="when">${esc(short(k.first))}</td><td class="when" title="${esc(date(k.last))}">${esc(ago(k.last * 1000))}</td></tr>`).join('')}</tbody>`
            : '<tbody><tr><td class="empty">No customers yet.</td></tr></tbody>';
    }
    $('#cust-t').addEventListener('click', e => {
        const h = e.target.closest('th[data-sort]');
        if (h) { csort = { k: h.dataset.sort, dir: csort.k === h.dataset.sort ? -csort.dir : (h.dataset.sort === 'name' ? 1 : -1) }; return customersView(); }
        const tr = e.target.closest('tr[data-i]');
        if (!tr) return;
        select(tr);
        const k = data.customers[tr.dataset.i];
        openPanel(`<h2>${esc(k.name)}</h2><div class="kv"><span>Visits</span><span>${num(k.visits)}</span><span>Spent</span><span>${dia(k.spent)}</span>
            <span>Paid short</span><span>${k.shorts ? `${num(k.shorts)} time${k.shorts > 1 ? 's' : ''}, ${dia(k.shortBy)} in total` : 'never'}</span>
            <span>First visit</span><span>${esc(date(k.first))}</span><span>Last visit</span><span>${esc(date(k.last))}</span></div>
            <p><button class="btn" data-player="${esc(k.name)}">Their log this month</button></p>`);
    });

    // ---------- prices ----------
    function pricesView(sugg) {
        $('#prices-t').innerHTML = sugg.length ? `<thead><tr><th>Item</th><th>Price now</th><th class="num">Sold, 14 days</th><th class="num">In stock</th><th class="num">Days left</th><th>Suggestion</th></tr></thead><tbody>
            ${sugg.map(p => `<tr><td>${ic(p.id)}${esc(p.name)}</td><td>${esc(p.price)}</td><td class="num">${num(p.sold14)}</td><td class="num">${num(p.stock || 0)}</td><td class="num">${p.daysLeft !== null ? Math.round(p.daysLeft) : ''}</td><td><b>${esc(p.suggestion)}</b></td></tr>`).join('')}</tbody>`
            : '<tbody><tr><td class="empty">Nothing to change yet. Suggestions start once items have sold for a week or so.</td></tr></tbody>';
    }

    // ---------- details panel ----------
    const panel = $('#panel');
    function openPanel(html) {
        $('#panel-body').innerHTML = html;
        panel.hidden = false;
        panel.focus({ preventScroll: true });
    }
    function closePanel() { panel.hidden = true; $$('tr.sel').forEach(r => r.classList.remove('sel')); }
    function select(tr) { $$('tr.sel').forEach(r => r.classList.remove('sel')); tr.classList.add('sel'); }
    $('#panel-x').onclick = closePanel;
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !panel.hidden) closePanel(); });
    // "show in log" buttons: jump to the log filtered by a player or item
    panel.addEventListener('click', e => {
        const b = e.target.closest('[data-player],[data-item]');
        if (!b) return;
        $('#lq').value = b.dataset.player || b.dataset.item;
        kind = 'all';
        location.hash = '#log';
        setTimeout(() => { $$('#kinds button').forEach(x => x.setAttribute('aria-pressed', x.dataset.k === 'all')); drawLog(true); }, 0);
    });
})();
