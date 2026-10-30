// Dice Sets page: choose a set and a sound, roll, or land a die on a number. Data (sets, file versions) comes from index.php's #dd-data.
const DATA = JSON.parse(document.getElementById('dd-data').textContent);
const $ = (s, r = document) => r.querySelector(s), $$ = (s, r = document) => [...r.querySelectorAll(s)];
const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const out = $('#dd-out'), soundBox = $('#dd-sound');

// the sounds by kind; 'own' is the chosen set's own sound
const CATS = [
    ['own', 'Set’s own', ['own']],
    ['surfaces', 'Table surfaces', ['felt', 'wood', 'glass', 'stone', 'tavern']],
    ['materials', 'Dice materials', ['real', 'bone', 'ice']],
    ['metals', 'Metal dice', ['metal', 'steel', 'brass', 'anvil', 'skillet', 'aluminium', 'copper', 'silver', 'titanium', 'tungsten', 'tungsten2', 'tungsten3', 'tungsten4', 'tungsten5', 'chainmail', 'coins', 'rusty']],
    ['throws', 'How they’re thrown', ['hands', 'cup']],
    ['magic', 'Magic and drama', ['hearth', 'chime', 'sparkle', 'cinema']],
    ['playful', 'Light and playful', ['whisper', 'arcade', 'bouncy']],
];

// what's chosen, kept in this browser
const KEY = 'dice-sets-page';
let state = { set: 'ember', sound: 'own', tab: 'own', nat1: 'orig', nat20: 'orig' };
try { Object.assign(state, JSON.parse(localStorage.getItem(KEY)) || {}); soundBox.checked = localStorage.getItem('dice-sets-sound') !== '0'; } catch (e) { }
if (!DATA.sets.some((s) => s.key === state.set)) state.set = DATA.sets[0].key;
const save = () => { try { localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) { } };
soundBox.addEventListener('change', () => { try { localStorage.setItem('dice-sets-sound', soundBox.checked ? '1' : '0'); } catch (e) { } });

// the engine (three.js and cannon-es come from jsDelivr with it), each set and the sounds load on first use
let engine = null; const sets = {};
const load = () => (engine ||= import(DATA.engine).catch((e) => { engine = null; throw e; }));
const setOf = (k) => (sets[k] ||= import(`./sets/${k}.js?v=${DATA.sets.find((s) => s.key === k).v}`).then((m) => ({ ...m.default, key: 'set-' + k })));
const sounds = import(DATA.sounds).then((m) => m.default);
// nat 1 and nat 20 sounds from every natsounds/ file; one that fails to load is left out
const nats = Promise.all(DATA.nat.map((u) => import(u).then((m) => m.default, () => ({})))).then((mods) => ({
    1: mods.flatMap((m) => m.nat1 || []), 20: mods.flatMap((m) => m.nat20 || []) }));
// when the die blows up or flares, after the exit starts (Ember Cage's fireball and phoenix)
const HIT = { 1: 1.7 * 0.28, 20: 2.1 * 0.42 };
// a set's own trails and nat 1 / nat 20 animations (extras/<set>/), loaded when the set is chosen; a file that fails is left out
const extras = {};
const extrasOf = (k) => (extras[k] ||= (async () => {
    const ex = DATA.extras?.[k] || {}, load = (urls) => Promise.all((urls || []).map((u) => import(u).then((m) => m.default, () => null)));
    const [trails, anims] = await Promise.all([load(ex.trails), load(ex.anims)]);
    return { trails: trails.filter(Boolean).flat(), 1: anims.filter(Boolean).map((a) => a.nat1).filter(Boolean), 20: anims.filter(Boolean).map((a) => a.nat20).filter(Boolean) };
})());
// what's chosen for each set: { trail, anim1, anim20 } keys, or none for the set's own
const pick = () => (state.picks ||= {})[state.set] ||= {};
// a chosen nat animation, with the chosen nat sound (or the set's original one) played at its impact
const withSound = (a, n) => ({ ...a, start(x) {
    a.start?.(x);
    const s = x.audio && (x.st.natSounds?.[n] || x.st.natOriginal?.[n]);
    if (s) { const t0 = x.audio.ac.currentTime; s({ ...x.audio, t0, hit: t0 + (a.impact ?? 0) }); }
} });

