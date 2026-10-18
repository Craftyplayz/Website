// Tests for the NBT viewer's "View my server stats" sync (minecraft/tools/nbt-viewer).
//   usage: node _dev/scripts/nbtviewer-server-sync.test.js   (Node 22+, Chrome installed; set CHROME to override)
// There is no PHP locally, so this serves index.php with its PHP stripped and plays the asset server
// itself behind api.php?srv=. Requests to the real assets.craftyplayz.com are failed with CDP, which
// sends the viewer down its api.php fallback - the path the live site (www.) takes too.
const http = require('http');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawn } = require('child_process');

const root = path.join(__dirname, '..', '..');
const viewerDir = path.join(root, 'minecraft', 'tools', 'nbt-viewer');
const CHROME = process.env.CHROME || ['C:/Program Files/Google/Chrome/Application/chrome.exe', '/usr/bin/google-chrome', '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'].find((p) => fs.existsSync(p));
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'nbt-sync-'));

/* ---------- the pretend asset server ---------- */
const sample = (f) => fs.readFileSync(path.join(viewerDir, 'Sampledata', f));
const UUID = ['11111111-1111-4111-8111-111111111111', '22222222-2222-4222-8222-222222222222', '33333333-3333-4333-8333-333333333333'];
let clock = Date.parse('2026-10-01T00:00:00Z');
const remote = { stats: new Map(), advancements: new Map(), data: new Map() };
function setFile(dir, name, bytes, opts = {}) {
    clock += 60000;
    remote[dir].set(name, { bytes: Buffer.from(bytes), etag: opts.noValidators ? null : `"${clock.toString(16)}"`, lm: opts.noValidators ? null : new Date(clock).toUTCString() });
}
const statsFor = (n) => JSON.stringify({ stats: { 'minecraft:custom': { 'minecraft:play_time': 1000 * n, 'minecraft:jump': n } }, DataVersion: 4440 });
function resetRemote() {
    Object.values(remote).forEach((m) => m.clear());
    setFile('data', `${UUID[0]}.dat`, sample('playerdata-sample.dat'));
    setFile('data', `${UUID[0]}.dat_old`, sample('playerdata-sample.dat'));
    setFile('stats', `${UUID[0]}.json`, sample('playerstats-sample.json'));
    setFile('advancements', `${UUID[0]}.json`, sample('playeradvancement-sample.json'));
    setFile('stats', `${UUID[1]}.json`, statsFor(1));
    setFile('stats', `${UUID[2]}.json`, statsFor(2));
    setFile('stats', 'readme.txt', 'not a player file');
}
const WANTED = 5;   // everything above except the .dat_old and readme.txt
const srv = { down: false, failList: new Set(), failFile: new Set(), truncate: new Set(), slow: 0, bots: null };   // bots: the bots.json body, null = no file
let log = [];
const listing = (dir) => `<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"><html><head><title>Index of /minecraft-players/${dir}</title></head><body><h1>Index of /minecraft-players/${dir}</h1><ul><li><a href="/minecraft-players/"> Parent Directory</a></li>\n` +
    [...remote[dir].keys()].sort().map((n) => `<li><a href="${encodeURIComponent(n)}"> ${n}</a></li>`).join('\n') + '</ul></body></html>';

function serveSrv(req, res, q) {
    const dir = q.get('srv'), f = q.get('f') || '';
    const rec = { dir, f, status: 0 };
    log.push(rec);
    const send = (code, body, headers = {}) => setTimeout(() => { rec.status = code; res.writeHead(code, { 'Cache-Control': 'no-store', ...headers }); res.end(body); }, srv.slow);
    if (srv.down) return send(502, '{"error":"The server could not be reached"}');
    if (!remote[dir]) return send(400, '{}');
    if (!f) return srv.failList.has(dir) ? send(502, '<html><title>Bad gateway</title></html>') : send(200, listing(dir), { 'Content-Type': 'text/html' });
    const file = remote[dir].get(f);
    if (!file) return send(404, '');
    if (srv.failFile.has(`${dir}/${f}`)) return send(502, '');
    const h = {};
    if (file.etag) h.ETag = file.etag;
    if (file.lm) h['Last-Modified'] = file.lm;
    if ((file.etag && req.headers['if-none-match'] === file.etag) || (!req.headers['if-none-match'] && file.lm && req.headers['if-modified-since'] === file.lm)) return send(304, '', h);
    if (srv.truncate.has(`${dir}/${f}`)) return send(200, file.bytes.subarray(0, file.bytes.length >> 1), h);
    send(200, file.bytes, { 'Content-Type': 'application/octet-stream', ...h });
}

