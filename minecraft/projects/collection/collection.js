(() => {
    const D = window.MCC, esc = s => String(s).replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#39;" }[c]));
    const $ = s => document.querySelector(s);
    const NAMES = { items: 'items', templates: 'templates', sherds: 'sherds' };
    const CHUNK = 250;
    const p = new URLSearchParams(location.search);
    let sec = NAMES[p.get('s')] ? p.get('s') : 'items', q = p.get('q') || '', missing = p.get('m') === '1', shown = CHUNK, matches = [];
    const done = D.done || {};
    for (const s in D.list) for (const e of D.list[s]) {
        e.low = e.name.toLowerCase();
        const t = [];
        if (/ \+$/.test(e.name)) t.push(['+', 'Longer duration']);
        if (e.name.includes('[JE]')) t.push(['JE', 'Java Edition only']);
        e.disp = e.name.replace(/ ?\[JE\]/g, '').replace(/ \+$/, '');
        e.badges = t.map(b => `<span class="badge" title="${b[1]}">${b[0]}</span>`).join('');
    }

    function tile(e) {
        const on = !!done[e.k];
        const img = e.id ? `<img src="/minecraft/tools/item-list/minecraft_${esc(encodeURIComponent(e.id))}.png" width="32" height="32" alt="" loading="lazy" decoding="async" onerror="this.remove()">` : '';
        const inner = `${img}<span class="nm" title="${esc(e.name)}">${esc(e.disp)}${e.badges}</span><span class="tk" aria-hidden="true">&#10003;</span>`;
        return D.edit
            ? `<button type="button" class="mcc-t${on ? ' on' : ''}" data-k="${esc(e.k)}" aria-pressed="${on}">${inner}</button>`
            : `<div class="mcc-t${on ? ' on' : ''}" title="${on ? 'Collected' : 'Missing'}">${inner}</div>`;
    }

    function stats() {
        let all = 0, tot = 0;
        for (const s in D.list) {
            const n = D.list[s].filter(e => done[e.k]).length, t = D.list[s].length;
            all += n; tot += t;
            document.querySelector(`[data-n="${s}"]`).textContent = `${n}/${t}`;
            document.querySelector(`[data-stat="${s}"] b`).textContent = `${n.toLocaleString()} / ${t.toLocaleString()}`;
        }
        const pc = tot ? Math.floor(all / tot * 10000) / 100 : 0, r = $('#mcc-ring');
        r.style.setProperty('--p', pc); r.classList.toggle('full', pc >= 100);
        r.firstChild.textContent = pc.toFixed(2) + '%'; r.setAttribute('aria-label', `${all} of ${tot} collected`);
        $('#mcc-all').textContent = `${all.toLocaleString()} of ${tot.toLocaleString()}`;
    }

    function render() {
        const ql = q.trim().toLowerCase();
        matches = D.list[sec].filter(e => (!ql || e.low.includes(ql)) && !(missing && done[e.k]));
        $('#mcc-grid').innerHTML = matches.slice(0, shown).map(tile).join('') || '<p class="muted">Nothing matches.</p>';
        $('#mcc-more').hidden = matches.length <= shown;
        $('#mcc-more').textContent = `Show more (${(matches.length - shown).toLocaleString()} left)`;
        $('#mcc-count').textContent = `${matches.length.toLocaleString()} shown`;
        document.querySelectorAll('#mcc-tabs button').forEach(b => b.classList.toggle('on', b.dataset.s === sec));
        const u = new URLSearchParams();
        if (sec !== 'items') u.set('s', sec);
        if (ql) u.set('q', q.trim());
        if (missing) u.set('m', '1');
        history.replaceState(null, '', location.pathname + (u.toString() ? '?' + u : ''));
    }
    const reset = () => { shown = CHUNK; render(); };

    document.addEventListener('DOMContentLoaded', () => {
        $('#mcc-q').value = q; $('#mcc-missing').checked = missing;
        $('#mcc-q').addEventListener('input', UI.debounce(e => { q = e.target.value; reset(); }, 80));
        $('#mcc-missing').addEventListener('change', e => { missing = e.target.checked; reset(); });
        $('#mcc-tabs').addEventListener('click', e => { const b = e.target.closest('button'); if (b) { sec = b.dataset.s; reset(); } });
        $('#mcc-more').addEventListener('click', () => { shown += CHUNK; render(); });
        if (D.edit) $('#mcc-grid').addEventListener('click', async e => {
            const b = e.target.closest('.mcc-t'); if (!b) return;
            const k = b.dataset.k, want = !done[k];
            const set = v => { if (v) done[k] = 1; else delete done[k]; b.classList.toggle('on', v); b.setAttribute('aria-pressed', v); stats(); };
            set(want);
            try {
                const r = await fetch('/private/minecraft/save.php', { method: 'POST', headers: { 'X-MC-Tick': '1', 'Content-Type': 'application/json' }, body: JSON.stringify({ list: 'collection', key: k, done: want }) });
                const t = await r.text();
                if (!r.ok || t.trim() !== 'ok') throw new Error(t);
            } catch (err) {
                set(!want);
                UI.toast("Couldn't save that tick. " + (err.message || '').slice(0, 80), 'err');
            }
        });
        stats(); render();
    });
})();