// ---- dice set ----
function drawSets() {
    for (const b of $$('.dd-set')) b.setAttribute('aria-pressed', b.dataset.set === state.set);
    const s = DATA.sets.find((x) => x.key === state.set);
    $('#dd-setline').textContent = s.name + ': ' + s.look;
}
// each set's d20 with the 20 on top, one at a time so the page stays responsive
load().then(async (m) => {
    for (const b of $$('.dd-set')) {
        try { $('img', b).src = m.previewStyle(await setOf(b.dataset.set), '#5b3f8f', 96, 96, [20]); } catch (e) { $('img', b).hidden = true; }
        await new Promise((r) => setTimeout(r, 0));
    }
}, () => { out.textContent = "Couldn't load the 3D dice. Check your connection and reload."; });

// ---- sound ----
async function drawSounds() {
    const list = await sounds, set = DATA.sets.find((x) => x.key === state.set);
    $('#dd-scount').textContent = list.length + 1;
    $('#dd-cats').innerHTML = CATS.map(([id, title, keys]) => `<button type="button" data-tab="${id}" class="${state.tab === id ? 'on' : ''}"
        aria-pressed="${state.tab === id}">${esc(title)} <span class="n">${keys.length}</span></button>`).join('');
    const keys = CATS.find(([id]) => id === state.tab)[2];
    $('#dd-sounds').innerHTML = keys.map((k) => {
        const m = k === 'own' ? { name: set.name + '’s own sound', blurb: set.sound } : list.find((x) => x.key === k);
        return `<div class="dd-snd${state.sound === k ? ' on' : ''}">
            <button type="button" class="dd-pick" data-pick="${k}" aria-pressed="${state.sound === k}"><b>${esc(m.name)}</b><span>${esc(m.blurb)}</span></button>
            <button type="button" class="btn small" data-play="throw" data-k="${k}">Play throw</button>
            <button type="button" class="btn small" data-play="roll" data-k="${k}">Play a roll</button></div>`;
    }).join('');
}
// the nat 1 and nat 20 lists, shown only for a set whose d20 has its own nat 1 and nat 20 exits
async function drawNats() {
    const set = await setOf(state.set), has = !!(set.exits?.['d20:1'] && set.exits?.['d20:20']);
    $('#dd-nats').hidden = !has; if (!has) return;
    const all = await nats;
    for (const n of [1, 20]) {
        const list = [{ key: 'orig', name: 'Original', blurb: `The set’s own nat ${n} sound.` }, ...all[n]], cur = state['nat' + n];
        $(`#dd-n${n}count`).textContent = list.length;
        $(`#dd-nat${n}`).innerHTML = list.map((s) => `<div class="dd-snd${cur === s.key ? ' on' : ''}">
            <button type="button" class="dd-pick" data-nat="${n}" data-natkey="${esc(s.key)}" aria-pressed="${cur === s.key}"><b>${esc(s.name)}</b><span>${esc(s.blurb)}</span></button>
            ${s.key === 'orig' ? '' : `<button type="button" class="btn small" data-natplay="${n}" data-natkey="${esc(s.key)}">Play</button>`}</div>`).join('');
    }
}
const natOf = async (n, k) => (await nats)[n].find((s) => s.key === k);
// a set's chosen trails, top layer first (older picks kept one trail as p.trail)
const trailsOf = (p) => { if (!p.trails) { p.trails = p.trail ? [p.trail] : []; delete p.trail; } return p.trails; };
// several trails at once: each die gets every chosen trail, and what a higher layer adds to the scene is drawn over the layers below it
const layered = (list) => (ctx) => {
    const parts = list.map((t, i) => { const order = 10 + (list.length - i) * 10;
        const scene = Object.assign(Object.create(ctx.scene), {
            add: (...o) => { for (const obj of o) obj.traverse((c) => { c.renderOrder = order; }); return ctx.scene.add(...o); },
            remove: (...o) => ctx.scene.remove(...o) });
        return t.make({ ...ctx, scene }); });
    return { tick: (...a) => parts.forEach((q) => q?.tick?.(...a)), dispose: () => parts.forEach((q) => q?.dispose?.()) };
};
// the trail and nat animation lists, shown only for a set that has its own
async function drawExtras() {
    const k = state.set, ex = await extrasOf(k); if (k !== state.set) return;
    const has = ex.trails.length || ex[1].length || ex[20].length, p = pick();
    $('#dd-extras').hidden = !has; if (!has) return;
    const layers = trailsOf(p), isOn = (field, key) => (field === 'trail' ? (key ? layers.includes(key) : !layers.length) : (p[field] || '') === key);
    const rows = (list, field, tryAs) => list.map((s) => { const on = isOn(field, s.key || '');
        return `<div class="dd-snd${on ? ' on' : ''}">
            <button type="button" class="dd-pick" data-field="${field}" data-xkey="${esc(s.key || '')}" aria-pressed="${on}"><b>${esc(s.name)}</b><span>${esc(s.blurb)}</span></button>
            <button type="button" class="btn small" data-field="${field}" data-xkey="${esc(s.key || '')}" data-xtry="${tryAs}">Try</button></div>`; }).join('');
    $('#dd-trailcount').textContent = ex.trails.length + 1;
    $('#dd-trails').innerHTML = rows([{ key: '', name: 'None', blurb: 'No trail.' }, ...ex.trails], 'trail', 'set');
    // the chosen trails as layers, top first: move up or down, or take one off
    const named = layers.map((key) => ex.trails.find((t) => t.key === key)).filter(Boolean);
    $('#dd-layers').hidden = !named.length;
    $('#dd-layers').innerHTML = `<div class="dd-layhead"><b>Layers</b><span class="muted">top first</span>
        <button type="button" class="btn small" data-layer="roll">Roll with these</button></div><ol>${named.map((t, i) => `<li>
        <span class="dd-layname">${esc(t.name)}</span>
        <button type="button" class="dd-laybtn" data-layer="up" data-i="${i}" aria-label="Move ${esc(t.name)} up" title="Move up"${i ? '' : ' disabled'}>↑</button>
        <button type="button" class="dd-laybtn" data-layer="down" data-i="${i}" aria-label="Move ${esc(t.name)} down" title="Move down"${i < named.length - 1 ? '' : ' disabled'}>↓</button>
        <button type="button" class="dd-laybtn" data-layer="off" data-i="${i}" aria-label="Remove ${esc(t.name)}" title="Remove">×</button></li>`).join('')}</ol>`;
    for (const n of [1, 20]) {
        $(`#dd-a${n}count`).textContent = ex[n].length + 1;
        $(`#dd-anim${n}`).innerHTML = rows([{ key: '', name: 'Original', blurb: `The set’s own nat ${n} animation.` }, ...ex[n]], 'anim' + n, n);
    }
}
// the page's own audio for listening without dice, built like the engine's: a compressor and a short noise buffer
let A = null;
const audio = () => {
    if (!A) { const ac = new (window.AudioContext || window.webkitAudioContext)(), o = ac.createDynamicsCompressor(); o.connect(ac.destination);
        const noise = ac.createBuffer(1, ac.sampleRate * 0.4, ac.sampleRate), d = noise.getChannelData(0); for (let i = 0; i < d.length; i++) d[i] = Math.random() * 2 - 1;
        A = { ac, out: o, noise }; }
    if (A.ac.state === 'suspended') A.ac.resume();
    return A;
};
// a sound's throw and hit: one of sounds.js, or the chosen set's own
const soundOf = async (k) => (k === 'own' ? (await setOf(state.set)).sound || {} : (await sounds).find((x) => x.key === k));
// a pretend roll: the throw, then bumps that come quicker and softer, a few of them dice on dice
const HITS = [[0.38, 1, 1], [0.52, 0.8, 1], [0.6, 0.55, 0], [0.7, 0.6, 1], [0.79, 0.45, 1], [0.86, 0.3, 0], [0.93, 0.32, 1], [0.99, 0.2, 1], [1.04, 0.14, 1], [1.08, 0.08, 1]];

