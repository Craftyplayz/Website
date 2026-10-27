// All Possible Farms: filters, chunked list, details drawer and (on the private page) saving ticks.
(() => {
    const { $, esc, debounce, toast, num } = UI;
    const { items, done, edit } = MCF;
    const noTex = new Set(MCF.noTex);
    const byKey = new Map(items.map(it => [it.k, it]));
    const byName = new Map();
    items.forEach(it => byName.has(it.name) || byName.set(it.name, it));
    items.forEach(it => it.hay = [it.name, it.info, it.method, ...(it.parents || [])].join(' ').toLowerCase());
    const CHUNK = 250;
    const BADGE = { 'Playerless': 'g', 'Player Loaded': 'b', 'Macro': 'p', 'Not Farmable': 'r' };
    const checks = ['renew', 'inf', 'be', 'todo'], selects = ['cat', 'type', 'method'];
    let list = [], shown = 0;

    const remote = it => 'https://ofekn-mods.github.io/sprites/items/' + it.id + '-' + encodeURIComponent(it.name.replace(/[\\/:*?"<>|]/g, '')) + '.png';
    const icon = (it, size, lazy) => `<img src="${esc(noTex.has(it.id) ? remote(it) : '/minecraft/tools/item-list/minecraft_' + it.id + '.png')}" data-alt="${noTex.has(it.id) ? '' : esc(remote(it))}" alt="" width="${size}" height="${size}"${lazy ? ' loading="lazy"' : ''} decoding="async">`;
    // missing art: try the fallback once, then hide (never a broken icon)
    document.addEventListener('error', e => {
        const img = e.target;
        if (img.tagName !== 'IMG' || !img.closest('.mcf-list, .mcf-drawer')) return;
        if (img.dataset.alt) { img.src = img.dataset.alt; img.dataset.alt = ''; } else img.style.visibility = 'hidden';
    }, true);

    // fill the selects with their counts
    for (const [id, field] of [['cat', 'cat'], ['type', 'type'], ['method', 'method']]) {
        const counts = {};
        items.forEach(it => it[field] && (counts[it[field]] = (counts[it[field]] || 0) + 1));
        Object.keys(counts).sort().forEach(v => $('#' + id).add(new Option(`${v} (${num(counts[v])})`, v)));
    }

    // filter state from the URL
    const params = new URLSearchParams(location.search);
    $('#q').value = params.get('q') || '';
    selects.forEach(id => { const v = params.get(id); if (v && [...$('#' + id).options].some(o => o.value === v)) $('#' + id).value = v; });
    checks.forEach(id => $('#' + id).checked = params.get(id) === '1');

    const tick = it => {
        const on = !!done[it.k];
        const label = on ? 'Farmed' : 'Not farmed yet';
        return edit
            ? `<button class="mcf-tick${on ? ' on' : ''}" data-tick="${esc(it.k)}" aria-pressed="${on}" aria-label="${esc(it.name)}: ${label}" title="${label}"></button>`
            : `<span class="mcf-tick${on ? ' on' : ''}" role="img" aria-label="${label}" title="${label}"></span>`;
    };
    const row = (it, i) => `<div class="mcf-row${done[it.k] ? ' done' : ''}" data-row="${esc(it.k)}">
        <button class="mcf-open" data-k="${esc(it.k)}">${icon(it, 32, i > 40)}<span class="mcf-name"><b>${esc(it.name)}${it.info ? ` <span class="muted">${esc(it.info)}</span>` : ''}</b><small>${esc(it.method || '')}</small></span><span class="badge ${BADGE[it.type] || ''}">${esc(it.type)}</span></button>${tick(it)}</div>`;

    function render() {
        const q = $('#q').value.trim().toLowerCase(), f = {};
        selects.forEach(id => f[id] = $('#' + id).value);
        checks.forEach(id => f[id] = $('#' + id).checked);
        list = items.filter(it => (!q || it.hay.includes(q)) && (!f.cat || it.cat === f.cat) && (!f.type || it.type === f.type) && (!f.method || it.method === f.method)
            && (!f.renew || it.renew) && (!f.inf || it.infinite) && (!f.be || it.be) && (!f.todo || !done[it.k]));
        $('#count').textContent = list.length === items.length ? num(items.length) + ' items' : num(list.length) + ' of ' + num(items.length);
        shown = 0;
        $('#list').innerHTML = list.length ? '' : '<p class="muted">No items match.</p>';
        more();
        const p = new URLSearchParams();
        if (q) p.set('q', $('#q').value.trim());
        selects.forEach(id => f[id] && p.set(id, f[id]));
        checks.forEach(id => f[id] && p.set(id, '1'));
        history.replaceState(null, '', location.pathname + (p.size ? '?' + p : '') + location.hash);
    }
    function more() {
        $('#list').insertAdjacentHTML('beforeend', list.slice(shown, shown + CHUNK).map((it, i) => row(it, shown + i)).join(''));
        shown = Math.min(list.length, shown + CHUNK);
        $('#more').hidden = shown >= list.length;
        $('#more').textContent = `Show more (${num(list.length - shown)} left)`;
    }

    const yes = v => v ? 'Yes' : 'No';
    const kv = (k, v) => v === undefined || v === '' ? '' : `<span>${k}</span><span>${v}</span>`;
    const video = v => /^[\w-]{11}$/.test(v) ? 'https://youtu.be/' + v : /^https?:\/\//.test(v) ? v : '';
    let current = '';

    function open(k) {
        const it = byKey.get(k);
        if (!it) return;
        current = k;
        const parents = (it.parents || []).map(n => byName.has(n) ? `<a href="#${esc(byName.get(n).k)}" data-k="${esc(byName.get(n).k)}">${esc(n)}</a>` : esc(n)).join(', ');
        const url = it.video && video(it.video);
        const on = !!done[k];
        $('#detail').innerHTML = `
            <div class="mcf-head">${icon(it, 48)}<div><h2>${esc(it.name)}</h2>${it.info ? `<p class="muted">${esc(it.info)}</p>` : ''}</div></div>
            <div class="badges"><span class="badge ${BADGE[it.type] || ''}">${esc(it.type)}</span><span class="badge${on ? ' g' : ''}">${on ? 'Farmed' : 'Not farmed yet'}</span></div>
            ${url || edit ? `<p class="actions">${url ? `<a class="btn${edit ? '' : ' primary'}" href="${esc(url)}" target="_blank" rel="noopener">Video</a>` : ''}${edit ? `<button class="btn primary" data-tick="${esc(k)}">${on ? 'Mark as not farmed' : 'Mark as farmed'}</button>` : ''}</p>` : ''}
            <div class="kv">
                ${kv('Method', esc(it.method || ''))}
                ${kv('First time', esc(it.first || ''))}
                ${kv('After the first', esc(it.post || ''))}
                ${kv('Made from', parents)}
                ${kv('Category', esc(it.cat || ''))}
                ${kv('Renewable', yes(it.renew))}
                ${kv('Runs with no player nearby', yes(it.playerless))}
                ${kv('Runs while you are nearby', yes(it.loaded))}
                ${kv('Runs forever (AFK)', yes(it.infinite))}
                ${kv('Needs a manual menu', it.gui ? 'Yes (crafting, trading, stonecutting or smithing)' : 'No')}
                ${kv('Works in Bedrock', yes(it.be))}
                ${kv('Stack size', it.stack)}
                ${kv('Added in', esc(it.ver || ''))}
                ${kv('ID', `<span class="mono">minecraft:${esc(it.id)}</span>`)}
            </div>`;
        $('#drawer').classList.add('open');
        $('#drawer').focus({ preventScroll: true });
        history.replaceState(null, '', location.pathname + location.search + '#' + k);
    }
    function close() {
        if (!$('#drawer').classList.contains('open')) return;
        $('#drawer').classList.remove('open');
        current = '';
        history.replaceState(null, '', location.pathname + location.search);
    }

    // show the new state at once, put it back if the save fails
    function setDone(k, on) {
        if (on) done[k] = 1; else delete done[k];
        const r = document.querySelector(`[data-row="${CSS.escape(k)}"]`);
        if (r) r.outerHTML = row(byKey.get(k), 0);
        if (current === k) open(k);
        const n = items.filter(it => done[it.k]).length, p = Math.floor(n * 100 / items.length);
        $('#farmed').textContent = num(n) + ' / ' + num(items.length);
        $('#ring').style.setProperty('--p', p);
        $('#ring').classList.toggle('full', p === 100);
        $('#ring').querySelector('b').innerHTML = p + '<small>%</small>';
        $('#ring').setAttribute('aria-label', n + ' of ' + items.length + ' farmed');
    }
    function toggle(k) {
        const on = !done[k];
        setDone(k, on);
        fetch('/private/minecraft/save.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-MC-Tick': '1' }, body: JSON.stringify({ list: 'farms', key: k, done: on }) })
            .then(r => r.text().then(t => { if (!r.ok || t !== 'ok') throw 0; }))
            .catch(() => { setDone(k, !on); toast(`Couldn't save ${byKey.get(k).name}. Try again.`, 'err'); });
    }

    document.addEventListener('click', e => {
        const t = e.target.closest('[data-tick]');
        if (t && edit) return toggle(t.dataset.tick);
        const o = e.target.closest('[data-k]');
        if (o) { e.preventDefault(); open(o.dataset.k); }
    });
    $('#q').addEventListener('input', debounce(render, 80));
    [...selects, ...checks].forEach(id => $('#' + id).addEventListener('change', render));
    $('#more').addEventListener('click', more);
    $('#close').addEventListener('click', close);
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') close();
        if (e.key === '/' && !/^(INPUT|SELECT|TEXTAREA)$/.test(document.activeElement.tagName)) { e.preventDefault(); $('#q').focus(); }
    });

    render();
    if (location.hash) open(decodeURIComponent(location.hash.slice(1)));
})();
