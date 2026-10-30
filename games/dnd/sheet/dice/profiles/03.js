// Outdoors sound profiles: forest floor (moss, leaves, twigs) and beach pebbles (shingle, waves).
const R = Math.random;

// Filtered noise burst with an attack/decay envelope; f may glide to f2.
function burst(c, t, dur, type, f, q, peak, f2, atk = 0.003) {
    const ac = c.ac, s = ac.createBufferSource(), fl = ac.createBiquadFilter(), g = ac.createGain();
    s.buffer = c.noise; s.loop = true;
    fl.type = type; fl.Q.value = q; fl.frequency.setValueAtTime(f, t);
    if (f2) fl.frequency.exponentialRampToValueAtTime(f2, t + dur);
    g.gain.setValueAtTime(0.0001, t);
    g.gain.linearRampToValueAtTime(peak, t + atk);
    g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    s.connect(fl); fl.connect(g); g.connect(c.out);
    s.start(t, R() * 0.3); s.stop(t + dur + 0.02);
}

// Short decaying sine thump / knock.
function tone(c, t, f, f2, dur, peak, type = 'sine') {
    const ac = c.ac, o = ac.createOscillator(), g = ac.createGain();
    o.type = type; o.frequency.setValueAtTime(f, t);
    o.frequency.exponentialRampToValueAtTime(f2, t + dur);
    g.gain.setValueAtTime(peak, t);
    g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    o.connect(g); g.connect(c.out); o.start(t); o.stop(t + dur + 0.02);
}

// Many tiny clicks as gain spikes on ONE band-passed noise source.
function clicks(c, t, n, span, f, q, peak) {
    const ac = c.ac, s = ac.createBufferSource(), fl = ac.createBiquadFilter(), g = ac.createGain();
    s.buffer = c.noise; s.loop = true;
    fl.type = 'bandpass'; fl.frequency.value = f; fl.Q.value = q;
    g.gain.setValueAtTime(0, t);
    for (let i = 0; i < n; i++) {
        const ti = t + (i === 0 ? 0 : R() * span), p = peak * (0.35 + R() * 0.65);
        g.gain.setValueAtTime(0, ti);
        g.gain.linearRampToValueAtTime(p, ti + 0.0008);
        g.gain.exponentialRampToValueAtTime(0.0001, ti + 0.007 + R() * 0.006);
    }
    s.connect(fl); fl.connect(g); g.connect(c.out);
    s.start(t, R() * 0.3); s.stop(t + span + 0.03);
}

// Bird chirp: quick rising/falling sine with vibrato-free sweep.
function chirp(c, t, f, peak) {
    const ac = c.ac, o = ac.createOscillator(), g = ac.createGain();
    o.type = 'sine';
    o.frequency.setValueAtTime(f, t);
    o.frequency.exponentialRampToValueAtTime(f * 1.5, t + 0.05);
    o.frequency.exponentialRampToValueAtTime(f * 1.15, t + 0.1);
    g.gain.setValueAtTime(0.0001, t);
    g.gain.linearRampToValueAtTime(peak, t + 0.012);
    g.gain.exponentialRampToValueAtTime(0.0001, t + 0.11);
    o.connect(g); g.connect(c.out); o.start(t); o.stop(t + 0.13);
}

export default [
    {
        key: 'p03-forest-floor', name: 'Forest Floor',
        blurb: 'Dice thud softly over moss and crunch through dry leaves, with a twig snap and a bird at the end.',
        throw(c) {
            const t = c.t;
            burst(c, t, 0.28, 'bandpass', 1500, 1.2, 0.35, 4200, 0.08);
            burst(c, t + 0.05, 0.2, 'highpass', 3000, 0.7, 0.12, null, 0.05);
            clicks(c, t + 0.08, 6, 0.2, 3500, 3, 0.2);
        },
        hit(c) {
            const t = c.t, v = c.vol;
            if (c.onTable) {
                tone(c, t, 150 + R() * 40, 70, 0.1, 0.4 * v);
                burst(c, t, 0.06, 'lowpass', 600, 0.7, 0.25 * v);
                clicks(c, t + 0.008, 3 + Math.round(v * 5), 0.12, 2600 + R() * 2000, 4, 0.3 * v);
                if (v > 0.65) { // twig snap
                    burst(c, t + 0.01, 0.025, 'bandpass', 2200 + R() * 800, 6, 0.4 * v);
                    tone(c, t + 0.01, 900, 500, 0.03, 0.12 * v, 'triangle');
                }
            } else {
                const f = 380 + R() * 80;
                tone(c, t, f, f * 0.8, 0.07, 0.4 * v, 'triangle');
                tone(c, t, f * 2.3, f * 1.9, 0.04, 0.2 * v);
                burst(c, t, 0.025, 'bandpass', 1800, 2, 0.25 * v);
            }
        },
        settle(c) {
            const t = c.t;
            burst(c, t, 0.35, 'bandpass', 3000, 1.5, 0.25, 1800, 0.05);
            clicks(c, t, 8, 0.3, 3200, 3, 0.18);
            chirp(c, t + 0.35, 3200, 0.18);
            chirp(c, t + 0.5, 3600, 0.14);
            if (c.count > 1) chirp(c, t + 0.9, 2900, 0.1);
        },
    },
    {
        key: 'p03-beach-pebbles', name: 'Beach Pebbles',
        blurb: 'Gritty shingle clatter and a clean stone clack, ending with a wave washing in and out.',
        throw(c) {
            const t = c.t;
            clicks(c, t, 22, 0.25, 4200, 2.5, 0.45);
            burst(c, t, 0.25, 'highpass', 5000, 0.7, 0.1, null, 0.05);
        },
        hit(c) {
            const t = c.t, v = c.vol;
            if (c.onTable) {
                clicks(c, t, 4 + Math.round(v * 6), 0.06, 3500 + R() * 1500, 2, 0.5 * v);
                burst(c, t, 0.12, 'bandpass', 6000, 0.8, 0.12 * v); // sandy hiss
                tone(c, t, 220, 110, 0.05, 0.2 * v);
            } else {
                const f = 2400 + R() * 600;
                tone(c, t, f, f * 0.9, 0.04, 0.35 * v);
                tone(c, t, f * 1.6, f * 1.5, 0.025, 0.2 * v);
                burst(c, t, 0.015, 'bandpass', 4500, 3, 0.4 * v);
            }
        },
        settle(c) {
            const t = c.t, ac = c.ac;
            const s = ac.createBufferSource(), fl = ac.createBiquadFilter(), g = ac.createGain();
            s.buffer = c.noise; s.loop = true;
            fl.type = 'lowpass'; fl.Q.value = 0.5;
            fl.frequency.setValueAtTime(300, t);
            fl.frequency.exponentialRampToValueAtTime(1800, t + 0.9);
            fl.frequency.exponentialRampToValueAtTime(250, t + 2.0);
            g.gain.setValueAtTime(0.0001, t);
            g.gain.linearRampToValueAtTime(0.5, t + 0.9);
            g.gain.exponentialRampToValueAtTime(0.0001, t + 2.0);
            s.connect(fl); fl.connect(g); g.connect(c.out);
            s.start(t); s.stop(t + 2.05);
            clicks(c, t + 1.1, 8, 0.7, 4000, 2, 0.1); // pebbles dragged back
        },
    },
];
