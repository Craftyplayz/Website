// Enchanting Guide: recommended enchant sets and the cheapest Java anvil order.
(function () {
    'use strict';

    // id: [name, max level, book multiplier] (Java; every sacrifice here is a book)
    const ENCH = {
        protection: ['Protection', 4, 1],
        fire_protection: ['Fire Protection', 4, 1],
        blast_protection: ['Blast Protection', 4, 2],
        projectile_protection: ['Projectile Protection', 4, 1],
        feather_falling: ['Feather Falling', 4, 1],
        thorns: ['Thorns', 3, 4],
        respiration: ['Respiration', 3, 2],
        aqua_affinity: ['Aqua Affinity', 1, 2],
        depth_strider: ['Depth Strider', 3, 2],
        frost_walker: ['Frost Walker', 2, 2],
        soul_speed: ['Soul Speed', 3, 4],
        swift_sneak: ['Swift Sneak', 3, 4],
        sharpness: ['Sharpness', 5, 1],
        knockback: ['Knockback', 2, 1],
        fire_aspect: ['Fire Aspect', 2, 2],
        looting: ['Looting', 3, 2],
        sweeping_edge: ['Sweeping Edge', 3, 2],
        efficiency: ['Efficiency', 5, 1],
        silk_touch: ['Silk Touch', 1, 4],
        fortune: ['Fortune', 3, 2],
        unbreaking: ['Unbreaking', 3, 1],
        mending: ['Mending', 1, 2],
        power: ['Power', 5, 1],
        punch: ['Punch', 2, 2],
        flame: ['Flame', 1, 2],
        infinity: ['Infinity', 1, 4],
        luck_of_the_sea: ['Luck of the Sea', 3, 2],
        lure: ['Lure', 3, 2],
        loyalty: ['Loyalty', 3, 1],
        impaling: ['Impaling', 5, 2],
        riptide: ['Riptide', 3, 2],
        channeling: ['Channeling', 1, 4],
        multishot: ['Multishot', 1, 2],
        quick_charge: ['Quick Charge', 3, 1],
        piercing: ['Piercing', 4, 1],
        density: ['Density', 5, 1],
        breach: ['Breach', 4, 2],
        wind_burst: ['Wind Burst', 3, 2],
    };

    // Pairs that can't sit on one item (only the ones these items can meet).
    const CONFLICTS = [
        ['depth_strider', 'frost_walker'], ['fortune', 'silk_touch'], ['infinity', 'mending'],
        ['multishot', 'piercing'], ['riptide', 'loyalty'], ['riptide', 'channeling'],
        ['density', 'breach'],
        ['protection', 'fire_protection'], ['protection', 'blast_protection'], ['protection', 'projectile_protection'],
    ];

    // base: always offered, ticked unless listed in `off`; choices: either/or groups, first option picked.
    const HELMET = { base: ['protection', 'aqua_affinity', 'respiration', 'unbreaking', 'mending', 'thorns'] };
    const BASIC = { base: ['unbreaking', 'mending'] };
    const TOOL = { base: ['efficiency', 'unbreaking', 'mending'], choices: [[['fortune'], ['silk_touch']]] };
    const ITEMS = [
        ['helmet', 'Helmet', 'netherite_helmet', HELMET],
        ['chestplate', 'Chestplate', 'netherite_chestplate', { base: ['protection', 'unbreaking', 'mending', 'thorns'] }],
        ['leggings', 'Leggings', 'netherite_leggings', { base: ['protection', 'swift_sneak', 'unbreaking', 'mending', 'thorns'] }],
        ['boots', 'Boots', 'netherite_boots', { base: ['protection', 'feather_falling', 'soul_speed', 'unbreaking', 'mending', 'thorns'], choices: [[['depth_strider'], ['frost_walker']]] }],
        ['turtle_shell', 'Turtle shell', 'turtle_helmet', HELMET],
        ['elytra', 'Elytra', 'elytra', BASIC],
        ['shield', 'Shield', 'shield', BASIC],
        ['sword', 'Sword', 'netherite_sword', { base: ['sharpness', 'looting', 'sweeping_edge', 'unbreaking', 'mending', 'fire_aspect', 'knockback'], off: ['knockback'] }],
        ['axe', 'Axe', 'netherite_axe', TOOL],
        ['pickaxe', 'Pickaxe', 'netherite_pickaxe', TOOL],
        ['shovel', 'Shovel', 'netherite_shovel', TOOL],
        ['hoe', 'Hoe', 'netherite_hoe', TOOL],
        ['bow', 'Bow', 'bow', { base: ['power', 'punch', 'flame', 'unbreaking'], choices: [[['infinity'], ['mending']]] }],
        ['crossbow', 'Crossbow', 'crossbow', { base: ['quick_charge', 'unbreaking', 'mending'], choices: [[['multishot'], ['piercing']]] }],
        ['trident', 'Trident', 'trident', { base: ['impaling', 'unbreaking', 'mending'], choices: [[['loyalty', 'channeling'], ['riptide']]] }],
        ['mace', 'Mace', 'mace', { base: ['wind_burst', 'unbreaking', 'mending'], choices: [[['density'], ['breach']]] }],
        ['fishing_rod', 'Fishing rod', 'fishing_rod', { base: ['luck_of_the_sea', 'lure', 'unbreaking', 'mending'] }],
        ['shears', 'Shears', 'shears', { base: ['efficiency', 'unbreaking', 'mending'] }],
        ['flint_and_steel', 'Flint and steel', 'flint_and_steel', BASIC],
        ['brush', 'Brush', 'brush', BASIC],
        ['carrot_on_a_stick', 'Carrot on a stick', 'carrot_on_a_stick', BASIC],
        ['warped_fungus_on_a_stick', 'Warped fungus on a stick', 'warped_fungus_on_a_stick', BASIC],
    ].map(([id, name, icon, s]) => ({ id, name, icon, base: s.base, off: s.off || [], choices: s.choices || [] }));

    const ROMAN = ['', 'I', 'II', 'III', 'IV', 'V'];
    const label = (id) => ENCH[id][0] + (ENCH[id][1] > 1 ? ' ' + ROMAN[ENCH[id][1]] : '');
    const conflicts = (ids) => CONFLICTS.filter(([a, b]) => ids.includes(a) && ids.includes(b));

    // XP points to go from level 0 to `l`.
    function xpFor(l) {
        if (l <= 16) return l * l + 6 * l;
        if (l <= 31) return 2.5 * l * l - 40.5 * l + 360;
        return 4.5 * l * l - 162.5 * l + 2220;
    }

    // Cheapest merge tree for a bare item plus one max-level book per enchant.
    // DP over (leaf subset, work count). Score, in order: steps that are too expensive (>= 40),
    // total levels, most expensive step, XP points.
    function solve(ids) {
        const n = ids.length;
        if (!n) return { steps: [], total: 0, max: 0, xp: 0, tooExpensive: 0 };
        const N = n + 1, FULL = (1 << N) - 1, ITEM = 1; // bit 0 = item, bit i+1 = book ids[i]
        const val = new Array(1 << N).fill(0); // enchant cost of a book holding these enchants
        for (let s = 1; s <= FULL; s++) {
            const low = s & -s, i = 31 - Math.clz32(low);
            val[s] = val[s ^ low] + (i ? ENCH[ids[i - 1]][1] * ENCH[ids[i - 1]][2] : 0);
        }
        const better = (a, b) => a.bad - b.bad || a.total - b.total || a.max - b.max || a.xp - b.xp;
        const best = Array.from({ length: 1 << N }, () => []); // best[s][w]
        for (let i = 0; i < N; i++) best[1 << i][0] = { bad: 0, total: 0, max: 0, xp: 0 };
        const order = [];
        for (let s = 1; s <= FULL; s++) if (s & (s - 1)) order.push(s);
        order.sort((a, b) => popcount(a) - popcount(b));
        for (const s of order) {
            // target t, sacrifice s^t; the item is always the target, never a sacrifice
            for (let t = (s - 1) & s; t; t = (t - 1) & s) {
                const k = s ^ t;
                if (k & ITEM) continue;
                const bt = best[t], bk = best[k];
                for (let wt = 0; wt < bt.length; wt++) {
                    const A = bt[wt];
                    if (!A) continue;
                    for (let wk = 0; wk < bk.length; wk++) {
                        const B = bk[wk];
                        if (!B) continue;
                        const cost = val[k] + (2 ** wt - 1) + (2 ** wk - 1);
                        const w = Math.max(wt, wk) + 1;
                        const c = {
                            bad: A.bad + B.bad + (cost >= 40 ? 1 : 0),
                            total: A.total + B.total + cost,
                            max: Math.max(A.max, B.max, cost),
                            xp: A.xp + B.xp + xpFor(cost),
                            t, wt, k, wk, cost,
                        };
                        const cur = best[s][w];
                        if (!cur || better(c, cur) < 0) best[s][w] = c;
                    }
                }
            }
        }
        let top = null, tw = 0;
        best[FULL].forEach((c, w) => { if (c && (!top || better(c, top) < 0)) { top = c; tw = w; } });

        // Walk the tree into steps, children first; combined books get numbers as they're made.
        const steps = [];
        let bookNo = 0;
        function walk(s, w) {
            if (!(s & (s - 1))) {
                const i = 31 - Math.clz32(s);
                return i ? { kind: 'book', ench: [ids[i - 1]] } : { kind: 'item', ench: [] };
            }
            const c = best[s][w];
            const left = walk(c.t, c.wt), right = walk(c.k, c.wk);
            const out = { kind: left.kind === 'item' ? 'item' : 'combined', ench: left.ench.concat(right.ench), work: w };
            if (out.kind === 'combined') out.no = ++bookNo;
            steps.push({ left, right, out, cost: c.cost, tooExpensive: c.cost >= 40 });
            return out;
        }
        walk(FULL, tw);
        return { steps, total: top.total, max: top.max, xp: top.xp, tooExpensive: top.bad };
    }

    function popcount(x) { let c = 0; for (; x; x &= x - 1) c++; return c; }

    // The enchant list for an item given picks (one index per choice group, -1 = none) and unticked ids.
    function chosen(item, picks, off) {
        const ids = item.base.filter((id) => !off.includes(id));
        item.choices.forEach((opts, g) => {
            const p = picks[g] == null ? 0 : picks[g];
            if (opts[p]) ids.push(...opts[p]);
        });
        return ids;
    }

    const api = { ENCH, CONFLICTS, ITEMS, solve, chosen, conflicts, xpFor, label };
    if (typeof module !== 'undefined') module.exports = api;
    if (typeof document === 'undefined') return;

    // ---- page ----
    const $ = (s) => document.querySelector(s);
    const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);
    const icon = (name, cls) => `<img class="${cls}" src="/minecraft/tools/item-list/minecraft_${name}.png" alt="" width="24" height="24" loading="lazy" onerror="this.hidden=true">`;
    const state = { item: ITEMS[0], picks: [], off: [] };

    function readUrl() {
        const q = new URLSearchParams(location.search);
        state.item = ITEMS.find((i) => i.id === q.get('item')) || ITEMS[0];
        state.picks = (q.get('pick') || '').split('.').filter(Boolean).map(Number);
        state.off = q.has('off') ? q.get('off').split('.').filter((id) => ENCH[id]) : state.item.off.slice();
    }
    function writeUrl() {
        const q = new URLSearchParams({ item: state.item.id });
        if (state.picks.some((p) => p)) q.set('pick', state.picks.join('.'));
        if (state.off.join() !== state.item.off.join()) q.set('off', state.off.join('.'));
        history.replaceState(null, '', '?' + q);
    }

    function render() {
        const it = state.item;
        document.querySelectorAll('#ench-items button').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.item === it.id)));
        $('#ench-item-name').textContent = it.name;

        let html = '';
        it.choices.forEach((opts, g) => {
            const p = state.picks[g] == null ? 0 : state.picks[g];
            html += `<div class="stabs ench-choice" role="group" aria-label="Pick one">` +
                opts.map((o, i) => `<button type="button" data-group="${g}" data-pick="${i}" class="${p === i ? 'on' : ''}" aria-pressed="${p === i}">${esc(o.map(label).join(' + '))}</button>`).join('') +
                `<button type="button" data-group="${g}" data-pick="-1" class="${p === -1 ? 'on' : ''}" aria-pressed="${p === -1}">Neither</button></div>`;
        });
        html += '<div class="ench-checks">' + it.base.map((id) =>
            `<label class="check"><input type="checkbox" data-id="${id}" ${state.off.includes(id) ? '' : 'checked'}> ${esc(label(id))}</label>`).join('') + '</div>';
        $('#ench-set').innerHTML = html;

        const ids = chosen(it, state.picks, state.off);
        const r = solve(ids);
        $('#ench-total').textContent = r.total;
        $('#ench-total').title = `${r.xp.toLocaleString()} XP points if you start each step from level 0`;
        $('#ench-steps-n').textContent = r.steps.length;
        $('#ench-max').textContent = r.max;
        $('#ench-xp').textContent = Math.round(r.xp).toLocaleString();
        $('#ench-max-card').classList.toggle('ench-bad', r.tooExpensive > 0);

        const side = (x) => x.kind === 'item'
            ? `<span class="ench-part">${icon(it.icon, 'ench-ic')}<span><b>${esc(it.name)}</b>${x.ench.length ? `<small>${esc(x.ench.map(label).join(', '))}</small>` : ''}</span></span>`
            : x.kind === 'book'
                ? `<span class="ench-part">${icon('enchanted_book', 'ench-ic')}<span><b>${esc(label(x.ench[0]))}</b></span></span>`
                : `<span class="ench-part">${icon('enchanted_book', 'ench-ic')}<span><b>Book ${x.no}</b><small>${esc(x.ench.map(label).join(', '))}</small></span></span>`;
        $('#ench-order').innerHTML = r.steps.length ? r.steps.map((s, i) =>
            `<li class="ench-step${s.tooExpensive ? ' ench-bad' : ''}"><span class="ench-n">${i + 1}</span>` +
            `<div class="ench-combo">${side(s.left)}<span class="ench-op" aria-hidden="true">+</span>${side(s.right)}` +
            `<span class="ench-op" aria-hidden="true">&rarr;</span>${s.out.kind === 'item' ? `<b class="ench-out">${esc(it.name)}</b>` : `<b class="ench-out">Book ${s.out.no}</b>`}</div>` +
            `<span class="ench-cost"><b>${s.cost}</b> levels${s.tooExpensive ? '<span class="badge r">Too expensive</span>' : ''}</span></li>`).join('')
            : '<li class="muted">Tick at least one enchant.</li>';
        $('#ench-warn').hidden = !r.tooExpensive;
        writeUrl();
    }

    document.addEventListener('DOMContentLoaded', () => {
        readUrl();
        $('#ench-items').addEventListener('click', (e) => {
            const b = e.target.closest('button[data-item]');
            if (!b) return;
            state.item = ITEMS.find((i) => i.id === b.dataset.item);
            state.picks = [];
            state.off = state.item.off.slice();
            render();
        });
        $('#ench-set').addEventListener('click', (e) => {
            const b = e.target.closest('button[data-group]');
            if (!b) return;
            state.picks[+b.dataset.group] = +b.dataset.pick;
            for (let g = 0; g < state.item.choices.length; g++) if (state.picks[g] == null) state.picks[g] = 0;
            render();
        });
        $('#ench-set').addEventListener('change', (e) => {
            const id = e.target.dataset.id;
            state.off = e.target.checked ? state.off.filter((x) => x !== id) : state.off.concat(id);
            render();
        });
        render();
    });
})();
