// Sci-fi sound profiles: Hologram (glassy digital blips) and Mech (servo clunks and hydraulics).

// Oscillator with an exponential pitch glide and a pluck envelope, through an optional filter.
function tone(c, type, f0, f1, t, dur, peak, att = 0.003, filt) {
    const { ac, out } = c;
    const o = ac.createOscillator(), g = ac.createGain();
    o.type = type;
    o.frequency.setValueAtTime(f0, t);
    if (f1 !== f0) o.frequency.exponentialRampToValueAtTime(f1, t + dur);
    g.gain.setValueAtTime(0.0001, t);
    g.gain.linearRampToValueAtTime(peak, t + att);
    g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    let last = o;
    if (filt) {
        const f = ac.createBiquadFilter();
        f.type = filt[0]; f.frequency.value = filt[1]; f.Q.value = filt[2] || 0.7;
        o.connect(f); last = f;
    }
    last.connect(g); g.connect(out);
    o.start(t); o.stop(t + dur + 0.02);
}

// Filtered noise burst; fHi optionally sweeps the filter frequency.
function puff(c, type, f, fEnd, q, t, dur, peak, att = 0.004) {
    const { ac, out, noise } = c;
    const s = ac.createBufferSource(), fl = ac.createBiquadFilter(), g = ac.createGain();
    s.buffer = noise; s.loop = true;
    fl.type = type; fl.Q.value = q;
    fl.frequency.setValueAtTime(f, t);
    if (fEnd !== f) fl.frequency.exponentialRampToValueAtTime(fEnd, t + dur);
    g.gain.setValueAtTime(0.0001, t);
    g.gain.linearRampToValueAtTime(peak, t + att);
    g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    s.connect(fl); fl.connect(g); g.connect(out);
    s.start(t); s.stop(t + dur + 0.02);
}

// FM blip: sine carrier modulated by a sine with a decaying index.
function fm(c, fc, ratio, idx, f1, t, dur, peak) {
    const { ac, out } = c;
    const o = ac.createOscillator(), m = ac.createOscillator();
    const mg = ac.createGain(), g = ac.createGain();
    o.frequency.setValueAtTime(fc, t);
    o.frequency.exponentialRampToValueAtTime(f1, t + dur);
    m.frequency.setValueAtTime(fc * ratio, t);
    m.frequency.exponentialRampToValueAtTime(f1 * ratio, t + dur);
    mg.gain.setValueAtTime(fc * idx, t);
    mg.gain.exponentialRampToValueAtTime(1, t + dur);
    g.gain.setValueAtTime(0.0001, t);
    g.gain.linearRampToValueAtTime(peak, t + 0.002);
    g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    m.connect(mg); mg.connect(o.frequency);
    o.connect(g); g.connect(out);
    o.start(t); m.start(t); o.stop(t + dur + 0.02); m.stop(t + dur + 0.02);
}

export default [
    {
        key: 'p02-hologram', name: 'Hologram',
        blurb: 'Dice made of light: glassy digital blips, a projection sweep and a soft confirmation chime.',
        throw(c) {
            const t = c.t;
            tone(c, 'sine', 300, 1800, t, 0.4, 0.28, 0.05);
            tone(c, 'triangle', 600, 3600, t, 0.4, 0.1, 0.06, ['lowpass', 5000, 0.7]);
            puff(c, 'highpass', 4000, 9000, 0.7, t, 0.45, 0.07, 0.1);
        },
        hit(c) {
            const { t, vol, onTable } = c;
            const f = (onTable ? 500 : 1100) * (0.8 + vol * 0.9);
            fm(c, f * 1.5, onTable ? 2 : 3.01, onTable ? 1.2 : 1.8, f * 0.8, t, onTable ? 0.14 : 0.1, 0.5 * vol + 0.05);
            // quick digital crackle shimmer
            puff(c, 'bandpass', onTable ? 5000 : 8000, 3000, 4, t, 0.04, 0.12 * vol);
        },
        settle(c) {
            const t = c.t;
            tone(c, 'sine', 880, 880, t, 0.5, 0.3, 0.005);
            tone(c, 'sine', 1318.5, 1318.5, t + 0.13, 0.9, 0.3, 0.005);
            tone(c, 'sine', 2637, 2637, t + 0.13, 0.9, 0.06, 0.005);
            // fading digital shimmer: stuttering high ticks
            for (let i = 0; i < 6; i++) {
                const a = 0.08 * Math.pow(0.65, i);
                tone(c, 'sine', 3000 + (i % 3) * 700, 2500, t + 0.25 + i * 0.09, 0.05, a, 0.002);
            }
        },
    },
    {
        key: 'p02-mech', name: 'Mech',
        blurb: 'Heavy robotic dice: servo clunks, sharp metal clanks, hydraulic release and locking latches.',
        throw(c) {
            const t = c.t;
            tone(c, 'sawtooth', 120, 900, t, 0.5, 0.2, 0.05, ['lowpass', 1800, 2]);
            tone(c, 'square', 60, 450, t, 0.5, 0.08, 0.05, ['lowpass', 900, 1]);
            // hydraulic release
            puff(c, 'bandpass', 3000, 1200, 1.2, t + 0.5, 0.35, 0.3, 0.01);
            tone(c, 'sine', 70, 45, t + 0.5, 0.2, 0.4, 0.004);
        },
        hit(c) {
            const { t, vol, onTable } = c;
            if (onTable) {
                tone(c, 'sine', 140, 55, t, 0.16, 0.65 * vol + 0.05, 0.002);
                tone(c, 'sawtooth', 500 + 300 * vol, 220, t, 0.12, 0.12 * vol, 0.003, ['lowpass', 1500, 3]);
                puff(c, 'bandpass', 2500, 1500, 3, t, 0.03, 0.2 * vol, 0.001);
            } else {
                // sharp metal clank: inharmonic square pair
                tone(c, 'square', 1870, 1800, t, 0.12, 0.14 * vol + 0.02, 0.001, ['bandpass', 2400, 4]);
                tone(c, 'square', 2930, 2850, t, 0.09, 0.1 * vol + 0.01, 0.001, ['bandpass', 3400, 4]);
                tone(c, 'sine', 300, 120, t, 0.08, 0.35 * vol, 0.002);
            }
            if (vol > 0.6) puff(c, 'highpass', 6000, 6000, 0.7, t + 0.02, 0.1, 0.12 * vol, 0.01);
        },
        settle(c) {
            const t = c.t;
            [0, 0.11].forEach((d, i) => {
                tone(c, 'sine', 120 - i * 15, 50, t + d, 0.15, 0.55, 0.002);
                tone(c, 'square', 1500 + i * 400, 1300, t + d, 0.06, 0.1, 0.001, ['bandpass', 2200, 5]);
            });
            tone(c, 'sawtooth', 700, 70, t + 0.25, 1.1, 0.16, 0.02, ['lowpass', 1200, 1.5]);
            tone(c, 'sine', 350, 35, t + 0.25, 1.1, 0.2, 0.02);
        },
    },
];
