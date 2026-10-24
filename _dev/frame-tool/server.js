// Local web UI that turns video files into still frames (1 every N seconds) for the episode guessing game.
// Run: node _dev/frame-tool/server.js   then open http://localhost:8200
// Needs ffmpeg + ffprobe (winget install Gyan.FFmpeg). Frames land in _dev/frame-tool/output/<episode>/0001.jpg.
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const { spawn, execFile } = require('node:child_process');

const PORT = 8200;
const OUT = path.join(__dirname, 'output');
const TMP = fs.mkdtempSync(path.join(os.tmpdir(), 'frame-tool-'));
const uploads = new Map(); // id -> { file, duration }

// winget puts ffmpeg here; a terminal opened before the install won't have it on PATH yet
function tool(name) {
    const linked = path.join(process.env.LOCALAPPDATA || '', 'Microsoft', 'WinGet', 'Links', name + '.exe');
    return fs.existsSync(linked) ? linked : name;
}
const FFMPEG = tool('ffmpeg'), FFPROBE = tool('ffprobe');

// the same maths as the page: frames at start, start + interval, ... while before (duration - end)
function times(duration, interval, start, end) {
    const len = duration - start - end;
    const n = len > 0 && interval > 0 ? Math.ceil(len / interval) : 0;
    return Array.from({ length: n }, (_, k) => start + k * interval);
}

// folder name for an episode: no path separators or characters Windows rejects
const safe = name => String(name).replace(/[\\/:*?"<>|\x00-\x1f]/g, '_').replace(/^[.\s]+|[.\s]+$/g, '').slice(0, 100) || 'episode';

function probe(file) {
    return new Promise((resolve, reject) => execFile(FFPROBE, ['-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', file],
        (err, out) => err ? reject(new Error("Couldn't read this file. Is it a video?")) : resolve(parseFloat(out))));
}

function grab(file, t, out) {
    return new Promise((resolve, reject) => {
        // -ss before -i seeks fast; ffmpeg then decodes to the exact time
        const p = spawn(FFMPEG, ['-hide_banner', '-loglevel', 'error', '-ss', String(t), '-i', file, '-frames:v', '1', '-vf', 'scale=960:-2', '-q:v', '5', '-y', out]);
        let err = '';
        p.stderr.on('data', d => err += d);
        p.on('error', reject);
        p.on('close', code => code === 0 ? resolve() : reject(new Error(err.trim().split('\n').pop() || 'ffmpeg failed')));
    });
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
            const { id, name, folder, interval, start, end } = await readJson(req);
            const up = uploads.get(id);
            if (!up) return json(res, 400, { error: 'That video is no longer uploaded. Add it again.' });
            const list = times(up.duration, +interval, +start, +end);
            if (!list.length) return json(res, 400, { error: 'No frames to extract with these settings.' });
            // "Game of Thrones/Season 1" + "Episode 5" -> output/Game of Thrones/Season 1/Episode 5
            const parts = String(folder || '').split('/').filter(p => p.trim()).map(safe);
            const dir = path.join(OUT, ...parts, safe(name));
            fs.mkdirSync(dir, { recursive: true });
            for (const f of fs.readdirSync(dir)) if (f.endsWith('.jpg')) fs.unlinkSync(path.join(dir, f));

            res.writeHead(200, { 'Content-Type': 'application/x-ndjson' });
            let done = 0, next = 0;
            const worker = async () => {
                while (next < list.length) {
                    const k = next++;
                    await grab(up.file, list[k], path.join(dir, String(k + 1).padStart(4, '0') + '.jpg'));
                    res.write(JSON.stringify({ done: ++done, total: list.length }) + '\n');
                }
            };
            try {
                await Promise.all(Array.from({ length: Math.min(4, os.cpus().length) }, worker));
                fs.unlinkSync(up.file);
                uploads.delete(id);
                res.end(JSON.stringify({ finished: true, folder: dir }) + '\n');
            } catch (e) {
                res.end(JSON.stringify({ error: e.message }) + '\n');
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
