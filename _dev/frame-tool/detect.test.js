// Self-check for detect.js: builds 4 synthetic 13-minute episodes (audio only) with a shared intro at
// different offsets, one altered intro, one episode without an intro, and shared end credits.
// Run: node _dev/frame-tool/detect.test.js   (needs ffmpeg; ~1 minute)
const assert = require('node:assert');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { detectSeason, fingerprint } = require('./detect');

const FFMPEG = (n => fs.existsSync(n) ? n : 'ffmpeg')(path.join(process.env.LOCALAPPDATA || '', 'Microsoft', 'WinGet', 'Links', 'ffmpeg.exe'));
const LEN = 780, INTRO = 50, CREDITS = 45;
const fmt = 'aformat=sample_fmts=fltp:sample_rates=44100:channel_layouts=mono';
// a different note sequence per episode, so bodies never match each other
// (noise is no good here: every kind of noise fingerprints alike, so noise "episodes" match each other)
const body = (d, n) => `aevalsrc='0.4*sin(2*PI*t*(180+15*mod(floor(t*4)*${13 + n * 6}+${n * 5},41)))+0.2*sin(2*PI*t*(500+23*mod(floor(t*5)*${7 + n * 4},29)))':d=${d}:s=44100,${fmt}`;
const melody = (d, base) => `aevalsrc='0.5*sin(2*PI*t*(${base}+${base / 2}*mod(floor(t*3),7)))+0.2*sin(2*PI*t*(${base * 1.5}+40*mod(floor(t*2),5)))':d=${d}:s=44100,${fmt}`;
const intro = melody(INTRO, 220), credits = melody(CREDITS, 330);
const altered = `${intro},volume=-6dB[ia];anoisesrc=d=${INTRO}:c=white:seed=99:a=0.03,${fmt}[iz];[ia][iz]amix=inputs=2:normalize=0`;

// episodes as [segment, ...]; truth in seconds
const EPS = [
    { intro: 0, credits: true, segs: [intro, body(LEN - INTRO - CREDITS, 1), credits] },
    { intro: 30, credits: true, segs: [body(30, 2), intro, body(LEN - 30 - INTRO - CREDITS, 2), credits] },
    { intro: 75, credits: true, segs: [body(75, 3), altered, body(LEN - 75 - INTRO - CREDITS, 3), credits] },
    { intro: null, credits: false, segs: [body(LEN, 4)] },
];

const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'detect-test-'));
// the skipped range must cover the whole intro (±1 s), and be padded by no more than 8 s at either end (the 2 s averaging window plus PAD)
const near = (got, want, what) => assert.ok(got && got[0] <= want[0] + 1 && got[0] >= want[0] - 8 && got[1] >= Math.min(want[1] - 1, LEN) && got[1] <= want[1] + 8,
    `${what}: got ${JSON.stringify(got)}, want ~${JSON.stringify(want)}`);

(async () => {
    try {
        const files = EPS.map((ep, i) => {
            const file = path.join(dir, `ep${i + 1}.mkv`);
            const graph = ep.segs.map((s, k) => `${s}[s${k}]`).join(';') + ';' +
                ep.segs.map((_, k) => `[s${k}]`).join('') + `concat=n=${ep.segs.length}:v=0:a=1[out]`;
            execFileSync(FFMPEG, ['-hide_banner', '-loglevel', 'error', '-filter_complex', graph, '-map', '[out]', '-c:a', 'flac', '-y', file]);
            return { id: 'e' + (i + 1), file, duration: LEN };
        });

        let t = Date.now();
        const fp = await fingerprint(files[0].file, 0, 600);
        console.log(`fingerprint of a 10-min window (synthetic flac): ${Date.now() - t} ms, ${fp.items.length} items`);

        t = Date.now();
        const calls = [];
        const res = await detectSeason(files, { intro: true, credits: true }, (d, n) => calls.push(d + '/' + n));
        console.log(`detectSeason, 4 episodes: ${Date.now() - t} ms`);
        for (const [id, r] of res) console.log(id, JSON.stringify(r));

        EPS.forEach((ep, i) => {
            const r = res.get('e' + (i + 1));
            if (ep.intro === null) assert.strictEqual(r.intro, null, `e${i + 1} has no intro`);
            else near(r.intro, [ep.intro, ep.intro + INTRO], `e${i + 1} intro`);
            if (!ep.credits) assert.strictEqual(r.credits, null, `e${i + 1} has no credits`);
            else near(r.credits, [LEN - CREDITS, LEN], `e${i + 1} credits`);
        });
        assert.strictEqual(calls.at(-1), '8/8');

        // one episode: nothing to compare with; a missing file: nulls, no throw
        const one = await detectSeason(files.slice(0, 1), { intro: true, credits: true });
        assert.deepStrictEqual(one.get('e1'), { intro: null, credits: null });
        const bad = await detectSeason([...files.slice(0, 2), { id: 'x', file: path.join(dir, 'missing.mkv'), duration: LEN }], { intro: true });
        assert.strictEqual(bad.get('x').intro, null);
        near(bad.get('e1').intro, [0, INTRO], 'e1 intro next to a bad file');

        console.log('all checks passed');
    } finally {
        fs.rmSync(dir, { recursive: true, force: true });
    }
})().catch(e => { console.error(e); process.exitCode = 1; });
