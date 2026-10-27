// Homebrew character sheet (/games/dnd/sheet/). Characters live in this browser's localStorage; export/import moves them.
// Every input carries data-k="path.in.character"; typing updates the character, saves, and refreshes the [data-c] numbers.
// Clicks that change structure (add/remove rows, conditions, tabs) redraw the sheet.
document.addEventListener('DOMContentLoaded', () => {
    'use strict';
    const { $, esc, toast, debounce } = UI;
    // test copy: its own storage, seeded once from the live sheet so testing never rewrites live characters
    const KEY = 'hb-sheet-dev';
    try { if (localStorage.getItem(KEY) === null && localStorage.getItem('hb-sheet')) localStorage.setItem(KEY, localStorage.getItem('hb-sheet')); } catch (e) { }
    const root = $('#hb-sheet'), dlg = $('#hb-dlg'), dlgBody = $('#hb-dlg-body'), drawer = $('#hb-drawer');

    const ABIL = [['STR', 'Strength'], ['DEX', 'Dexterity'], ['CON', 'Constitution'], ['INT', 'Intelligence'], ['WIS', 'Wisdom'], ['CHA', 'Charisma']];
    const SKILLS = [['Acrobatics', 'DEX'], ['Animal Handling', 'WIS'], ['Arcana', 'INT'], ['Athletics', 'STR'], ['Deception', 'CHA'], ['History', 'INT'],
        ['Insight', 'WIS'], ['Intimidation', 'CHA'], ['Investigation', 'INT'], ['Medicine', 'WIS'], ['Nature', 'INT'], ['Perception', 'WIS'],
        ['Performance', 'CHA'], ['Persuasion', 'CHA'], ['Religion', 'INT'], ['Sleight of Hand', 'DEX'], ['Stealth', 'DEX'], ['Survival', 'WIS']];
    const CONDITIONS = 'Blinded\nCharmed\nDeafened\nFrightened\nGrappled\nIncapacitated\nInvisible\nParalysed\nPetrified\nPoisoned\nProne\nRestrained\nStunned\nUnconscious';
    // lists you can edit in Customise, one entry per line
    const LISTS = {
        time: 'Action\nBonus action\nReaction\nFree\nSpecial',
        dmg: 'Acid\nBludgeoning\nCold\nFire\nForce\nLightning\nNecrotic\nPiercing\nPoison\nPsychic\nRadiant\nSlashing\nThunder',
        props: 'Ammunition\nFinesse\nHeavy\nLight\nLoading\nMagical\nReach\nSilvered\nThrown\nTwo-handed\nVersatile',
        cats: 'Weapons\nArmour\nGear\nConsumables\nTreasure\nMagic items',
        speeds: 'Walk\nFly\nSwim\nClimb\nBurrow',
    };
    const LIST_NAMES = { time: 'Timings', dmg: 'Damage types', props: 'Weapon properties', cats: 'Item categories', speeds: 'Speed types' };
    const PRESET = {
        armour: ['Light armour', 'Medium armour', 'Heavy armour', 'Shields'],
        weapons: ['Simple weapons', 'Martial weapons'],
        langs: ['Common', 'Dwarvish', 'Elvish', 'Giant', 'Gnomish', 'Goblin', 'Halfling', 'Orc', 'Abyssal', 'Celestial', 'Draconic', 'Deep Speech', 'Infernal', 'Primordial', 'Sylvan', 'Undercommon'],
    };
    const TOOLS = ["Alchemist's supplies", "Brewer's supplies", "Calligrapher's supplies", "Carpenter's tools", "Cartographer's tools", "Cobbler's tools", "Cook's utensils",
        "Glassblower's tools", "Jeweler's tools", "Leatherworker's tools", "Mason's tools", "Painter's supplies", "Potter's tools", "Smith's tools", "Tinker's tools",
        "Weaver's tools", "Woodcarver's tools", 'Disguise kit', 'Forgery kit', 'Herbalism kit', "Navigator's tools", "Poisoner's kit", "Thieves' tools",
        'Dice set', 'Playing card set', 'Bagpipes', 'Drum', 'Flute', 'Lute', 'Lyre', 'Horn', 'Viol', 'Vehicles (land)', 'Vehicles (water)'];
    const HIT_DIE = { barbarian: 12, fighter: 10, paladin: 10, ranger: 10, sorcerer: 6, wizard: 6, artificer: 8, bard: 8, cleric: 8, druid: 8, monk: 8, rogue: 8, warlock: 8 };
    const METAMAGIC = [['Careful Spell', '1', 'Some creatures you choose automatically succeed on the save against your spell.'],
        ['Distant Spell', '1', 'Double the range, or make a touch spell reach 30 ft.'],
        ['Empowered Spell', '1', 'Reroll some of the damage dice and use the new rolls.'],
        ['Extended Spell', '1', 'Double the duration, up to 24 hours.'],
        ['Heightened Spell', '3', 'One target has disadvantage on its first save against the spell.'],
        ['Quickened Spell', '2', 'Cast a spell that takes an action as a bonus action instead.'],
        ['Subtle Spell', '1', 'Cast without verbal or somatic components.'],
        ['Twinned Spell', 'spell level', 'Target a second creature with a spell that targets only one.']];
    const TYPES = { profs: 'Proficiencies', attacks: 'Actions', spells: 'Spells', items: 'Inventory', list: 'List', trackers: 'Trackers', text: 'Text', combat: 'Death saves and hit dice' };
    const PROF = [[0, 'Not proficient'], [0.5, 'Half proficient'], [1, 'Proficient'], [2, 'Expertise']];
    const HELP = 'Formulas can use ability keys for modifiers (DEX), _SCORE for scores (DEX_SCORE), PROF, LEVEL, class levels (FIGHTER_LEVEL), skill names without spaces (SleightofHand), numbers, + - * / ( ), min(), max(), floor(), ceil(), and [labels] like 2 [Shield]. Results round down.';
    const SRD_CREDIT = 'From the <a href="https://dnd.wizards.com/resources/systems-reference-document" target="_blank" rel="noopener">System Reference Document 5.1</a> by Wizards of the Coast, licensed under <a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener">CC-BY-4.0</a>.';
    const uid = () => Math.random().toString(36).slice(2, 10);
    // read a number out of whatever was typed ("30 ft" -> 30)
    const nm = (x) => parseFloat(x) || 0;
    const ROW = {
        attack: () => ({ id: uid(), name: '', time: 'Action', range: '', props: [], mode: 'hit', hit: 'STR + PROF', save: 'DEX', dc: '8 + PROF + STR', dmg: [{ dice: '1d6 + STR', type: 'Slashing' }], desc: '' }),
        spell: () => ({ id: uid(), lvl: 0, name: '', school: '', time: 'Action', range: '', comp: '', dur: '', conc: false, ritual: false, prep: false, mode: 'none', save: 'DEX', dc: '', dmg: [], desc: '', higher: '', srd: '' }),
        item: () => ({ id: uid(), cat: 'Gear', eq: false, att: '', name: '', qty: 1, wt: 0, notes: '' }),
        list: () => ({ id: uid(), name: '', src: '', text: '' }),
        tracker: () => ({ id: uid(), name: '', cur: 1, max: 1, reset: 'long' }),
        meta: () => ({ id: uid(), name: '', cost: '1', desc: '' }),
        dmg: () => ({ dice: '', type: '' }),
        speed: () => ({ type: 'Fly', ft: 30, hover: false }),
        cls: () => ({ name: '', level: 1, die: 8, hdUsed: 0 }),
        tool: () => ({ name: '', ab: 'DEX', p: 1 }),
        ability: () => ({ key: 'NEW', name: 'New ability', score: 10, save: false, sb: '' }),
        skill: () => ({ name: 'New skill', ab: 'STR', p: 0, b: '' }),
        passive: () => ({ name: 'Passive', f: '10' }),
    };
    const PANEL_ROW = { attacks: ROW.attack, spells: ROW.spell, items: ROW.item, list: ROW.list, trackers: ROW.tracker };

    function newPanel(type, title) {
        const p = { id: uid(), type, title: title || TYPES[type], on: true, sub: 'all' };
        if (PANEL_ROW[type]) p.rows = [];
        if (type === 'items') Object.assign(p, { coins: { cp: 0, sp: 0, ep: 0, gp: 0, pp: 0 }, attMax: 3 });
        if (type === 'spells') Object.assign(p, { dc: '8 + PROF + INT', atk: 'PROF + INT', slots: Array.from({ length: 9 }, () => ({ max: 0, used: 0 })), sp: { cur: 0, max: 0 }, meta: [] });
        if (type === 'text') p.text = '';
        if (type === 'combat') Object.assign(p, { ok: 0, bad: 0 });
        if (type === 'profs') p.sub = 'skills';
        return p;
    }

    function blank() {
        return {
            id: uid(), name: '', classes: [ROW.cls()], race: '', alignment: '', age: '', art: '',
            abilities: ABIL.map(([key, name]) => ({ key, name, score: 10, save: false, sb: '' })),
            skills: SKILLS.map(([name, ab]) => ({ name, ab, p: 0, b: '' })),
            prof: 'ceil(LEVEL / 4) + 1', ac: '10 + DEX', init: 'DEX',
            hp: { cur: 10, max: 10, temp: 0, mod: 0 }, insp: false,
            speeds: [{ type: 'Walk', ft: 30, hover: false }], conditions: [], exhaustion: 0, condList: CONDITIONS, res: '', imm: '', vul: '',
            passives: [['Passive Perception', '10 + Perception'], ['Passive Investigation', '10 + Investigation'], ['Passive Insight', '10 + Insight']].map(([name, f]) => ({ name, f })),
            senses: '',
            profs: { armour: [], weapons: [], langs: ['Common'], tools: [], notes: '' },
            lists: { ...LISTS },
            panels: [newPanel('profs'), newPanel('attacks'), newPanel('spells'), newPanel('items'), newPanel('list', 'Passive abilities'),
                newPanel('trackers', 'Resources'), newPanel('combat'), newPanel('text', 'Personality'), newPanel('text', 'Backstory'), newPanel('text', 'Notes')],
            view: { tab: '', all: false },
        };
    }

    const isObj = (x) => x && typeof x === 'object' && !Array.isArray(x);
    // older sheets (before classes, speed lists, proficiency tabs and damage parts) are upgraded in place
    function upgrade(c) {
        const panels = Array.isArray(c.panels) ? c.panels.filter(isObj) : null;
        if (!Array.isArray(c.classes)) {
            const comb = panels && panels.find((p) => p.type === 'combat');
            const die = comb && /d(\d+)/.exec(comb.hd || '');
            c.classes = [{ name: typeof c.cls === 'string' ? c.cls : '', level: +c.level || 1, die: die ? +die[1] : 8, hdUsed: comb ? nm(comb.hdUsed) : 0 }];
        }
        if (!Array.isArray(c.speeds) && typeof c.speed === 'string') {
            c.speeds = c.speed.split(',').map((s) => /^\s*([^\d]*?)\s*(\d+)/.exec(s)).filter(Boolean)
                .map((m, n) => ({ type: m[1] ? m[1][0].toUpperCase() + m[1].slice(1) : n ? 'Other' : 'Walk', ft: +m[2], hover: false }));
        }
        if (panels && !panels.some((p) => p.type === 'profs')) {
            const old = panels.find((p) => p.type === 'text' && p.title === 'Proficiencies and languages');
            const at = Math.max(0, panels.findIndex((p) => p.type === 'skills' || p === old));
            c.profs = Object.assign({ notes: old ? old.text || '' : '' }, isObj(c.profs) ? c.profs : {});
            c.panels = panels.filter((p) => p.type !== 'skills' && p !== old);
            c.panels.splice(at, 0, newPanel('profs'));
        }
        for (const p of Array.isArray(c.panels) ? c.panels.filter(isObj) : []) {
            if (p.type === 'list' && p.title === 'Features and traits') p.title = 'Passive abilities';
            if (p.title === 'Attacks' && p.type === 'attacks') p.title = 'Actions';
            for (const r of Array.isArray(p.rows) ? p.rows.filter(isObj) : []) {
                if (typeof r.dmg === 'string') r.dmg = r.dmg ? [{ dice: r.dmg, type: '' }] : [];
                if (typeof r.notes === 'string' && r.desc === undefined && p.type !== 'items') r.desc = r.notes;
            }
        }
        return c;
    }

    // fill gaps in an imported or older character so rendering never meets a missing field
    function normalise(c) {
        c = upgrade(isObj(c) ? c : {});
        const d = blank(), out = {};
        for (const k in d) out[k] = typeof c[k] === typeof d[k] && Array.isArray(c[k]) === Array.isArray(d[k]) && c[k] !== null ? c[k] : d[k];
        const rows = (list, make) => (Array.isArray(list) ? list : []).filter(isObj).map((r) => Object.assign(make(), r));
        const strs = (list) => (Array.isArray(list) ? list : []).filter((x) => typeof x === 'string');
        out.abilities = rows(out.abilities, ROW.ability);
        out.skills = rows(out.skills, ROW.skill);
        out.passives = rows(out.passives, ROW.passive);
        out.classes = rows(out.classes, ROW.cls);
        if (!out.classes.length) out.classes = [ROW.cls()];
        out.speeds = rows(out.speeds, ROW.speed);
        out.panels = out.panels.filter((p) => isObj(p) && TYPES[p.type]).map((p) => {
            const q = Object.assign(newPanel(p.type), p);
            if (q.rows) q.rows = rows(q.rows, PANEL_ROW[q.type]).map((r) => {
                if ('dmg' in r) r.dmg = rows(r.dmg, ROW.dmg);
                if ('props' in r) r.props = strs(r.props);
                return r;
            });
            if (q.type === 'spells') {
                q.meta = rows(q.meta, ROW.meta);
                q.sp = Object.assign({ cur: 0, max: 0 }, isObj(q.sp) ? q.sp : {});
                q.slots = Array.from({ length: 9 }, (_, l) => Object.assign({ max: 0, used: 0 }, isObj(q.slots && q.slots[l]) ? q.slots[l] : {}));
            }
            return q;
        });
        out.hp = Object.assign(d.hp, out.hp);
        out.view = Object.assign(d.view, out.view);
        out.lists = Object.assign(d.lists, out.lists);
        out.profs = Object.assign(d.profs, out.profs);
        for (const k of ['armour', 'weapons', 'langs']) out.profs[k] = strs(out.profs[k]);
        out.profs.tools = rows(out.profs.tools, ROW.tool);
        out.conditions = strs(out.conditions);
        if (!/^(data:image\/|https:\/\/)/.test(out.art)) out.art = '';
        out.id = typeof c.id === 'string' ? c.id : d.id;
        return out;
    }

    // ---- storage ----
    let db;
    try { db = JSON.parse(localStorage.getItem(KEY)); } catch (e) { db = null; }
    if (!isObj(db) || !isObj(db.chars)) db = { cur: '', chars: {} };
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
    const L = (name) => (C.lists[name] || '').split('\n').map((s) => s.trim()).filter(Boolean);
    const uniq = (a) => [...new Set(a)];

    // ---- numbers ----
    const kk = (s) => String(s).replace(/\W/g, '').toLowerCase();
    const sg = (n) => (n < 0 ? '−' + -n : '+' + n);
    const has = (o, k) => Object.prototype.hasOwnProperty.call(o, k);
    function ev(f, V) { try { return hbCalc(f, V); } catch (e) { return { v: NaN, err: e.message }; } }
    const level = () => C.classes.reduce((s, c) => s + nm(c.level), 0);
    const hpMax = () => nm(C.hp.max) + nm(C.hp.mod);
    function profT(ab, p, extra, V) {
        const m = V[kk(ab)];
        if (m === undefined) return { v: NaN, err: 'No ability called ' + ab };
        const b = ev(extra, V);
        return { v: m + Math.floor(V.prof * p) + (b.v || 0), err: b.err };
    }
    function vars() {
        const V = { level: level() };
        for (const c of C.classes) if (c.name) V[kk(c.name) + '_level'] = (V[kk(c.name) + '_level'] || 0) + nm(c.level);
        for (const a of C.abilities) { const s = nm(a.score); V[kk(a.key)] = Math.floor((s - 10) / 2); V[kk(a.key) + '_score'] = s; }
        V.prof = ev(C.prof, V).v || 0;
        for (const s of C.skills) V[kk(s.name)] = profT(s.ab, s.p, s.b, V).v;
        return V;
    }
    function saveT(a, V) { const b = ev(a.sb, V); return { v: V[kk(a.key)] + (a.save ? V.prof : 0) + (b.v || 0), err: b.err }; }
    const show = (r) => (r.err || typeof r.v === 'number' && isNaN(r.v) ? '?' : r.signed ? sg(r.v) : r.v);
    // "10 + DEX 2 + Shield 2" for a formula
    function why(src, V) {
        const t = hbTerms(src);
        if (t.length < 2 && !(t[0] && t[0].label)) return '';
        return t.map((x, n) => { const r = ev(x.src, V); return (n ? ` ${x.sign === '-' ? '−' : '+'} ` : x.sign === '-' ? '−' : '') + (x.label || x.src.replace(/^min\(\s*(\w+)\s*,\s*(\d+)\s*\)$/i, '$1 (max $2)')) + (x.label || !/^\d+$/.test(x.src) ? ' ' + show(r) : ''); }).join('');
    }
    // "1d8 + STR" -> "1d8 + 3"
    const resolve = (s, V) => String(s || '').replace(/[A-Za-z_]\w*/g, (w) => { const k = w.toLowerCase(); return /^d\d+$/.test(k) || !has(V, k) || isNaN(V[k]) ? w : String(V[k]); })
        .replace(/\+\s*-\s*(\d)/g, '− $1').replace(/\s+/g, ' ').trim();
    const dmgText = (list, V) => list.filter((d) => d.dice || d.type).map((d) => `${resolve(d.dice, V)} ${d.type}`.trim()).join(' + ');
    const ordinal = (n) => n + (n === 1 ? 'st' : n === 2 ? 'nd' : n === 3 ? 'rd' : 'th');
    const panelOf = (rp) => get(rp.split('.').slice(0, 2).join('.'));

    function actSum(r, V) {
        return [r.time, r.range,
            r.mode === 'hit' ? show({ ...ev(r.hit, V), signed: true }) + ' to hit' : '',
            r.mode === 'save' ? `DC ${show(ev(r.dc, V))} ${r.save}` : '',
            dmgText(r.dmg, V), r.props.join(', ')].filter(Boolean).join(' · ');
    }
    function spellSum(r, P, V) {
        return [nm(r.lvl) ? ordinal(nm(r.lvl)) : 'Cantrip', r.time, r.range, r.conc ? 'Concentration' : '', r.ritual ? 'Ritual' : '',
            r.mode === 'atk' ? show({ ...ev(P.atk, V), signed: true }) + ' to hit' : '',
            r.mode === 'save' ? `DC ${show(ev(r.dc || P.dc, V))} ${r.save}` : '',
            dmgText(r.dmg, V)].filter(Boolean).join(' · ');
    }

    // value behind a [data-c] key: { v (number or text), err, signed, why, warn }
    function value(key, V) {
        const [t, a] = key.split(':');
        switch (t) {
            case 'mod': return { v: V[kk(C.abilities[a].key)], signed: true };
            case 'save': return { ...saveT(C.abilities[a], V), signed: true };
            case 'skill': { const s = C.skills[a]; return { ...profT(s.ab, s.p, s.b, V), signed: true }; }
            case 'tool': { const s = C.profs.tools[a]; return { ...profT(s.ab, s.p, '', V), signed: true }; }
            case 'fx': return { ...ev(get(a), V), why: why(get(a), V) };
            case 'fs': return { ...ev(get(a), V), signed: true, why: why(get(a), V) };
            case 'asum': return { v: actSum(get(a), V) };
            case 'ssum': return { v: spellSum(get(a), panelOf(a), V) };
            case 'wt': return { v: C.panels[a].rows.reduce((s, r) => s + nm(r.qty) * nm(r.wt), 0) };
            case 'att': { const P = C.panels[a], n = P.rows.filter((r) => r.att === 'yes').length; return { v: `${n} / ${nm(P.attMax)}`, warn: n > nm(P.attMax) }; }
            case 'hdl': { const c = C.classes[a]; return { v: `${Math.max(0, nm(c.level) - nm(c.hdUsed))} of ${nm(c.level)} left` }; }
            case 'lvl': return { v: C.classes.length > 1 ? 'Level ' + level() + ' total' : '' };
            case 'hpmax': return { v: hpMax(), warn: nm(C.hp.mod) < 0, why: nm(C.hp.mod) ? `${nm(C.hp.max)} ${sg(nm(C.hp.mod))} change` : '' };
            case 'hptemp': return { v: nm(C.hp.temp) ? `+${nm(C.hp.temp)} temp` : '' };
            case 'speed': return { v: C.speeds.map((s) => `${s.type} ${/^\s*[\d.]+\s*$/.test(s.ft) ? s.ft + ' ft' : s.ft}${s.hover ? ' (hover)' : ''}`).join(' · ') };
            case 'cond': return { v: [...C.conditions, nm(C.exhaustion) ? 'Exhaustion ' + C.exhaustion : ''].filter(Boolean).join(', ') };
            case 'def': return { v: [C.res && 'Resists ' + C.res, C.imm && 'Immune to ' + C.imm, C.vul && 'Vulnerable to ' + C.vul].filter(Boolean).join(' · ') };
        }
        return { v: '' };
    }

    function refresh() {
        const V = vars();
        for (const el of document.querySelectorAll('.hb [data-c]')) {
            const r = value(el.dataset.c, V);
            el.textContent = show(r);
            el.classList.toggle('hb-bad', !!r.err);
            el.classList.toggle('hb-warn', !!r.warn);
            if (r.err || r.why) el.title = r.err || r.why; else el.removeAttribute('title');
        }
        for (const el of document.querySelectorAll('.hb [data-e]')) el.textContent = value(el.dataset.e, V).err || '';
        for (const el of document.querySelectorAll('.hb [data-w]')) el.textContent = value(el.dataset.w, V).why || '';
        const total = Math.max(hpMax(), nm(C.hp.cur) + nm(C.hp.temp)) || 1;
        const hp = $('#hb-hpbar'), tmp = $('#hb-tempbar');
        if (hp) hp.style.width = Math.max(0, Math.min(100, (nm(C.hp.cur) / total) * 100)) + '%';
        if (tmp) tmp.style.width = Math.max(0, Math.min(100, (nm(C.hp.temp) / total) * 100)) + '%';
    }

    // ---- markup helpers ----
    const PENCIL = '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M3 17.25V21h3.75L17.8 9.94l-3.75-3.75L3 17.25zm17.7-10.2a1 1 0 0 0 0-1.42l-2.33-2.33a1 1 0 0 0-1.42 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>';
    function f(k, label, o = {}) {
        const v = get(k), cls = o.cls ? ' ' + o.cls : '', ph = o.ph ? ` placeholder="${esc(o.ph)}"` : '';
        if (o.area) return `<textarea class="field${cls}" data-k="${k}" aria-label="${esc(label)}"${ph}>${esc(v ?? '')}</textarea>`;
        if (o.check) return `<input type="checkbox" data-k="${k}" aria-label="${esc(label)}"${v ? ' checked' : ''}>`;
        const num = o.num ? ' inputmode="decimal"' : '';
        return `<input class="field${cls}"${num} data-k="${k}" value="${esc(v ?? '')}" aria-label="${esc(label)}"${ph}${o.list ? ` list="${o.list}"` : ''} autocomplete="off">`;
    }
    const sel = (k, label, opts) => `<select class="field" data-k="${k}" aria-label="${esc(label)}">${opts.map(([v, t]) => `<option value="${esc(v)}"${String(get(k)) === String(v) ? ' selected' : ''}>${esc(t)}</option>`).join('')}</select>`;
    const abSel = (k, label) => { const keys = C.abilities.map((a) => [a.key, a.key]); const v = get(k); return sel(k, label, keys.some(([x]) => x === v) ? keys : [[v, v + ' (missing)'], ...keys]); };
    const val = (key, cls = '') => `<b class="hb-val${cls}" data-c="${key}"></b>`;
    // a computed number that opens the formula builder
    const fx = (path, title, signed, cls = '') => `<button type="button" class="hb-roll${cls}" data-act="formula" data-p="${path}" data-t="${esc(title)}"${signed ? ' data-s="1"' : ''} data-c="${signed ? 'fs' : 'fx'}:${path}" aria-label="${esc(title)}, edit the formula"></button>`;
    const del = (p, i, what, text) => `<button type="button" class="${text ? 'btn small' : 'hb-x'}" data-act="del" data-p="${p}" data-i="${i}" aria-label="Remove ${esc(what)}"${text ? '' : ' title="Remove"'}>${text || '&times;'}</button>`;
    const add = (p, t, text, extra = '') => `<button type="button" class="btn small" data-act="add" data-p="${p}" data-t="${t}"${extra}>${text}</button>`;
    const pips = (path, max) => `<span class="hb-pips">${Array.from({ length: Math.max(0, Math.min(nm(max), 30)) }, (_, i) =>
        `<button type="button" class="${i < get(path) ? 'used' : ''}" data-act="pip" data-p="${path}" data-i="${i}" aria-label="${i + 1}" aria-pressed="${i < get(path)}"></button>`).join('')}</span>`;
    const table = (head, body) => !body ? '' : `<div class="table-wrap"><table class="table hb-table"><thead><tr>${head.map((h) => `<th>${h}</th>`).join('')}</tr></thead><tbody>${body}</tbody></table></div>`;
    const dot = (act, i, s, name) => { const lv = Math.max(0, PROF.findIndex(([p]) => p === s.p)); return `<button type="button" class="hb-prof l${lv}" data-act="${act}" data-i="${i}" aria-label="${esc(name)}: ${PROF[lv][1]}" title="${PROF[lv][1]}"></button>`; };
    // sub-tabs inside a panel; the choice is saved on the panel as .sub
    function subtabs(path, opts, cur) {
        return `<div class="stabs hb-sub">${opts.map(([id, label, n]) => `<button type="button" class="${id === cur ? 'on' : ''}" data-act="sub" data-p="${path}" data-id="${esc(id)}" aria-pressed="${id === cur}">${esc(label)}${n != null ? ` <span class="muted">${n}</span>` : ''}</button>`).join('')}</div>`;
    }
    const pickSub = (p, opts) => (opts.some(([id]) => id === p.sub) ? p.sub : opts[0][0]);
    // removable tags with an "add your own" field
    function tags(path, presets, ph, list) {
        const have = get(path), all = uniq([...presets, ...have]);
        return `<div class="badges">${all.map((n) => `<button type="button" class="badge${have.includes(n) ? ' b' : ''}" data-act="chip" data-p="${path}" data-n="${esc(n)}" aria-pressed="${have.includes(n)}">${esc(n)}${presets.includes(n) ? '' : ' <span aria-hidden="true">&times;</span>'}</button>`).join('')}</div>
            <div class="hb-addtag"><input class="field hb-tagin" data-p="${path}" aria-label="Add your own" placeholder="${esc(ph)}"${list ? ` list="${list}"` : ''} autocomplete="off"><button type="button" class="btn small" data-act="tagadd">Add</button></div>`;
    }
    const dmgEdit = (rp) => `<div class="hb-dmgs">${get(rp + '.dmg').map((d, j) => `<div class="hb-dmgrow">${f(`${rp}.dmg.${j}.dice`, 'Damage dice', { cls: 'hb-formula', ph: '1d8 + STR' })}${f(`${rp}.dmg.${j}.type`, 'Damage type', { list: 'hb-dl-dmg', ph: 'Slashing' })}${del(rp + '.dmg', j, 'damage')}</div>`).join('')}</div>
        <div class="actions">${add(rp + '.dmg', 'dmg', 'Add damage')}</div>`;
    function datalists() {
        const dl = (id, items) => `<datalist id="${id}">${items.map((x) => `<option value="${esc(x)}">`).join('')}</datalist>`;
        return dl('hb-dl-time', L('time')) + dl('hb-dl-dmg', L('dmg')) + dl('hb-dl-props', L('props')) + dl('hb-dl-speeds', L('speeds')) + dl('hb-dl-tools', TOOLS);
    }

    // ---- the always-visible top ----
    function top() {
        const DICE = [4, 6, 8, 10, 12, 20].map((n) => [n, 'd' + n]);
        const id = `<div class="hb-id">
            <label class="hb-name">Name ${f('name', 'Name')}</label>
            <div class="hb-classes">${C.classes.map((c, i) => `<div class="hb-cls">
                <label>Class ${f(`classes.${i}.name`, 'Class', { ph: 'Fighter' })}</label>
                <label class="hb-lvl">Level ${f(`classes.${i}.level`, 'Level', { num: true })}</label>
                <label class="hb-die">Hit die ${sel(`classes.${i}.die`, 'Hit die', DICE)}</label>
                ${C.classes.length > 1 ? del('classes', i, c.name || 'class') : ''}
            </div>`).join('')}
                <div class="hb-cls-more">${add('classes', 'cls', 'Add a class')}<span class="muted" data-c="lvl"></span></div>
            </div>
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
        const combat = `<div class="hb-combat">
            <div class="hb-box hb-hp">
                <span class="hb-lbl">Hit points</span>
                <div class="hb-hpv">${f('hp.cur', 'Current hit points', { num: true })}<span>/</span><b data-c="hpmax"></b><span class="hb-temp" data-c="hptemp"></span></div>
                <div class="hb-hpbar" aria-hidden="true"><i id="hb-hpbar"></i><i id="hb-tempbar"></i></div>
                <div class="hb-hpf">
                    <label>Max ${f('hp.max', 'Maximum hit points', { num: true })}</label>
                    <label>Max change ${f('hp.mod', 'Temporary change to maximum hit points', { num: true })}</label>
                    <label>Temp ${f('hp.temp', 'Temporary hit points', { num: true })}</label>
                </div>
                <form class="hb-dmg" data-act="hp"><input class="field" id="hb-amt" inputmode="decimal" min="0" aria-label="Amount" placeholder="5"><button class="btn small" value="dmg">Damage</button><button class="btn small" value="heal">Heal</button><button class="btn small" value="temp">Set temp</button></form>
            </div>
            <div class="hb-box"><span class="hb-lbl">Armour class</span>${fx('ac', 'Armour class', false, ' hb-big')}</div>
            <div class="hb-box"><span class="hb-lbl">Initiative</span>${fx('init', 'Initiative', true, ' hb-big')}</div>
            <div class="hb-box"><span class="hb-lbl">Proficiency</span>${fx('prof', 'Proficiency bonus', true, ' hb-big')}</div>
            <label class="hb-box hb-insp"><span class="hb-lbl">Heroic inspiration</span>${f('insp', 'Heroic inspiration', { check: true })}</label>
        </div>`;
        const conds = C.condList.split('\n').map((s) => s.trim()).filter(Boolean);
        const more = `<div class="hb-more">
            <button type="button" class="btn small" data-act="more" aria-expanded="${!!open.more}" aria-controls="hb-more">Conditions, speed and defences</button>
            <span data-c="speed"></span><span class="hb-condsum" data-c="cond"></span><span class="muted" data-c="def"></span>
        </div>
        <div class="card hb-more-body" id="hb-more"${open.more ? '' : ' hidden'}>
            <h3>Conditions</h3>
            <div class="badges" aria-label="Conditions">${conds.map((n) => `<button type="button" class="badge${C.conditions.includes(n) ? ' r' : ''}" data-act="cond" data-n="${esc(n)}" aria-pressed="${C.conditions.includes(n)}">${esc(n)}</button>`).join('')}</div>
            <label class="hb-exh">Exhaustion ${f('exhaustion', 'Exhaustion level', { num: true })}</label>
            <h3>Speed</h3>
            <div class="hb-speeds">${C.speeds.map((s, i) => `<div class="hb-speed">${f(`speeds.${i}.type`, 'Speed type', { list: 'hb-dl-speeds' })}${f(`speeds.${i}.ft`, 'Feet', { num: true, cls: 'hb-n' })}<span class="muted">ft</span><label class="check">${f(`speeds.${i}.hover`, 'Hover', { check: true })} Hover</label>${del('speeds', i, s.type + ' speed')}</div>`).join('')}</div>
            <div class="actions">${add('speeds', 'speed', 'Add speed')}</div>
            <h3>Defences</h3>
            <div class="hb-grid">
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
            <div class="rows">${C.passives.map((p, i) => `<div class="rowi"><span class="grow">${esc(p.name)}</span>${fx(`passives.${i}.f`, p.name, false)}</div>`).join('')}</div>
            <label class="hb-lbl hb-other">Other senses ${f('senses', 'Other senses', { area: true, ph: '120 ft magical darkvision' })}</label>
        </section>`;
        return `<div class="hb-top-id">${id}</div><div class="hb-top-art">${art}</div><div class="hb-top-abil">${abils}</div>
            <div class="hb-top-combat">${combat}${more}</div><div class="hb-top-saves">${saves}</div><div class="hb-top-senses">${senses}</div>`;
    }

    // ---- configurable panels ----
    // one action or spell: a summary line you can open to edit
    function entry(rp, r, summaryKey, editor, lead = '') {
        const isOpen = openRows.has(r.id);
        return `<div class="hb-entry">
            <div class="hb-entry-h">${lead}<button type="button" class="hb-link" data-act="info" data-p="${rp}">${esc(r.name || 'Unnamed')}</button>
                <span class="hb-sum" data-c="${summaryKey}:${rp}"></span>
                <button type="button" class="hb-x hb-toggle" data-act="toggle" data-id="${esc(r.id)}" aria-expanded="${isOpen}" aria-label="Edit ${esc(r.name || 'entry')}" title="Edit">${PENCIL}</button></div>
            ${isOpen ? `<div class="hb-editor">${editor()}</div>` : ''}
        </div>`;
    }

    function actionEditor(rp, r) {
        return `<div class="hb-grid">
            <label>Name ${f(rp + '.name', 'Name', { ph: 'Longsword' })}</label>
            <label>Timing ${f(rp + '.time', 'Timing', { list: 'hb-dl-time' })}</label>
            <label>Range or reach ${f(rp + '.range', 'Range or reach', { ph: '5 ft or 80/320 ft' })}</label>
            <label>Resolves with ${sel(rp + '.mode', 'Resolves with', [['hit', 'Attack roll'], ['save', 'Saving throw'], ['none', 'Nothing']])}</label>
            ${r.mode === 'hit' ? `<div class="hb-fxbox"><span>To hit</span>${fx(rp + '.hit', 'To hit', true)}</div>` : ''}
            ${r.mode === 'save' ? `<label>Save ${abSel(rp + '.save', 'Save ability')}</label><div class="hb-fxbox"><span>Save DC</span>${fx(rp + '.dc', 'Save DC', false)}</div>` : ''}
        </div>
        <h4>Damage</h4>${dmgEdit(rp)}
        <h4>Properties</h4>${tags(rp + '.props', [], 'Finesse', 'hb-dl-props')}
        <label class="hb-wide">Description ${f(rp + '.desc', 'Description', { area: true })}</label>
        <div class="actions">${del(panelPath(rp) + '.rows', rp.split('.').pop(), r.name || 'action', 'Remove action')}</div>`;
    }

    function spellEditor(rp, r) {
        return `<div class="hb-grid">
            <label>Name ${f(rp + '.name', 'Spell name', { list: 'hb-dl-srd', ph: 'Fireball' })}</label>
            <label>Level ${f(rp + '.lvl', 'Level, 0 for a cantrip', { num: true })}</label>
            <label>School ${f(rp + '.school', 'School', { ph: 'Evocation' })}</label>
            <label>Casting time ${f(rp + '.time', 'Casting time', { list: 'hb-dl-time' })}</label>
            <label>Range ${f(rp + '.range', 'Range', { ph: '150 feet' })}</label>
            <label>Components ${f(rp + '.comp', 'Components', { ph: 'V, S, M (bat guano)' })}</label>
            <label>Duration ${f(rp + '.dur', 'Duration', { ph: 'Instantaneous' })}</label>
            <div class="hb-checks"><label class="check">${f(rp + '.conc', 'Concentration', { check: true })} Concentration</label><label class="check">${f(rp + '.ritual', 'Ritual', { check: true })} Ritual</label></div>
            <label>Resolves with ${sel(rp + '.mode', 'Resolves with', [['none', 'Nothing'], ['atk', 'Spell attack'], ['save', 'Saving throw']])}</label>
            ${r.mode === 'save' ? `<label>Save ${abSel(rp + '.save', 'Save ability')}</label><label>DC override (optional) ${f(rp + '.dc', 'DC override formula', { cls: 'hb-formula', ph: 'Uses your spell save DC' })}</label>` : ''}
        </div>
        <p class="muted hb-hint">Pick a name from the list to fill in an SRD spell.</p>
        <h4>Damage</h4>${dmgEdit(rp)}
        <label class="hb-wide">Description ${f(rp + '.desc', 'Description', { area: true })}</label>
        <label class="hb-wide">At higher levels ${f(rp + '.higher', 'At higher levels', { area: true })}</label>
        <div class="actions">${del(panelPath(rp) + '.rows', rp.split('.').pop(), r.name || 'spell', 'Remove spell')}</div>`;
    }
    const panelPath = (rp) => rp.split('.').slice(0, 2).join('.');

    const R = {
        profs: (p, path) => {
            const opts = [['skills', 'Skills'], ['armour', 'Armour'], ['weapons', 'Weapons'], ['tools', 'Tools'], ['langs', 'Languages']];
            if (C.profs.notes) opts.push(['notes', 'Notes']);
            const cur = pickSub(p, opts);
            let body;
            if (cur === 'skills') body = `<div class="rows hb-two">${C.skills.map((s, i) => `<div class="rowi">${dot('prof', i, s, s.name)}<span class="grow">${esc(s.name)}</span><small>${esc(s.ab)}</small>${val('skill:' + i)}</div>`).join('')}</div>
                <p class="muted hb-hint">Click a dot to change proficiency. Add or rename skills in Customise.</p>`;
            else if (cur === 'tools') body = `<div class="rows">${C.profs.tools.map((t, i) => `<div class="rowi hb-tool">${dot('tprof', i, t, t.name || 'tool')}${f(`profs.tools.${i}.name`, 'Tool', { cls: 'grow', list: 'hb-dl-tools', ph: "Thieves' tools" })}${abSel(`profs.tools.${i}.ab`, 'Ability')}${val('tool:' + i)}${del('profs.tools', i, t.name || 'tool')}</div>`).join('')}</div>
                <div class="actions">${add('profs.tools', 'tool', 'Add tool')}</div>`;
            else if (cur === 'notes') body = f('profs.notes', 'Notes', { area: true, cls: 'hb-text' });
            else body = tags('profs.' + cur, PRESET[cur], { armour: 'Mithral armour', weapons: 'Longsword', langs: 'Thieves’ Cant' }[cur]);
            return subtabs(path + '.sub', opts, cur) + body;
        },

        attacks: (p, path, pi) => {
            const times = uniq([...L('time'), ...p.rows.map((r) => r.time)]).filter((t) => p.rows.some((r) => r.time === t));
            const opts = [['all', 'All', p.rows.length], ...times.map((t) => [t, t, p.rows.filter((r) => r.time === t).length])];
            const cur = pickSub(p, opts);
            const list = p.rows.map((r, i) => [r, `${path}.rows.${i}`]).filter(([r]) => cur === 'all' || r.time === cur)
                .map(([r, rp]) => entry(rp, r, 'asum', () => actionEditor(rp, r))).join('');
            return (p.rows.length ? subtabs(path + '.sub', opts, cur) : '') + `<div class="hb-list">${list}</div>
                <div class="actions">${add(path + '.rows', 'attack', 'Add action', cur !== 'all' ? ` data-time="${esc(cur)}"` : '')}</div>`;
        },

        spells: (p, path, pi) => {
            const lv = uniq(p.rows.map((r) => nm(r.lvl))).sort((a, b) => a - b);
            const opts = [['all', 'All', p.rows.length], ...lv.map((l) => [String(l), l ? ordinal(l) : 'Cantrips', p.rows.filter((r) => nm(r.lvl) === l).length]),
                ['prep', 'Prepared', p.rows.filter((r) => r.prep).length], ['meta', 'Metamagic']];
            const cur = pickSub(p, opts);
            if (cur === 'meta') return subtabs(path + '.sub', opts, cur) + `
                <div class="rowi hb-track"><span class="grow">Sorcery points</span>
                    <button type="button" class="btn small" data-act="track" data-p="${path}.sp" data-d="-1" aria-label="Spend a sorcery point">−</button>
                    ${f(path + '.sp.cur', 'Sorcery points left', { num: true, cls: 'hb-n' })}<span>/</span>${f(path + '.sp.max', 'Sorcery points maximum', { num: true, cls: 'hb-n' })}
                    <button type="button" class="btn small" data-act="track" data-p="${path}.sp" data-d="1" aria-label="Get a sorcery point back">+</button></div>
                <div class="hb-list">${p.meta.map((m, i) => `<div class="hb-entry"><div class="hb-entry-h">${f(`${path}.meta.${i}.name`, 'Name', { cls: 'hb-strong', ph: 'Quickened Spell' })}<label class="hb-cost">Cost ${f(`${path}.meta.${i}.cost`, 'Cost in sorcery points', { cls: 'hb-n' })}</label>${del(path + '.meta', i, m.name || 'metamagic')}</div>${f(`${path}.meta.${i}.desc`, 'Description', { area: true })}</div>`).join('')}</div>
                <div class="actions"><select class="field hb-auto" id="hb-meta-pick" aria-label="Metamagic option"><option value="">Your own</option>${METAMAGIC.map(([n]) => `<option>${esc(n)}</option>`).join('')}</select><button type="button" class="btn small" data-act="addmeta" data-p="${path}.meta">Add metamagic</button></div>`;
            const slots = p.slots.map((s, l) => (nm(s.max) > 0 ? `<span class="hb-slot"><small>${ordinal(l + 1)}</small>${pips(`${path}.slots.${l}.used`, s.max)}</span>` : '')).join('');
            const list = p.rows.map((r, i) => [r, `${path}.rows.${i}`]).sort((a, b) => (nm(a[0].lvl) || 0) - (nm(b[0].lvl) || 0))
                .filter(([r]) => cur === 'all' || (cur === 'prep' ? r.prep : String(nm(r.lvl)) === cur))
                .map(([r, rp]) => entry(rp, r, 'ssum', () => spellEditor(rp, r), `<label class="hb-prep" title="Prepared">${f(rp + '.prep', 'Prepared', { check: true })}</label>`)).join('');
            return `<div class="hb-grid">
                    <div class="hb-box"><span class="hb-lbl">Save DC</span>${fx(path + '.dc', 'Spell save DC', false, ' hb-big')}</div>
                    <div class="hb-box"><span class="hb-lbl">Spell attack</span>${fx(path + '.atk', 'Spell attack', true, ' hb-big')}</div>
                </div>
                ${slots ? `<div class="hb-slots">${slots}</div>` : ''}
                <details class="hb-details"><summary>Spell slots per level</summary><div class="hb-slotmax">${p.slots.map((s, l) => `<label>${ordinal(l + 1)} ${f(`${path}.slots.${l}.max`, ordinal(l + 1) + ' level slots', { num: true })}</label>`).join('')}</div></details>
                ${subtabs(path + '.sub', opts, cur)}<div class="hb-list">${list}</div>
                <div class="actions">${add(path + '.rows', 'spell', 'Add spell', /^\d$/.test(cur) ? ` data-lvl="${cur}"` : cur === 'prep' ? ' data-prep="1"' : '')}</div>
                ${p.rows.some((r) => r.srd) ? `<p class="muted hb-hint">SRD spell text: ${SRD_CREDIT}</p>` : ''}`;
        },

        items: (p, path, pi) => {
            const cats = uniq([...L('cats'), ...p.rows.map((r) => r.cat).filter(Boolean)]);
            const opts = [['all', 'All', p.rows.length], ...cats.map((c) => [c, c, p.rows.filter((r) => r.cat === c).length])];
            const cur = pickSub(p, opts);
            const rows = p.rows.map((r, i) => [r, i]).filter(([r]) => cur === 'all' || r.cat === cur).map(([r, i]) => `<tr>
                <td class="hb-c">${f(`${path}.rows.${i}.eq`, 'Equipped', { check: true })}</td>
                <td>${f(`${path}.rows.${i}.name`, 'Item', { ph: 'Rope, 50 ft' })}</td>
                <td>${sel(`${path}.rows.${i}.cat`, 'Category', cats.map((c) => [c, c]))}</td>
                <td>${f(`${path}.rows.${i}.qty`, 'Quantity', { num: true, cls: 'hb-n' })}</td>
                <td>${f(`${path}.rows.${i}.wt`, 'Weight each', { num: true, cls: 'hb-n' })}</td>
                <td>${sel(`${path}.rows.${i}.att`, 'Attunement', [['', 'None'], ['needs', 'Needs attunement'], ['yes', 'Attuned']])}</td>
                <td>${f(`${path}.rows.${i}.notes`, 'Notes')}</td><td>${del(path + '.rows', i, r.name || 'item')}</td></tr>`).join('');
            return `<div class="hb-coins">${['cp', 'sp', 'ep', 'gp', 'pp'].map((c) => `<label>${c.toUpperCase()} ${f(`${path}.coins.${c}`, c.toUpperCase(), { num: true })}</label>`).join('')}</div>
                <div class="hb-attune"><span>Attuned <b data-c="att:${pi}"></b></span><label>Attunement slots ${f(path + '.attMax', 'Attunement slots', { num: true, cls: 'hb-n' })}</label></div>
                ${subtabs(path + '.sub', opts, cur)}
                ${table(['Eq.', 'Item', 'Category', 'Qty', 'Weight', 'Attunement', 'Notes', ''], rows)}
                <div class="actions">${add(path + '.rows', 'item', 'Add item', ` data-cat="${esc(cur === 'all' ? 'Gear' : cur)}"`)}<span class="muted">Total weight <b data-c="wt:${pi}"></b></span></div>`;
        },

        list: (p, path) => `<div class="hb-list">${p.rows.map((r, i) => `<div class="hb-entry">
                <div class="hb-entry-h">${f(`${path}.rows.${i}.name`, 'Name', { cls: 'hb-strong', ph: 'Darkvision' })}${f(`${path}.rows.${i}.src`, 'Source (optional)', { cls: 'hb-src', ph: 'Elf' })}${del(path + '.rows', i, r.name || 'entry')}</div>
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

        combat: (p, path) => `<div class="kv"><span>Death save successes</span><span>${pips(path + '.ok', 3)}</span></div>
            <div class="kv"><span>Death save failures</span><span class="hb-bad-pips">${pips(path + '.bad', 3)}</span></div>
            <h3>Hit dice</h3>
            <div class="rows">${C.classes.map((c, i) => `<div class="rowi"><span class="grow">${esc(c.name || 'Class')} · d${nm(c.die) || 8}</span><small data-c="hdl:${i}"></small>${pips(`classes.${i}.hdUsed`, c.level)}</div>`).join('')}</div>
            <p class="muted hb-hint">One hit die per level; filled boxes are spent. Spend any number in a short rest; a long rest gives half back, rounded up.</p>`,
    };

    function panels() {
        const vis = C.panels.map((p, i) => [p, i]).filter(([p]) => p.on);
        if (!vis.length) return '<p class="note">Every panel is hidden. Turn some back on in Customise.</p>';
        const card = ([p, i]) => `<section class="card hb-panel" id="hb-p-${esc(p.id)}"><h2>${esc(p.title)}</h2>${R[p.type](p, 'panels.' + i, i)}</section>`;
        if (C.view.all) return `<div class="masonry">${vis.map(card).join('')}</div>`;
        const cur = vis.find(([p]) => p.id === C.view.tab) || vis[0];
        return `<div class="stabs hb-tabs">${vis.map(([p]) => `<button type="button" class="${p === cur[0] ? 'on' : ''}" data-act="tab" data-id="${esc(p.id)}" aria-pressed="${p === cur[0]}">${esc(p.title)}</button>`).join('')}</div>${card(cur)}`;
    }

    // ---- toolbar ----
    const clsText = (c) => c.classes.map((x) => [x.name, x.level].filter(Boolean).join(' ')).filter(Boolean).join(' / ');
    function bar() {
        const opts = Object.values(db.chars).sort((a, b) => (a.name || '').localeCompare(b.name || '')).map((c) =>
            `<option value="${esc(c.id)}"${c.id === C.id ? ' selected' : ''}>${esc(c.name || 'Unnamed character')}${clsText(c) ? ' — ' + esc(clsText(c)) : ''}</option>`).join('');
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

    // ---- formula builder ----
    const KINDS = [['num', 'Number'], ['ab', 'Ability modifier'], ['prof', 'Proficiency bonus'], ['level', 'Level'], ['f', 'Other formula']];
    function parseParts(src) {
        const ab = (s) => C.abilities.find((a) => a.key.toLowerCase() === s.toLowerCase());
        return hbTerms(src).map((t) => {
            const s = t.src, base = { sign: t.sign, label: t.label, cap: '' };
            let m;
            if (/^\d+$/.test(s)) return { ...base, k: 'num', v: s };
            if (ab(s)) return { ...base, k: 'ab', v: ab(s).key };
            if ((m = /^min\(\s*([A-Za-z_]\w*)\s*,\s*(\d+)\s*\)$/i.exec(s)) && ab(m[1])) return { ...base, k: 'ab', v: ab(m[1]).key, cap: m[2] };
            if (/^prof$/i.test(s)) return { ...base, k: 'prof', v: '' };
            if (/^level$/i.test(s)) return { ...base, k: 'level', v: '' };
            return { ...base, k: 'f', v: s };
        });
    }
    function buildParts(parts) {
        return parts.map((p, n) => {
            const v = String(p.v || '').trim();
            const body = p.k === 'num' ? String(nm(v)) : p.k === 'ab' ? (String(p.cap).trim() !== '' ? `min(${v}, ${nm(p.cap)})` : v)
                : p.k === 'prof' ? 'PROF' : p.k === 'level' ? 'LEVEL' : /[+-]/.test(v) && !/^\(.*\)$/.test(v) ? `(${v})` : v || '0';
            const lab = String(p.label || '').replace(/[[\]]/g, '').trim();
            return (n ? ` ${p.sign} ` : p.sign === '-' ? '-' : '') + body + (lab ? ` [${lab}]` : '');
        }).join('');
    }
    function partsHTML(parts) {
        const keys = C.abilities.map((a) => [a.key, a.key]);
        const opt = (list, v) => list.map(([k, t]) => `<option value="${esc(k)}"${String(v) === String(k) ? ' selected' : ''}>${esc(t)}</option>`).join('');
        return parts.map((p, n) => `<div class="hb-part">
            <select class="field hb-sign" data-b="${n}.sign" aria-label="Add or subtract">${opt([['+', '+'], ['-', '−']], p.sign)}</select>
            <select class="field" data-b="${n}.k" aria-label="Kind of part">${opt(KINDS, p.k)}</select>
            ${p.k === 'num' ? `<input class="field hb-n" inputmode="decimal" data-b="${n}.v" value="${esc(p.v)}" aria-label="Number">` : ''}
            ${p.k === 'ab' ? `<select class="field" data-b="${n}.v" aria-label="Ability">${opt(keys, p.v)}</select><input class="field hb-n" inputmode="decimal" data-b="${n}.cap" value="${esc(p.cap)}" aria-label="Highest this can add (optional)" placeholder="no cap" title="Highest this can add, e.g. 2 for medium armour">` : ''}
            ${p.k === 'f' ? `<input class="field hb-formula" data-b="${n}.v" value="${esc(p.v)}" aria-label="Formula" placeholder="ceil(LEVEL / 2)">` : ''}
            <input class="field hb-plabel" data-b="${n}.label" value="${esc(p.label)}" aria-label="Label (optional)" placeholder="Label, e.g. Shield">
            <button type="button" class="hb-x" data-act="pdel" data-i="${n}" aria-label="Remove this part" title="Remove">&times;</button>
        </div>`).join('');
    }
    function formulaDlg(m) {
        const key = (m.s ? 'fs:' : 'fx:') + m.p;
        return `<h2>${esc(m.t)}</h2>
            <p class="hb-result"><b class="hb-big" data-c="${key}"></b> <small class="hb-err" data-e="${key}"></small></p>
            <p class="muted hb-hint" data-w="${key}"></p>
            <div id="hb-parts">${partsHTML(m.parts)}</div>
            <div class="actions"><button type="button" class="btn small" data-act="padd">Add part</button></div>
            <details class="hb-details"><summary>Edit as text</summary><label class="hb-lbl hb-wide">Formula ${f(m.p, m.t + ' formula', { cls: 'hb-formula' })}</label><p class="muted hb-hint">${HELP}</p></details>
            <div class="actions"><button type="button" class="btn primary" data-act="close">Done</button></div>`;
    }

    // ---- rests ----
    function restDlg(kind) {
        const back = Math.ceil(level() / 2);
        if (kind === 'long') return `<h2>Long rest</h2>
            <p>Taking a long rest will:</p>
            <ul class="hb-plain"><li>Set hit points to the maximum, and clear temp HP and any max HP change</li><li>Restore spell slots, sorcery points and every resource</li>
            <li>Give back up to ${back} spent hit ${back === 1 ? 'die' : 'dice'}</li><li>Lower exhaustion by 1 and clear death saves</li></ul>
            <div class="actions"><button type="button" class="btn primary" data-act="dolong">Take long rest</button><button type="button" class="btn" data-act="close">Cancel</button></div>`;
        const con = C.abilities.find((a) => kk(a.key) === 'con'), V = vars();
        const rows = C.classes.map((c, i) => { const left = Math.max(0, nm(c.level) - nm(c.hdUsed)); return `<tr><td>${esc(c.name || 'Class')}</td><td>d${nm(c.die) || 8}</td><td>${left}</td>
            <td><input class="field hb-n" inputmode="decimal" min="0" max="${left}" value="0" data-spend="${i}" aria-label="Hit dice to spend"${left ? '' : ' disabled'}></td></tr>`; }).join('');
        return `<h2>Short rest</h2>
            <p>Resources that reset on a short rest come back. To heal, roll the hit dice you spend${con ? ` and add your ${esc(con.key)} modifier (${sg(V[kk(con.key)])}) to each` : ''}, then enter the total.</p>
            ${table(['Class', 'Die', 'Left', 'Spend'], rows)}
            <label class="hb-lbl hb-wide">Hit points regained <input class="field" id="hb-heal" inputmode="decimal" min="0" value="0"></label>
            <div class="actions"><button type="button" class="btn primary" data-act="doshort">Take short rest</button><button type="button" class="btn" data-act="close">Cancel</button></div>`;
    }
    function rest(kind) {
        for (const p of C.panels) {
            if (p.type === 'trackers') for (const r of p.rows) if (r.reset === 'short' || (kind === 'long' && r.reset === 'long')) r.cur = r.max;
            if (kind !== 'long') continue;
            if (p.type === 'spells') { for (const s of p.slots) s.used = 0; p.sp.cur = p.sp.max; }
            if (p.type === 'combat') { p.ok = 0; p.bad = 0; }
        }
        if (kind === 'long') {
            let back = Math.ceil(level() / 2);
            for (const c of [...C.classes].sort((a, b) => b.die - a.die)) { const n = Math.min(back, nm(c.hdUsed)); c.hdUsed = nm(c.hdUsed) - n; back -= n; }
            C.hp.mod = 0; C.hp.cur = hpMax(); C.hp.temp = 0;
            if (nm(C.exhaustion) > 0) C.exhaustion = nm(C.exhaustion) - 1;
        } else {
            for (const el of dlgBody.querySelectorAll('[data-spend]')) {
                const c = C.classes[el.dataset.spend], left = Math.max(0, nm(c.level) - nm(c.hdUsed));
                c.hdUsed = nm(c.hdUsed) + Math.max(0, Math.min(left, Math.floor(nm(el.value))));
            }
            C.hp.cur = Math.min(hpMax(), nm(C.hp.cur) + Math.max(0, nm($('#hb-heal').value)));
        }
        dlg.close();
        saveNow(); draw();
        toast(kind === 'long' ? 'Long rest taken.' : 'Short rest taken.', 'ok');
    }

    // ---- SRD spells (loaded on first use) ----
    let srdList = null;
    function loadSrdList() {
        if (srdList) return srdList;
        srdList = fetch('spells.php?list=1').then((r) => (r.ok ? r.json() : Promise.reject())).then((list) => {
            $('#hb-dl-srd').innerHTML = list.map(([, n, l]) => `<option value="${esc(n)}">${l ? ordinal(l) + ' level' : 'Cantrip'}</option>`).join('');
            return list;
        }).catch(() => { srdList = null; toast("Couldn't load the SRD spell list. You can still type spells in yourself.", 'err'); return []; });
        return srdList;
    }
    const pickDice = (d, lvl) => {
        if (d.slot) return d.slot[lvl] || Object.values(d.slot)[0] || '';
        if (d.char) { let best = ''; for (const [k, v] of Object.entries(d.char)) if (+k <= level()) best = v; return best || Object.values(d.char)[0] || ''; }
        return '';
    };
    function fromSrd(s, r) {
        const ct = s.casting_time || '';
        Object.assign(r, {
            name: s.name, lvl: nm(s.level), school: s.school || '', range: s.range || '', dur: s.duration || '',
            time: /^1 action$/i.test(ct) ? 'Action' : /^1 bonus action$/i.test(ct) ? 'Bonus action' : /^1 reaction/i.test(ct) ? 'Reaction' : ct,
            comp: (s.components || []).join(', ') + (s.material ? ` (${s.material})` : ''),
            conc: !!s.concentration, ritual: !!s.ritual, mode: s.save ? 'save' : s.attack_type ? 'atk' : 'none', save: s.save || r.save,
            dmg: (s.damage || []).map((d) => ({ dice: pickDice(d, nm(s.level)), type: d.type || '' })).filter((d) => d.dice),
            desc: (s.desc || []).join('\n\n'), higher: (s.higher_level || []).join('\n\n'), srd: s.index,
        });
    }
    async function srdFill(rp) {
        const r = get(rp), list = await loadSrdList();
        const hit = list.find(([, n]) => n.toLowerCase() === String(r.name).trim().toLowerCase());
        if (!hit || r.srd === hit[0]) return;
        try {
            const res = await fetch('spells.php?i=' + encodeURIComponent(hit[0]));
            if (!res.ok) throw 0;
            fromSrd(await res.json(), r);
            saveNow(); draw();
            toast(`Filled in ${r.name} from the SRD.`, 'ok');
        } catch (e) { toast(`Couldn't load ${r.name} from the SRD. Try again in a minute.`, 'err'); }
    }

    // ---- details drawer ----
    const paras = (s) => String(s || '').split(/\n+/).filter((x) => x.trim()).map((x) => `<p>${esc(x)}</p>`).join('');
    function info(rp) {
        const r = get(rp), V = vars(), spell = 'lvl' in r, P = panelOf(rp);
        const kv = (rows) => rows.filter(([, v]) => v).map(([k, v]) => `<div class="kv"><span>${k}</span><span>${esc(v)}</span></div>`).join('');
        const head = spell ? (nm(r.lvl) ? `${ordinal(nm(r.lvl))}-level ${(r.school || 'spell').toLowerCase()}` : `${r.school || 'Spell'} cantrip`) + (r.ritual ? ' (ritual)' : '') : r.time;
        const res = spell ? (r.mode === 'atk' ? show({ ...ev(P.atk, V), signed: true }) + ' to hit' : r.mode === 'save' ? `DC ${show(ev(r.dc || P.dc, V))} ${r.save} save` : '')
            : r.mode === 'hit' ? show({ ...ev(r.hit, V), signed: true }) + ' to hit' : r.mode === 'save' ? `DC ${show(ev(r.dc, V))} ${r.save} save` : '';
        $('#hb-drawer-body').innerHTML = `<h2>${esc(r.name || 'Unnamed')}</h2><p class="muted">${esc(head || '')}</p>
            ${kv(spell ? [['Casting time', r.time], ['Range', r.range], ['Components', r.comp], ['Duration', (r.conc ? 'Concentration, ' : '') + r.dur], ['Resolves with', res], ['Damage', dmgText(r.dmg, V)]]
                : [['Timing', r.time], ['Range', r.range], ['Resolves with', res], ['Damage', dmgText(r.dmg, V)], ['Properties', r.props.join(', ')]])}
            <div class="hb-desc">${paras(r.desc) || '<p class="muted">No description yet. Add one with the edit button.</p>'}
            ${spell && r.higher ? `<p><b>At higher levels.</b></p>${paras(r.higher)}` : ''}</div>
            ${spell && r.srd ? `<p class="muted hb-hint">${SRD_CREDIT}</p>` : ''}`;
        drawer.classList.add('open'); drawer.setAttribute('aria-hidden', 'false'); drawer.focus();
    }
    function closeDrawer() { drawer.classList.remove('open'); drawer.setAttribute('aria-hidden', 'true'); }

    // ---- customise ----
    function customDlg() {
        const mini = (head, rows, addBtn) => table(head, rows) + `<div class="actions">${addBtn}</div>`;
        return `<h2>Customise</h2>
            <p class="muted hb-hint">Changes apply as you type. ${HELP}</p>
            <h3>Panels</h3>
            <label class="check">${f('view.all', 'Show every panel at once', { check: true })} Show every panel at once instead of tabs</label>
            ${table(['Show', 'Title', 'Type', ''], C.panels.map((p, i) => `<tr><td class="hb-c">${f(`panels.${i}.on`, 'Show ' + p.title, { check: true })}</td>
                <td>${f(`panels.${i}.title`, 'Title')}</td><td class="muted">${TYPES[p.type]}</td>
                <td class="hb-nowrap"><button type="button" class="hb-x" data-act="mv" data-i="${i}" data-d="-1" aria-label="Move ${esc(p.title)} up" title="Move up"${i ? '' : ' disabled'}>↑</button><button type="button" class="hb-x" data-act="mv" data-i="${i}" data-d="1" aria-label="Move ${esc(p.title)} down" title="Move down"${i < C.panels.length - 1 ? '' : ' disabled'}>↓</button>${del('panels', i, p.title)}</td></tr>`).join(''))}
            <div class="actions hb-addpanel"><select class="field hb-auto" id="hb-newtype" aria-label="Panel type">${Object.entries(TYPES).map(([k, t]) => `<option value="${k}">${t}</option>`).join('')}</select><button type="button" class="btn small" data-act="addpanel">Add panel</button></div>
            <h3>Numbers</h3>
            ${table(['Number', 'Formula', 'Result'], [['prof', 'Proficiency bonus', 1], ['ac', 'Armour class', 0], ['init', 'Initiative', 1]].map(([k, t, s]) =>
                `<tr><td>${t}</td><td>${f(k, t + ' formula', { cls: 'hb-formula' })}</td><td><b data-c="${s ? 'fs' : 'fx'}:${k}"></b></td></tr>`).join(''))}
            <h3>Abilities</h3>
            ${mini(['Key', 'Name', 'Extra save bonus', ''], C.abilities.map((a, i) => `<tr><td>${f(`abilities.${i}.key`, 'Key', { cls: 'hb-n' })}</td><td>${f(`abilities.${i}.name`, 'Name')}</td><td>${f(`abilities.${i}.sb`, 'Extra save bonus formula', { cls: 'hb-formula', ph: '0' })}</td><td>${del('abilities', i, a.name)}</td></tr>`).join(''), add('abilities', 'ability', 'Add ability'))}
            <h3>Skills</h3>
            ${mini(['Skill', 'Ability', 'Extra bonus', ''], C.skills.map((s, i) => `<tr><td>${f(`skills.${i}.name`, 'Skill')}</td><td>${abSel(`skills.${i}.ab`, 'Ability')}</td><td>${f(`skills.${i}.b`, 'Extra bonus formula', { cls: 'hb-formula', ph: '0' })}</td><td>${del('skills', i, s.name)}</td></tr>`).join(''), add('skills', 'skill', 'Add skill'))}
            <h3>Senses</h3>
            ${mini(['Name', 'Formula', 'Result', ''], C.passives.map((p, i) => `<tr><td>${f(`passives.${i}.name`, 'Name')}</td><td>${f(`passives.${i}.f`, 'Formula', { cls: 'hb-formula' })}</td><td><b data-c="fx:passives.${i}.f"></b></td><td>${del('passives', i, p.name)}</td></tr>`).join(''), add('passives', 'passive', 'Add sense'))}
            <h3>Lists</h3>
            <p class="muted hb-hint">One entry per line. These fill the suggestions and tabs.</p>
            <div class="hb-grid hb-lists"><label>Conditions ${f('condList', 'Conditions', { area: true })}</label>${Object.entries(LIST_NAMES).map(([k, t]) => `<label>${t} ${f('lists.' + k, t, { area: true })}</label>`).join('')}</div>
            ${C.art ? '<h3>Art</h3><button type="button" class="btn small" data-act="artdel">Remove art</button>' : ''}
            <div class="actions"><button type="button" class="btn" data-act="close">Close</button></div>`;
    }

    // ---- drawing ----
    const open = { more: false };
    const openRows = new Set();
    let dlgMode = null;
    const dlgHTML = () => (dlgMode === 'custom' ? customDlg() : dlgMode.kind === 'rest' ? restDlg(dlgMode.r) : formulaDlg(dlgMode));
    function draw() {
        $('#hb-bar').innerHTML = bar();
        root.innerHTML = top() + `<div class="hb-top-panels">${panels()}</div><div hidden>${datalists()}</div>`;
        if (dlg.open && dlgMode && dlgMode.kind !== 'rest') dlgBody.innerHTML = dlgHTML();
        refresh();
    }
    function showDlg(mode) { dlgMode = mode; dlgBody.innerHTML = dlgHTML(); dlg.showModal(); refresh(); }
    dlg.addEventListener('close', () => { dlgMode = null; draw(); });

    function switchTo(c) { saveNow(); C = c; db.chars[c.id] = c; db.cur = c.id; openRows.clear(); saveNow(); draw(); }

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

    function addTag(input) {
        const v = input.value.trim(), list = get(input.dataset.p);
        input.value = '';
        if (v && !list.includes(v)) { list.push(v); saveNow(); draw(); }
    }

    // ---- events ----
    const main = $('.hb');
    main.addEventListener('input', (e) => {
        const el = e.target;
        if (el.dataset.b && dlgMode && dlgMode.parts) {
            const [n, field] = el.dataset.b.split('.'), part = dlgMode.parts[n];
            part[field] = el.value;
            if (field === 'k') { part.v = part.k === 'num' ? '0' : part.k === 'ab' ? C.abilities[0].key : ''; part.cap = ''; }
            set(dlgMode.p, buildParts(dlgMode.parts));
            const raw = dlgBody.querySelector(`[data-k="${dlgMode.p}"]`);
            if (raw) raw.value = get(dlgMode.p);
            if (field === 'k') $('#hb-parts').innerHTML = partsHTML(dlgMode.parts);
            save(); return refresh();
        }
        const k = el.dataset.k;
        if (!k) return;
        set(k, el.type === 'checkbox' ? el.checked : el.value);
        save(); refresh();
        if (dlgMode && dlgMode.parts && k === dlgMode.p) { dlgMode.parts = parseParts(el.value); $('#hb-parts').innerHTML = partsHTML(dlgMode.parts); }
        if (/\.att$/.test(k) && el.value === 'yes') {
            const P = panelOf(k), n = P.rows.filter((r) => r.att === 'yes').length;
            if (n > nm(P.attMax)) toast(`You're attuned to ${n} items but have ${nm(P.attMax)} attunement slots.`, 'note');
        }
        if (k === 'view.all' || /^panels\.\d+\.on$/.test(k) || /\.(mode|cat)$/.test(k) || (el.type === 'checkbox' && /\.prep$/.test(k) && panelOf(k).sub === 'prep')) draw();
    });
    main.addEventListener('change', (e) => {
        const el = e.target, k = el.dataset.k || '';
        if (el.dataset.act === 'pick') return switchTo(db.chars[el.value]);
        if (el.classList.contains('hb-tagin')) return addTag(el);
        if (/^classes\.\d+\.name$/.test(k)) { const d = HIT_DIE[el.value.trim().toLowerCase()]; if (d) set(k.replace(/name$/, 'die'), d); }
        if (/^panels\.\d+\.rows\.\d+\.name$/.test(k) && panelOf(k).type === 'spells' && el.value.trim()) srdFill(k.replace(/\.name$/, ''));
        if (/\.(time|lvl)$/.test(k) || /^classes\.\d+\.(name|level|die)$/.test(k) || /^(lists\.|condList)/.test(k) || /^speeds\./.test(k) && /\.type$/.test(k)) return dlg.open ? null : draw();
        if (/\.(name|key|title)$/.test(k) && !dlg.open) $('#hb-bar').innerHTML = bar();
    });
    main.addEventListener('focusin', (e) => { if (e.target.getAttribute('list') === 'hb-dl-srd') loadSrdList(); });
    main.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.classList.contains('hb-tagin')) { e.preventDefault(); addTag(e.target); }
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && drawer.classList.contains('open') && !dlg.open) closeDrawer(); });
    main.addEventListener('submit', (e) => {
        e.preventDefault();
        const amt = Math.abs(nm($('#hb-amt').value)), how = e.submitter && e.submitter.value;
        if (!amt && how !== 'temp') return $('#hb-amt').focus();
        if (how === 'dmg') {
            const soak = Math.min(nm(C.hp.temp), amt);
            C.hp.temp = nm(C.hp.temp) - soak;
            C.hp.cur = Math.max(0, nm(C.hp.cur) - (amt - soak));
        } else if (how === 'temp') {
            if (amt < nm(C.hp.temp)) toast(`Temp HP doesn't stack, so you keep ${C.hp.temp}.`, 'note');
            C.hp.temp = Math.max(nm(C.hp.temp), amt);
        } else C.hp.cur = Math.min(hpMax(), nm(C.hp.cur) + amt);
        saveNow(); draw();
    });
    main.addEventListener('click', (e) => {
        const b = e.target.closest('[data-act]');
        if (!b || b.tagName === 'SELECT' || b.tagName === 'FORM') return;
        const act = b.dataset.act, p = b.dataset.p, i = +b.dataset.i;
        if (act === 'formula') return showDlg({ kind: 'formula', p, t: b.dataset.t, s: !!b.dataset.s, parts: parseParts(get(p)) });
        if (act === 'padd' || act === 'pdel') {
            if (act === 'padd') dlgMode.parts.push({ sign: '+', k: 'num', v: '1', cap: '', label: '' }); else dlgMode.parts.splice(i, 1);
            set(dlgMode.p, buildParts(dlgMode.parts));
            dlgBody.innerHTML = formulaDlg(dlgMode); saveNow(); return refresh();
        }
        if (act === 'custom') return showDlg('custom');
        if (act === 'close') return dlg.close();
        if (act === 'rest') return showDlg({ kind: 'rest', r: b.dataset.r });
        if (act === 'doshort' || act === 'dolong') return rest(act === 'dolong' ? 'long' : 'short');
        if (act === 'info') { const r = get(p); if ('lvl' in r && r.srd && !r.desc) srdFill(p); return info(p); }
        if (act === 'dclose') return closeDrawer();
        if (act === 'more') { open.more = !open.more; return draw(); }
        if (act === 'tab') { C.view.tab = b.dataset.id; save(); return draw(); }
        if (act === 'toggle') { const id = b.dataset.id; openRows.has(id) ? openRows.delete(id) : openRows.add(id); return draw(); }
        if (act === 'tagadd') return addTag(b.previousElementSibling);
        if (act === 'sub') set(p, b.dataset.id);
        else if (act === 'cond') { const n = b.dataset.n; C.conditions = C.conditions.includes(n) ? C.conditions.filter((x) => x !== n) : [...C.conditions, n]; }
        else if (act === 'chip') { const list = get(p), n = b.dataset.n; set(p, list.includes(n) ? list.filter((x) => x !== n) : [...list, n]); }
        else if (act === 'prof' || act === 'tprof') { const s = act === 'prof' ? C.skills[i] : C.profs.tools[i], lv = PROF.findIndex(([q]) => q === s.p); s.p = PROF[(lv + 1) % PROF.length][0]; }
        else if (act === 'pip') set(p, get(p) === i + 1 ? i : i + 1);
        else if (act === 'track') { const r = get(p); r.cur = Math.max(0, Math.min(nm(r.max), nm(r.cur) + +b.dataset.d)); }
        else if (act === 'add') {
            const row = ROW[b.dataset.t]();
            if (b.dataset.time) row.time = b.dataset.time;
            if (b.dataset.cat) row.cat = b.dataset.cat;
            if (b.dataset.lvl) row.lvl = +b.dataset.lvl;
            if (b.dataset.prep) row.prep = true;
            if (row.id && 'desc' in row) openRows.add(row.id);
            get(p).push(row);
        }
        else if (act === 'addmeta') { const m = METAMAGIC.find(([n]) => n === $('#hb-meta-pick').value); get(p).push(Object.assign(ROW.meta(), m ? { name: m[0], cost: m[1], desc: m[2] } : {})); }
        else if (act === 'addpanel') C.panels.push(newPanel($('#hb-newtype').value));
        else if (act === 'del') {
            if (p === 'panels' && !confirm(`Remove the ${C.panels[i].title} panel and everything in it?`)) return;
            get(p).splice(i, 1);
        }
        else if (act === 'mv') { const j = i + +b.dataset.d; [C.panels[i], C.panels[j]] = [C.panels[j], C.panels[i]]; }
        else if (act === 'art') return $('#hb-artfile').click();
        else if (act === 'artdel') C.art = '';
        else if (act === 'new') return switchTo(blank());
        else if (act === 'import') return $('#hb-file').click();
        else if (act === 'export') return download((C.name || 'character').replace(/[^\w -]+/g, '').trim() + '.json', JSON.stringify(C, null, 1));
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