// ---- rolling ----
const NUMS = (d) => Array.from({ length: d }, (_, i) => i + 1);
const fillNums = () => { const d = +$('#dd-die').value, cur = +$('#dd-num').value || d;
    $('#dd-num').innerHTML = NUMS(d).map((n) => `<option${n === Math.min(cur, d) ? ' selected' : ''}>${n}</option>`).join(''); };
// only: trail keys to use instead of the chosen layers (a trail's Try shows it on its own)
async function roll(groups, land = null, only = null) {
    out.textContent = 'Rolling...';
    try {
        const [m, set, snd, n1, n20, ex] = await Promise.all([load(), setOf(state.set), soundOf(state.sound), natOf(1, state.nat1), natOf(20, state.nat20), extrasOf(state.set)]);
        const p = pick(), a1 = ex[1].find((a) => a.key === p.anim1), a20 = ex[20].find((a) => a.key === p.anim20);
        const trails = (only || trailsOf(p)).map((k) => ex.trails.find((t) => t.key === k)).filter(Boolean);
        const trail = trails.length === 1 ? trails[0] : trails.length ? { make: layered(trails) } : null;
        // the set as it is, with its throw and rolling sounds swapped when another sound is chosen, the chosen nat 1 and nat 20 sounds,
        // and the set's chosen trail and nat animations
        const style = { ...set, ...(state.sound !== 'own' && { sound: { ...set.sound, throw: snd.throw, hit: snd.hit } }),
            natSounds: { 1: n1 && ((c) => n1.play(c)), 20: n20 && ((c) => n20.play(c)) },
            ...(trail && { trail: trail.make }),
            ...((a1 || a20) && { exits: { ...set.exits, ...(a1 && { 'd20:1': withSound(a1, 1) }), ...(a20 && { 'd20:20': withSound(a20, 20) }) } }) };
        // a new roll takes the last one's dice off the table first, even mid-roll
        m.clearTable(true);
        const res = await m.throwDice(groups, { style, sound: soundBox.checked, land });
        out.textContent = res.flatMap(([n, r]) => r.map((v) => `d${n} ${v}`)).join(' · ');
    } catch (e) { out.textContent = "Couldn't roll. Try again."; }
}

