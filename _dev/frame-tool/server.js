// Local web UI that turns video files into still frames (1 every N seconds) for the episode guessing game.
// Run: node _dev/frame-tool/server.js   then open http://localhost:8200
// Needs ffmpeg + ffprobe (winget install Gyan.FFmpeg). Frames land in Z:\web\videoframegame\<folders>\<episode>\0001.jpg.
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const { spawn, execFile } = require('node:child_process');

const PORT = +process.env.PORT || 8200;
const OUT = String.raw`Z:\web\videoframegame`;
const TMP = fs.mkdtempSync(path.join(os.tmpdir(), 'frame-tool-'));
const uploads = new Map(); // id -> { file, duration }

// winget puts ffmpeg here; a terminal opened before the install won't have it on PATH yet
function tool(name) {
    const linked = path.join(process.env.LOCALAPPDATA || '', 'Microsoft', 'WinGet', 'Links', name + '.exe');
    return fs.existsSync(linked) ? linked : name;
}
const FFMPEG = tool('ffmpeg'), FFPROBE = tool('ffprobe');

// the same maths as the page: frames at start, start + interval, ... while before (duration - end),
// leaving out any that fall inside a skipped range (intro, recap, credits) [[from, to], ...]
function times(duration, interval, start, end, skip = []) {
    const len = duration - start - end;
    const n = len > 0 && interval > 0 ? Math.ceil(len / interval) : 0;
    return Array.from({ length: n }, (_, k) => start + k * interval).filter(t => !skip.some(([a, b]) => t >= a && t < b));
}

