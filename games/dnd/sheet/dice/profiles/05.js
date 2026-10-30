// Retro sound profiles: 8-bit console chiptune, and lo-fi vinyl with warm muffled knocks.
const g = (ac, out, t, peak, dur, atk = 0.003) => {
    const n = ac.createGain();
    n.gain.setValueAtTime(0.0001, t);
    n.gain.linearRampToValueAtTime(peak, t + atk);
    n.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    n.connect(out);
    return n;
};
const tone = (ac, out, type, f0, f1, t, dur, peak, cut = 2400, atk = 0.003) => {
    const o = ac.createOscillator(), lp = ac.createBiquadFilter();
    o.type = type; lp.type = 'lowpass'; lp.frequency.value = cut;
    o.frequency.setValueAtTime(f0, t);
    if (f1 !== f0) o.frequency.exponentialRampToValueAtTime(f1, t + dur);
    o.connect(lp); lp.connect(g(ac, out, t, peak, dur, atk));
    o.start(t); o.stop(t + dur + 0.02);
};
const puff = (ac, out, noise, type, freq, q, t, dur, peak, atk = 0.002) => {
    const s = ac.createBufferSource(), f = ac.createBiquadFilter();
    s.buffer = noise; s.loop = true; f.type = type; f.frequency.value = freq; f.Q.value = q;
    s.connect(f); f.connect(g(ac, out, t, peak, dur, atk));
    const off = Math.random() * 0.2;
    s.start(t, off); s.stop(t + dur + 0.02);
};
// sparse tiny clicks: a few very short highpassed noise ticks
const crackle = (ac, out, noise, t, dur, n, peak) => {
    for (let i = 0; i < n; i++) puff(ac, out, noise, 'highpass', 2500 + Math.random() * 3000, 0.7, t + Math.random() * dur, 0.006, peak * (0.4 + Math.random() * 0.6), 0.0005);
};
const mtof = m => 440 * Math.pow(2, (m - 69) / 12);

export default [
    {
        key: 'p05-8bit', name: '8-Bit Console', blurb: 'Chiptune bloops, a jump arpeggio when thrown and a three-note item jingle when the dice settle.',
        throw(c) {
            const { ac, out, t } = c;
            [60, 64, 67, 72, 76].forEach((m, i) => tone(ac, out, 'square', mtof(m), mtof(m), t + i * 0.055, 0.07, 0.22, 2800));
            puff(ac, out, c.noise, 'highpass', 4000, 0.5, t, 0.04, 0.12);
        },
        hit(c) {
            const { ac, out, t, vol, onTable } = c;
            const v = 0.15 + 0.4 * vol;
            if (onTable) {
                const f = 200 + Math.random() * 120;
                tone(ac, out, 'triangle', f * 1.6, f * 0.5, t, 0.09, v, 1800);
                puff(ac, out, c.noise, 'bandpass', 3000, 1, t, 0.025, v * 0.5);
            } else {
                const f = 880 + Math.random() * 300;
                tone(ac, out, 'square', f, f * 0.7, t, 0.05, v * 0.55, 2600);
            }
        },
        settle(c) {
            const { ac, out, t } = c;
            [[67, 0], [72, 0.09], [79, 0.18]].forEach(([m, d], i) => tone(ac, out, 'square', mtof(m), mtof(m), t + d, i === 2 ? 0.35 : 0.1, 0.22, 2600));
            tone(ac, out, 'triangle', mtof(55), mtof(55), t + 0.18, 0.35, 0.3, 1200);
        },
    },
    {
        key: 'p05-lofi', name: 'Lo-Fi Vinyl', blurb: 'Soft muffled knocks over vinyl crackle, a tape-start whoosh and a mellow Fmaj7 chord on settle.',
        throw(c) {
            const { ac, out, noise, t } = c;
            const s = ac.createBufferSource(), f = ac.createBiquadFilter();
            s.buffer = noise; s.loop = true; f.type = 'lowpass'; f.Q.value = 1.5;
            f.frequency.setValueAtTime(200, t); f.frequency.exponentialRampToValueAtTime(1800, t + 0.35);
            s.connect(f); f.connect(g(ac, out, t, 0.22, 0.5, 0.15));
            s.start(t); s.stop(t + 0.55);
            tone(ac, out, 'sine', 90, 220, t, 0.4, 0.15, 800, 0.1);
            crackle(ac, out, noise, t, 0.5, 8, 0.15);
        },
        hit(c) {
            const { ac, out, t, vol, onTable } = c;
            const v = 0.12 + 0.45 * vol;
            if (onTable) {
                const f = 110 + Math.random() * 40;
                tone(ac, out, 'sine', f * 1.5, f, t, 0.14, v, 1700);
                puff(ac, out, c.noise, 'lowpass', 1600, 0.7, t, 0.06, v * 0.7);
            } else {
                const f = 700 + Math.random() * 200;
                tone(ac, out, 'triangle', f, f * 0.9, t, 0.05, v * 0.5, 1900);
                puff(ac, out, c.noise, 'bandpass', 1500, 3, t, 0.03, v * 0.5);
            }
            if (Math.random() < 0.5) crackle(ac, out, c.noise, t, 0.12, 1, 0.08);
        },
        settle(c) {
            const { ac, out, t } = c;
            [53, 60, 64, 69, 76].forEach((m, i) => {
                const t0 = t + i * 0.025, f = mtof(m);
                tone(ac, out, 'sine', f * 1.003, f * 1.003, t0, 1.8, 0.14, 1800, 0.01);
                tone(ac, out, 'triangle', f * 0.997, f * 0.997, t0, 1.2, 0.05, 1500, 0.01);
                tone(ac, out, 'sine', f * 2, f * 2, t0, 0.3, 0.04, 2000, 0.003); // tine
            });
            crackle(ac, out, c.noise, t, 1.6, 8, 0.12);
        },
    },
];
