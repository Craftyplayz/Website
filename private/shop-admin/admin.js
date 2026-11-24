// Castle Crafty admin: renders /shop/admin/data (admin.json from the home server's shop-log container)
document.addEventListener('DOMContentLoaded', () => {
    const { $, esc, debounce, num, ago } = UI;
    const ICON = id => `/minecraft/tools/item-list/minecraft_${encodeURIComponent(id)}.png`;
    const d2 = n => (n > 0 && n < 0.01 ? '<0.01' : (Math.round(n * 100) / 100).toLocaleString('en-GB'));
    const dia = n => `${d2(n)} diamond${Math.round(n * 100) === 100 ? '' : 's'}`;
    const date = t => new Date(t * 1000).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' });
    const md = t => esc(t).replace(/\*\*(.+?)\*\*/g, '<b>$1</b>').replace(/\n/g, '<br>'); // Discord bold → HTML
    const icon = (id, size = 24) => `<img src="${ICON(id)}" alt="" width="${size}" height="${size}" loading="lazy" onerror="this.style.visibility='hidden'">`;
    let data;

    fetch('/shop/admin/data', { cache: 'no-store' }).then(async r => {
        const j = await r.json().catch(() => ({}));
        if (!r.ok) throw Error(j.error || `The data page answered ${r.status}.`);
        if (r.headers.get('X-Stale')) $('#sa-lede').textContent += ' The home server is offline: this is the last copy.';
        return j;
    }).then(d => {
        data = d;
        $('#sa-status').remove();
        $('#sa-body').hidden = false;
        render();
    }).catch(e => {
        const s = $('#sa-status');
        s.className = 'err';
        s.textContent = `Couldn't load the shop data. ${e.message}`;
    });

    function render() {
        const t = data.takings;
        $('#sa-lede').innerHTML = `Takings, stock, chests and alerts from the shop's chest log. Updated <span title="${esc(date(data.updated))}">${esc(ago(data.updated * 1000))}</span>; stock as of the last world save, <span title="${esc(date(data.saved))}">${esc(ago(data.saved * 1000))}</span>.`;
        const stat = (b, s, href) => `<a class="stat-card" href="${href}"><b>${b}</b><span>${s}</span></a>`;
        $('#sa-stats').innerHTML = stat(d2(t.all), 'diamonds all time', '#sa-takings') + stat(d2(t.today), 'today', '#sa-takings') + stat(d2(t.week), 'last 7 days', '#sa-takings')
            + (t.uncollected === null ? '' : stat(d2(t.uncollected), 'uncollected in chests', '#sa-chests')) + stat(num(data.customers.length), 'customers', '#sa-customers');
        chart(t.daily);
        events();
        suggestions();
        stock();
        customers();
        chests();
    }

    // --- takings: one bar per day ---
    function chart(daily) {
        const max = Math.max(1, ...daily.map(d => d[1]));
        const W = 900, H = 160, bw = W / daily.length;
        const bars = daily.map(([day, v], k) => {
            const h = Math.max(v ? 2 : 0, (v / max) * (H - 20));
            const label = `${new Date(day + 'T12:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}: ${dia(v)}`;
            return `<rect x="${(k * bw + 1).toFixed(1)}" y="${(H - h).toFixed(1)}" width="${(bw - 2).toFixed(1)}" height="${h.toFixed(1)}" rx="1"><title>${esc(label)}</title></rect>`;
        }).join('');
        const total = daily.reduce((s, d) => s + d[1], 0);
        $('#sa-chart').innerHTML = `<svg class="sa-bars" viewBox="0 0 ${W} ${H}" preserveAspectRatio="none" role="img" aria-label="Diamonds earned per day, last 90 days: ${esc(dia(total))} in total">${bars}</svg>
            <div class="sa-axis muted"><span>${esc(daily[0][0])}</span><span>most in a day: ${esc(dia(max === 1 && !total ? 0 : max))}</span><span>today</span></div>
            <details><summary class="muted">Numbers</summary><div class="table-wrap"><table class="table"><thead><tr><th>Day</th><th class="num">Diamonds</th></tr></thead><tbody>
            ${daily.filter(d => d[1]).reverse().map(d => `<tr><td>${esc(d[0])}</td><td class="num">${d2(d[1])}</td></tr>`).join('') || '<tr><td colspan="2" class="muted">No sales yet.</td></tr>'}</tbody></table></div></details>`;
    }

    // --- alerts: short payments, losses, hoppers, low stock, wrong chests ---
    function events() {
        const kinds = { short: ['Paid short', 'r'], loss: ['Missing stock', 'r'], hopper: ['Hopper', 'r'], low: ['Low stock', 'b'], wrong: ['Wrong chest', 'p'] };
        $('#sa-alerts-n').textContent = data.events.length ? num(data.events.length) : '';
        $('#sa-events').innerHTML = data.events.map(e => {
            const [label, cls] = kinds[e.kind] || [e.kind, ''];
            return `<div class="rowi sa-ev"><span class="badge ${cls}">${esc(label)}</span><span class="grow">${md(e.text.replace(/^⚠️ /, ''))}</span><small title="${esc(date(e.t))}">${esc(ago(e.t * 1000))}</small></div>`;
        }).join('') || '<p class="muted">No alerts yet.</p>';
    }

    // --- price suggestions ---
    function suggestions() {
        const list = data.products.filter(p => p.suggestion);
        $('#sa-prices-n').textContent = list.length ? num(list.length) : '';
        $('#sa-suggest').innerHTML = list.length ? `<div class="table-wrap"><table class="table"><thead><tr><th>Item</th><th>Now</th><th class="num">Sold (14 days)</th><th class="num">In stock</th><th>Suggestion</th></tr></thead><tbody>
            ${list.map(p => `<tr><td>${icon(p.id, 16)} ${esc(p.name)}</td><td>${esc(p.price)}</td><td class="num">${num(p.sold14)}</td><td class="num">${num(p.stock)}</td><td>${esc(p.suggestion)}</td></tr>`).join('')}</tbody></table></div>`
            : '<p class="muted">Nothing to change yet. Suggestions start once items have sold for a while.</p>';
    }

    // --- stock table with a sparkline per item; click a row for its full history ---
    function spark(h, w = 120, hgt = 28) {
        if (!h.length) return '';
        const now = data.updated, t0 = Math.min(h[0][0], now - 7 * 86400);
        const pts = [...h, [now, h[h.length - 1][1]]];
        const max = Math.max(1, ...pts.map(p => p[1]));
        const x = t => ((t - t0) / (now - t0)) * w, y = v => hgt - 2 - (v / max) * (hgt - 4);
        let d = `M${x(pts[0][0]).toFixed(1)},${y(pts[0][1]).toFixed(1)}`;
        for (let k = 1; k < pts.length; k++) d += `H${x(pts[k][0]).toFixed(1)}V${y(pts[k][1]).toFixed(1)}`; // steps: stock changes in jumps
        return `<svg class="sa-spark" viewBox="0 0 ${w} ${hgt}" width="${w}" height="${hgt}" aria-hidden="true"><path d="${d}"/></svg>`;
    }
    function stock() {
        const rows = data.products.map((p, k) => `<tr data-k="${k}" data-q="${esc((p.name + ' ' + p.id).toLowerCase())}" data-listed="${p.price ? 1 : 0}">
            <td>${icon(p.id, 16)} ${esc(p.name)}</td><td class="num${p.stock ? '' : ' sa-zero'}">${p.stock ? num(p.stock) : '0'}</td><td class="num">${p.sold14 ? num(p.sold14) : ''}</td>
            <td class="num">${p.daysLeft !== null ? Math.round(p.daysLeft) : ''}</td><td>${spark(p.history)}</td></tr>`).join('');
        $('#sa-stock-table').innerHTML = `<thead><tr><th>Item</th><th class="num">Stock</th><th class="num">Sold (14 days)</th><th class="num">Days left</th><th>Last 7+ days</th></tr></thead><tbody>${rows}</tbody>`;
        filter();
    }
    const filter = () => {
        const q = $('#sa-q').value.trim().toLowerCase(), only = $('#sa-listed').checked;
        document.querySelectorAll('#sa-stock-table tbody tr').forEach(tr => { tr.hidden = !tr.dataset.q.includes(q) || (only && tr.dataset.listed !== '1'); });
    };
    $('#sa-q').addEventListener('input', debounce(filter, 80));
    $('#sa-listed').addEventListener('change', filter);

    // drawer: one item's stock history, bigger
    const drawer = $('#sa-drawer');
    $('#sa-stock-table').addEventListener('click', e => {
        const tr = e.target.closest('tr[data-k]');
        if (!tr) return;
        const p = data.products[tr.dataset.k];
        const h = p.history;
        $('#sa-detail').innerHTML = `<h2 class="sa-dh">${icon(p.id, 32)} ${esc(p.name)}</h2>
            <div class="kv"><span>Price</span><span>${esc(p.price || 'not on the price list')}</span><span>In stock</span><span>${num(p.stock)}</span>
            <span>Sold, 14 days</span><span>${num(p.sold14)}</span>${p.suggestion ? `<span>Suggestion</span><span>${esc(p.suggestion)}</span>` : ''}</div>
            <h3>Stock history</h3>${h.length ? spark(h, 420, 140).replace('class="sa-spark"', 'class="sa-spark big"')
                + `<p class="muted sa-cap">From ${esc(date(Math.min(h[0][0], data.updated - 7 * 86400)))} to now · highest ${num(Math.max(...h.map(e => e[1])))}, lowest ${num(Math.min(...h.map(e => e[1])))}</p>` : '<p class="muted">No history yet.</p>'}
            ${h.length ? `<details><summary class="muted">Every change (${num(h.length)})</summary><div class="rows">${h.slice().reverse().map(([t, n]) => `<div class="rowi"><span class="grow">${esc(date(t))}</span><small>${num(n)}</small></div>`).join('')}</div></details>` : ''}`;
        drawer.classList.add('open');
        drawer.setAttribute('aria-hidden', 'false');
        drawer.focus();
    });
    const close = () => { drawer.classList.remove('open'); drawer.setAttribute('aria-hidden', 'true'); };
    $('.close', drawer).onclick = close;
    document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });

    // --- customers ---
    function customers() {
        const c = data.customers;
        $('#sa-customers-n').textContent = c.length ? num(c.length) : '';
        $('#sa-cust').innerHTML = c.length ? `<div class="table-wrap"><table class="table"><thead><tr><th>Player</th><th class="num">Visits</th><th class="num">Spent</th><th class="num">Paid short</th><th>Last visit</th></tr></thead><tbody>
            ${c.map(k => `<tr><td>${esc(k.name)}</td><td class="num">${num(k.visits)}</td><td class="num">${d2(k.spent)}</td>
            <td class="num">${k.shorts ? `<span class="badge r">${num(k.shorts)}× · ${d2(k.shortBy)}</span>` : ''}</td><td title="${esc(date(k.last))}">${esc(ago(k.last * 1000))}</td></tr>`).join('')}</tbody></table></div>`
            : '<p class="muted">No customers yet.</p>';
    }

    // --- chest map: one row of chests per height, the chest's item as its icon ---
    function chests() {
        const all = data.chests, showEmpty = $('#sa-empty').checked;
        const byY = {};
        for (const c of all) {
            const empty = !Object.keys(c.items).length;
            if (empty && !showEmpty) continue;
            (byY[c.y] = byY[c.y] || []).push(c);
        }
        const used = all.filter(c => Object.keys(c.items).length).length, wrong = all.filter(c => c.misplaced.length).length;
        $('#sa-chests-n').textContent = `${num(used)} in use of ${num(all.length)}${wrong ? ` · ${num(wrong)} with the wrong item` : ''}`;
        $('#sa-map').innerHTML = Object.keys(byY).sort((a, b) => b - a).map(y => `<div class="sa-level"><span class="sa-y">y ${esc(y)}</span><div class="sa-chests">
            ${byY[y].sort((a, b) => a.x - b.x || a.z - b.z).map(c => {
                const ids = Object.entries(c.items).sort((a, b) => b[1] - a[1]);
                const title = `${c.type.replace(/_/g, ' ')} at ${c.x} ${c.y} ${c.z}\n` + (ids.length ? ids.map(([id, n]) => `${num(n)} ${id.replace(/_/g, ' ')}`).join('\n') : 'empty');
                const main = c.main || (ids[0] && ids[0][0]);
                return `<span class="sa-chest${c.misplaced.length ? ' wrong' : ''}${ids.length ? '' : ' empty'}" title="${esc(title)}">${main ? icon(main, 24) : ''}<small>${ids.length ? num(ids.reduce((s, e) => s + e[1], 0)) : ''}</small></span>`;
            }).join('')}</div></div>`).join('') || '<p class="muted">No chests in use.</p>';
    }
    $('#sa-empty').addEventListener('change', chests);
});