const TYPES = { '.js': 'text/javascript', '.css': 'text/css', '.json': 'application/json', '.png': 'image/png', '.svg': 'image/svg+xml', '.webp': 'image/webp' };
const server = http.createServer((req, res) => {
    const u = new URL(req.url, 'http://x');
    const p = decodeURIComponent(u.pathname);
    if (p.endsWith('/nbt-viewer/api.php') && u.searchParams.has('srv')) return serveSrv(req, res, u.searchParams);
    if (p.endsWith('/nbt-viewer/bots.json')) { res.writeHead(srv.bots === null ? 404 : 200, { 'Content-Type': 'application/json' }); return res.end(srv.bots || ''); }
    if (p.endsWith('.php') && !p.endsWith('/index.php')) { res.writeHead(404); return res.end(); }
    let file = path.join(root, p);
    if (p.endsWith('/')) file = path.join(file, 'index.php');
    if (!file.startsWith(root) || !fs.existsSync(file) || fs.statSync(file).isDirectory()) { res.writeHead(404); return res.end(); }
    if (file.endsWith('.php')) {
        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        // the app lives in an IIFE - hand the tests a window into it
        const hook = 'window.__t = { get players() { return players; }, get active() { return active; }, get srvJob() { return srvJob; }, srvCache, srvValid, srvSync, store };';
        return res.end(fs.readFileSync(file, 'utf8').replace(/<\?php[\s\S]*?\?>/g, '').replace(/\n    \}\)\(\);\r?\n/, `\n        ${hook}\n    })();\n`));
    }
    res.writeHead(200, { 'Content-Type': TYPES[path.extname(file)] || 'application/octet-stream' });
    fs.createReadStream(file).pipe(res);
});

/* ---------- a tiny CDP client ---------- */
let chrome, ws, seq = 0;
const pending = new Map(), listeners = [];
function cdp(method, params = {}) {
    const id = ++seq;
    ws.send(JSON.stringify({ id, method, params }));
    return new Promise((res, rej) => pending.set(id, { res, rej, method }));
}
async function startChrome() {
    chrome = spawn(CHROME, ['--headless=new', '--remote-debugging-port=0', `--user-data-dir=${profile}`, '--no-first-run', '--no-default-browser-check', 'about:blank'], { stdio: ['ignore', 'ignore', 'pipe'] });
    const wsUrl = await new Promise((res, rej) => {
        let buf = '';
        chrome.stderr.on('data', (d) => { buf += d; const m = /DevTools listening on (ws:\S+)/.exec(buf); if (m) res(m[1]); });
        setTimeout(() => rej(new Error('Chrome did not start')), 15000);
    });
    const port = new URL(wsUrl).port;
    const targets = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    ws = new WebSocket(targets.find((t) => t.type === 'page').webSocketDebuggerUrl);
    await new Promise((r) => (ws.onopen = r));
    ws.onmessage = (m) => {
        const msg = JSON.parse(m.data);
        if (msg.id && pending.has(msg.id)) {
            const p = pending.get(msg.id);
            pending.delete(msg.id);
            msg.error ? p.rej(new Error(p.method + ': ' + msg.error.message)) : p.res(msg.result);
        } else if (msg.method) listeners.forEach((l) => l(msg));
    };
    await cdp('Page.enable');
    await cdp('Runtime.enable');
    await cdp('Network.enable');
    // the real asset server is never contacted: fail it so the viewer uses api.php (the mock)
    await cdp('Fetch.enable', { patterns: [{ urlPattern: 'https://assets.craftyplayz.com/*' }] });
}
listeners.push((m) => { if (m.method === 'Fetch.requestPaused') { directTries++; cdp('Fetch.failRequest', { requestId: m.params.requestId, errorReason: 'Failed' }).catch(() => { }); } });
listeners.push((m) => { if (m.method === 'Runtime.exceptionThrown') pageErrors.push(m.params.exceptionDetails.exception ? m.params.exceptionDetails.exception.description : m.params.exceptionDetails.text); });
let directTries = 0, pageErrors = [];
async function stopChrome() { try { await cdp('Browser.close'); } catch (e) { } await new Promise((r) => (chrome.exitCode !== null ? r() : chrome.once('exit', r))); }

