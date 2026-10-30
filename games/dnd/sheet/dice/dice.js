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
async function roll(groups, land = null) {
    out.textContent = 'Rolling...';
    try {
        const [m, set, snd, n1, n20] = await Promise.all([load(), setOf(state.set), soundOf(state.sound), natOf(1, state.nat1), natOf(20, state.nat20)]);
        // the set as it is, with its throw and rolling sounds swapped when another sound is chosen, and the chosen nat 1 and nat 20 sounds
        const style = { ...set, ...(state.sound !== 'own' && { sound: { ...set.sound, throw: snd.throw, hit: snd.hit } }),
            natSounds: { 1: n1 && ((c) => n1.play(c)), 20: n20 && ((c) => n20.play(c)) } };
        // a new roll takes the last one's dice off the table first, even mid-roll
        m.clearTable(true);
        const res = await m.throwDice(groups, { style, sound: soundBox.checked, land });
        out.textContent = res.flatMap(([n, r]) => r.map((v) => `d${n} ${v}`)).join(' · ');
    } catch (e) { out.textContent = "Couldn't roll. Try again."; }
}

document.addEventListener('click', async (e) => {
    const b = e.target.closest('button'); if (!b) return;
    // the set's own sound row names the set, so it redraws too
    if (b.dataset.set) { state.set = b.dataset.set; save(); drawSets(); drawSounds(); drawNats(); }
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
fillNums(); drawSets(); drawSounds(); drawNats();