// folder name for an episode: no path separators or characters Windows rejects
const safe = name => String(name).replace(/[\\/:*?"<>|\x00-\x1f]/g, '_').replace(/^[.\s]+|[.\s]+$/g, '').slice(0, 100) || 'episode';

// same list as the page
const VIDEO = /\.(mp4|mkv|avi|mov|m4v|webm|wmv|flv|ts|m2ts|mpg|mpeg|3gp)$/i;

function probe(file) {
    return new Promise((resolve, reject) => execFile(FFPROBE, ['-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', file],
        (err, out) => err ? reject(new Error("Couldn't read this file. Is it a video?")) : resolve(parseFloat(out))));
}

// one pool of ffmpeg processes shared by every video being extracted, one per CPU thread
// (measured on a 10-bit HEVC 1080p clip: 16 processes beat 4 by ~35%, and beat fewer multi-threaded ones)
const JOBS = os.cpus().length;
let running = 0;
const waiting = [];
const slot = () => new Promise(ok => running < JOBS ? (running++, ok()) : waiting.push(ok));
const release = () => { const next = waiting.shift(); next ? next() : running--; };

async function grab(file, t, out, checkBlank) {
    await slot();
    try { return await grabNow(file, t, out, checkBlank); } finally { release(); }
}

// Black or one-colour frames (fades, credits text on black) say nothing about the episode.
// Judged on a 64x36 grey thumbnail. Average brightness can't tell credits from night scenes (both ~9 on
// Game of Thrones S01), but credits sit on truly black pixels (~2) while dark scenes have grain (5-15):
// measured, credits/black frames are 79-100% truly black, dark scenes 15-39%. Grey first, then an area average:
// shrinking the 10-bit picture before converting it dithers black to 0-6 and the share drops to ~15% for credits.
// format=yuv420p before split pins the saved jpg to colour; without it ffmpeg turns both branches grey.
const TRUE_BLACK = 4, BLACK_SHARE = 0.6, FLAT_SD = 4;
function blank(px) {
    if (!px.length) return false;
    const mean = px.reduce((a, b) => a + b, 0) / px.length;
    const sd = Math.sqrt(px.reduce((a, b) => a + (b - mean) ** 2, 0) / px.length);
    return px.filter(v => v < TRUE_BLACK).length / px.length >= BLACK_SHARE || sd < FLAT_SD;
}

// saves the frame at t as a jpg; with checkBlank, also measures it and deletes it when blank (returns true then)
function grabNow(file, t, out, checkBlank) {
    return new Promise((resolve, reject) => {
        // -ss before -i seeks fast; ffmpeg then decodes to the exact time
        const args = ['-hide_banner', '-loglevel', 'error', '-ss', String(t), '-i', file, '-frames:v', '1'];
        const p = spawn(FFMPEG, checkBlank
            ? [...args, '-filter_complex', '[0:v]scale=960:-2,format=yuv420p,split[a][b];[b]format=gray,scale=64:36:flags=area[c]', '-map', '[a]', '-q:v', '5', '-y', out, '-map', '[c]', '-frames:v', '1', '-f', 'rawvideo', 'pipe:1']
            : [...args, '-vf', 'scale=960:-2', '-q:v', '5', '-y', out]);
        let err = '';
        const px = [];
        p.stdout.on('data', d => px.push(d));
        p.stderr.on('data', d => err += d);
        p.on('error', reject);
        p.on('close', code => {
            // a time in the last split second after the final picture has nothing to save: skip it, don't fail the episode
            if (code !== 0 && /received no packets|Output file is empty/i.test(err)) {
                fs.rmSync(out, { force: true });
                return resolve(true);
            }
            if (code !== 0) return reject(new Error(`Frame at ${Math.round(t)} s: ` + (err.trim().split('\n').pop() || 'ffmpeg failed')));
            if (checkBlank && blank(Buffer.concat(px))) {
                fs.rmSync(out, { force: true });
                return resolve(true);
            }
            resolve(false);
        });
    });
}

// The asset host only lists folders through static index.html pages written by Z:\web\_tools\build-index.js,
// so new frames stay invisible to the Frame Guesser game until it runs. A run walks the whole share (~95 s),
// so runs never overlap: requests made during a run are folded into one more run after it.
// Failures are only logged; they never stop extraction or touch frames.
const WEB = path.dirname(OUT);
const GAME_REFRESH = 'https://www.craftyplayz.com/games/frame-guesser/?refresh=1';
let indexing = null, indexAgain = false;
function rebuildIndex() {
    if (indexing) { indexAgain = true; return indexing; }
    indexing = new Promise(resolve => execFile(process.execPath, [path.join('_tools', 'build-index.js')], { cwd: WEB, timeout: 15 * 60000 }, (err, out, errOut) => {
        if (err) console.log('Listing rebuild failed:', (errOut || err.message).trim().split('\n').pop());
        else console.log('Listing rebuilt:', out.trim());
        resolve(!err);
    })).then(ok => {
        indexing = null;
        if (!indexAgain) return ok;
        indexAgain = false;
        return rebuildIndex();
    });
    return indexing;
}

const json = (res, code, body) => { res.writeHead(code, { 'Content-Type': 'application/json' }); res.end(JSON.stringify(body)); };
const readJson = req => new Promise(r => { let b = ''; req.on('data', d => b += d); req.on('end', () => { try { r(JSON.parse(b)); } catch { r({}); } }); });

// save a request body to a file; the temp folder is recreated if something cleared it while the tool runs
function receive(req, file) {
    fs.mkdirSync(TMP, { recursive: true });
    return new Promise((resolve, reject) => req.pipe(fs.createWriteStream(file)).on('finish', resolve).on('error', e => {
        req.resume(); // keep reading so the browser gets the error message instead of a dropped connection
        reject(new Error('Could not save a working copy: ' + e.message));
    }));
}

const server = http.createServer(async (req, res) => {
    const url = new URL(req.url, 'http://localhost');
    try {
        if (req.method === 'GET' && url.pathname === '/') {
            res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            return fs.createReadStream(path.join(__dirname, 'index.html')).pipe(res);
        }

        // a folder or video path on this PC: list its videos (subfolders too) and read them in place, no copying
        if (req.method === 'POST' && url.pathname === '/scan') {
            const given = String((await readJson(req)).path || '').trim();
            if (!given) return json(res, 400, { error: 'Type a folder or video path.' });
            const root = path.resolve(given);
            let stat;
            try { stat = fs.statSync(root); } catch { return json(res, 400, { error: 'Nothing found at ' + root }); }
            const found = [], others = [];
            const walk = (dir, rel) => {
                for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
                    const full = path.join(dir, e.name);
                    if (e.isDirectory()) walk(full, rel ? rel + '/' + e.name : e.name);
                    else if (VIDEO.test(e.name)) found.push({ full, file: e.name, folder: rel });
                    else others.push(e.name);
                }
            };
            // the picked folder's own name is kept, like the browser folder picker: Game of Thrones/Season 1
            if (stat.isDirectory()) walk(root, path.basename(root));
            else if (VIDEO.test(root)) found.push({ full: root, file: path.basename(root), folder: '' });
            else return json(res, 400, { error: "That file isn't a video." });

            let next = 0;
            const videos = [];
            await Promise.all(Array.from({ length: 4 }, async () => {
                while (next < found.length) {
                    const v = found[next++];
                    const duration = await probe(v.full).catch(() => 0);
                    if (!(duration > 0)) { others.push(v.file); continue; }
                    const id = Math.random().toString(36).slice(2);
                    uploads.set(id, { file: v.full, duration, local: true });
                    videos.push({ id, file: v.file, folder: v.folder, duration });
                }
            }));
            return json(res, 200, { videos, others });
        }

        // length of a video from its first few MB, so a whole show can be counted without copying it
        // (works for .mkv; an .mp4 that keeps its index at the end answers null until it's copied in full)
        if (req.method === 'POST' && url.pathname === '/probe') {
            const file = path.join(TMP, 'probe-' + Math.random().toString(36).slice(2) + path.extname(url.searchParams.get('name') || '').replace(/[^.\w]/g, ''));
            await receive(req, file);
            const duration = await probe(file).catch(() => null);
            fs.unlinkSync(file);
            return json(res, 200, { duration: duration > 0 ? duration : null });
        }

        // the browser streams a whole video here just before extracting it; it's deleted once its frames are out
        if (req.method === 'POST' && url.pathname === '/upload') {
            const id = Math.random().toString(36).slice(2);
            const file = path.join(TMP, id + path.extname(url.searchParams.get('name') || '').replace(/[^.\w]/g, ''));
            await receive(req, file);
            const duration = await probe(file).catch(e => { fs.unlinkSync(file); throw e; });
            if (!(duration > 0)) { fs.unlinkSync(file); throw new Error('Could not find the length of this video.'); }
            uploads.set(id, { file, duration });
            return json(res, 200, { id, duration });
        }

        // extract one episode; answers with one JSON line per frame so the page can show progress
        if (req.method === 'POST' && url.pathname === '/extract') {
            const { id, name, folder, interval, start, end, skip, blank: skipBlank } = await readJson(req);
            const up = uploads.get(id);
            if (!up) return json(res, 400, { error: 'That video is no longer uploaded. Add it again.' });
            const ranges = Array.isArray(skip) ? skip.filter(r => Array.isArray(r) && r.length === 2).map(r => r.map(Number)) : [];
            const list = times(up.duration, +interval, +start, +end, ranges);
            if (!list.length) return json(res, 400, { error: 'No frames to extract with these settings.' });
            // "Game of Thrones/Season 1" + "Episode 5" -> output/Game of Thrones/Season 1/Episode 5
            const parts = String(folder || '').split('/').filter(p => p.trim()).map(safe);
            const dir = path.join(OUT, ...parts, safe(name));
            // frames go into a hidden ".<episode>.partial" folder first (build-index.js skips dot-names), and only a
            // finished episode is renamed into place, so the asset listing never shows a half-written episode
            const partial = path.join(path.dirname(dir), '.' + path.basename(dir) + '.partial');
            fs.rmSync(partial, { recursive: true, force: true });
            fs.mkdirSync(partial, { recursive: true });

            res.writeHead(200, { 'Content-Type': 'application/x-ndjson' });
            let done = 0, next = 0, blanks = 0;
            const worker = async () => {
                while (next < list.length) {
                    const k = next++;
                    if (await grab(up.file, list[k], path.join(partial, String(k + 1).padStart(4, '0') + '.jpg'), !!skipBlank)) blanks++;
                    res.write(JSON.stringify({ done: ++done, total: list.length, blanks }) + '\n');
                }
            };
            try {
                await Promise.all(Array.from({ length: Math.min(JOBS, list.length) }, worker));
                fs.rmSync(dir, { recursive: true, force: true }); // an earlier run of this episode
                fs.renameSync(partial, dir);
                // only the tool's own copies are deleted, never a video read where it is on disk
                if (!up.local) {
                    fs.unlinkSync(up.file);
                    uploads.delete(id);
                }
                res.end(JSON.stringify({ finished: true, folder: dir, blanks }) + '\n');
                rebuildIndex();
            } catch (e) {
                res.end(JSON.stringify({ error: e.message }) + '\n');
            }
            return;
        }

        // intros and end credits for one season: audio shared between its episodes (detect.js)
        if (req.method === 'POST' && url.pathname === '/detect') {
            const { ids, intro, credits } = await readJson(req);
            const files = (Array.isArray(ids) ? ids : []).map(id => ({ id, up: uploads.get(id) }))
                .filter(f => f.up && f.up.local).map(f => ({ id: f.id, file: f.up.file, duration: f.up.duration }));
            if (!files.length) return json(res, 400, { error: 'Intro detection only works on videos added by path.' });
            const found = await require('./detect.js').detectSeason(files, { intro: !!intro, credits: !!credits });
            return json(res, 200, { found: Object.fromEntries(found) });
        }

        // the page calls this when a whole run ends: one last listing rebuild, then tell the game to reload its list
        if (req.method === 'POST' && url.pathname === '/job-done') {
            json(res, 200, { ok: true });
            if (await rebuildIndex()) {
                // the game honours this at most once every 10 minutes; its answer doesn't matter
                fetch(GAME_REFRESH, { signal: AbortSignal.timeout(20000) }).catch(e => console.log('Game refresh request failed:', e.message));
            }
            return;
        }

        if (req.method === 'POST' && url.pathname === '/open') {
            fs.mkdirSync(OUT, { recursive: true });
            spawn('explorer.exe', [OUT], { detached: true }).unref();
            return json(res, 200, { ok: true });
        }

        json(res, 404, { error: 'Not found' });
    } catch (e) {
        json(res, 500, { error: e.message });
    }
});

if (require.main === module) {
    // localhost only: this server can read and write files on your PC
    server.listen(PORT, '127.0.0.1', () => {
        console.log(`Frame tool: http://localhost:${PORT}  (Ctrl+C to stop)`);
        execFile(FFMPEG, ['-version'], err => { if (err) console.log('ffmpeg not found. Install it with: winget install Gyan.FFmpeg'); });
    });
    const cleanup = () => { fs.rmSync(TMP, { recursive: true, force: true }); process.exit(); };
    process.on('SIGINT', cleanup);
    process.on('SIGTERM', cleanup);
} else {
    fs.rmSync(TMP, { recursive: true, force: true });
}

module.exports = { times, safe };
