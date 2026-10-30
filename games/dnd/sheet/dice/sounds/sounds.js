// Ten sound mockups for the dice: each has throw (as the dice leave the hand) and hit (every bump while they roll), in the same shape as a set's sound.
// hit ctx: { ac, out, noise, vol 0-1, onTable, t }; throw ctx: { ac, out, noise, t, count }. Everything is scheduled from ctx.t.
const env = (ac, at, peak, a, d) => { const g = ac.createGain(); g.gain.setValueAtTime(0.0001, at); g.gain.linearRampToValueAtTime(Math.max(0.0002, peak), at + a); g.gain.exponentialRampToValueAtTime(0.0001, at + a + d); return g; };
const nz = (ac, noise, at, dur) => { const s = ac.createBufferSource(); s.buffer = noise; s.loop = true; s.start(at, Math.random() * 0.3); s.stop(at + dur + 0.02); return s; };
const filt = (ac, type, f, q = 1) => { const b = ac.createBiquadFilter(); b.type = type; b.frequency.value = f; b.Q.value = q; return b; };
const osc = (ac, at, f, dur, type = 'sine') => { const o = ac.createOscillator(); o.type = type; o.frequency.setValueAtTime(f, at); o.start(at); o.stop(at + dur + 0.02); return o; };
const chain = (...n) => { for (let i = 0; i < n.length - 1; i++) n[i].connect(n[i + 1]); };
const r = (a, b) => a + Math.random() * (b - a);
// a filtered noise click: the basic "die touches something"
const click = (c, at, f, q, peak, d) => chain(nz(c.ac, c.noise, at, d + 0.01), filt(c.ac, 'bandpass', f, q), env(c.ac, at, peak, 0.002, d), c.out);
// a sine that drops in pitch: weight landing
const thump = (c, at, f1, f2, peak, d) => { const o = osc(c.ac, at, f1, d); o.frequency.exponentialRampToValueAtTime(f2, at + d); chain(o, env(c.ac, at, peak, 0.003, d), c.out); };
// a ringing tone made of partials: metal or glass
const ring = (c, at, base, parts, peak, d, type = 'sine') => { const g = env(c.ac, at, peak, 0.002, d); g.connect(c.out);
    parts.forEach(([m, v]) => { const o = osc(c.ac, at, base * m, d, type), og = c.ac.createGain(); og.gain.value = v; chain(o, og, g); }); };
// a burst of noise swept through a filter: air, cloth, fire
const sweep = (c, at, type, f1, f2, q, peak, d) => { const f = filt(c.ac, type, f1, q); f.frequency.exponentialRampToValueAtTime(f2, at + d);
    chain(nz(c.ac, c.noise, at, d), f, env(c.ac, at, peak, d * 0.35, d * 0.65), c.out); };
// many small clicks spread over a time: dice rattling together
const rattle = (c, at, n, span, f1, f2, peak, lp) => { for (let i = 0; i < n; i++) { const tt = at + Math.random() * span;
    const s = nz(c.ac, c.noise, tt, 0.03), b = filt(c.ac, 'bandpass', r(f1, f2), 3), g = env(c.ac, tt, peak * r(0.5, 1), 0.001, 0.025);
    lp ? chain(s, b, filt(c.ac, 'lowpass', lp), g, c.out) : chain(s, b, g, c.out); } };

