// Homebrew character sheet (/games/dnd/sheet/). Characters live in this browser's localStorage; export/import moves them.
// Every input carries data-k="path.in.character"; typing updates the character, saves, and refreshes the [data-c] numbers.
// Clicks that change structure (add/remove rows, conditions, tabs) redraw the sheet.
document.addEventListener('DOMContentLoaded', () => {
    'use strict';
    const { $, esc, toast, debounce } = UI;
    const KEY = 'hb-sheet';
    const root = $('#hb-sheet'), dlg = $('#hb-dlg'), dlgBody = $('#hb-dlg-body');

    const ABIL = [['STR', 'Strength'], ['DEX', 'Dexterity'], ['CON', 'Constitution'], ['INT', 'Intelligence'], ['WIS', 'Wisdom'], ['CHA', 'Charisma']];
    const SKILLS = [['Acrobatics', 'DEX'], ['Animal Handling', 'WIS'], ['Arcana', 'INT'], ['Athletics', 'STR'], ['Deception', 'CHA'], ['History', 'INT'],
        ['Insight', 'WIS'], ['Intimidation', 'CHA'], ['Investigation', 'INT'], ['Medicine', 'WIS'], ['Nature', 'INT'], ['Perception', 'WIS'],
        ['Performance', 'CHA'], ['Persuasion', 'CHA'], ['Religion', 'INT'], ['Sleight of Hand', 'DEX'], ['Stealth', 'DEX'], ['Survival', 'WIS']];
    const CONDITIONS = 'Blinded\nCharmed\nDeafened\nFrightened\nGrappled\nIncapacitated\nInvisible\nParalysed\nPetrified\nPoisoned\nProne\nRestrained\nStunned\nUnconscious';
    const TYPES = { skills: 'Skills', attacks: 'Attacks', spells: 'Spells', items: 'Inventory', list: 'List', trackers: 'Trackers', text: 'Text', combat: 'Death saves and hit dice' };
    const PROF = [[0, 'Not proficient'], [0.5, 'Half proficient'], [1, 'Proficient'], [2, 'Expertise']];
    const HELP = 'Formulas can use ability keys for modifiers (DEX), _SCORE for scores (DEX_SCORE), PROF, LEVEL, skill names without spaces (SleightofHand), numbers, + - * / ( ) and min(), max(), floor(), ceil(). Results round down.';
    const ROW = {
        attack: () => ({ name: '', hit: 'STR + PROF', dmg: '1d6 + STR', notes: '' }),
        spell: () => ({ lvl: 0, name: '', prep: false, notes: '' }),
        item: () => ({ eq: false, name: '', qty: 1, wt: 0, notes: '' }),
        list: () => ({ name: '', src: '', text: '' }),
        tracker: () => ({ name: '', cur: 1, max: 1, reset: 'long' }),
        ability: () => ({ key: 'NEW', name: 'New ability', score: 10, save: false, sb: '' }),
        skill: () => ({ name: 'New skill', ab: 'STR', p: 0, b: '' }),
        passive: () => ({ name: 'Passive', f: '10' }),
    };
    const uid = () => Math.random().toString(36).slice(2, 10);

    function newPanel(type, title) {
        const p = { id: uid(), type, title: title || TYPES[type], on: true };
        if (['attacks', 'items', 'list', 'trackers', 'spells'].includes(type)) p.rows = [];
        if (type === 'items') p.coins = { cp: 0, sp: 0, ep: 0, gp: 0, pp: 0 };
        if (type === 'spells') Object.assign(p, { dc: '8 + PROF + INT', atk: 'PROF + INT', slots: Array.from({ length: 9 }, () => ({ max: 0, used: 0 })) });
        if (type === 'text') p.text = '';
        if (type === 'combat') Object.assign(p, { ok: 0, bad: 0, hd: '1d8', hdMax: 'LEVEL', hdUsed: 0 });
        return p;
    }

    function blank() {
        return {
            id: uid(), name: '', cls: '', level: 1, race: '', alignment: '', age: '', art: '',
            abilities: ABIL.map(([key, name]) => ({ key, name, score: 10, save: false, sb: '' })),
            skills: SKILLS.map(([name, ab]) => ({ name, ab, p: 0, b: '' })),
            prof: 'ceil(LEVEL / 4) + 1', ac: '10 + DEX', init: 'DEX',
            hp: { cur: 10, max: 10, temp: 0 }, insp: false,
            speed: '30 ft', conditions: [], exhaustion: 0, condList: CONDITIONS, res: '', imm: '', vul: '',
            passives: [['Passive Perception', '10 + Perception'], ['Passive Investigation', '10 + Investigation'], ['Passive Insight', '10 + Insight']].map(([name, f]) => ({ name, f })),
            senses: '',
            panels: [newPanel('skills'), newPanel('attacks', 'Actions'), newPanel('spells'), newPanel('items'), newPanel('list', 'Features and traits'),
                newPanel('trackers', 'Resources'), newPanel('text', 'Proficiencies and languages'), newPanel('combat'), newPanel('text', 'Personality'),
                newPanel('text', 'Backstory'), newPanel('text', 'Notes')],
            view: { tab: '', all: false },
        };
    }

    // fill gaps in an imported or older character so rendering never meets a missing field
    function normalise(c) {
        const d = blank(), out = {};
        for (const k in d) out[k] = c && typeof c[k] === typeof d[k] && Array.isArray(c[k]) === Array.isArray(d[k]) ? c[k] : d[k];
        const rows = (list, make) => list.filter((r) => r && typeof r === 'object').map((r) => Object.assign(make(), r));
        out.abilities = rows(out.abilities, ROW.ability);
        out.skills = rows(out.skills, ROW.skill);
        out.passives = rows(out.passives, ROW.passive);
        out.panels = out.panels.filter((p) => p && TYPES[p.type]).map((p) => {
            const q = Object.assign(newPanel(p.type), p);
            if (q.rows) q.rows = rows(Array.isArray(q.rows) ? q.rows : [], { attacks: ROW.attack, spells: ROW.spell, items: ROW.item, list: ROW.list, trackers: ROW.tracker }[q.type]);
            return q;
        });
        out.hp = Object.assign(d.hp, out.hp);
        out.view = Object.assign(d.view, out.view);
        out.conditions = out.conditions.filter((x) => typeof x === 'string');
        if (!/^(data:image\/|https:\/\/)/.test(out.art)) out.art = '';
        out.id = typeof c.id === 'string' ? c.id : d.id;
        return out;
    }

    // ---- storage ----
    let db;
    try { db = JSON.parse(localStorage.getItem(KEY)); } catch (e) { db = null; }
    if (!db || typeof db.chars !== 'object') db = { cur: '', chars: {} };
    for (const id in db.chars) db.chars[id] = normalise(db.chars[id]);
    if (!db.chars[db.cur]) db.cur = Object.keys(db.chars)[0] || '';
    if (!db.cur) { const c = blank(); db.chars[c.id] = c; db.cur = c.id; }
    let C = db.chars[db.cur];
    let warned = false;
    function saveNow() {
        try { localStorage.setItem(KEY, JSON.stringify(db)); warned = false; }
        catch (e) { if (!warned) toast("Couldn't save: this browser's storage is full or blocked. Export the character so you don't lose it.", 'err'); warned = true; }
    }
    const save = debounce(saveNow, 250);
    addEventListener('pagehide', saveNow);

    const get = (path) => path.split('.').reduce((o, k) => (o == null ? undefined : o[k]), C);
    function set(path, val) {
        const ks = path.split('.');
        const o = ks.slice(0, -1).reduce((o, k) => o[k], C);
        o[ks[ks.length - 1]] = val;
    }

    // ---- numbers ----
    const kk = (s) => String(s).replace(/\W/g, '').toLowerCase();
    const sg = (n) => (n < 0 ? '−' + -n : '+' + n);
    function ev(f, V) { try { return hbCalc(f, V); } catch (e) { return { v: NaN, err: e.message }; } }
    function skillT(s, V) {
        const m = V[kk(s.ab)];
        if (m === undefined) return { v: NaN, err: 'No ability called ' + s.ab };
        const b = ev(s.b, V);
        return { v: m + Math.floor(V.prof * s.p) + (b.v || 0), err: b.err };
    }
    function vars() {
        const V = { level: +C.level || 0 };
        for (const a of C.abilities) { const s = +a.score || 0; V[kk(a.key)] = Math.floor((s - 10) / 2); V[kk(a.key) + '_score'] = s; }
        V.prof = ev(C.prof, V).v || 0;
        for (const s of C.skills) V[kk(s.name)] = skillT(s, V).v;
        return V;
    }
    function saveT(a, V) { const b = ev(a.sb, V); return { v: V[kk(a.key)] + (a.save ? V.prof : 0) + (b.v || 0), err: b.err }; }

    // value behind a [data-c] key: { v (number or text), err, signed }
    function value(key, V) {
        const [t, a, b] = key.split(':');
        const P = C.panels[a];
        switch (t) {
            case 'mod': return { v: V[kk(C.abilities[a].key)], signed: true };
            case 'save': return { ...saveT(C.abilities[a], V), signed: true };
            case 'skill': return { ...skillT(C.skills[a], V), signed: true };
            case 'prof': return { ...ev(C.prof, V), signed: true };
            case 'ac': return ev(C.ac, V);
            case 'init': return { ...ev(C.init, V), signed: true };
            case 'pas': return ev(C.passives[a].f, V);
            case 'hit': return { ...ev(P.rows[b].hit, V), signed: true };
            case 'dc': return ev(P.dc, V);
            case 'sa': return { ...ev(P.atk, V), signed: true };
            case 'hd': return ev(P.hdMax, V);
            case 'wt': return { v: P.rows.reduce((s, r) => s + (+r.qty || 0) * (+r.wt || 0), 0) };
            case 'speed': return { v: C.speed ? 'Speed ' + C.speed : '' };
            case 'cond': return { v: [...C.conditions, +C.exhaustion ? 'Exhaustion ' + C.exhaustion : ''].filter(Boolean).join(', ') };
        }
        return { v: '' };
    }
    const show = (r) => (r.err || typeof r.v === 'number' && isNaN(r.v) ? '?' : r.signed ? sg(r.v) : r.v);

    function refresh() {
        const V = vars();
        for (const el of document.querySelectorAll('.hb [data-c]')) {
            const r = value(el.dataset.c, V);
            el.textContent = show(r);
            el.classList.toggle('hb-bad', !!r.err);
            if (r.err) el.title = r.err; else el.removeAttribute('title');
        }
        for (const el of document.querySelectorAll('.hb [data-e]')) el.textContent = value(el.dataset.e, V).err || '';
        const bar = $('#hb-hpbar');
        if (bar) bar.style.width = Math.max(0, Math.min(100, (C.hp.cur / C.hp.max) * 100 || 0)) + '%';
    }

    // ---- markup helpers ----
    const PENCIL = '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M3 17.25V21h3.75L17.8 9.94l-3.75-3.75L3 17.25zm17.7-10.2a1 1 0 0 0 0-1.42l-2.33-2.33a1 1 0 0 0-1.42 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>';
    function f(k, label, o = {}) {
        const v = get(k);
        if (o.area) return `<textarea class="field${o.cls ? ' ' + o.cls : ''}" data-k="${k}" aria-label="${esc(label)}"${o.ph ? ` placeholder="${esc(o.ph)}"` : ''}>${esc(v ?? '')}</textarea>`;
        if (o.check) return `<input type="checkbox" data-k="${k}" aria-label="${esc(label)}"${v ? ' checked' : ''}>`;
        const num = o.num ? ' type="number" inputmode="numeric"' : '';
        return `<input class="field${o.cls ? ' ' + o.cls : ''}"${num} data-k="${k}" value="${esc(v ?? '')}" aria-label="${esc(label)}"${o.ph ? ` placeholder="${esc(o.ph)}"` : ''} autocomplete="off">`;
    }
    const sel = (k, label, opts) => `<select class="field" data-k="${k}" aria-label="${esc(label)}">${opts.map(([v, t]) => `<option value="${esc(v)}"${String(get(k)) === String(v) ? ' selected' : ''}>${esc(t)}</option>`).join('')}</select>`;
    const val = (key, cls = '') => `<b class="hb-val${cls}" data-c="${key}"></b>`;
    const del = (p, i, what) => `<button type="button" class="hb-x" data-act="del" data-p="${p}" data-i="${i}" aria-label="Remove ${esc(what)}" title="Remove">&times;</button>`;
    const add = (p, t, text) => `<button type="button" class="btn small" data-act="add" data-p="${p}" data-t="${t}">${text}</button>`;
    const pips = (path, max) => `<span class="hb-pips">${Array.from({ length: Math.max(0, Math.min(+max || 0, 30)) }, (_, i) =>
        `<button type="button" class="${i < get(path) ? 'used' : ''}" data-act="pip" data-p="${path}" data-i="${i}" aria-label="${i + 1}" aria-pressed="${i < get(path)}"></button>`).join('')}</span>`;
    const table = (head, body) => !body ? '' : `<div class="table-wrap"><table class="table hb-table"><thead><tr>${head.map((h) => `<th>${h}</th>`).join('')}</tr></thead><tbody>${body}</tbody></table></div>`;

    // ---- the always-visible top ----
    function top() {
        const id = `<div class="hb-id">
            <label class="hb-name">Name ${f('name', 'Name')}</label>
            <label>Class ${f('cls', 'Class')}</label>
            <label class="hb-lvl">Level ${f('level', 'Level', { num: true })}</label>
            <label>Race ${f('race', 'Race')}</label>
            <label>Alignment ${f('alignment', 'Alignment')}</label>
            <label class="hb-age">Age ${f('age', 'Age')}</label>
        </div>`;
        const art = `<button type="button" class="hb-art" data-act="art" aria-label="${C.art ? 'Change' : 'Add'} character art">${C.art ? `<img src="${esc(C.art)}" alt="" width="300" height="300">` : '<span>Add art</span>'}</button>`;
        const abils = `<div class="hb-abils">${C.abilities.map((a, i) => `<div class="hb-abil">
            <span class="hb-lbl" title="${esc(a.name)}">${esc(a.key)}</span>
            ${val('mod:' + i, ' hb-big')}
            ${f(`abilities.${i}.score`, a.name + ' score', { num: true, cls: 'hb-score' })}
        </div>`).join('')}</div>`;
        const edit = (path, label) => `<button type="button" class="hb-edit" data-act="formula" data-p="${path}" data-t="${esc(label)}" aria-label="Edit the ${esc(label.toLowerCase())} formula" title="Edit formula">${PENCIL}</button>`;
        const combat = `<div class="hb-combat">
            <div class="hb-box hb-hp">
                <span class="hb-lbl">Hit points</span>
                <div class="hb-hpv">${f('hp.cur', 'Current hit points', { num: true })}<span>/</span>${f('hp.max', 'Maximum hit points', { num: true })}</div>
                <div class="progress"><i id="hb-hpbar"></i></div>
                <form class="hb-dmg" data-act="hp"><input class="field" id="hb-amt" type="number" inputmode="numeric" min="0" aria-label="Amount" placeholder="5"><button class="btn small" value="-1">Damage</button><button class="btn small" value="1">Heal</button></form>
            </div>
            <div class="hb-box"><span class="hb-lbl">Temp HP</span>${f('hp.temp', 'Temporary hit points', { num: true, cls: 'hb-score' })}</div>
            <div class="hb-box"><span class="hb-lbl">Armour class</span><button type="button" class="hb-roll hb-big" data-act="formula" data-p="ac" data-t="Armour class" data-c="ac" aria-label="Armour class, edit the formula"></button></div>
            <div class="hb-box"><span class="hb-lbl">Initiative</span>${val('init', ' hb-big')}${edit('init', 'Initiative')}</div>
            <div class="hb-box"><span class="hb-lbl">Proficiency</span><button type="button" class="hb-roll hb-big" data-act="formula" data-p="prof" data-t="Proficiency bonus" data-c="prof" aria-label="Proficiency bonus, edit the formula"></button></div>
            <label class="hb-box hb-insp"><span class="hb-lbl">Heroic inspiration</span>${f('insp', 'Heroic inspiration', { check: true })}</label>
        </div>`;
        const conds = C.condList.split('\n').map((s) => s.trim()).filter(Boolean);
        const more = `<div class="hb-more">
            <button type="button" class="btn small" data-act="more" aria-expanded="${!!open.more}" aria-controls="hb-more">Conditions, speed and defences</button>
            <span data-c="speed"></span><span class="hb-condsum" data-c="cond"></span>
        </div>
        <div class="card hb-more-body" id="hb-more"${open.more ? '' : ' hidden'}>
            <div class="badges" aria-label="Conditions">${conds.map((n) => `<button type="button" class="badge${C.conditions.includes(n) ? ' r' : ''}" data-act="cond" data-n="${esc(n)}" aria-pressed="${C.conditions.includes(n)}">${esc(n)}</button>`).join('')}</div>
            <div class="hb-grid">
                <label>Speed ${f('speed', 'Speed', { ph: '30 ft, fly 60 ft' })}</label>
                <label>Exhaustion ${f('exhaustion', 'Exhaustion level', { num: true })}</label>
                <label>Resistances ${f('res', 'Resistances', { ph: 'fire, cold' })}</label>
                <label>Immunities ${f('imm', 'Immunities')}</label>
                <label>Vulnerabilities ${f('vul', 'Vulnerabilities')}</label>
            </div>
        </div>`;
        const saves = `<section class="card hb-saves"><h2>Saving throws</h2><div class="hb-savegrid">${C.abilities.map((a, i) => `<div class="hb-save">
            <label class="hb-dot">${f(`abilities.${i}.save`, 'Proficient in ' + a.name + ' saves', { check: true })}</label>
            <span>${esc(a.key)}</span>${val('save:' + i)}
        </div>`).join('')}</div></section>`;
        const senses = `<section class="card hb-senses"><h2>Senses</h2>
            <div class="rows">${C.passives.map((p, i) => `<div class="rowi"><span class="grow">${esc(p.name)}</span><b data-c="pas:${i}"></b></div>`).join('')}</div>
            <label class="hb-lbl hb-other">Other senses ${f('senses', 'Other senses', { area: true, ph: '120 ft magical darkvision' })}</label>
        </section>`;
        return `<div class="hb-top-id">${id}</div><div class="hb-top-art">${art}</div><div class="hb-top-abil">${abils}</div>
            <div class="hb-top-combat">${combat}${more}</div><div class="hb-top-saves">${saves}</div><div class="hb-top-senses">${senses}</div>`;
    }

    // ---- configurable panels ----
    const R = {
        skills: () => `<div class="rows hb-two">${C.skills.map((s, i) => {
            const lv = PROF.findIndex(([p]) => p === s.p);
            return `<div class="rowi"><button type="button" class="hb-prof l${Math.max(0, lv)}" data-act="prof" data-i="${i}" aria-label="${esc(s.name)}: ${PROF[Math.max(0, lv)][1]}" title="${PROF[Math.max(0, lv)][1]}"></button>
                <span class="grow">${esc(s.name)}</span><small>${esc(s.ab)}</small>${val('skill:' + i)}</div>`;
        }).join('')}</div><p class="muted hb-hint">Click a dot to change proficiency. Add or rename skills in Customise.</p>`,

        attacks: (p, path, pi) => table(['Name', 'To hit', 'Damage', 'Notes', ''], p.rows.map((r, i) => `<tr>
            <td>${f(`${path}.rows.${i}.name`, 'Name', { ph: 'Longsword' })}</td>
            <td><div class="hb-fx">${f(`${path}.rows.${i}.hit`, 'To hit formula', { cls: 'hb-formula' })}${val(`hit:${pi}:${i}`)}</div></td>
            <td>${f(`${path}.rows.${i}.dmg`, 'Damage', { cls: 'hb-formula' })}</td>
            <td>${f(`${path}.rows.${i}.notes`, 'Notes', { ph: 'slashing, versatile' })}</td><td>${del(path + '.rows', i, r.name || 'attack')}</td></tr>`).join(''))
            + `<div class="actions">${add(path + '.rows', 'attack', 'Add attack')}</div>`,

        spells: (p, path, pi) => {
            const slots = p.slots.map((s, l) => (s.max > 0 ? `<span class="hb-slot"><small>${ordinal(l + 1)}</small>${pips(`${path}.slots.${l}.used`, s.max)}</span>` : '')).join('');
            const rows = p.rows.map((r, i) => [r, i]).sort((a, b) => (+a[0].lvl || 0) - (+b[0].lvl || 0)).map(([r, i]) => `<tr>
                <td>${f(`${path}.rows.${i}.lvl`, 'Level', { num: true, cls: 'hb-n' })}</td>
                <td>${f(`${path}.rows.${i}.name`, 'Spell name', { ph: 'Fire Bolt' })}</td>
                <td class="hb-c">${f(`${path}.rows.${i}.prep`, 'Prepared', { check: true })}</td>
                <td>${f(`${path}.rows.${i}.notes`, 'Notes', { ph: '120 ft, 1d10 fire' })}</td><td>${del(path + '.rows', i, r.name || 'spell')}</td></tr>`).join('');
            return `<div class="hb-grid">
                <div class="hb-box"><span class="hb-lbl">Save DC</span><b class="hb-big" data-c="dc:${pi}"></b>${f(path + '.dc', 'Save DC formula', { cls: 'hb-formula' })}</div>
                <div class="hb-box"><span class="hb-lbl">Spell attack</span>${val('sa:' + pi, ' hb-big')}${f(path + '.atk', 'Spell attack formula', { cls: 'hb-formula' })}</div>
            </div>
            ${slots ? `<div class="hb-slots">${slots}</div>` : ''}
            <details class="hb-details"><summary>Spell slots per level</summary><div class="hb-slotmax">${p.slots.map((s, l) => `<label>${ordinal(l + 1)} ${f(`${path}.slots.${l}.max`, ordinal(l + 1) + ' level slots', { num: true })}</label>`).join('')}</div></details>
            ${table(['Lvl', 'Spell', 'Prep.', 'Notes', ''], rows)}<div class="actions">${add(path + '.rows', 'spell', 'Add spell')}</div>`;
        },

        items: (p, path, pi) => `<div class="hb-coins">${['cp', 'sp', 'ep', 'gp', 'pp'].map((c) => `<label>${c.toUpperCase()} ${f(`${path}.coins.${c}`, c.toUpperCase(), { num: true })}</label>`).join('')}</div>`
            + table(['Eq.', 'Item', 'Qty', 'Weight', 'Notes', ''], p.rows.map((r, i) => `<tr>
                <td class="hb-c">${f(`${path}.rows.${i}.eq`, 'Equipped', { check: true })}</td>
                <td>${f(`${path}.rows.${i}.name`, 'Item', { ph: 'Rope, 50 ft' })}</td>
                <td>${f(`${path}.rows.${i}.qty`, 'Quantity', { num: true, cls: 'hb-n' })}</td>
                <td>${f(`${path}.rows.${i}.wt`, 'Weight each', { num: true, cls: 'hb-n' })}</td>
                <td>${f(`${path}.rows.${i}.notes`, 'Notes')}</td><td>${del(path + '.rows', i, r.name || 'item')}</td></tr>`).join(''))
            + `<div class="actions">${add(path + '.rows', 'item', 'Add item')}<span class="muted">Total weight <b data-c="wt:${pi}"></b></span></div>`,

        list: (p, path) => `<div class="hb-list">${p.rows.map((r, i) => `<div class="hb-entry">
                <div class="hb-entry-h">${f(`${path}.rows.${i}.name`, 'Name', { cls: 'hb-strong', ph: 'Second Wind' })}${f(`${path}.rows.${i}.src`, 'Source (optional)', { cls: 'hb-src', ph: 'Fighter 1' })}${del(path + '.rows', i, r.name || 'entry')}</div>
                ${f(`${path}.rows.${i}.text`, 'Description', { area: true })}
            </div>`).join('')}</div><div class="actions">${add(path + '.rows', 'list', 'Add entry')}</div>`,

        trackers: (p, path) => `<div class="rows">${p.rows.map((r, i) => `<div class="rowi hb-track">
                ${f(`${path}.rows.${i}.name`, 'Name', { cls: 'grow', ph: 'Rage' })}
                <button type="button" class="btn small" data-act="track" data-p="${path}.rows.${i}" data-d="-1" aria-label="Use one">−</button>
                ${f(`${path}.rows.${i}.cur`, 'Left', { num: true, cls: 'hb-n' })}<span>/</span>${f(`${path}.rows.${i}.max`, 'Maximum', { num: true, cls: 'hb-n' })}
                <button type="button" class="btn small" data-act="track" data-p="${path}.rows.${i}" data-d="1" aria-label="Get one back">+</button>
                ${sel(`${path}.rows.${i}.reset`, 'Resets on', [['', 'No reset'], ['short', 'Short rest'], ['long', 'Long rest']])}
                ${del(path + '.rows', i, r.name || 'tracker')}
            </div>`).join('')}</div><div class="actions">${add(path + '.rows', 'tracker', 'Add tracker')}</div>`,

        text: (p, path) => f(path + '.text', p.title, { area: true, cls: 'hb-text' }),

        combat: (p, path, pi) => `<div class="kv"><span>Death save successes</span><span>${pips(path + '.ok', 3)}</span></div>
            <div class="kv"><span>Death save failures</span><span class="hb-bad-pips">${pips(path + '.bad', 3)}</span></div>
            <div class="hb-grid">
                <label>Hit die ${f(path + '.hd', 'Hit die', { ph: '1d8' })}</label>
                <label>Hit dice total (formula) ${f(path + '.hdMax', 'Hit dice total formula', { cls: 'hb-formula' })}</label>
                <label>Used ${f(path + '.hdUsed', 'Hit dice used', { num: true })}</label>
            </div>
            <div class="actions"><span class="muted">Total <b data-c="hd:${pi}"></b></span></div>`,
    };
    const ordinal = (n) => n + (n === 1 ? 'st' : n === 2 ? 'nd' : n === 3 ? 'rd' : 'th');

    function panels() {
        const vis = C.panels.map((p, i) => [p, i]).filter(([p]) => p.on);
        if (!vis.length) return '<p class="note">Every panel is hidden. Turn some back on in Customise.</p>';
        const card = ([p, i]) => `<section class="card hb-panel" id="hb-p-${esc(p.id)}"><h2>${esc(p.title)}</h2>${R[p.type](p, 'panels.' + i, i)}</section>`;
        if (C.view.all) return `<div class="masonry">${vis.map(card).join('')}</div>`;
        const cur = vis.find(([p]) => p.id === C.view.tab) || vis[0];
        return `<div class="stabs hb-tabs">${vis.map(([p]) => `<button type="button" class="${p === cur[0] ? 'on' : ''}" data-act="tab" data-id="${esc(p.id)}" aria-pressed="${p === cur[0]}">${esc(p.title)}</button>`).join('')}</div>${card(cur)}`;
    }

    // ---- toolbar and dialogs ----
    function bar() {
        const opts = Object.values(db.chars).sort((a, b) => (a.name || '').localeCompare(b.name || '')).map((c) =>
            `<option value="${esc(c.id)}"${c.id === C.id ? ' selected' : ''}>${esc(c.name || 'Unnamed character')}${c.cls ? ' — ' + esc(c.cls) : ''}</option>`).join('');
        return `<select class="field hb-pick" data-act="pick" aria-label="Character">${opts}</select>
            <button type="button" class="btn small" data-act="new">New</button>
            <button type="button" class="btn small" data-act="import">Import</button>
            <button type="button" class="btn small" data-act="export">Export</button>
            <button type="button" class="btn small" data-act="delete">Delete</button>
            <span class="hb-gap"></span>
            <button type="button" class="btn small" data-act="rest" data-r="short">Short rest</button>
            <button type="button" class="btn small" data-act="rest" data-r="long">Long rest</button>
            <button type="button" class="btn small primary" data-act="custom">Customise</button>`;
    }

    function formulaDlg(path, title) {
        return `<h2>${esc(title)}</h2>
            <label class="hb-lbl">Formula ${f(path, title + ' formula')}</label>
            <p>Result <b data-c="${path}"></b> <small class="hb-err" data-e="${path}"></small></p>
            <p class="muted hb-hint">${HELP}</p>`;
    }

    function customDlg() {
        const keys = C.abilities.map((a) => [a.key, a.key]);
        const mini = (head, rows, addBtn) => table(head, rows) + `<div class="actions">${addBtn}</div>`;
        return `<h2>Customise</h2>
            <p class="muted hb-hint">Changes apply as you type. ${HELP}</p>
            <h3>Panels</h3>
            <label class="check">${f('view.all', 'Show every panel at once', { check: true })} Show every panel at once instead of tabs</label>
            ${table(['Show', 'Title', 'Type', ''], C.panels.map((p, i) => `<tr><td class="hb-c">${f(`panels.${i}.on`, 'Show ' + p.title, { check: true })}</td>
                <td>${f(`panels.${i}.title`, 'Title')}</td><td class="muted">${TYPES[p.type]}</td>
                <td class="hb-nowrap"><button type="button" class="hb-x" data-act="mv" data-i="${i}" data-d="-1" aria-label="Move ${esc(p.title)} up" title="Move up"${i ? '' : ' disabled'}>↑</button><button type="button" class="hb-x" data-act="mv" data-i="${i}" data-d="1" aria-label="Move ${esc(p.title)} down" title="Move down"${i < C.panels.length - 1 ? '' : ' disabled'}>↓</button>${del('panels', i, p.title)}</td></tr>`).join(''))}
            <div class="actions hb-addpanel"><select class="field" id="hb-newtype" aria-label="Panel type">${Object.entries(TYPES).map(([k, t]) => `<option value="${k}">${t}</option>`).join('')}</select><button type="button" class="btn small" data-act="addpanel">Add panel</button></div>
            <h3>Numbers</h3>
            ${table(['Number', 'Formula', 'Result'], [['prof', 'Proficiency bonus'], ['ac', 'Armour class'], ['init', 'Initiative']].map(([k, t]) =>
                `<tr><td>${t}</td><td>${f(k, t + ' formula', { cls: 'hb-formula' })}</td><td><b data-c="${k}"></b></td></tr>`).join(''))}
            <h3>Abilities</h3>
            ${mini(['Key', 'Name', 'Extra save bonus', ''], C.abilities.map((a, i) => `<tr><td>${f(`abilities.${i}.key`, 'Key', { cls: 'hb-n' })}</td><td>${f(`abilities.${i}.name`, 'Name')}</td><td>${f(`abilities.${i}.sb`, 'Extra save bonus formula', { cls: 'hb-formula', ph: '0' })}</td><td>${del('abilities', i, a.name)}</td></tr>`).join(''), add('abilities', 'ability', 'Add ability'))}
            <h3>Skills</h3>
            ${mini(['Skill', 'Ability', 'Extra bonus', ''], C.skills.map((s, i) => `<tr><td>${f(`skills.${i}.name`, 'Skill')}</td><td>${sel(`skills.${i}.ab`, 'Ability', keys.some(([k]) => k === s.ab) ? keys : [[s.ab, s.ab + ' (missing)'], ...keys])}</td><td>${f(`skills.${i}.b`, 'Extra bonus formula', { cls: 'hb-formula', ph: '0' })}</td><td>${del('skills', i, s.name)}</td></tr>`).join(''), add('skills', 'skill', 'Add skill'))}
            <h3>Senses</h3>
            ${mini(['Name', 'Formula', 'Result', ''], C.passives.map((p, i) => `<tr><td>${f(`passives.${i}.name`, 'Name')}</td><td>${f(`passives.${i}.f`, 'Formula', { cls: 'hb-formula' })}</td><td><b data-c="pas:${i}"></b></td><td>${del('passives', i, p.name)}</td></tr>`).join(''), add('passives', 'passive', 'Add sense'))}
            <h3>Conditions</h3>
            <label class="hb-lbl">One per line ${f('condList', 'Conditions, one per line', { area: true })}</label>
            ${C.art ? '<h3>Art</h3><button type="button" class="btn small" data-act="artdel">Remove art</button>' : ''}
            <div class="actions"><button type="button" class="btn" data-act="close">Close</button></div>`;
    }

    // ---- drawing ----
    const open = { more: false };
    let dlgMode = null;
    function draw() {
        $('#hb-bar').innerHTML = bar();
        root.innerHTML = top() + `<div class="hb-top-panels">${panels()}</div>`;
        if (dlg.open) dlgBody.innerHTML = dlgMode === 'custom' ? customDlg() : formulaDlg(dlgMode.p, dlgMode.t);
        refresh();
    }
    function showDlg(mode) { dlgMode = mode; dlgBody.innerHTML = mode === 'custom' ? customDlg() : formulaDlg(mode.p, mode.t); dlg.showModal(); refresh(); }
    dlg.addEventListener('close', () => { dlgMode = null; draw(); });

    function switchTo(c) { saveNow(); C = c; db.chars[c.id] = c; db.cur = c.id; saveNow(); draw(); }

    function download(name, text) {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([text], { type: 'application/json' }));
        a.download = name;
        a.click();
        setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    }

    // shrink art to 480 px so a few characters fit in localStorage (about 5 MB)
    async function loadArt(file) {
        try {
            const img = await createImageBitmap(file);
            const s = Math.min(1, 480 / Math.max(img.width, img.height));
            const cv = document.createElement('canvas');
            cv.width = Math.round(img.width * s); cv.height = Math.round(img.height * s);
            cv.getContext('2d').drawImage(img, 0, 0, cv.width, cv.height);
            C.art = cv.toDataURL('image/jpeg', 0.85);
            saveNow(); draw();
        } catch (e) { toast("Couldn't read that image. Try a PNG or JPEG.", 'err'); }
    }

    function rest(kind) {
        for (const p of C.panels) {
            if (p.type === 'trackers') for (const r of p.rows) if (r.reset === 'short' || (kind === 'long' && r.reset === 'long')) r.cur = r.max;
            if (kind !== 'long') continue;
            if (p.type === 'spells') for (const s of p.slots) s.used = 0;
            if (p.type === 'combat') {
                const max = ev(p.hdMax, vars()).v || 0;
                p.ok = 0; p.bad = 0;
                p.hdUsed = Math.max(0, (+p.hdUsed || 0) - Math.max(1, Math.floor(max / 2)));
            }
        }
        if (kind === 'long') { C.hp.cur = C.hp.max; C.hp.temp = 0; if (+C.exhaustion > 0) C.exhaustion = +C.exhaustion - 1; }
        saveNow(); draw();
        toast(kind === 'long' ? 'Long rest: hit points, spell slots, resources and half your hit dice are back.' : 'Short rest: short-rest resources are back. Mark hit dice you spend under Death saves and hit dice.', 'ok');
    }

    // ---- events ----
    const main = $('.hb');
    main.addEventListener('input', (e) => {
        const el = e.target, k = el.dataset.k;
        if (!k) return;
        set(k, el.type === 'checkbox' ? el.checked : el.type === 'number' ? (el.value === '' ? 0 : +el.value) : el.value);
        save(); refresh();
        if (k === 'view.all' || /^panels\.\d+\.on$/.test(k)) draw();
    });
    main.addEventListener('change', (e) => {
        if (e.target.dataset.act === 'pick') switchTo(db.chars[e.target.value]);
        else if (/\.(name|key|title)$/.test(e.target.dataset.k || '') && !dlg.open) $('#hb-bar').innerHTML = bar();
    });
    main.addEventListener('submit', (e) => {
        e.preventDefault();
        const amt = Math.abs(+$('#hb-amt').value || 0);
        if (!amt) return $('#hb-amt').focus();
        if (e.submitter && e.submitter.value === '-1') {
            const soak = Math.min(+C.hp.temp || 0, amt);
            C.hp.temp = (+C.hp.temp || 0) - soak;
            C.hp.cur = Math.max(0, (+C.hp.cur || 0) - (amt - soak));
        } else C.hp.cur = Math.min(+C.hp.max || 0, (+C.hp.cur || 0) + amt);
        saveNow(); draw();
    });
    main.addEventListener('click', (e) => {
        const b = e.target.closest('[data-act]');
        if (!b || b.tagName === 'SELECT' || b.tagName === 'FORM') return;
        const act = b.dataset.act, p = b.dataset.p, i = +b.dataset.i;
        if (act === 'formula') return showDlg({ p, t: b.dataset.t });
        if (act === 'custom') return showDlg('custom');
        if (act === 'close') return dlg.close();
        if (act === 'more') { open.more = !open.more; return draw(); }
        if (act === 'tab') { C.view.tab = b.dataset.id; save(); return draw(); }
        if (act === 'cond') { const n = b.dataset.n; C.conditions = C.conditions.includes(n) ? C.conditions.filter((x) => x !== n) : [...C.conditions, n]; }
        else if (act === 'prof') { const s = C.skills[i], lv = PROF.findIndex(([q]) => q === s.p); s.p = PROF[(lv + 1) % PROF.length][0]; }
        else if (act === 'pip') set(p, get(p) === i + 1 ? i : i + 1);
        else if (act === 'track') { const r = get(p); r.cur = Math.max(0, Math.min(+r.max || 0, (+r.cur || 0) + +b.dataset.d)); }
        else if (act === 'add') get(p).push(ROW[b.dataset.t]());
        else if (act === 'addpanel') { const t = $('#hb-newtype').value; C.panels.push(newPanel(t)); }
        else if (act === 'del') { if (p === 'panels' && C.panels[i].type !== 'skills' && !confirm(`Remove the ${C.panels[i].title} panel and everything in it?`)) return; get(p).splice(i, 1); }
        else if (act === 'mv') { const j = i + +b.dataset.d; [C.panels[i], C.panels[j]] = [C.panels[j], C.panels[i]]; }
        else if (act === 'art') return $('#hb-artfile').click();
        else if (act === 'artdel') C.art = '';
        else if (act === 'new') return switchTo(blank());
        else if (act === 'import') return $('#hb-file').click();
        else if (act === 'export') return download((C.name || 'character').replace(/[^\w -]+/g, '').trim() + '.json', JSON.stringify(C, null, 1));
        else if (act === 'rest') return rest(b.dataset.r);
        else if (act === 'delete') {
            if (!confirm(`Delete ${C.name || 'this character'} from this browser? Export it first if you want to keep a copy.`)) return;
            delete db.chars[C.id];
            return switchTo(Object.values(db.chars)[0] || blank());
        } else return;
        saveNow(); draw();
    });

    $('#hb-file').addEventListener('change', async (e) => {
        const file = e.target.files[0];
        e.target.value = '';
        if (!file) return;
        try {
            const c = normalise(JSON.parse(await file.text()));
            c.id = uid();
            switchTo(c);
            toast(`Imported ${c.name || 'the character'}.`, 'ok');
        } catch (err) { toast("Couldn't import that file. Choose a .json file exported from this sheet.", 'err'); }
    });
    $('#hb-artfile').addEventListener('change', (e) => { const file = e.target.files[0]; e.target.value = ''; if (file) loadArt(file); });

    draw();
});
