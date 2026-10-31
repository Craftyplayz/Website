// Monster Sheet (/games/dnd/sheets/monster/): an encounter of editable monster stat blocks, saved in this browser.
// The search list is monsters.json (built by _dev/scripts/build-monsters.js); monsters.php fetches one stat block.
// Clicking a bonus or a dice expression rolls it with the dice chosen on Dice Sets, like the player sheet.
document.addEventListener('DOMContentLoaded', () => {
    const { $, esc, toast, debounce } = UI;
    const KEY = 'monster-sheet';
    const root = $('#mon'), main = $('#main');
    const ABIL = ['str', 'dex', 'con', 'int', 'wis', 'cha'], FULL = ['strength', 'dexterity', 'constitution', 'intelligence', 'wisdom', 'charisma'];
    const LISTS = [['traits', 'Traits', 'trait'], ['actions', 'Actions', 'action'], ['bonus', 'Bonus actions', 'bonus action'], ['reactions', 'Reactions', 'reaction'], ['legendary', 'Legendary actions', 'legendary action']];
    const PROPS = [['saves', 'Saving throws'], ['skills', 'Skills'], ['vuln', 'Damage vulnerabilities'], ['resist', 'Damage resistances'], ['immune', 'Damage immunities'],
        ['cond', 'Condition immunities'], ['senses', 'Senses'], ['langs', 'Languages'], ['hd', 'Hit dice']];
    const CR_XP = { 0: 10, 0.125: 25, 0.25: 50, 0.5: 100, 1: 200, 2: 450, 3: 700, 4: 1100, 5: 1800, 6: 2300, 7: 2900, 8: 3900, 9: 5000, 10: 5900, 11: 7200, 12: 8400, 13: 10000, 14: 11500, 15: 13000,
        16: 15000, 17: 18000, 18: 20000, 19: 22000, 20: 25000, 21: 33000, 22: 41000, 23: 50000, 24: 62000, 25: 75000, 26: 90000, 27: 105000, 28: 120000, 29: 135000, 30: 155000 };

    // ---- data: { cur: id, list: [monster] }; every field is text so anything can be typed ----
    let db;
    try { db = JSON.parse(localStorage.getItem(KEY)); } catch (e) { db = null; }
    if (!db || !Array.isArray(db.list)) db = { cur: '', list: [] };
    let warned = false;
    const saveNow = () => {
        try { localStorage.setItem(KEY, JSON.stringify(db)); warned = false; }
        catch (e) { if (!warned) toast("Couldn't save: this browser's storage is full or blocked. Export the encounter so you don't lose it.", 'err'); warned = true; }
    };
    const save = debounce(saveNow, 300);
    const uid = () => Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
    const blank = () => ({ id: uid(), name: 'New monster', meta: '', ac: '10', hp: '10', hpMax: '10', speed: '30 ft.', str: '10', dex: '10', con: '10', int: '10', wis: '10', cha: '10',
        saves: '', skills: '', vuln: '', resist: '', immune: '', cond: '', senses: '', langs: '', hd: '', cr: '0', xp: '10', legDesc: '', src: '',
        traits: [], actions: [], bonus: [], reactions: [], legendary: [] });
    // a monster from a file: known fields only, all as text
    const normalise = (x) => {
        const o = blank();
        for (const k of Object.keys(o)) {
            if (Array.isArray(o[k])) o[k] = (Array.isArray(x[k]) ? x[k] : []).map((e) => ({ name: String(e?.name ?? ''), desc: String(e?.desc ?? '') }));
            else if (k !== 'id' && x[k] != null) o[k] = String(x[k]);
        }
        return o;
    };
    db.list = db.list.map((m) => ({ ...normalise(m), id: m.id || uid() }));
    const cur = () => db.list.find((m) => m.id === db.cur) || db.list[0];

    // "Goblin" twice becomes "Goblin 1" and "Goblin 2"
    function numbered(name) {
        const base = name.replace(/\s+\d+$/, '');
        if (!db.list.some((x) => x.name.replace(/\s+\d+$/, '') === base)) return name;
        db.list.forEach((x) => { if (x.name === base) x.name = base + ' 1'; });
        let n = 2;
        while (db.list.some((x) => x.name === `${base} ${n}`)) n++;
        return `${base} ${n}`;
    }
    function add(m, after) {
        m.name = numbered(m.name);
        const i = after ? db.list.indexOf(after) + 1 : db.list.length;
        db.list.splice(i, 0, m);
        db.cur = m.id;
        saveNow(); draw();
    }

    // ---- numbers in text: "+4", "−1", "Dex +4, Wis +2" ----
    const num = (s) => +String(s).replace(/−/g, '-').replace(/\s+/g, '');
    const sign = (n) => (n < 0 ? '−' : '+') + Math.abs(n);
    const mod = (m, k) => { const v = parseInt(String(m[k]), 10); return Number.isFinite(v) ? Math.floor((v - 10) / 2) : 0; };
    const bonuses = (text) => [...String(text).matchAll(/([A-Za-z][A-Za-z' ]*?)\s*([+\-−]\s*\d+)/g)].map(([, n, b]) => [n.trim(), num(b)]);
    const saveOf = (m, i) => { const b = bonuses(m.saves).find(([n]) => n.slice(0, 3).toLowerCase() === ABIL[i]); return b ? b[1] : mod(m, ABIL[i]); };
    // roll buttons for an action's text: "+4 to hit" and each dice expression, with its damage type when one follows
    function rollsIn(text) {
        const out = [], seen = new Set();
        const hit = String(text).match(/([+\-−]\s?\d+)\s+to hit/i);
        if (hit) out.push({ label: sign(num(hit[1])) + ' to hit', g: [[20, 1]], m: num(hit[1]), what: 'to hit' });
        for (const [, c, s, op, k, type] of String(text).matchAll(/\b(\d+)d(\d+)(?:\s*([+\-−])\s*(\d+))?\)?(?:\s+([a-z]+)\s+damage)?/gi)) {
            const md = k ? (op === '+' ? +k : -k) : 0, expr = `${c}d${s}${md ? (md < 0 ? '−' : '+') + Math.abs(md) : ''}`, label = expr + (type ? ' ' + type.toLowerCase() : '');
            if (seen.has(label) || +c > 100 || ![4, 6, 8, 10, 12, 20, 100].includes(+s)) continue;
            seen.add(label);
            out.push({ label, g: [[+s, +c]], m: md, what: type ? type.toLowerCase() + ' damage' : expr });
        }
        return out.slice(0, 8);
    }

    // ---- a stat block from monsters.php: dnd5eapi.co (src "srd") or Open5e v1 ----
    const cap = (s) => String(s).charAt(0).toUpperCase() + String(s).slice(1);
    const crText = (n) => ({ 0.125: '1/8', 0.25: '1/4', 0.5: '1/2' })[n] || String(n);
    const usage = (u) => (!u ? '' : u.type === 'recharge on roll' ? ` (Recharge ${u.min_value}${u.min_value < 6 ? '–6' : ''})` : u.type === 'per day' ? ` (${u.times}/Day)`
        : u.type === 'recharge after rest' ? ` (Recharges after a ${(u.rest_types || []).join(' or ')} rest)` : '');
    const entries = (l) => (Array.isArray(l) ? l : []).map((a) => ({ name: (a.name || '') + usage(a.usage), desc: a.desc || '' }));
    const plusN = (v) => (v < 0 ? '' : '+') + v;
    function fromApi({ src, m }, sources) {
        const o = blank(), cr = +(src === 'srd' ? m.challenge_rating : m.cr) || 0;
        ABIL.forEach((k, i) => { o[k] = String(m[FULL[i]] ?? 10); });
        Object.assign(o, { name: m.name || 'Monster', hp: String(m.hit_points ?? ''), hpMax: String(m.hit_points ?? ''), cr: crText(cr), xp: String(m.xp ?? CR_XP[cr] ?? ''), langs: m.languages || '',
            meta: `${m.size || ''} ${String(m.type || '').toLowerCase()}${m.subtype ? ` (${m.subtype})` : ''}${m.alignment ? ', ' + m.alignment : ''}`.trim(),
            traits: entries(m.special_abilities), actions: entries(m.actions), bonus: entries(m.bonus_actions), reactions: entries(m.reactions), legendary: entries(m.legendary_actions),
            src: sources[src === 'srd' ? 'srd' : m.document__slug]?.title || m.document__title || '' });
        if (src === 'srd') {
            o.ac = (m.armor_class || []).map((a) => a.value + (a.armor?.length ? ` (${a.armor.map((x) => x.name.toLowerCase()).join(', ')})` : a.type === 'natural' ? ' (natural armor)'
                : a.spell ? ` (${a.spell.name.toLowerCase()})` : a.condition ? ` (${a.condition.name.toLowerCase()})` : '')).join(', ');
            o.hd = m.hit_points_roll || m.hit_dice || '';
            o.speed = Object.entries(m.speed || {}).map(([k, v]) => (k === 'hover' ? (v ? '(hover)' : '') : k === 'walk' ? v : `${k} ${v}`)).filter(Boolean).join(', ');
            const prof = (pre) => (m.proficiencies || []).filter((p) => p.proficiency.name.startsWith(pre)).map((p) => `${cap(p.proficiency.name.slice(pre.length).toLowerCase())} ${plusN(p.value)}`).join(', ');
            o.saves = prof('Saving Throw: '); o.skills = prof('Skill: ');
            o.vuln = (m.damage_vulnerabilities || []).join(', '); o.resist = (m.damage_resistances || []).join(', '); o.immune = (m.damage_immunities || []).join(', ');
            o.cond = (m.condition_immunities || []).map((c) => c.name.toLowerCase()).join(', ');
            o.senses = Object.entries(m.senses || {}).map(([k, v]) => (k === 'passive_perception' ? `passive Perception ${v}` : `${k.replace(/_/g, ' ')} ${v}`)).join(', ');
        } else {
            o.ac = String(m.armor_class ?? '') + (m.armor_desc ? ` (${m.armor_desc})` : '');
            o.hd = m.hit_dice || '';
            o.speed = Object.entries(m.speed || {}).map(([k, v]) => (k === 'hover' ? (v ? '(hover)' : '') : `${k === 'walk' ? '' : k + ' '}${v} ft.`)).filter(Boolean).join(', ');
            o.saves = ABIL.map((k, i) => (m[FULL[i] + '_save'] != null ? `${cap(k)} ${plusN(m[FULL[i] + '_save'])}` : '')).filter(Boolean).join(', ');
            o.skills = Object.entries(m.skills || {}).map(([k, v]) => `${k.split('_').map(cap).join(' ')} ${plusN(v)}`).join(', ');
            Object.assign(o, { vuln: m.damage_vulnerabilities || '', resist: m.damage_resistances || '', immune: m.damage_immunities || '', cond: m.condition_immunities || '',
                senses: m.senses || '', legDesc: m.legendary_desc || '' });
        }
        return o;
    }

    // ---- drawing ----
    const field = (m, k, label, cls = '') => `<input class="field ${cls}" data-k="${k}" value="${esc(k.split('.').reduce((o, p) => o[p], m))}" aria-label="${esc(label)}" autocomplete="off">`;
    const rows = (s) => Math.min(14, Math.max(2, Math.ceil(String(s).length / 60) + String(s).split('\n').length - 1));
    const chipsHTML = (text) => rollsIn(text).map((r, j) => `<button type="button" class="hb-rollbtn" data-act="chip" data-j="${j}">${esc(r.label)}</button>`).join('');
    const listHTML = (m) => db.list.map((x) => `<button type="button" class="mon-item${x === m ? ' on' : ''}${num(x.hp) <= 0 ? ' down' : ''}" data-act="pick" data-id="${x.id}"${x === m ? ' aria-current="true"' : ''}>
            <b>${esc(x.name || 'Unnamed')}</b><small>HP ${esc(x.hp)}/${esc(x.hpMax)}</small></button>`).join('');
    const pickHTML = (m) => db.list.map((x) => `<option value="${x.id}"${x === m ? ' selected' : ''}>${esc(x.name || 'Unnamed')} (HP ${esc(x.hp)}/${esc(x.hpMax)})</option>`).join('');
    const abilHTML = (m) => ABIL.map((k, i) => `<div class="mon-ab"><span class="hb-lbl">${k}</span>${field(m, k, FULL[i] + ' score')}
            <button type="button" class="hb-d20 mon-mod" data-act="roll" data-t="${cap(FULL[i])} check" data-m="${mod(m, k)}" title="Roll a ${cap(FULL[i])} check">${sign(mod(m, k))}</button>
            <button type="button" class="hb-d20 mon-save" data-act="roll" data-t="${cap(FULL[i])} save" data-m="${saveOf(m, i)}" title="Roll a ${cap(FULL[i])} saving throw">save ${sign(saveOf(m, i))}</button></div>`).join('');
    const skillsHTML = (m) => bonuses(m.skills).map(([n, b]) => `<button type="button" class="hb-rollbtn" data-act="roll" data-t="${esc(n)}" data-m="${b}">${esc(n)} ${sign(b)}</button>`).join('');
    const entryHTML = (m, l, i) => { const e = m[l][i];
        return `<div class="mon-entry" data-l="${l}" data-i="${i}">
            <div class="mon-ehead">${field(m, `${l}.${i}.name`, 'Name', 'mon-ename')}<span class="mon-chips">${chipsHTML(e.desc)}</span>
                <button type="button" class="btn small mon-del" data-act="del" aria-label="Remove ${esc(e.name || 'this entry')}" title="Remove">&times;</button></div>
            <textarea class="field" data-k="${l}.${i}.desc" rows="${rows(e.desc)}" aria-label="${esc(e.name || 'Entry')} description">${esc(e.desc)}</textarea></div>`; };

    function drawList(m) {
        const l = $('#mon-list'), p = $('#mon-pick');
        if (l) l.innerHTML = listHTML(m);
        if (p) p.innerHTML = pickHTML(m);
    }
    function draw() {
        const m = cur();
        if (!m) {
            root.innerHTML = `<section class="card mon-empty"><h2>No monsters created</h2><p class="muted">Import one from the monster list, or start a blank one and type in your own.</p>
                <div class="actions"><button type="button" class="btn" data-act="find">Import from the monster list</button><button type="button" class="btn" data-act="new">New monster</button></div></section>`;
            return;
        }
        db.cur = m.id;
        const props = PROPS.filter(([k]) => m[k]), empty = PROPS.filter(([k]) => !m[k]);
        const shown = LISTS.filter(([l], i) => i < 2 || m[l].length), hidden = LISTS.filter(([l], i) => i >= 2 && !m[l].length);
        root.innerHTML = `<aside class="card mon-side" aria-label="Encounter"><h2>Encounter <span class="n">${db.list.length}</span></h2><div class="mon-list" id="mon-list">${listHTML(m)}</div></aside>
            <label class="mon-pickw">Monster <select class="field" id="mon-pick">${pickHTML(m)}</select></label>
            <section class="card mon-block" aria-label="Stat block">
                <div class="mon-top">${field(m, 'name', 'Name', 'mon-name')}<button type="button" class="btn small" data-act="dup">Duplicate</button><button type="button" class="btn small" data-act="delmon">Remove</button></div>
                ${field(m, 'meta', 'Size, type and alignment', 'mon-meta')}
                <div class="mon-core">
                    <label><span class="hb-lbl">AC</span>${field(m, 'ac', 'Armour class', 'mon-ac')}</label>
                    <div class="mon-hp"><span class="hb-lbl">HP</span><span class="mon-hprow">${field(m, 'hp', 'Current hit points', 'mon-num')}<span>/</span>${field(m, 'hpMax', 'Maximum hit points', 'mon-num')}
                        <button type="button" class="btn small" data-act="hp" data-d="-1" aria-label="Lose 1 hit point" title="Type a sum like 27-8 to take damage">&minus;</button><button type="button" class="btn small" data-act="hp" data-d="1" aria-label="Gain 1 hit point">+</button></span></div>
                    <label class="mon-speed"><span class="hb-lbl">Speed</span>${field(m, 'speed', 'Speed')}</label>
                </div>
                <div class="mon-abil" id="mon-abil">${abilHTML(m)}</div>
                <div class="mon-props">${props.map(([k, t]) => `<label><span class="hb-lbl">${t}</span>${field(m, k, t)}</label>`).join('')}
                    <label><span class="hb-lbl">Challenge</span><span class="mon-cr">${field(m, 'cr', 'Challenge rating', 'mon-num')}<span class="muted">XP</span>${field(m, 'xp', 'XP', 'mon-num')}</span></label></div>
                <div class="mon-skills" id="mon-skillrolls">${skillsHTML(m)}</div>
                ${empty.length ? `<div class="actions"><select class="field hb-auto" id="mon-addprop" aria-label="Line to add">${empty.map(([k, t]) => `<option value="${k}">${t}</option>`).join('')}</select><button type="button" class="btn small" data-act="addprop">Add line</button></div>` : ''}
                ${shown.map(([l, t, one]) => `<h3 class="mon-h">${t}</h3>${l === 'legendary' ? `<textarea class="field" data-k="legDesc" rows="${rows(m.legDesc)}" aria-label="Legendary actions intro" placeholder="How many legendary actions it can take">${esc(m.legDesc)}</textarea>` : ''}
                    ${m[l].map((e, i) => entryHTML(m, l, i)).join('')}<div class="actions"><button type="button" class="btn small" data-act="addrow" data-l="${l}">Add ${one}</button></div>`).join('')}
                ${hidden.length ? `<div class="actions">${hidden.map(([l, , one]) => `<button type="button" class="btn small" data-act="addrow" data-l="${l}">Add ${one}</button>`).join('')}</div>` : ''}
                ${m.src ? `<p class="muted hb-hint">From ${esc(m.src)}. See the sources and licences below.</p>` : ''}
            </section>`;
    }

    // ---- rolling: the roll card and 3D dice, as on the player sheet ----
    let choiceP = null, dice3dP = null, rollTimer = 0;
    // shortcut: dice3d.js and dice/choice.js reuse monster.js's ?v= version, so a change to either alone needs monster.js touched too
    const ver = () => (($('script[src*="/monster.js"]')?.src || '').match(/\?v=\d+/) || [''])[0];
    const dice3d = () => dice3dP || (dice3dP = import(new URL('/games/dnd/dice/dice3d.js' + ver(), location.href).href));
    const chosenStyle = (m) => (choiceP ||= import(`/games/dnd/dice/choice.js${ver()}`).catch((e) => { choiceP = null; throw e; }))
        .then((c) => c.styleFrom(c.readChoice() || { set: { key: 'purple', builtin: true } }, m)).catch(() => 'purple');
    const die = (n) => { const a = new Uint32Array(1); crypto.getRandomValues(a); return (a[0] % n) + 1; };
    function rollShow(title, groups, md = 0) {
        const card = (rolls) => {
            const total = rolls.reduce((t, [, r]) => t + r.reduce((a, b) => a + b, 0), 0) + md;
            const tail = md ? ` ${md < 0 ? '−' : '+'} ${Math.abs(md)}` : '';
            const nat = groups.length === 1 && groups[0][0] === 20 && groups[0][1] === 1 ? (rolls[0][1][0] === 20 ? ' hb-nat20' : rolls[0][1][0] === 1 ? ' hb-nat1' : '') : '';
            $('#hb-rolls').innerHTML = `<button type="button" class="hb-rollcard${nat}" data-act="rollx" aria-label="Dismiss roll">
                <span class="hb-rollhead"><b>${esc(title)}</b><small>${esc(groups.map(([n, c]) => `${c}d${n}`).join(' + ') + tail)}</small></span>
                <span class="hb-rolldice">${esc(rolls.map(([, r]) => r.join(', ')).join(' | ') + tail)}</span><span class="hb-rolltot">${total}</span></button>`;
            clearTimeout(rollTimer); rollTimer = setTimeout(() => { $('#hb-rolls').innerHTML = ''; }, 12000);
        };
        const quick = () => card(groups.map(([n, c]) => [n, Array.from({ length: c }, () => die(n))]));
        if (matchMedia('(prefers-reduced-motion: reduce)').matches) return quick();
        $('#hb-rolls').innerHTML = '';
        dice3d().then(async (m) => m.throwDice(groups, { style: await chosenStyle(m), accent: getComputedStyle(main).getPropertyValue('--btn').trim() || '#5b3f8f' }))
            .then(card, () => { dice3dP = null; quick(); });
    }

    // ---- editing: every field writes straight to the monster; only the parts that depend on it are redrawn ----
    const setPath = (m, k, v) => { const p = k.split('.'); if (p.length === 1) m[k] = v; else m[p[0]][+p[1]][p[2]] = v; };
    root.addEventListener('input', (e) => {
        const k = e.target.dataset.k, m = cur();
        if (!k || !m) return;
        setPath(m, k, e.target.value); save();
        // the score being typed in stays; only its roll buttons change
        if (ABIL.includes(k) || k === 'saves') ABIL.forEach((a, i) => { const b = $(`[data-k="${a}"]`).parentNode;
            Object.assign(b.querySelector('.mon-mod'), { textContent: sign(mod(m, a)) }).dataset.m = mod(m, a);
            Object.assign(b.querySelector('.mon-save'), { textContent: 'save ' + sign(saveOf(m, i)) }).dataset.m = saveOf(m, i); });
        else if (k === 'skills') $('#mon-skillrolls').innerHTML = skillsHTML(m);
        else if (k === 'name' || k === 'hp' || k === 'hpMax') drawList(m);
        else if (/\.desc$/.test(k)) e.target.closest('.mon-entry').querySelector('.mon-chips').innerHTML = chipsHTML(e.target.value);
    });
    // hit points take a sum: "27-8" becomes 19
    root.addEventListener('change', (e) => {
        const m = cur();
        if (e.target.id === 'mon-pick') { db.cur = e.target.value; saveNow(); return draw(); }
        if (e.target.dataset.k === 'hp' && /^\s*-?\d+(\s*[+\-−]\s*\d+)+\s*$/.test(e.target.value)) {
            m.hp = String(e.target.value.replace(/−/g, '-').match(/[+-]?\s*\d+/g).reduce((t, x) => t + num(x), 0));
            e.target.value = m.hp; saveNow(); drawList(m);
        }
    });

    async function openFind() {
        const dlg = $('#mon-dlg');
        $('.mon-menu').open = false;
        dlg.showModal(); $('#mon-q').focus();
        if (index) return search();
        try {
            const r = await fetch($('script[src*="/monster.js"]').src.replace(/monster\.js.*$/, 'monsters.json'));
            if (!r.ok) throw new Error();
            index = await r.json();
            search();
        } catch (err) { $('#mon-status').className = 'err'; $('#mon-status').textContent = "Couldn't load the monster list. Try again in a minute."; }
    }
    let index = null;
    // names that start with the search first, then names that contain it; at most 100 shown
    function search() {
        const q = $('#mon-q').value.trim().toLowerCase(), all = index.list;
        const hits = q ? [...all.filter((x) => x[1].toLowerCase().startsWith(q)), ...all.filter((x) => !x[1].toLowerCase().startsWith(q) && x[1].toLowerCase().includes(q))] : all;
        const st = $('#mon-status');
        st.className = hits.length ? 'muted' : 'note';
        st.textContent = !hits.length ? 'No monsters match that name.' : hits.length > 100 ? `${hits.length.toLocaleString()} monsters. Type to narrow them down.` : `${hits.length} monster${hits.length === 1 ? '' : 's'}`;
        $('#mon-results').innerHTML = hits.slice(0, 100).map(([key, name, cr, type, src]) => `<button type="button" class="mon-hit" data-act="take" data-key="${esc(key)}">
            <b>${esc(name)}</b><small>CR ${esc(crText(cr))} ${esc(type)} · ${esc(index.sources[src]?.title || src)}</small></button>`).join('');
    }
    $('#mon-q').addEventListener('input', debounce(() => index && search(), 80));

    async function take(b) {
        b.disabled = true; b.querySelector('small').textContent = 'Adding...';
        try {
            const r = await fetch(`${$('script[src*="/monster.js"]').src.replace(/monster\.js.*$/, 'monsters.php')}?i=${encodeURIComponent(b.dataset.key)}`);
            const j = await r.json();
            if (!r.ok) throw new Error(j.error);
            const m = fromApi(j, index.sources);
            $('#mon-dlg').close();
            add(m);
            toast(`Added ${m.name}.`, 'ok');
        } catch (err) { toast(err.message || "Couldn't reach the monster list. Try again in a minute.", 'err'); search(); }
    }

    function download(name, text) {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([text], { type: 'application/json' }));
        a.download = name;
        a.click();
        setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    }

    main.addEventListener('click', (e) => {
        const b = e.target.closest('[data-act]');
        if (!b) return;
        const act = b.dataset.act, m = cur();
        if (act === 'new') return add(blank());
        if (act === 'find') return openFind();
        if (act === 'close') return $('#mon-dlg').close();
        if (act === 'take') return take(b);
        if (act === 'file') { $('.mon-menu').open = false; return $('#mon-file').click(); }
        if (act === 'export') return db.list.length ? download('encounter.json', JSON.stringify({ monsters: db.list }, null, 1)) : toast('Add a monster first.', 'note');
        if (act === 'rollx') { $('#hb-rolls').innerHTML = ''; return; }
        if (!m) return;
        const who = m.name || 'Monster';
        if (act === 'pick') { db.cur = b.dataset.id; saveNow(); return draw(); }
        if (act === 'roll') return rollShow(`${who}: ${b.dataset.t}`, [[20, 1]], +b.dataset.m || 0);
        if (act === 'chip') {
            const en = b.closest('.mon-entry'), e2 = m[en.dataset.l][+en.dataset.i], r = rollsIn(e2.desc)[+b.dataset.j];
            return r && rollShow(`${who}: ${e2.name || 'Roll'} ${r.what}`, r.g, r.m);
        }
        if (act === 'hp') { m.hp = String((Number.isFinite(num(m.hp)) ? num(m.hp) : 0) + +b.dataset.d); saveNow(); $('[data-k="hp"]').value = m.hp; return drawList(m); }
        if (act === 'dup') return add({ ...structuredClone(m), id: uid(), hp: m.hpMax }, m);
        if (act === 'delmon') {
            if (!confirm(`Remove ${who} from the encounter?`)) return;
            const i = db.list.indexOf(m);
            db.list.splice(i, 1);
            db.cur = (db.list[i] || db.list[i - 1] || {}).id || '';
        }
        else if (act === 'addprop') { const k = $('#mon-addprop').value; m[k] = ' '; saveNow(); draw(); return $(`[data-k="${k}"]`).focus(); }
        else if (act === 'addrow') { m[b.dataset.l].push({ name: '', desc: '' }); saveNow(); draw(); return $(`[data-k="${b.dataset.l}.${m[b.dataset.l].length - 1}.name"]`).focus(); }
        else if (act === 'del') { const en = b.closest('.mon-entry'); m[en.dataset.l].splice(+en.dataset.i, 1); }
        else return;
        saveNow(); draw();
    });
    // Esc closes the import menu too
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && $('.mon-menu').open) $('.mon-menu').open = false; });

    // a file from Export (the whole encounter) or one monster's JSON is added to the encounter
    $('#mon-file').addEventListener('change', async (e) => {
        const file = e.target.files[0];
        e.target.value = '';
        if (!file) return;
        try {
            const j = JSON.parse(await file.text()), list = Array.isArray(j.monsters) ? j.monsters : Array.isArray(j) ? j : [j];
            if (!list.length || !list.every((x) => x && typeof x === 'object')) throw new Error();
            list.forEach((x) => add({ ...normalise(x), id: uid() }));
            toast(`Imported ${list.length === 1 ? cur().name : list.length + ' monsters'}.`, 'ok');
        } catch (err) { toast("Couldn't import that file. Choose a .json file exported from this sheet.", 'err'); }
    });

    draw();
});
