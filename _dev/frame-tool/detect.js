// Finds intros and end credits in the episodes of one season by matching audio fingerprints between episodes.
// A stretch of audio that also plays in another episode of the same folder is the theme / credits music.
// Uses ffmpeg's chromaprint muxer (raw sub-fingerprints), no npm packages.
const fs = require('node:fs');
const path = require('node:path');
const { spawn } = require('node:child_process');

// winget puts ffmpeg here; a terminal opened before the install won't have it on PATH yet
function tool(name) {
    const linked = path.join(process.env.LOCALAPPDATA || '', 'Microsoft', 'WinGet', 'Links', name + '.exe');
    return fs.existsSync(linked) ? linked : name;
}
const FFMPEG = tool('ffmpeg');

// Measured, not assumed: 600 s of noise gives 4825 items and 60 s gives 463, so (4825 - 463) / 540 s
// = 0.12380 s per item (chromaprint resamples to 11025 Hz, 4096-sample frames, 1/3 hop: 4096 / 3 / 11025).
// Each item also looks ~2.5 s ahead (its filters span ~20 frames), so the last matching item of a
// shared stretch sits ~LOOKAHEAD before the stretch really ends.
const STEP = 4096 / 3 / 11025;
const LOOKAHEAD = 2.5;

const WINDOW = 600;     // seconds searched at each end of an episode
// Matching is judged on the average over ~2 s, never on single items: unrelated audio differs by ~16 of 32 bits
// on average, yet 18% of unrelated item pairs differ by 8 or fewer, so single-item hits chain into false stretches.
const AVG_ITEMS = 16;   // ~2 s
const MAX_BITS = 10;    // average differing bits per item over AVG_ITEMS that still counts as the same audio
const MIN_LEN = 15;     // a shared stretch must last this many seconds
const MAX_GAP = 3;      // seconds of mismatch allowed inside a stretch (a sound effect over the theme)
const PAD = 1.5;        // widen ranges so the edges of the intro aren't kept
const JOIN = 10;        // seconds between two matching pieces that are still one stretch

// raw chromaprint sub-fingerprints of [start, start + length) seconds of the first audio track.
// Returns { items: Uint32Array, start, step }: item i covers episode second start + i * step.
function fingerprint(file, start, length) {
    return new Promise((resolve, reject) => {
        // -ss/-t before -i: seek in the container so only this window is read from disk
        const p = spawn(FFMPEG, ['-hide_banner', '-loglevel', 'error', '-ss', String(start), '-t', String(length), '-i', file,
            // chromaprint takes at most 2 channels; Blu-ray rips are usually 5.1, so mix down to mono
            '-map', '0:a:0', '-vn', '-ac', '1', '-f', 'chromaprint', '-fp_format', 'raw', '-']);
        const out = [];
        let err = '';
        p.stdout.on('data', d => out.push(d));
        p.stderr.on('data', d => err += d);
        p.on('error', reject);
        p.on('close', code => {
            const buf = Buffer.concat(out);
            if (code !== 0 || buf.length < 4) return reject(new Error(err.trim().split('\n').pop() || 'no audio'));
            const items = new Uint32Array(buf.length >> 2);
            for (let i = 0; i < items.length; i++) items[i] = buf.readUInt32LE(i * 4);
            resolve({ items, start, step: STEP });
        });
    });
}

function bits(x) {
    x -= (x >>> 1) & 0x55555555;
    x = (x & 0x33333333) + ((x >>> 2) & 0x33333333);
    return (((x + (x >>> 4)) & 0x0f0f0f0f) * 0x01010101) >>> 24;
}

