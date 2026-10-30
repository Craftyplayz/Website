// Musical dice profiles: Marimba (pentatonic wooden notes) and Steel drum (major-scale pan notes).
const PENT = [0, 2, 4, 7, 9], MAJOR = [0, 2, 4, 5, 7, 9, 11];
const hz = (semi) => 261.63 * Math.pow(2, semi / 12);
const pick = (scale, oct = 0) => scale[(Math.random() * scale.length) | 0] + 12 * oct;

// One note: partials = [[ratio, gain, decayMul], ...]; peak is the summed amplitude; bend = start pitch multiplier.
function note(c, t, f, peak, dur, partials, bend = 1) {
    const { ac, out } = c;
    for (const [r, g, dm] of partials) {
        const o = ac.createOscillator(), e = ac.createGain();
        o.frequency.setValueAtTime(f * r * bend, t);
        if (bend !== 1) o.frequency.exponentialRampToValueAtTime(f * r, t + 0.04);
        const d = dur * dm;
        e.gain.setValueAtTime(0, t);
        e.gain.linearRampToValueAtTime(peak * g, t + 0.003);
        e.gain.exponentialRampToValueAtTime(0.0005, t + d);
        o.connect(e); e.connect(out);
        o.start(t); o.stop(t + d + 0.02);
    }
}
function click(c, t, level) {
    const s = c.ac.createBufferSource(), e = c.ac.createGain();
    s.buffer = c.noise;
    e.gain.setValueAtTime(level, t);
    e.gain.exponentialRampToValueAtTime(0.0005, t + 0.012);
    s.connect(e); e.connect(c.out);
    s.start(t); s.stop(t + 0.02);
}

const MAR = (bright) => [[1, 1, 1], [4, 0.12 + 0.2 * bright, 0.25]];
const marimba = (c, t, semi, peak, dur) => note(c, t, hz(semi), peak, dur, MAR(c.vol ?? 0.5));
const PAN = [[1, 1, 1], [2, 0.5, 0.8], [2.9, 0.3, 0.55], [4, 0.2, 0.4]];
const pan = (c, t, semi, peak, dur) => note(c, t, hz(semi), peak / 2, dur, PAN, 1.03);

export default [
    {
        key: 'p04-marimba', name: 'Marimba Run',
        blurb: 'Warm wooden mallet notes from a pentatonic scale: every bump plays a note, dice on dice ring an octave higher.',
        throw(c) {
            const run = [0, 2, 4, 7, 9, 12];
            run.forEach((s, i) => marimba({ ...c, vol: 0.5 }, c.t + i * 0.055, s + 5, 0.22, 0.35));
        },
        hit(c) {
            const v = Math.min(1, c.vol), peak = 0.12 + 0.45 * v;
            marimba(c, c.t, pick(PENT, c.onTable ? 0 : 1), c.onTable ? peak : peak * 0.7, c.onTable ? 0.35 : 0.28);
            click(c, c.t, 0.04 + 0.1 * v);
        },
        settle(c) {
            [0, 4, 7].forEach((s, i) => marimba({ ...c, vol: 0.4 }, c.t + i * 0.03, s, 0.17, 1.8));
            marimba({ ...c, vol: 0.4 }, c.t + 0.09, 12, 0.1, 1.8);
        },
    },
    {
        key: 'p04-steel-drum', name: 'Steel Drum Calypso',
        blurb: 'Bright steel-pan notes from a major scale, a tremolo roll on the throw and a cheerful ta-da when the dice stop.',
        throw(c) {
            for (let i = 0; i < 10; i++) pan(c, c.t + i * 0.05, 7, 0.14 + 0.012 * i, 0.3);
        },
        hit(c) {
            const v = Math.min(1, c.vol);
            if (c.onTable) pan(c, c.t, pick(MAJOR, 0), 0.14 + 0.42 * v, 0.4);
            else pan(c, c.t, pick(MAJOR, 2), 0.07 + 0.2 * v, 0.25);
        },
        settle(c) {
            pan(c, c.t, 7, 0.3, 0.5);
            pan(c, c.t + 0.14, 16, 0.38, 1.5);
            pan(c, c.t + 0.14, 12, 0.2, 1.5);
        },
    },
];