document.addEventListener('click', async (e) => {
    const b = e.target.closest('button'); if (!b) return;
    // the set's own sound row names the set, so it redraws too
    if (b.dataset.set) { state.set = b.dataset.set; save(); drawSets(); drawSounds(); drawNats(); drawExtras(); }
    else if (b.dataset.field === 'trail') {
        // a trail row adds that trail as the top layer, or takes it off again; None takes them all off; Try rolls the set with just that trail
        const key = b.dataset.xkey, list = trailsOf(pick());
        if (b.dataset.xtry) return roll([[4, 1], [6, 1], [8, 1], [10, 1], [12, 1], [20, 1]], null, key ? [key] : []);
        if (!key) list.length = 0; else if (list.includes(key)) list.splice(list.indexOf(key), 1); else list.unshift(key);
        save(); drawExtras();
    }
    else if (b.dataset.field) {
        // picking an animation; Try also lands a d20 on 1 or 20 with it
        pick()[b.dataset.field] = b.dataset.xkey || undefined; save(); drawExtras();
        if (b.dataset.xtry) roll([[20, 1]], [[+b.dataset.xtry]]);
    }
    else if (b.dataset.layer) {
        const list = trailsOf(pick()), i = +b.dataset.i;
        if (b.dataset.layer === 'roll') return roll([[4, 1], [6, 1], [8, 1], [10, 1], [12, 1], [20, 1]]);
        if (b.dataset.layer === 'off') list.splice(i, 1);
        else { const j = b.dataset.layer === 'up' ? i - 1 : i + 1; if (j < 0 || j >= list.length) return; [list[i], list[j]] = [list[j], list[i]]; }
        save(); drawExtras();
    }
    else if (b.dataset.nat) { state['nat' + b.dataset.nat] = b.dataset.natkey; save(); drawNats(); }
    else if (b.dataset.natplay) { const n = +b.dataset.natplay, s = await natOf(n, b.dataset.natkey), a = audio(), t0 = a.ac.currentTime + 0.05;
        s.play({ ...a, t0, hit: t0 + HIT[n] }); }
    else if (b.dataset.try) roll([[20, 1]], [[+b.dataset.try]]);
    else if (b.dataset.tab) { state.tab = b.dataset.tab; save(); drawSounds(); }
    else if (b.dataset.pick) { state.sound = b.dataset.pick; save(); drawSounds(); }
    else if (b.dataset.play) {
        const m = await soundOf(b.dataset.k), a = audio(), t = a.ac.currentTime + 0.05;
        m.throw?.({ ...a, t, count: 3 });
        if (b.dataset.play === 'roll') for (const [at, vol, table] of HITS) m.hit?.({ ...a, t: t + at, vol, onTable: !!table });
    }
    else if (b.dataset.roll) roll(b.dataset.roll.split(',').map((g) => g.split('x').map(Number)));
    else if (b.id === 'dd-landgo') roll([[+$('#dd-die').value, 1]], [[+$('#dd-num').value]]);
});
$('#dd-die').addEventListener('change', fillNums);
fillNums(); drawSets(); drawSounds(); drawNats(); drawExtras();
