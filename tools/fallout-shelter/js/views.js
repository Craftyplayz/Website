// Fallout Shelter Save Editor: Vault Map, Dwellers, Resources, Inventory and the details drawer.
(() => {
    'use strict';
    const { $, $$, toast, num, store } = UI;
    const { html, put, state, act, openDrawer, renderDrawer, showDialog, closeDialog } = window.FOS;
    const C = window.FOSCore, M = window.FOSModel;
    const { get, val, items, numberLike, stringRaw } = C;
    const FOS = window.FOS;
    const raw = (s) => ({ __html: s });

    /* ---------- fields: one per value in the save ---------- */
    const fields = { view: new Map(), drawer: new Map() };
    let nextField = 0;
    const lockIcon = raw('<svg viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M5 7V5a3 3 0 0 1 6 0v2h1v7H4V7zm1.5 0h3V5a1.5 1.5 0 0 0-3 0z"/></svg>');
    FOS.lockIcon = lockIcon;

    // Returns nothing when the save doesn't have this value, so older saves simply show fewer fields.
    function field(scope, node, label, opts = {}) {
        if (!node || node.t === 'o' || node.t === 'a') return '';
        const id = 'f' + (++nextField);
        fields[scope].set(id, { node, label: opts.history || label, opts });
        const locked = C.isLockedNode(node);
        const attrs = raw(`data-field="${id}" data-scope="${scope}"${locked ? ' disabled' : ''}`);
        const hint = html`${opts.hint ? html`<small>${opts.hint}</small>` : ''}${locked ? html`<small>${C.LOCK_MESSAGE}</small>` : ''}`;
        const value = val(node);
        const text = node.t === 's' ? value : node.raw;
        if (opts.type === 'bool' || node.t === 'b') {
            return html`<label class="check"><input type="checkbox" ${attrs} ${raw(value === true ? 'checked' : '')}> ${label}</label>`;
        }
        if (opts.choices) {
            const has = opts.choices.some(([v]) => String(v) === String(value));
            const list = has ? opts.choices : [[value, String(value)], ...opts.choices];
            return html`<label>${label} <select class="field" ${attrs}>${list.map(([v, l]) => html`<option value="${v}" ${raw(String(v) === String(value) ? 'selected' : '')}>${l}</option>`)}</select>${hint}</label>`;
        }
        if (opts.type === 'color') {
            const hex = '#' + (Number(value) & 0xffffff).toString(16).padStart(6, '0');
            return html`<label>${label} <input class="field" type="color" value="${hex}" ${attrs}>${hint}</label>`;
        }
        if (node.t === 'n') {
            // very large integers stay as text so the browser can't round them
            const safe = C.isSafeNumber(node.raw);
            return html`<label>${label} <input class="field" ${raw(safe ? 'type="number" step="any"' : 'type="text"')} inputmode="decimal" value="${text}" ${attrs}>${hint}</label>`;
        }
        return html`<label>${label} <input class="field" type="text" value="${text === null ? '' : text}" ${opts.list ? raw(`list="${opts.list}"`) : ''} ${attrs}>${hint}</label>`;
    }
    function rawFromInput(el, node, opts) {
        if (el.type === 'checkbox') return el.checked ? 'true' : 'false';
        if (el.type === 'color') {
            const alpha = (Number(val(node)) >>> 24) & 0xff;
            return String(((alpha << 24) | parseInt(el.value.slice(1), 16)) >>> 0);
        }
        if (node.t === 'n') return numberLike(node.raw, el.value);
        if (node.t === 'z' && el.value === '') return 'null';
        return stringRaw(el.value);
    }
    function onFieldChange(e) {
        const el = e.target;
        const id = el.dataset && el.dataset.field;
        if (!id) return;
        const scope = el.dataset.scope;
        const f = fields[scope].get(id);
        if (!f) return;
        const labelEl = el.closest('label');
        const clear = () => { el.removeAttribute('aria-invalid'); const s = labelEl && $('.fos-err-small', labelEl); if (s) s.remove(); };
        clear();
        let rawValue;
        try {
            rawValue = rawFromInput(el, f.node, f.opts);
        } catch (err) {
            el.setAttribute('aria-invalid', 'true');
            if (labelEl) { const s = document.createElement('small'); s.className = 'fos-err-small'; s.textContent = err.message; labelEl.appendChild(s); }
            return;
        }
        if (act(f.label, [{ op: 'set', node: f.node, raw: rawValue }], scope)) {
            if (el.type !== 'checkbox' && el.type !== 'color') el.value = f.node.t === 's' ? val(f.node) : f.node.raw;
        } else if (el.type === 'checkbox') el.checked = f.node.raw === 'true';
    }
    document.addEventListener('change', onFieldChange);
    // Enter in a text box commits it without moving on, as a change event would on blur
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.dataset && e.target.dataset.field && e.target.tagName === 'INPUT' && e.target.type !== 'checkbox') e.target.blur();
    });

    /* ---------- shared bits ---------- */
    const initials = (d) => ((String(d.name)[0] || '') + (String(d.lastName)[0] || '')).toUpperCase() || '?';
    const dName = (d) => M.fullName(d);
    const statusOf = (d) => (d.dead ? 'Dead' : d.inWasteland ? 'In the wasteland' : d.room ? d.room.name : 'No room');
    const chip = (d, q) => {
        const hit = q && dName(d).toLowerCase().includes(q);
        return html`<button class="fos-chip ${d.rarity === 'Legendary' ? 'fos-legend' : ''} ${hit ? 'fos-hit' : ''}" data-open-d="${d.id}" data-chip="${d.id}" title="${dName(d)}, level ${d.level}" aria-label="${dName(d)}, level ${d.level}">${initials(d)}</button>`;
    };
    const ownHas = (o, k) => Object.prototype.hasOwnProperty.call(o, k);
    function roomBadges(r) {
        const out = [];
        if (r.power === false) out.push(['r', 'No power']);
        if (r.broken) out.push(['r', 'Damaged']);
        if (ownHas(M.STATE_BADGES, r.state)) out.push([r.state === 'RoomEmergency' || r.state === 'RoomBurned' ? 'r' : 'b', M.STATE_BADGES[r.state]]);
        if (r.deadIds.length) out.push(['r', `${r.deadIds.length} dead`]);
        return out;
    }

    /* ---------- Vault Map ---------- */
    const ZOOMS = [0.5, 0.75, 1, 1.5];
    FOS.views.map = (el, f) => {
        const v = f.v();
        const z = ZOOMS.includes(store.get('fos-zoom', 1)) ? store.get('fos-zoom', 1) : 1;
        const q = state.query.toLowerCase();
        const matches = (r) => !q || r.name.toLowerCase().includes(q) || r.dwellerIds.some((id) => { const d = v.dwellerById.get(id); return d && dName(d).toLowerCase().includes(q); });
        const tile = (r) => {
            const people = r.dwellerIds.map((id) => v.dwellerById.get(id)).filter(Boolean);
            const elev = r.type === 'Elevator';
            const cap = M.capacity(r);
            const meta = `Level ${r.level} · Merge ${r.merge}${cap ? ` · ${people.length} of ${cap}` : ''}`;
            return html`<div class="fos-room ${elev ? 'fos-elev' : ''} ${q ? (matches(r) ? 'fos-hit' : 'fos-dim') : ''}" data-room="${r.id}" style="grid-column:${r.col + 1}/span ${r.width};grid-row:${r.row + 1}">
                <button class="fos-room-btn" data-open-r="${r.id}" aria-label="${r.name}, ${meta}" title="${r.name}. ${meta}">${elev ? html`<small>Elevator</small>` : html`<b>${r.name}</b><small>${meta}</small>`}</button>
                ${elev ? '' : html`<div class="fos-room-tags">${roomBadges(r).map(([k, t]) => html`<span class="badge ${k}">${t}</span>`)}</div>
                <div class="fos-chips">${people.map((d) => chip(d, q))}</div>`}
            </div>`;
        };
        const free = v.dwellers.filter((d) => !d.room && !d.dead && !d.inWasteland);
        put(el, html`
            <div class="fos-tools">
                <span class="muted fos-small fos-grow">${v.rooms.length} Rooms. Drag a Dweller to another Room, or click them and choose Move to.</span>
                <button class="btn small" data-zoom="-1" ${raw(z === ZOOMS[0] ? 'disabled' : '')} aria-label="Zoom out" title="Zoom out">−</button>
                <span class="muted fos-small">${Math.round(z * 100)}%</span>
                <button class="btn small" data-zoom="1" ${raw(z === ZOOMS[ZOOMS.length - 1] ? 'disabled' : '')} aria-label="Zoom in" title="Zoom in">+</button>
            </div>
            <div class="fos-map-scroll" tabindex="0" aria-label="Vault Map, scrolls in both directions">
                <div class="fos-map ${z < 1 ? 'fos-zoomed-out' : ''}" style="--fos-z:${z};--fos-cols:${v.cols}">
                    ${v.rocks.map((s) => html`<div class="fos-rock" style="grid-column:${s.col + 1}/span 2;grid-row:${s.row + 1}">Rock</div>`)}
                    ${v.rooms.map(tile)}
                </div>
            </div>
            ${free.length ? html`<h2 class="sec-h">Without a room <span class="n">${free.length}</span></h2><div class="fos-noroom fos-chips" data-noroom>${free.map((d) => chip(d, q))}</div>` : ''}
            ${f.base.length ? html`<p class="muted fos-note">This save came with ${f.base.length} mismatch${f.base.length === 1 ? '' : 'es'} the game accepts. They're left as they are.</p>` : ''}`);
    };

    /* ---------- dragging a Dweller ---------- */
    let drag = null;
    FOS.dragging = () => !!drag;
    const roomAt = (x, y) => {
        const hit = document.elementFromPoint(x, y);
        return hit ? hit.closest('[data-room],[data-noroom]') : null;
    };
    function endDrag(apply, x, y) {
        if (!drag) return;
        const d = drag;
        drag = null;
        d.ghost.remove();
        d.chip.classList.remove('fos-dragging');
        $$('.fos-room, .fos-noroom').forEach((n) => n.classList.remove('fos-ok', 'fos-no', 'fos-over'));
        if (!apply) return;
        const target = roomAt(x, y);
        if (!target) return;
        const f = state.active, v = f.v();
        const person = v.dwellerById.get(d.id);
        const room = target.dataset.room !== undefined ? v.roomById.get(Number(target.dataset.room)) : null;
        moveDweller(person, room);
    }
    function moveDweller(person, room) {
        const f = state.active;
        const res = M.moveOps(f.v(), person, room);
        if (res.why) { toast(res.why, 'err'); return false; }
        return act(room ? `Move ${dName(person)} to ${room.name}` : `Remove ${dName(person)} from their Room`, res.ops);
    }
    function suppressClick(e) { e.stopPropagation(); e.preventDefault(); }
    document.addEventListener('pointerdown', (e) => {
        const c = e.target.closest && e.target.closest('[data-chip]');
        if (!c || e.button !== 0 || state.tab !== 'map') return;
        const start = { x: e.clientX, y: e.clientY };
        const id = Number(c.dataset.chip);
        const move = (m) => {
            if (!drag) {
                if (Math.hypot(m.clientX - start.x, m.clientY - start.y) < 6) return;
                const v = state.active.v(), person = v.dwellerById.get(id);
                if (!person) return;
                const ghost = c.cloneNode(true);
                ghost.classList.add('fos-ghost');
                ghost.removeAttribute('data-chip');
                document.body.appendChild(ghost);
                c.classList.add('fos-dragging');
                drag = { id, chip: c, ghost, cancelled: false };
                for (const n of $$('[data-room]')) {
                    const r = v.roomById.get(Number(n.dataset.room));
                    n.classList.add(M.whyNotAssign(v, person, r) ? 'fos-no' : 'fos-ok');
                }
                const nr = $('[data-noroom]');
                if (nr) nr.classList.add('fos-ok');
            }
            drag.ghost.style.left = m.clientX + 'px';
            drag.ghost.style.top = m.clientY + 'px';
            $$('.fos-over').forEach((n) => n.classList.remove('fos-over'));
            const t = roomAt(m.clientX, m.clientY);
            if (t) t.classList.add('fos-over');
            // keep the map moving when the pointer nears its edge
            const sc = $('.fos-map-scroll');
            if (sc) {
                const b = sc.getBoundingClientRect(), edge = 40, step = 16;
                if (m.clientX < b.left + edge) sc.scrollLeft -= step; else if (m.clientX > b.right - edge) sc.scrollLeft += step;
                if (m.clientY < b.top + edge) sc.scrollTop -= step; else if (m.clientY > b.bottom - edge) sc.scrollTop += step;
            }
        };
        const up = (u) => {
            cleanup();
            if (drag) {
                // the click that follows a drag must not also open the drawer
                document.addEventListener('click', suppressClick, { capture: true, once: true });
                setTimeout(() => document.removeEventListener('click', suppressClick, { capture: true }), 50);
                endDrag(u.type === 'pointerup', u.clientX, u.clientY);
            }
        };
        const keys = (k) => { if (k.key === 'Escape' && drag) { k.stopPropagation(); cleanup(); endDrag(false); } };
        const cleanup = () => {
            document.removeEventListener('pointermove', move);
            document.removeEventListener('pointerup', up);
            document.removeEventListener('pointercancel', up);
            document.removeEventListener('keydown', keys, true);
        };
        document.addEventListener('pointermove', move);
        document.addEventListener('pointerup', up);
        document.addEventListener('pointercancel', up);
        document.addEventListener('keydown', keys, true);
    });

    /* ---------- Dwellers ---------- */
    const dw = { filter: 'all', rarity: '', sort: 'name', limit: 250 };
    const FILTERS = [['all', 'All', () => true], ['alive', 'Alive', (d) => !d.dead], ['dead', 'Dead', (d) => d.dead], ['assigned', 'Assigned', (d) => !!d.room], ['unassigned', 'Unassigned', (d) => !d.room && !d.dead && !d.inWasteland], ['wasteland', 'In the wasteland', (d) => d.inWasteland]];
    const SORTS = { name: ['Name', (a, b) => dName(a).localeCompare(dName(b))], level: ['Level', (a, b) => b.level - a.level], happiness: ['Happiness', (a, b) => b.happiness - a.happiness], rarity: ['Rarity', (a, b) => M.RARITIES.indexOf(b.rarity) - M.RARITIES.indexOf(a.rarity)] };
    M.SPECIAL.forEach((s, i) => { SORTS[s] = [s, (a, b) => (b.special[i] || 0) - (a.special[i] || 0)]; });
    FOS.views.dwellers = (el, f) => {
        const v = f.v(), q = state.query.toLowerCase();
        const base = v.dwellers.filter((d) => (!dw.rarity || d.rarity === dw.rarity) && (!q || (dName(d) + ' ' + statusOf(d)).toLowerCase().includes(q)));
        const pass = FILTERS.find((x) => x[0] === dw.filter)[2];
        const list = base.filter(pass).sort(SORTS[dw.sort][1]);
        put(el, html`
            <div class="fos-tools">
                <div class="stabs">${FILTERS.map(([k, l, fn]) => html`<button class="${dw.filter === k ? 'on' : ''}" data-dfilter="${k}">${l}<small>${base.filter(fn).length}</small></button>`)}</div>
                <select class="field" data-drarity aria-label="Rarity"><option value="">Any rarity</option>${M.RARITIES.map((r) => html`<option ${raw(dw.rarity === r ? 'selected' : '')}>${r}</option>`)}</select>
                <select class="field" data-dsort aria-label="Sort by">${Object.keys(SORTS).map((k) => html`<option value="${k}" ${raw(dw.sort === k ? 'selected' : '')}>Sort by ${SORTS[k][0].toLowerCase()}</option>`)}</select>
            </div>
            ${list.length ? '' : html`<p class="muted">No Dwellers match this.</p>`}
            <div class="fos-dgrid">${list.slice(0, dw.limit).map((d) => html`
                <button class="fos-dw" data-open-d="${d.id}">
                    <span class="fos-chip ${d.rarity === 'Legendary' ? 'fos-legend' : ''}">${initials(d)}</span>
                    <span class="fos-dw-text"><b>${dName(d)}</b><small>Level ${d.level} · ${d.rarity} · ${statusOf(d)}</small></span>
                </button>`)}</div>
            ${list.length > dw.limit ? html`<button class="btn small fos-more" data-dmore>Show ${num(Math.min(250, list.length - dw.limit))} more</button>` : ''}`);
    };

    /* ---------- Resources ---------- */
    FOS.views.resources = (el, f) => {
        const r = f.doc.root, vault = get(r, 'vault');
        const q = state.query.toLowerCase();
        const on = (label) => !q || label.toLowerCase().includes(q);
        fields.view.clear();
        const res = get(vault, 'storage', 'resources'), bonus = get(vault, 'storage', 'bonus');
        const resFields = (node, scope, prefix) => Object.keys(M.RESOURCES).filter((k) => get(node, k) && on(M.RESOURCES[k])).map((k) => field(scope, get(node, k), M.RESOURCES[k], { history: (prefix || '') + M.RESOURCES[k] }));
        const byType = items(get(vault, 'LunchBoxesByType')).map((e) => val(e.v));
        const stores = f.v().rooms.filter((x) => x.storage && on(x.name));
        put(el, html`<div class="fos-cards">
            <section class="card"><h2>Resources</h2><div class="form"><div class="fos-grid2">${resFields(res, 'view')}</div></div>
                ${bonus && !q ? html`<details class="fos-details"><summary>Bonus resources</summary><div class="form"><div class="fos-grid2">${resFields(bonus, 'view', 'Bonus ')}</div></div></details>` : ''}
            </section>
            ${get(vault, 'LunchBoxesByType') && on('lunchbox') ? html`<section class="card"><h2>Lunchboxes <span class="n">${num(byType.length)}</span></h2><div class="form"><div class="fos-grid2">
                ${M.LUNCHBOX_TYPES.filter(([t, , can]) => can || byType.includes(t)).map(([t, label, can]) => html`<label>${label} <input class="field" type="number" min="0" max="5000" inputmode="numeric" value="${byType.filter((x) => x === t).length}" data-lunch="${t}" ${raw(can ? '' : 'disabled')}>${can ? '' : html`<small>Bought with real money, so it can't be changed.</small>`}</label>`)}
            </div></div></section>` : ''}
            ${on('vault name mode theme') ? html`<section class="card"><h2>Vault</h2><div class="form">
                ${field('view', get(vault, 'VaultName'), 'Vault name', { hint: 'The game uses numbers from 000 to 999.' })}
                ${field('view', get(vault, 'VaultMode'), 'Mode', { choices: [['Normal', 'Normal'], ['Survival', 'Survival']] })}
                ${field('view', get(vault, 'VaultTheme'), 'Theme', { choices: M.THEMES })}
            </div></section>` : ''}
            ${stores.length ? html`<section class="card"><h2>Stored in Rooms <span class="n">${stores.length}</span></h2>
                ${stores.map((x) => html`<details class="fos-details" data-room-store="${x.id}"><summary>${x.name} <small>row ${x.row}, column ${x.col}${storeSummary(x.storage)}</small></summary><div class="form"></div></details>`)}
            </section>` : ''}
        </div>`);
    };
    function storeSummary(storage) {
        const parts = [];
        for (const k of Object.keys(M.RESOURCES)) { const n = Number(val(get(storage, 'resources', k))); if (n > 0) parts.push(`${M.RESOURCES[k]} ${num(Math.round(n))}`); }
        return parts.length ? ' · ' + parts.join(', ') : '';
    }
    // room stores build their fields when opened
    document.addEventListener('toggle', (e) => {
        const d = e.target;
        if (!d.dataset || d.dataset.roomStore === undefined || !d.open) return;
        const form = $('.form', d);
        if (form.children.length) return;
        const room = state.active.v().roomById.get(Number(d.dataset.roomStore));
        if (!room) return;
        const res = get(room.storage, 'resources');
        put(form, html`<div class="fos-grid2">${Object.keys(M.RESOURCES).filter((k) => get(res, k)).map((k) => field('view', get(res, k), M.RESOURCES[k], { history: `${room.name}: ${M.RESOURCES[k]}` }))}</div>`);
    }, true);

    /* ---------- Inventory ---------- */
    const inv = { type: 'all', limit: 250 };
    const ITYPES = [['all', 'All'], ['Weapon', 'Weapons'], ['Outfit', 'Outfits'], ['Pet', 'Pets'], ['Junk', 'Junk']];
    function stacks(doc) {
        const groups = new Map();
        for (const e of items(get(doc.root, 'vault', 'inventory', 'items'))) {
            const type = val(get(e.v, 'type')), id = val(get(e.v, 'id'));
            const key = type === 'Pet' ? e : type + '\u0000' + id; // pets differ item by item
            if (!groups.has(key)) groups.set(key, { type, id, entries: [] });
            groups.get(key).entries.push(e);
        }
        return [...groups.values()];
    }
    FOS.views.inventory = (el, f) => {
        const q = state.query.toLowerCase();
        const all = stacks(f.doc);
        const search = (s) => !q || String(s.id).toLowerCase().includes(q) || (s.type === 'Pet' && String(val(get(s.entries[0].v, 'extraData', 'uniqueName'))).toLowerCase().includes(q));
        const base = all.filter(search);
        const count = (t) => base.filter((s) => t === 'all' || s.type === t).reduce((a, s) => a + s.entries.length, 0);
        const list = base.filter((s) => inv.type === 'all' || s.type === inv.type).sort((a, b) => String(a.id).localeCompare(String(b.id)));
        put(el, html`
            <div class="fos-tools">
                <div class="stabs fos-grow">${ITYPES.map(([k, l]) => html`<button class="${inv.type === k ? 'on' : ''}" data-itype="${k}">${l}<small>${num(count(k))}</small></button>`)}</div>
                <button class="btn small" data-add-item>Add item</button>
            </div>
            ${list.length ? '' : html`<p class="muted">No items match this.</p>`}
            <div class="fos-igrid">${list.slice(0, inv.limit).map((s, i) => html`
                <button class="fos-it" data-stack="${all.indexOf(s)}"><b>${s.type === 'Pet' ? val(get(s.entries[0].v, 'extraData', 'uniqueName')) || s.id : s.id}</b>
                <small>${s.type}${s.entries.length > 1 ? ` · ${num(s.entries.length)} stored` : ''}${s.type === 'Pet' ? ' · ' + s.id : ''}</small></button>`)}</div>
            ${list.length > inv.limit ? html`<button class="btn small fos-more" data-imore>Show ${num(Math.min(250, list.length - inv.limit))} more</button>` : ''}
            ${datalists(f.doc)}`);
    };
    const datalists = (doc) => {
        const k = M.knownIds(doc);
        return html`${Object.keys(k).map((t) => html`<datalist id="fos-ids-${t}">${[...k[t]].sort().map((id) => html`<option value="${id}"></option>`)}</datalist>`)}`;
    };
    function showAddItem(preType) {
        const type = preType && preType !== 'all' ? preType : 'Weapon';
        showDialog(html`<h2>Add item</h2><div class="form">
            <label>Type <select class="field" data-add-type>${['Weapon', 'Outfit', 'Pet', 'Junk'].map((t) => html`<option ${raw(t === type ? 'selected' : '')}>${t}</option>`)}</select></label>
            <label>Item ID <input class="field" data-add-id list="fos-ids-${type}" autocomplete="off"><small>IDs this save already uses are suggested. An ID the game doesn't know could cause problems.</small></label>
            <div data-add-pet ${raw(type === 'Pet' ? '' : 'hidden')} class="form">
                <label>Pet name <input class="field" data-add-petname></label>
                <label>Bonus <input class="field" data-add-bonus placeholder="HealingBoost" autocomplete="off"></label>
                <label>Bonus value <input class="field" type="number" inputmode="numeric" value="1" data-add-bonusval></label>
            </div>
            <p class="err" data-add-err hidden></p>
        </div><div class="actions"><button class="btn primary" data-add-go>Add to Inventory</button><button class="btn" data-dlg-close>Close</button></div>`);
    }

    /* ---------- drawer ---------- */
    FOS.views.drawer = (el, f, what) => {
        fields.drawer.clear();
        if (what.type === 'dweller') return dwellerDrawer(el, f, what);
        if (what.type === 'room') return roomDrawer(el, f, what);
        if (what.type === 'stack') return stackDrawer(el, f, what);
        return false;
    };
    const flag = (n, k, label) => field('drawer', get(n, k), label);
    function dwellerDrawer(el, f, what) {
        const v = f.v();
        const d = v.dwellers.find((x) => x.n === what.node);
        if (!d) return false;
        const n = d.n, H = (k) => get(n, 'health', k), X = (k) => get(n, 'experience', k);
        const rel = get(n, 'relations');
        const who = (id) => { const p = v.dwellerById.get(val(id)); return p ? dName(p) : ''; };
        const ids = (label, node) => field('drawer', node, label, { hint: node && val(node) >= 0 ? who(node) : '' });
        const eq = (key, label, type) => {
            const slot = get(n, key);
            if (!slot) return '';
            const ex = get(slot, 'extraData');
            return html`${field('drawer', get(slot, 'id'), label, { list: 'fos-ids-' + type, history: `${dName(d)}: ${label}` })}
                ${ex ? html`<div class="fos-grid2">${field('drawer', get(ex, 'uniqueName'), 'Pet name')}${field('drawer', get(ex, 'bonus'), 'Bonus')}${field('drawer', get(ex, 'bonusValue'), 'Bonus value')}</div>` : ''}`;
        };
        const hist = (label) => ({ history: `${dName(d)}: ${label}` });
        put(el, html`
            <h2>${dName(d)}</h2>
            <p class="fos-sub">Level ${d.level} · ${statusOf(d)} · ID <span class="mono">${d.id}</span></p>
            <h3>Assignment</h3>
            <div class="actions"><span class="fos-grow">${statusOf(d)}</span><button class="btn small" data-move="${d.id}">Move to...</button></div>
            <h3>Identity</h3>
            <div class="form">
                <div class="cols">${field('drawer', get(n, 'name'), 'First name', hist('first name'))}${field('drawer', get(n, 'lastName'), 'Last name', hist('last name'))}</div>
                <div class="cols">
                    ${field('drawer', get(n, 'gender'), 'Gender', { choices: [[1, 'Female'], [2, 'Male']], ...hist('gender') })}
                    ${field('drawer', get(n, 'rarity'), 'Rarity', { choices: M.RARITIES.map((r) => [r, r]), ...hist('rarity') })}
                </div>
                ${field('drawer', get(n, 'hair'), 'Hair style', hist('hair style'))}
                <div class="fos-grid2">
                    ${field('drawer', get(n, 'skinColor'), 'Skin colour', { type: 'color', ...hist('skin colour') })}
                    ${field('drawer', get(n, 'hairColor'), 'Hair colour', { type: 'color', ...hist('hair colour') })}
                    ${field('drawer', get(n, 'outfitColor'), 'Outfit colour', { type: 'color', ...hist('outfit colour') })}
                </div>
            </div>
            <h3>Condition</h3>
            <div class="form"><div class="fos-grid2">
                ${field('drawer', get(n, 'happiness', 'happinessValue'), 'Happiness', hist('happiness'))}
                ${field('drawer', H('healthValue'), 'Health', hist('health'))}
                ${field('drawer', H('maxHealth'), 'Maximum health', hist('maximum health'))}
                ${field('drawer', H('radiationValue'), 'Radiation', hist('radiation'))}
                ${field('drawer', H('lastLevelUpdated'), 'Health level', hist('health level'))}
                ${field('drawer', get(n, 'deathSource'), 'Death source', hist('death source'))}
                ${field('drawer', get(n, 'deathTime'), 'Death time', hist('death time'))}
            </div>${field('drawer', H('permaDeath'), 'Permanently dead', hist('permanently dead'))}</div>
            <h3>Experience</h3>
            <div class="form"><div class="fos-grid2">
                ${field('drawer', X('currentLevel'), 'Level', hist('level'))}
                ${field('drawer', X('experienceValue'), 'Experience', hist('experience'))}
                ${field('drawer', X('storage'), 'Stored experience', hist('stored experience'))}
                ${field('drawer', X('accum'), 'Accumulated', hist('accumulated experience'))}
                ${field('drawer', X('wastelandExperience'), 'Wasteland experience', hist('wasteland experience'))}
                ${field('drawer', get(n, 'pendingExperienceReward'), 'Pending reward', hist('pending reward'))}
            </div>${field('drawer', X('needLvUp'), 'Needs a level up', hist('needs a level up'))}</div>
            <h3>SPECIAL</h3>
            <div class="fos-special"><span></span><span>Value</span><span>Bonus</span><span>Training</span>
                ${M.SPECIAL.map((s, i) => {
                    const st = get(n, 'stats', 'stats', i + 1);
                    if (!st) return '';
                    const cell = (k, l) => {
                        const node = get(st, k);
                        if (!node) return html`<span></span>`;
                        const id = 'f' + (++nextField);
                        fields.drawer.set(id, { node, label: `${dName(d)}: ${s} ${l}`, opts: {} });
                        return html`<input class="field" type="number" step="any" inputmode="decimal" value="${node.raw}" aria-label="${s} ${l}" data-field="${id}" data-scope="drawer">`;
                    };
                    return html`<span>${s}</span>${cell('value', 'value')}${cell('mod', 'bonus')}${cell('exp', 'training')}`;
                })}
            </div>
            <h3>Equipment</h3>
            <div class="form">${eq('equipedOutfit', 'Outfit', 'Outfit')}${eq('equipedWeapon', 'Weapon', 'Weapon')}${eq('equippedPet', 'Pet', 'Pet')}</div>
            <h3>Relationships</h3>
            <div class="form">
                <div class="fos-grid2">${ids('Partner ID', get(rel, 'partner'))}${ids('Last partner ID', get(rel, 'lastPartner'))}${ids('Last child ID', get(n, 'lastChildBorn'))}</div>
                <p class="muted fos-small">Use -1 for nobody. Parents and grandparents:</p>
                <div class="fos-grid2">${items(get(rel, 'ascendants')).map((e, i) => ids(`Ancestor ${i + 1}`, e.v))}</div>
            </div>
            <h3>Status</h3>
            <div class="form">
                ${flag(n, 'pregnant', 'Pregnant')}${flag(n, 'babyReady', 'Baby ready')}${flag(n, 'assigned', 'Assigned flag')}${flag(n, 'sawIncident', 'Saw an incident')}
                ${flag(n, 'WillGoToWasteland', 'Will go to the wasteland')}${flag(n, 'WillBeEvicted', 'Will be evicted')}${flag(n, 'IsEvictedWaitingForFollowers', 'Evicted, waiting for followers')}${flag(n, 'wasTemporarilyAssigned', 'Was temporarily assigned')}
            </div>
            ${datalists(f.doc)}`);
        return true;
    }
    function roomDrawer(el, f, what) {
        const v = f.v();
        const r = v.rooms.find((x) => x.n === what.node);
        if (!r) return false;
        const people = r.dwellerIds.map((id) => v.dwellerById.get(id)).filter(Boolean);
        const cap = M.capacity(r);
        const can = v.dwellers.filter((d) => !M.whyNotAssign(v, d, r)).sort((a, b) => dName(a).localeCompare(dName(b)));
        const n = r.n;
        put(el, html`
            <h2>${r.name}</h2>
            <p class="fos-sub">Level ${r.level} · Merge Level ${r.merge} · Row ${r.row}, column ${r.col} · ID <span class="mono">${r.id}</span></p>
            ${roomBadges(r).length ? html`<div class="badges">${roomBadges(r).map(([k, t]) => html`<span class="badge ${k}">${t}</span>`)}</div>` : ''}
            ${cap ? html`<h3>Dwellers <span class="muted fos-small">${people.length} of ${cap}</span></h3>
            <div class="fos-rowlist rows">${people.map((d) => html`<div class="rowi"><button class="fos-linkish grow" data-open-d="${d.id}">${dName(d)}</button><small>Level ${d.level}</small><button class="btn small" data-move="${d.id}">Move to...</button></div>`)}</div>
            ${people.length < cap && can.length ? html`<div class="form fos-more"><label>Assign a Dweller <select class="field" data-assign="${r.id}"><option value="">Choose...</option>${can.map((d) => html`<option value="${d.id}">${dName(d)} (level ${d.level}, ${statusOf(d)})</option>`)}</select></label></div>` : ''}` : ''}
            ${r.deadIds.length ? html`<h3>Dead here</h3><div class="fos-rowlist rows">${r.deadIds.map((id) => { const d = v.dwellerById.get(id); return d ? html`<div class="rowi"><button class="fos-linkish grow" data-open-d="${d.id}">${dName(d)}</button></div>` : ''; })}</div>` : ''}
            ${r.storage ? html`<h3>Stored output</h3><div class="form"><div class="fos-grid2">${Object.keys(M.RESOURCES).filter((k) => Number(val(get(r.storage, 'resources', k))) !== 0).map((k) => field('drawer', get(r.storage, 'resources', k), M.RESOURCES[k], { history: `${r.name}: ${M.RESOURCES[k]}` }))}</div></div>` : ''}
            <h3>Details</h3>
            <div class="form">
                <div class="kv"><span>State</span><span>${r.state}</span><span>Type</span><span class="mono">${r.type}</span><span>Class</span><span>${r.cls}</span></div>
                ${flag(n, 'power', 'Has power')}${flag(n, 'broken', 'Damaged')}${flag(n, 'emergencyDone', 'Incident finished')}
            </div>`);
        return true;
    }
    function stackDrawer(el, f, what) {
        const stack = stacks(f.doc).find((s) => (what.entry ? s.entries.includes(what.entry) : s.type === what.itype && s.id === what.id));
        if (!stack) return false;
        const first = stack.entries[0], isPet = stack.type === 'Pet', ex = get(first.v, 'extraData');
        put(el, html`
            <h2>${isPet ? val(get(ex, 'uniqueName')) || stack.id : stack.id}</h2>
            <p class="fos-sub">${stack.type}${isPet ? '' : html` · <span>${num(stack.entries.length)} stored</span>`}</p>
            <div class="form">
                ${isPet ? html`
                    ${field('drawer', get(first.v, 'id'), 'Pet ID', { list: 'fos-ids-Pet', history: 'Pet ID' })}
                    ${field('drawer', get(ex, 'uniqueName'), 'Pet name')}${field('drawer', get(ex, 'bonus'), 'Bonus')}${field('drawer', get(ex, 'bonusValue'), 'Bonus value')}`
                : html`<label>Change every one to <input class="field" data-retype list="fos-ids-${stack.type}" value="${stack.id}" autocomplete="off"><small>Item IDs the game doesn't know could cause problems.</small></label>`}
            </div>
            <div class="actions fos-more">
                ${isPet ? '' : html`<button class="btn small" data-stack-add>Add one</button>`}
                <button class="btn small" data-stack-del>${isPet ? 'Remove this pet' : 'Remove one'}</button>
            </div>
            ${datalists(f.doc)}`);
        return true;
    }

    /* ---------- moving with the keyboard: the same action as a drag ---------- */
    function showMove(d) {
        const v = state.active.v();
        const rows = v.rooms.filter((r) => M.capacity(r)).sort((a, b) => a.row - b.row || a.col - b.col);
        showDialog(html`<h2>Move ${dName(d)}</h2><p class="muted fos-small">Now: ${statusOf(d)}</p>
            ${M.whyNotAssign(v, d, null) ? html`<p class="err">${M.whyNotAssign(v, d, null)}</p><div class="actions"><button class="btn" data-dlg-close>Close</button></div>` : html`
            <div class="form"><label>Move to <select class="field" data-move-to>
                ${d.room ? html`<option value="none">No room</option>` : ''}
                ${rows.map((r) => { const why = M.whyNotAssign(v, d, r); return html`<option value="${r.id}" ${raw(why ? 'disabled' : '')}>${r.name}, row ${r.row}, ${r.dwellerIds.length} of ${M.capacity(r)}${why ? (d.room === r ? ' (here now)' : ' (full)') : ''}</option>`; })}
            </select></label></div>
            <div class="actions"><button class="btn primary" data-move-go="${d.id}">Move Dweller</button><button class="btn" data-dlg-close>Close</button></div>`}`);
    }

    /* ---------- one listener for everything these views render ---------- */
    document.addEventListener('click', (e) => {
        const t = e.target;
        if (!t.closest || !t.closest('#main')) return;
        const f = state.active;
        if (!f) return;
        const hit = (sel) => t.closest(sel);
        let b;
        if ((b = hit('[data-zoom]'))) {
            const z = store.get('fos-zoom', 1);
            const i = Math.max(0, Math.min(ZOOMS.length - 1, ZOOMS.indexOf(ZOOMS.includes(z) ? z : 1) + Number(b.dataset.zoom)));
            store.set('fos-zoom', ZOOMS[i]);
            FOS.views.map($('#fosView'), f);
            return;
        }
        if ((b = hit('[data-open-d]'))) {
            const d = f.v().dwellerById.get(Number(b.dataset.openD));
            if (d) openDrawer({ type: 'dweller', node: d.n });
            return;
        }
        if ((b = hit('[data-open-r]'))) {
            const r = f.v().roomById.get(Number(b.dataset.openR));
            if (r) openDrawer({ type: 'room', node: r.n });
            return;
        }
        if ((b = hit('[data-move]'))) {
            const d = f.v().dwellerById.get(Number(b.dataset.move));
            if (d) showMove(d);
            return;
        }
        if ((b = hit('[data-move-go]'))) {
            const v = f.v(), d = v.dwellerById.get(Number(b.dataset.moveGo));
            const to = $('[data-move-to]').value;
            closeDialog();
            moveDweller(d, to === 'none' ? null : v.roomById.get(Number(to)));
            return;
        }
        if ((b = hit('[data-dfilter]'))) { dw.filter = b.dataset.dfilter; dw.limit = 250; FOS.views.dwellers($('#fosView'), f); return; }
        if (hit('[data-dmore]')) { dw.limit += 250; FOS.views.dwellers($('#fosView'), f); return; }
        if ((b = hit('[data-itype]'))) { inv.type = b.dataset.itype; inv.limit = 250; FOS.views.inventory($('#fosView'), f); return; }
        if (hit('[data-imore]')) { inv.limit += 250; FOS.views.inventory($('#fosView'), f); return; }
        if ((b = hit('[data-stack]'))) {
            const s = stacks(f.doc)[Number(b.dataset.stack)];
            if (s) openDrawer(s.type === 'Pet' ? { type: 'stack', entry: s.entries[0] } : { type: 'stack', itype: s.type, id: s.id });
            return;
        }
        if (hit('[data-add-item]')) { showAddItem(inv.type); return; }
        if (hit('[data-add-go]')) { addItemFromDialog(f); return; }
        if (hit('[data-stack-add]')) {
            const w = state.drawer;
            act(`Add ${w.id}`, M.addItemOps(f.doc, w.itype, w.id));
            return;
        }
        if (hit('[data-stack-del]')) {
            const w = state.drawer;
            const s = stacks(f.doc).find((x) => (w.entry ? x.entries.includes(w.entry) : x.type === w.itype && x.id === w.id));
            if (s) act(`Remove ${s.id}`, [{ op: 'del', entry: s.entries[s.entries.length - 1] }]);
        }
    });
    function addItemFromDialog(f) {
        const type = $('[data-add-type]').value, id = $('[data-add-id]').value.trim();
        const errEl = $('[data-add-err]');
        const fail = (m) => { errEl.hidden = false; errEl.textContent = m; };
        if (!id) return fail('Enter an item ID.');
        let extra;
        if (type === 'Pet') {
            const bonusValue = Number($('[data-add-bonusval]').value);
            const bonus = $('[data-add-bonus]').value.trim();
            if (!bonus) return fail('Enter a bonus. Pets need one.');
            if (!Number.isInteger(bonusValue)) return fail('The bonus value must be a whole number.');
            extra = { uniqueName: $('[data-add-petname]').value.trim() || id, bonus, bonusValue };
        }
        if (act(`Add ${id}`, M.addItemOps(f.doc, type, id, extra))) closeDialog();
    }
    document.addEventListener('change', (e) => {
        const t = e.target;
        if (!t.closest || !t.closest('#main') || !state.active) return;
        const f = state.active;
        if (t.matches('[data-drarity]')) { dw.rarity = t.value; dw.limit = 250; FOS.views.dwellers($('#fosView'), f); }
        else if (t.matches('[data-dsort]')) { dw.sort = t.value; FOS.views.dwellers($('#fosView'), f); }
        else if (t.matches('[data-add-type]')) { $('[data-add-pet]').hidden = t.value !== 'Pet'; $('[data-add-id]').setAttribute('list', 'fos-ids-' + t.value); }
        else if (t.matches('[data-assign]') && t.value) {
            const v = f.v();
            moveDweller(v.dwellerById.get(Number(t.value)), v.roomById.get(Number(t.dataset.assign)));
        } else if (t.matches('[data-lunch]')) {
            const type = Number(t.dataset.lunch), n = Number(t.value);
            if (!Number.isInteger(n) || n < 0 || n > 5000) { t.setAttribute('aria-invalid', 'true'); return; }
            t.removeAttribute('aria-invalid');
            const label = (M.LUNCHBOX_TYPES.find((x) => x[0] === type) || [])[1];
            const allowed = (M.LUNCHBOX_TYPES.find((x) => x[0] === type) || [])[2];
            if (allowed) act(`${label} count`, M.lunchboxOps(f.doc, type, n));
        } else if (t.matches('[data-retype]')) {
            const w = state.drawer, id = t.value.trim();
            if (!id || !w || id === w.id) return;
            const s = stacks(f.doc).find((x) => x.type === w.itype && x.id === w.id);
            if (!s) return;
            const ops = s.entries.map((en) => ({ op: 'set', node: get(en.v, 'id'), raw: stringRaw(id) }));
            w.id = id;
            act(`Change ${s.id} to ${id}`, ops);
        }
    });
})();
