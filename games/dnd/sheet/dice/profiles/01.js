// Ambient-space sound profiles: a damp dungeon cave and a vast castle hall, each with a shared convolution reverb.
const reverbs = new WeakMap();

// One convolver per AudioContext and kind, built once. Decaying noise, optionally darkened, plus early reflections.
function reverb(c, kind) {
    const { ac, out } = c;
    let m = reverbs.get(ac);
    if (!m) reverbs.set(ac, (m = {}));
    if (m[kind]) return m[kind];
    const cfg = kind === 'cave' ? { dur: 2.8, k: 3.2, dark: 0.82, er: [0.031, 0.067, 0.113] }
                                : { dur: 2.4, k: 3.8, dark: 0.35, er: [0.012, 0.027, 0.043, 0.061, 0.089] };
    const len = Math.floor(ac.sampleRate * cfg.dur);
    const buf = ac.createBuffer(2, len, ac.sampleRate);
    for (let ch = 0; ch < 2; ch++) {
        const d = buf.getChannelData(ch);
        let lp = 0;
        for (let i = 0; i < len; i++) {
            const tt = i / len;
            lp += (Math.random() * 2 - 1 - lp) * (1 - cfg.dark * (0.6 + 0.4 * tt));
            d[i] = lp * Math.pow(1 - tt, cfg.k) * 0.9;
        }
        cfg.er.forEach((e, j) => { const p = Math.floor((e + ch * 0.004) * ac.sampleRate); if (p < len) d[p] += (j % 2 ? -1 : 1) * 0.9 / (1 + j * 0.6); });
    }
    const cv = ac.createConvolver();
    cv.buffer = buf;
    const g = ac.createGain();
    g.gain.value = kind === 'cave' ? 0.55 : 0.5;
    cv.connect(g).connect(out);
    return (m[kind] = cv);
}

function env(c, dest, t, dur, peak, atk = 0.002) {
    const g = c.ac.createGain();
    g.gain.setValueAtTime(0.0001, t);
    g.gain.exponentialRampToValueAtTime(Math.max(peak, 0.0002), t + atk);
    g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    g.connect(dest);
    return g;
}

// Filtered noise burst. dry goes to out, wet goes to the reverb send.
function burst(c, t, dur, type, f, q, dry, wet, rv, ft = f) {
    const s = c.ac.createBufferSource();
    s.buffer = c.noise; s.loop = true;
    const fl = c.ac.createBiquadFilter();
    fl.type = type; fl.Q.value = q;
    fl.frequency.setValueAtTime(f, t);
    fl.frequency.exponentialRampToValueAtTime(ft, t + dur);
    s.connect(fl);
    if (dry) fl.connect(env(c, c.out, t, dur, dry));
    if (wet) fl.connect(env(c, rv, t, dur, wet));
    s.start(t, Math.random() * 0.3); s.stop(t + dur + 0.02);
}

function tone(c, t, f0, f1, dur, dry, wet, rv, atk = 0.002) {
    const o = c.ac.createOscillator();
    o.frequency.setValueAtTime(f0, t);
    o.frequency.exponentialRampToValueAtTime(f1, t + dur);
    if (dry) o.connect(env(c, c.out, t, dur, dry, atk));
    if (wet) o.connect(env(c, rv, t, dur, wet, atk));
    o.start(t); o.stop(t + dur + 0.02);
}

const drip = (c, t, a, rv) => { const f = 700 + Math.random() * 500; tone(c, t, f, f * 2.1, 0.09, a * 0.5, a, rv); };

export default [
    {
        key: 'p01-dungeon-cave',
        name: 'Dungeon Cave',
        blurb: 'Dice clack on damp stone far underground, with a long dark echo, stray water drips and a distant plip as they settle.',
        throw(c) {
            const rv = reverb(c, 'cave');
            const n = Math.min(4 + c.count * 2, 14);
            for (let i = 0; i < n; i++) {
                const t = c.t + i * 0.035 + Math.random() * 0.03;
                burst(c, t, 0.07, 'bandpass', 1400 + Math.random() * 900, 2.5, 0.22, 0.4, rv);
            }
            burst(c, c.t, 0.5, 'lowpass', 900, 0.7, 0.12, 0.3, rv, 200);
        },
        hit(c) {
            const rv = reverb(c, 'cave');
            const v = c.vol;
            if (c.onTable) {
                burst(c, c.t, 0.09, 'bandpass', 1100 + Math.random() * 400, 2, 0.55 * v, 0.5 * v, rv);
                tone(c, c.t, 190, 90, 0.12, 0.3 * v, 0.2 * v, rv);
            } else {
                burst(c, c.t, 0.05, 'bandpass', 2600 + Math.random() * 600, 4, 0.45 * v, 0.4 * v, rv);
            }
            if (Math.random() < 0.1) drip(c, c.t + 0.05 + Math.random() * 0.15, 0.15 * v, rv);
        },
        settle(c) {
            const rv = reverb(c, 'cave');
            burst(c, c.t, 1.2, 'lowpass', 500, 0.7, 0.07, 0.35, rv, 120);
            tone(c, c.t, 120, 70, 0.3, 0.2, 0.3, rv);
            drip(c, c.t + 0.25, 0.4, rv);
            drip(c, c.t + 1.3, 0.18, rv);
        },
    },
    {
        key: 'p01-castle-hall',
        name: 'Castle Hall',
        blurb: 'Crisp clicks on polished flagstones in a vast hall with a bright echo, a low boom as they settle and a faint torch crackle.',
        throw(c) {
            const rv = reverb(c, 'hall');
            burst(c, c.t, 0.45, 'bandpass', 600, 1.2, 0.1, 0.35, rv, 5000);
            const n = Math.min(5 + c.count * 2, 16);
            for (let i = 0; i < n; i++) {
                const t = c.t + 0.05 + i * 0.03 + Math.random() * 0.03;
                burst(c, t, 0.04, 'highpass', 3000 + Math.random() * 1500, 1, 0.14, 0.3, rv);
            }
        },
        hit(c) {
            const rv = reverb(c, 'hall');
            const v = c.vol;
            if (c.onTable) {
                burst(c, c.t, 0.05, 'highpass', 2800 + Math.random() * 800, 1.5, 0.5 * v, 0.45 * v, rv);
                tone(c, c.t, 900, 450, 0.06, 0.2 * v, 0.2 * v, rv);
            } else {
                burst(c, c.t, 0.035, 'bandpass', 4500 + Math.random() * 1200, 5, 0.45 * v, 0.45 * v, rv);
                tone(c, c.t, 2400, 2000, 0.05, 0.1 * v, 0.15 * v, rv);
            }
        },
        settle(c) {
            const rv = reverb(c, 'hall');
            tone(c, c.t, 90, 45, 1.0, 0.4, 0.45, rv, 0.02);
            burst(c, c.t, 0.3, 'lowpass', 700, 0.7, 0.05, 0.25, rv, 150);
            for (let i = 0; i < 14; i++) {
                const t = c.t + 0.1 + Math.random() * 1.4;
                burst(c, t, 0.012, 'highpass', 4000 + Math.random() * 2000, 1, 0.07 + Math.random() * 0.06, 0, rv);
            }
        },
    },
];