async function evaluate(expr) {
    const r = await cdp('Runtime.evaluate', { expression: expr, awaitPromise: true, returnByValue: true });
    if (r.exceptionDetails) throw new Error('page: ' + (r.exceptionDetails.exception ? r.exceptionDetails.exception.description : r.exceptionDetails.text));
    return r.result.value;
}
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
async function waitFor(expr, ms = 15000) {
    const t = Date.now();
    while (Date.now() - t < ms) { if (await evaluate(expr)) return; await sleep(50); }
    throw new Error('timed out waiting for ' + expr);
}
let base;
async function openPage() {
    const loaded = new Promise((r) => { const l = (m) => { if (m.method === 'Page.loadEventFired') { listeners.splice(listeners.indexOf(l), 1); r(); } }; listeners.push(l); });
    await cdp('Page.navigate', { url: base });
    await loaded;
    // record every status message and whether the bar was indeterminate, for the loading-state checks
    await evaluate(`window.__srv = []; new MutationObserver(() => { const b = document.querySelector('#srvSync'); window.__srv.push({ msg: b.querySelector('.srv-msg').textContent, ind: b.querySelector('.progress').classList.contains('ind'), bar: !b.querySelector('.progress').classList.contains('hide') && !b.classList.contains('hide'), w: b.querySelector('.progress i').style.width }); }).observe(document.querySelector('#app'), { subtree: true, childList: true, attributes: true, characterData: true }); true`);
}
async function sync(selector = '#mySrv') {
    log = [];
    await evaluate(`document.querySelector('${selector}').click(); true`);
    await sleep(30);
    await waitFor('!__t.srvJob');
}
const cacheRecords = () => evaluate(`__t.srvCache.all().then((a) => a.map((r) => ({ key: r.key, hash: r.hash, size: r.bytes.length })).sort((a, b) => a.key < b.key ? -1 : 1))`);
const files = () => log.filter((r) => r.f);
const downloads = () => files().filter((r) => r.status === 200).length;
const viewerOpen = () => evaluate(`!document.querySelector('#viewer').classList.contains('hide') && __t.players.length > 0`);
const srvMsg = () => evaluate(`document.querySelector('#srvSync').classList.contains('hide') ? '' : document.querySelector('#srvSync').textContent`);
const warnText = () => evaluate(`(document.querySelector('#errors .srv-warn') || {}).textContent || ''`);

/* ---------- the tests ---------- */
const results = [];
async function test(name, fn) {
    try { await fn(); results.push([true, name]); console.log('  ok  ' + name); }
    catch (e) { results.push([false, name]); console.log('  FAIL ' + name + '\n       ' + e.message); }
}
function assert(c, msg) { if (!c) throw new Error(msg); }
const eq = (a, b, what) => assert(a === b, `${what}: expected ${JSON.stringify(b)}, got ${JSON.stringify(a)}`);