export default [
    {
        key: 'felt', name: 'Felt mat', blurb: 'Soft, muffled thuds like dice on a cloth playmat; a quiet swish of cloth on the throw.',
        throw: (c) => sweep(c, c.t, 'bandpass', 350, 900, 0.8, 0.3, 0.32),
        hit: (c) => c.onTable ? (click(c, c.t, 500, 0.7, c.vol * 0.5, 0.06), thump(c, c.t, 95, 60, c.vol * 0.35, 0.08)) : click(c, c.t, 2000, 2, c.vol * 0.25, 0.03),
    },
    {
        key: 'wood', name: 'Wooden tray', blurb: 'Hollow wooden clacks with a little body to them; a short rattle in the hand as they go.',
        throw: (c) => rattle(c, c.t, 6, 0.22, 2200, 3200, 0.3),
        hit: (c) => { const f = c.onTable ? r(800, 1000) : r(2800, 3400); click(c, c.t, f, 4, c.vol * 0.55, 0.05);
            if (c.onTable) ring(c, c.t, r(200, 240), [[1, 1], [2.3, 0.3]], c.vol * 0.25, 0.12); },
    },
    {
        key: 'hands', name: 'Shaken in hands', blurb: 'Dice shaken in cupped hands before they fly, then crisp resin clicks on a hard table.',
        throw: (c) => { rattle(c, c.t, 14, 0.42, 3000, 4500, 0.4, 2600); sweep(c, c.t + 0.4, 'highpass', 1500, 3500, 0.7, 0.08, 0.18); },
        hit: (c) => { click(c, c.t, 4200, 1.2, c.vol * 0.55, 0.022); click(c, c.t, 1200, 1.5, c.vol * (c.onTable ? 0.3 : 0.12), 0.04); },
    },
    {
        key: 'glass', name: 'Glass table', blurb: 'Bright, glassy tinks that ring briefly; a quick airy swish on the throw.',
        throw: (c) => sweep(c, c.t, 'highpass', 2500, 6000, 0.7, 0.1, 0.18),
        hit: (c) => { ring(c, c.t, r(2300, 2900) * (c.onTable ? 1 : 1.3), [[1, 1], [1.59, 0.4], [2.71, 0.2]], c.vol * 0.22, c.onTable ? 0.25 : 0.35); click(c, c.t, 5000, 1, c.vol * 0.2, 0.01); },
    },
    {
        key: 'metal', name: 'Metal dice on stone', blurb: 'Heavy clanks with a metal ring; two clinks in the palm on the throw.',
        throw: (c) => { ring(c, c.t, 1500, [[1, 1], [2.76, 0.4]], 0.08, 0.3); ring(c, c.t + 0.09, 1750, [[1, 1], [2.76, 0.4]], 0.07, 0.3); },
        hit: (c) => { ring(c, c.t, r(600, 750) * (c.onTable ? 1 : 2), [[1, 1], [2.76, 0.45], [5.4, 0.2]], c.vol * 0.3, c.onTable ? 0.3 : 0.5);
            if (c.onTable) thump(c, c.t, 120, 70, c.vol * 0.35, 0.07); },
    },
    {
        key: 'cup', name: 'Leather dice cup', blurb: 'A muffled rattle in a leather cup, slapped down; firm, medium clacks as they roll.',
        throw: (c) => { rattle(c, c.t, 10, 0.45, 1500, 2600, 0.45, 1400); thump(c, c.t + 0.5, 80, 50, 0.4, 0.12); click(c, c.t + 0.5, 300, 0.7, 0.3, 0.08); },
        hit: (c) => { click(c, c.t, c.onTable ? 1600 : 2800, 3, c.vol * 0.5, 0.04); if (c.onTable) ring(c, c.t, 160, [[1, 1]], c.vol * 0.2, 0.06); },
    },
    {
        key: 'hearth', name: 'Ember hearth', blurb: 'Low iron thunks with a crackle of embers; a soft, low fwoomp of fire on the throw instead of a bright whoosh.',
        throw: (c) => { const f = filt(c.ac, 'lowpass', 200, 0.8); f.frequency.exponentialRampToValueAtTime(1100, c.t + 0.18); f.frequency.exponentialRampToValueAtTime(250, c.t + 0.55);
            chain(nz(c.ac, c.noise, c.t, 0.6), f, env(c.ac, c.t, 0.3, 0.12, 0.45), c.out); thump(c, c.t, 70, 45, 0.25, 0.4); },
        hit: (c) => { thump(c, c.t, c.onTable ? 160 : 320, c.onTable ? 90 : 200, c.vol * 0.4, 0.12); click(c, c.t, 900, 1, c.vol * 0.25, 0.07);
            for (let i = 0; i < 2; i++) click(c, c.t + r(0.02, 0.14), 5000, 1, c.vol * 0.12, 0.012); },
    },
    {
        key: 'whisper', name: 'Whisper quiet', blurb: 'Barely-there soft ticks so rolling never gets in the way; no sound on the throw.',
        throw: () => { },
        hit: (c) => click(c, c.t, c.onTable ? 2500 : 4000, 1.5, c.vol * 0.16, 0.02),
    },
    {
        key: 'arcade', name: 'Arcade', blurb: 'Little game-like blips that rise with each harder hit; a rising chirp on the throw.',
        throw: (c) => { const o = osc(c.ac, c.t, 300, 0.16, 'square'); o.frequency.exponentialRampToValueAtTime(1200, c.t + 0.15); chain(o, filt(c.ac, 'lowpass', 3000), env(c.ac, c.t, 0.14, 0.01, 0.15), c.out); },
        hit: (c) => { const o = osc(c.ac, c.t, (c.onTable ? 260 : 520) + c.vol * 700, 0.07, c.onTable ? 'square' : 'triangle'); chain(o, filt(c.ac, 'lowpass', 2500), env(c.ac, c.t, c.vol * 0.1, 0.002, 0.06), c.out); },
    },
    {
        key: 'real', name: 'Realistic resin', blurb: 'A sharp click with a low table knock under it, like real plastic dice on a wooden table; a quick rattle and swish as they leave the hand.',
        throw: (c) => { rattle(c, c.t, 4, 0.08, 3000, 4200, 0.3); sweep(c, c.t + 0.05, 'bandpass', 600, 1800, 0.8, 0.18, 0.2); },
        hit: (c) => { click(c, c.t, r(3200, 3900), 1, c.vol * 0.5, 0.02); click(c, c.t, 420, 1, c.vol * (c.onTable ? 0.35 : 0.1), 0.05);
            if (c.onTable) thump(c, c.t, 115, 85, c.vol * 0.25, 0.09); },
    },
];