// Stretches of audio shared by two fingerprints, longest first: [{ a: [start, end], b: [start, end] }] in episode seconds.
// Walks every alignment (diagonal) of the two windows; 4,800 x 4,800 items is ~23M comparisons, ~0.1 s,
// so no hashing / offset histogram is needed.
function findMatches(fpA, fpB, { maxBits = MAX_BITS, minLen = MIN_LEN, maxGap = MAX_GAP } = {}) {
    const A = fpA.items, B = fpB.items, na = A.length, nb = B.length;
    const minItems = Math.round(minLen / STEP), gapItems = Math.round(maxGap / STEP);
    const runs = [];
    const close = (s, e, d) => {
        // silence and steady hum fingerprint as the same value over and over; real music doesn't
        if (e - s + 1 < minItems || new Set(A.subarray(s, e + 1)).size < (e - s + 1) / 4) return;
        runs.push({ s, e, d });
    };
    for (let d = -(nb - 1); d < na; d++) { // item i of A lines up with item i - d of B
        const lo = Math.max(0, d), hi = Math.min(na, nb + d);
        if (hi - lo < minItems) continue;
        let s = -1, last = -1, sum = 0;
        for (let i = lo; i < hi; i++) {
            sum += bits(A[i] ^ B[i - d]);
            if (i - lo >= AVG_ITEMS) sum -= bits(A[i - AVG_ITEMS] ^ B[i - AVG_ITEMS - d]);
            if (i - lo < AVG_ITEMS - 1 || sum > maxBits * AVG_ITEMS) continue;
            const from = i - AVG_ITEMS + 1; // this ~2 s window matches as a whole
            if (s >= 0 && from - last > gapItems) { close(s, last, d); s = -1; }
            if (s < 0) s = from;
            last = i;
        }
        if (s >= 0) close(s, last, d);
    }
    // neighbouring alignments find the same stretch again; keep the longest of overlapping ones
    runs.sort((x, y) => (y.e - y.s) - (x.e - x.s));
    const kept = [];
    for (const r of runs) if (!kept.some(k => r.s <= k.e && r.e >= k.s)) kept.push(r);
    const sec = (fp, i) => fp.start + i * STEP;
    const found = kept.map(r => ({
        a: [sec(fpA, r.s), sec(fpA, r.e) + LOOKAHEAD],
        b: [sec(fpB, r.s - r.d), sec(fpB, r.e - r.d) + LOOKAHEAD],
    })).sort((x, y) => x.a[0] - y.a[0]);
    // a loud moment over the theme can split one intro into back-to-back pieces (seen on Game of Thrones S01E01):
    // join pieces that follow on in both episodes within a few seconds
    const joined = [];
    for (const m of found) {
        const p = joined.at(-1);
        if (p && m.a[0] - p.a[1] <= JOIN && Math.abs((m.a[0] - p.a[1]) - (m.b[0] - p.b[1])) <= JOIN) { p.a[1] = m.a[1]; p.b[1] = m.b[1]; }
        else joined.push(m);
    }
    return joined.sort((x, y) => (y.a[1] - y.a[0]) - (x.a[1] - x.a[0]));
}

// For each episode, the stretch it shares with the most other episodes (compared with up to 2 on each side,
// so one episode without the intro doesn't cut its neighbours off from each other).
function shared(fps) {
    const found = fps.map(() => []);
    for (let i = 0; i < fps.length; i++)
        for (let j = i + 1; j <= i + 2 && j < fps.length; j++) {
            if (!fps[i] || !fps[j]) continue;
            const m = findMatches(fps[i], fps[j])[0];
            if (m) { found[i].push(m.a); found[j].push(m.b); }
        }
    return found.map(ranges => {
        let best = null, votes = 0;
        for (const r of ranges) {
            const n = ranges.filter(o => o[0] < r[1] && o[1] > r[0]).length;
            if (n > votes || (n === votes && r[1] - r[0] > best[1] - best[0])) { best = r; votes = n; }
        }
        return best;
    });
}

// files: [{ id, file, duration }] of ONE season in episode order; opts: { intro, credits, jobs }
// resolves Map<id, { intro: [start, end] | null, credits: [start, end] | null }> in seconds
async function detectSeason(files, opts = {}, onProgress) {
    const result = new Map(files.map(f => [f.id, { intro: null, credits: null }]));
    const parts = ['intro', 'credits'].filter(p => opts[p]);
    if (files.length < 2 || !parts.length) return result;

    // reads run a few at a time: the episodes may sit on a slow spinning disk
    const tasks = [];
    for (const part of parts) for (const f of files) tasks.push({ part, f });
    const fps = { intro: [], credits: [] };
    let next = 0, done = 0;
    const worker = async () => {
        while (next < tasks.length) {
            const k = next++, { part, f } = tasks[k];
            const len = Math.min(WINDOW, f.duration);
            const start = part === 'intro' ? 0 : Math.max(0, f.duration - WINDOW);
            fps[part][k % files.length] = await fingerprint(f.file, start, len).catch(() => null); // unreadable: stays null
            if (onProgress) onProgress(++done, tasks.length);
        }
    };
    await Promise.all(Array.from({ length: Math.max(1, opts.jobs || 4) }, worker));

    for (const part of parts)
        shared(fps[part]).forEach((r, i) => {
            if (r) result.get(files[i].id)[part] = [Math.max(0, r[0] - PAD), Math.min(files[i].duration, r[1] + PAD)];
        });
    return result;
}

module.exports = { detectSeason, fingerprint, findMatches };