(async () => {
    assert(CHROME, 'Chrome not found - set CHROME');
    await new Promise((r) => server.listen(0, '127.0.0.1', r));
    base = `http://127.0.0.1:${server.address().port}/minecraft/tools/nbt-viewer/`;
    resetRemote();
    await startChrome();
    console.log('NBT viewer server-stats sync');

    await test('startup does nothing until the button is pressed', async () => {
        log = [];
        await openPage();
        await sleep(300);
        eq(log.length, 0, 'srv requests at startup');
        eq(await evaluate(`indexedDB.databases().then((d) => d.some((x) => x.name === 'nbt-server'))`), false, 'cache database created at startup');
        eq(await evaluate(`!!document.querySelector('#mySrv') && document.querySelector('#mySrv').closest('.row') === document.querySelector('[data-example]').closest('.row')`), true, 'button sits next to Try an example');
        eq(await srvMsg(), '', 'status shown at startup');
    });

    await test('Try an example still works', async () => {
        await evaluate(`document.querySelector('[data-example]').click(); true`);
        await waitFor(`__t.players.length > 0`);
        // name the server's files after the sample player, so its stats attach to it
        UUID[0] = await evaluate(`__t.players[0].key`);
        resetRemote();
        eq(log.length, 0, 'srv requests');
        await openPage();
    });

    await test('first run downloads every file and opens the viewer', async () => {
        await sync();
        eq(log.filter((r) => !r.f).length, 3, 'listings fetched');
        eq(files().length, WANTED, 'files requested (no .dat_old, no readme.txt)');
        eq(downloads(), WANTED, 'files downloaded');
        eq((await cacheRecords()).length, WANTED, 'cached records');
        assert(await viewerOpen(), 'viewer did not open');
        assert(await evaluate(`!!__t.players[__t.active].extras.stats`), 'stats were not attached to the player');
        eq(await srvMsg(), '', 'status left on screen');
        eq(await warnText(), '', 'warning');
        assert(directTries > 0, 'never tried the asset server directly before api.php');
    });

    await test('loading states: indeterminate, then determinate, with phase messages, hidden at the end', async () => {
        const s = await evaluate('window.__srv');
        const msgs = s.map((x) => x.msg);
        for (const want of ['Discovering files', 'Checking for updates', 'Downloading files', 'Loading stats', 'Opening stats']) assert(msgs.some((m) => m.startsWith(want)), `no "${want}" message (saw ${[...new Set(msgs)].join(' | ')})`);
        assert(s.some((x) => x.msg.startsWith('Discovering') && x.ind), 'discovering was not indeterminate');
        assert(s.some((x) => x.msg.startsWith('Downloading') && !x.ind && x.w), 'downloading was not determinate');
        assert(msgs.some((m) => /5 \/ 5 checked · 5 downloaded/.test(m)), 'no file counts in the status');
        assert(!s[s.length - 1].bar, 'bar still visible at the end');
    });

    await test('the cache survives a reload; unchanged files are not downloaded again', async () => {
        await openPage();
        const before = await cacheRecords();
        await sync();
        eq(files().length, WANTED, 'files checked');
        eq(files().filter((r) => r.status === 304).length, WANTED, '304 answers');
        eq(downloads(), 0, 'downloads');
        assert(JSON.stringify(await cacheRecords()) === JSON.stringify(before), 'cache changed');
        assert(await viewerOpen(), 'viewer did not open from the cache');
    });

    await test('changed, new and removed files', async () => {
        setFile('stats', `${UUID[1]}.json`, statsFor(10));                 // changed
        setFile('advancements', `${UUID[1]}.json`, '{"DataVersion":4440}'); // new
        remote.stats.delete(`${UUID[2]}.json`);                            // gone
        await openPage();
        await sync();
        eq(downloads(), 2, 'downloads');
        const keys = (await cacheRecords()).map((r) => r.key);
        assert(!keys.includes(`stats/${UUID[2]}.json`), 'removed file still cached');
        assert(keys.includes(`advancements/${UUID[1]}.json`), 'new file not cached');
        eq(keys.length, WANTED, 'cached records');
        assert(await viewerOpen(), 'viewer did not open');
    });

    await test('a second press while syncing does not start another sync', async () => {
        await openPage();
        srv.slow = 150;
        log = [];
        await evaluate(`document.querySelector('#mySrv').click(); document.querySelector('#mySrv').click(); __t.srvSync(); true`);
        await sleep(30);
        await waitFor('!__t.srvJob');
        srv.slow = 0;
        eq(log.filter((r) => !r.f).length, 3, 'listing requests');
        eq(files().length, WANTED, 'file requests');
    });

    await test('a failed download keeps the cached copy, warns, and Retry fixes it', async () => {
        await openPage();
        const old = (await cacheRecords()).find((r) => r.key === `stats/${UUID[0]}.json`);
        setFile('stats', `${UUID[0]}.json`, statsFor(99));
        srv.failFile.add(`stats/${UUID[0]}.json`);
        await sync();
        const now = (await cacheRecords()).find((r) => r.key === `stats/${UUID[0]}.json`);
        eq(now && now.hash, old.hash, 'cached copy');
        assert(/1 file could not be downloaded \(HTTP 502\) - 1 shown from the saved copy/.test(await warnText()), 'no failure warning: ' + await warnText());
        assert(await viewerOpen(), 'viewer did not open');
        srv.failFile.clear();
        await sync('#errors .srv-warn [data-srv="sync"]');
        eq(downloads(), 1, 'retry downloads');
        eq(await warnText(), '', 'warning after a good retry');
        assert((await cacheRecords()).find((r) => r.key === `stats/${UUID[0]}.json`).hash !== old.hash, 'retry did not update the cache');
    });

    await test('an incomplete download is rejected and does not overwrite the cache', async () => {
        await openPage();
        const old = (await cacheRecords()).find((r) => r.key === `stats/${UUID[1]}.json`);
        setFile('stats', `${UUID[1]}.json`, statsFor(11));
        srv.truncate.add(`stats/${UUID[1]}.json`);
        await sync();
        srv.truncate.clear();
        eq((await cacheRecords()).find((r) => r.key === `stats/${UUID[1]}.json`).hash, old.hash, 'cached copy');
        assert(/could not be downloaded/.test(await warnText()), 'no warning');
    });

    await test('a corrupt cache entry is thrown away and downloaded again', async () => {
        await openPage();
        await evaluate(`__t.srvCache.all().then((a) => { const r = a.find((x) => x.key === 'advancements/${UUID[0]}.json'); r.bytes = r.bytes.slice(0, 10); return __t.srvCache.put(r); })`);
        await sync();
        const r = files().find((x) => x.f === `${UUID[0]}.json` && x.dir === 'advancements');
        eq(r && r.status, 200, 'corrupt file re-downloaded');
        eq(await warnText(), '', 'warning');
        assert(await evaluate(`__t.srvCache.all().then((a) => a.every(__t.srvValid))`), 'cache still has a bad entry');
    });

    await test('server unreachable with a cache: offer the saved copy, which opens without the network', async () => {
        srv.down = true;
        await openPage();
        await sync();
        assert(/Could not reach the server.*Open saved copy/.test(await srvMsg()), 'no offer: ' + await srvMsg());
        assert(!(await viewerOpen()), 'viewer opened anyway');
        log = [];
        await sync('#srvSync [data-srv="cached"]');
        eq(log.length, 0, 'requests when opening the saved copy');
        assert(await viewerOpen(), 'saved copy did not open');
        srv.down = false;
    });

    await test('one listing failing still syncs the rest and keeps that folder\'s cache', async () => {
        srv.failList.add('advancements');
        await openPage();
        await sync();
        srv.failList.clear();
        assert(/could not list advancements/.test(await warnText()), 'no warning: ' + await warnText());
        eq((await cacheRecords()).filter((r) => r.key.startsWith('advancements/')).length, 2, 'advancements still cached');
        assert(await evaluate(`!!__t.players[__t.active].extras.advancements`), 'cached advancements not loaded');
    });

    await test('offline: offer the saved copy', async () => {
        await openPage();
        await cdp('Network.emulateNetworkConditions', { offline: true, latency: 0, downloadThroughput: -1, uploadThroughput: -1 });
        await sync();
        await cdp('Network.emulateNetworkConditions', { offline: false, latency: 0, downloadThroughput: -1, uploadThroughput: -1 });
        assert(/offline.*Open saved copy/.test(await srvMsg()), 'no offline offer: ' + await srvMsg());
        eq(log.length, 0, 'requests while offline');
    });

    await test('an empty listing does not wipe the cache', async () => {
        const saved = new Map(remote.stats);
        remote.stats.clear();
        await openPage();
        await sync();
        remote.stats = saved;
        for (const [k, v] of saved) remote.stats.set(k, v);
        assert(/listed no files in stats/.test(await warnText()), 'no warning: ' + await warnText());
        eq((await cacheRecords()).filter((r) => r.key.startsWith('stats/')).length, 2, 'stats still cached');
    });

    await test('files without ETag / Last-Modified are compared by content, not size', async () => {
        setFile('stats', `${UUID[1]}.json`, statsFor(3), { noValidators: true });
        await openPage();
        await sync();
        eq(downloads(), 1, 'first download');
        await openPage();
        await sync();
        eq(downloads(), 1, 'downloaded again (cannot be verified otherwise)');
        assert(!(await evaluate(`document.querySelector('#errors').textContent`)).includes('updated'), 'same bytes reported as updated');
        setFile('stats', `${UUID[1]}.json`, statsFor(4), { noValidators: true });   // same length, different bytes
        const before = (await cacheRecords()).find((r) => r.key === `stats/${UUID[1]}.json`);
        await openPage();
        await sync();
        const after = (await cacheRecords()).find((r) => r.key === `stats/${UUID[1]}.json`);
        eq(after.size, before.size, 'test needs equal sizes');
        assert(after.hash !== before.hash, 'equal-size change was missed');
    });

    await test('empty cache and server down: a clear error, nothing opened', async () => {
        await evaluate(`__t.srvCache.all().then((a) => __t.srvCache.drop(a.map((r) => r.key)))`);
        srv.down = true;
        await openPage();
        await sync();
        srv.down = false;
        assert(/Could not reach the server.*Nothing has been saved/.test(await srvMsg()), 'message: ' + await srvMsg());
        assert(!(await viewerOpen()), 'viewer opened');
    });

    await test('the server has no files at all', async () => {
        const saved = Object.fromEntries(Object.entries(remote).map(([k, v]) => [k, new Map(v)]));
        Object.values(remote).forEach((m) => m.clear());
        await openPage();
        await sync();
        assert(/no player files/.test(await srvMsg()), 'message: ' + await srvMsg());
        Object.entries(saved).forEach(([k, v]) => (remote[k] = v));
    });

    await test('the cache survives a browser restart', async () => {
        await openPage();
        await sync();
        const before = await cacheRecords();
        eq(before.length, WANTED, 'cached before restart');
        await stopChrome();
        await startChrome();
        await openPage();
        eq(JSON.stringify(await cacheRecords()), JSON.stringify(before), 'cache after restart');
        await sync();
        eq(downloads(), 1, 'downloads after restart (only the validator-less file)');
        assert(await viewerOpen(), 'viewer did not open');
    });

    await test('bots.json leaves bots out and drops their saved files', async () => {
        const bot = (await cacheRecords()).find((r) => !r.key.startsWith('data/') && !r.key.includes(UUID[0])).key.split('/')[1].replace('.json', '');
        srv.bots = JSON.stringify([{ name: 'IronFarm', uuid: bot }, 'not-a-uuid']);
        await openPage();
        await sync();
        eq(files().filter((r) => r.f.includes(bot)).length, 0, 'requests for the bot\'s files');
        eq((await cacheRecords()).filter((r) => r.key.includes(bot)).length, 0, 'bot files still cached');
        assert(files().length > 0, 'other players were not synced');
        // the list is remembered for when bots.json can't be read
        srv.bots = '{broken';
        await openPage();
        await sync();
        eq(files().filter((r) => r.f.includes(bot)).length, 0, 'bot requested with a broken bots.json');
        srv.bots = null;
        await openPage();
        await sync();
        assert(files().some((r) => r.f.includes(bot)), 'bot still left out after bots.json was removed');
    });

    await test('no page errors', async () => { eq(pageErrors.length, 0, 'uncaught errors: ' + pageErrors.join(' / ')); });

    await stopChrome();
    server.close();
    try { fs.rmSync(profile, { recursive: true, force: true }); } catch (e) { }
    const bad = results.filter((r) => !r[0]).length;
    console.log(`\n${results.length - bad} passed, ${bad} failed`);
    process.exit(bad ? 1 : 0);
})().catch(async (e) => { console.error(e); try { await stopChrome(); } catch (er) { } process.exit(1); });
