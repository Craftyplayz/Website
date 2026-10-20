// Tests for the NBT viewer's Backpacks page and raw world data (minecraft/tools/nbt-viewer).
//   usage: node _dev/scripts/nbtviewer-backpacks.test.js   (Node 22+, Chrome installed; set CHROME to override)
// Builds a small world in memory - Sophisticated Backpacks storage, a region file with a chest and a placed
// backpack, an entities file with a dropped backpack, a level.dat with a seed - serves index.php with its
// PHP stripped, and feeds the files to the viewer as if a world folder had been dropped.
const http = require('http');
const fs = require('fs');
const os = require('os');
const path = require('path');
const zlib = require('zlib');
const { spawn } = require('child_process');

const root = path.join(__dirname, '..', '..');
const CHROME = process.env.CHROME || ['C:/Program Files/Google/Chrome/Application/chrome.exe', '/usr/bin/google-chrome', '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'].find((p) => fs.existsSync(p));
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'nbt-bp-'));

/* ---------- a tiny NBT writer: values are [type, value] ---------- */
const T = { byte: 1, short: 2, int: 3, long: 4, float: 5, double: 6, string: 8, list: 9, compound: 10, intArray: 11 };
const B = (v) => ['byte', v], I = (v) => ['int', v], L = (v) => ['long', v], D = (v) => ['double', v], S = (v) => ['string', v], IA = (v) => ['intArray', v];
const C = (o) => ['compound', o], LS = (et, a) => ['list', [et, a]];
function payload([t, v]) {
    switch (t) {
        case 'byte': return Buffer.from([v & 255]);
        case 'short': { const b = Buffer.alloc(2); b.writeInt16BE(v); return b; }
        case 'int': { const b = Buffer.alloc(4); b.writeInt32BE(v); return b; }
        case 'long': { const b = Buffer.alloc(8); b.writeBigInt64BE(BigInt(v)); return b; }
        case 'double': { const b = Buffer.alloc(8); b.writeDoubleBE(v); return b; }
        case 'string': { const s = Buffer.from(v, 'utf8'), b = Buffer.alloc(2); b.writeUInt16BE(s.length); return Buffer.concat([b, s]); }
        case 'intArray': { const b = Buffer.alloc(4 + v.length * 4); b.writeInt32BE(v.length); v.forEach((x, i) => b.writeInt32BE(x, 4 + i * 4)); return b; }
        case 'list': { const [et, a] = v, h = Buffer.alloc(5); h[0] = T[et]; h.writeInt32BE(a.length, 1); return Buffer.concat([h, ...a.map((x) => payload([et, x[1]]))]); }
        case 'compound': return Buffer.concat([...Object.entries(v).map(([k, x]) => Buffer.concat([Buffer.from([T[x[0]]]), payload(S(k)), payload(x)])), Buffer.from([0])]);
    }
}
const nbt = (rootObj) => Buffer.concat([Buffer.from([10]), payload(S('')), payload(C(rootObj))]);
// a region file holding the given chunks: [{i, root}]
function region(chunks) {
    const head = Buffer.alloc(8192), bodies = [];
    let sector = 2;
    for (const { i, root: r } of chunks) {
        const z = zlib.deflateSync(nbt(r)), len = Buffer.alloc(5);
        len.writeUInt32BE(z.length + 1); len[4] = 2;
        const data = Buffer.concat([len, z]), sectors = Math.ceil(data.length / 4096);
        head.writeUInt32BE((sector << 8) | sectors, i * 4);
        bodies.push(Buffer.concat([data, Buffer.alloc(sectors * 4096 - data.length)]));
        sector += sectors;
    }
    return Buffer.concat([head, ...bodies]);
}

/* ---------- the test world ---------- */
const U1 = [0x11111111, 0x22222222, 0x33333333, 0x44444444], U1S = '11111111-2222-2222-3333-333344444444';
const U2 = [0x55555555, 0x66666666, 0x77777777, 0x08888888], U2S = '55555555-6666-6666-7777-777708888888';
const U3 = [0x0a0a0a0a, 0x0b0b0b0b, 0x0c0c0c0c, 0x0d0d0d0d];     // a backpack kept inside backpack 1
const SEED = 918273645;
const bpItem = (id, u, extra = {}) => C({ id: S(id), count: I(1), components: C({ ...(u ? { 'sophisticatedcore:storage_uuid': IA(u) } : {}), ...extra }) });
const slot = (n, item) => C({ Slot: B(n), ...item[1] });
const files = {
    'world/level.dat': zlib.gzipSync(nbt({ Data: C({ LevelName: S('Test world'), DataVersion: I(4440), GameRules: C({ keepInventory: S('true') }), WorldGenSettings: C({ seed: L(SEED) }) }) })),
    'world/data/sophisticatedbackpacks.dat': zlib.gzipSync(nbt({
        DataVersion: I(4440),
        data: C({
            backpackContents: LS('compound', [
                C({ uuid: IA(U1), contents: C({ inventory: C({ Items: LS('compound', [slot(0, C({ id: S('minecraft:diamond'), count: I(12) })), slot(4, bpItem('sophisticatedbackpacks:backpack', U3))]) }), upgradeInventory: C({ Items: LS('compound', [slot(0, C({ id: S('sophisticatedbackpacks:pickup_upgrade'), count: I(1) }))]) }) }) }),
                C({ uuid: IA(U2), contents: C({ inventory: C({ Items: LS('compound', [slot(2, C({ id: S('minecraft:golden_apple'), count: I(3) }))]) }) }) }),
                C({ uuid: IA(U3), contents: C({ inventory: C({ Items: LS('compound', [slot(0, C({ id: S('minecraft:emerald'), count: I(7) }))]) }) }) }),
            ]),
            accessLogRecords: LS('compound', [
                C({ backpackItemRegistryName: S('sophisticatedbackpacks:iron_backpack'), backpackUuid: IA(U2), playerName: S('Alex'), accessTime: L(1760000000000), backpackName: S('Iron Backpack') }),
                C({ backpackItemRegistryName: S('sophisticatedbackpacks:iron_backpack'), backpackUuid: IA(U2), playerName: S('Steve'), accessTime: L(1770000000000), backpackName: S('Iron Backpack') }),
            ]),
        }),
    })),
    'world/region/r.0.0.mca': region([{
        i: 0, root: {
            DataVersion: I(4440), xPos: I(0), zPos: I(0),
            block_entities: LS('compound', [
                C({ id: S('minecraft:chest'), x: I(5), y: I(64), z: I(7), Items: LS('compound', [slot(3, bpItem('sophisticatedbackpacks:backpack', U1, { 'sophisticatedcore:main_color': I(0xff0000) }))]) }),
                C({ id: S('sophisticatedbackpacks:backpack'), x: I(10), y: I(70), z: I(12), backpackData: bpItem('sophisticatedbackpacks:iron_backpack', U2) }),
            ]),
        },
    }, { i: 33, root: { DataVersion: I(4440), xPos: I(1), zPos: I(1), block_entities: LS('compound', [C({ id: S('minecraft:furnace'), x: I(20), y: I(60), z: I(20) })]) } }]),
    'world/entities/r.0.0.mca': region([{
        i: 1, root: {
            DataVersion: I(4440), Position: IA([1, 0]),
            Entities: LS('compound', [C({ id: S('minecraft:item'), Pos: LS('double', [D(17.5), D(65), D(-3.2)]), Item: bpItem('sophisticatedbackpacks:gold_backpack', null) })]),
        },
    }]),
};

// page_head() would link global.css, components.css and the page's own stylesheets; the stripped page gets them here
const withCss = (src) => src.replace(/<\?php[\s\S]*?\?>/g, '').replace('</head>', ['/assets/css/global.css', '/assets/css/components.css', ...(src.match(/\/assets\/css\/pages\/[\w.-]+\.css/g) || [])].map((h) => `<link rel="stylesheet" href="${h}">`).join('') + '</head>');

/* ---------- static server ---------- */
const TYPES = { '.js': 'text/javascript', '.css': 'text/css', '.json': 'application/json', '.png': 'image/png', '.svg': 'image/svg+xml', '.webp': 'image/webp' };
const server = http.createServer((req, res) => {
    const u = new URL(req.url, 'http://x');
    const p = decodeURIComponent(u.pathname);
    if (p.startsWith('/__world/')) { const b = files[p.slice(9)]; res.writeHead(b ? 200 : 404); return res.end(b || ''); }
    if (p.endsWith('.php') && !p.endsWith('/index.php')) { res.writeHead(404); return res.end(); }
    let file = path.join(root, p);
    if (p.endsWith('/')) file = path.join(file, 'index.php');
    if (!file.startsWith(root) || !fs.existsSync(file) || fs.statSync(file).isDirectory()) { res.writeHead(404); return res.end(); }
    if (file.endsWith('.php')) {
        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        const hook = 'window.__t = { get players() { return players; }, get view() { return view; }, CTX, addFiles, bpList, scanRegions };';
        return res.end(withCss(fs.readFileSync(file, 'utf8')).replace(/\n    \}\)\(\);\r?\n/, `\n        ${hook}\n    })();\n`));
    }
    res.writeHead(200, { 'Content-Type': TYPES[path.extname(file)] || 'application/octet-stream' });
    fs.createReadStream(file).pipe(res);
});

/* ---------- a tiny CDP client ---------- */
let chrome, ws, seq = 0;
const pending = new Map(), listeners = [], pageErrors = [];
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
    const targets = await (await fetch(`http://127.0.0.1:${new URL(wsUrl).port}/json/list`)).json();
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
}
listeners.push((m) => { if (m.method === 'Runtime.exceptionThrown') pageErrors.push(m.params.exceptionDetails.exception ? m.params.exceptionDetails.exception.description : m.params.exceptionDetails.text); });
async function stopChrome() { try { await cdp('Browser.close'); } catch (e) { } await new Promise((r) => (chrome.exitCode !== null ? r() : chrome.once('exit', r))); }
async function evaluate(expr) {
    const r = await cdp('Runtime.evaluate', { expression: expr, awaitPromise: true, returnByValue: true });
    if (r.exceptionDetails) throw new Error('page: ' + (r.exceptionDetails.exception ? r.exceptionDetails.exception.description : r.exceptionDetails.text));
    return r.result.value;
}
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
async function openPage(url) {
    const loaded = new Promise((r) => { const l = (m) => { if (m.method === 'Page.loadEventFired') { listeners.splice(listeners.indexOf(l), 1); r(); } }; listeners.push(l); });
    await cdp('Page.navigate', { url });
    await loaded;
}
// drop the test world (or some of its files) on the viewer, as a folder
const drop = (names) => evaluate(`Promise.all(${JSON.stringify(names)}.map(async (p) => ({ path: p, file: new File([await (await fetch('/__world/' + p)).arrayBuffer()], p.split('/').pop(), { lastModified: Date.now() }) }))).then((l) => __t.addFiles(l)).then(() => true)`);
const html = (sel) => evaluate(`(document.querySelector(${JSON.stringify(sel)}) || {}).innerHTML || ''`);
const search = (v) => evaluate(`{ const q = document.querySelector('#q'); q.value = ${JSON.stringify(v)}; q.dispatchEvent(new Event('input')); } true`);
const click = (sel) => evaluate(`document.querySelector(${JSON.stringify(sel)}).click(); true`);

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
    const base = `http://127.0.0.1:${server.address().port}/minecraft/tools/nbt-viewer/`;
    await startChrome();
    console.log('NBT viewer backpacks + raw world data');
    await openPage(base);

    await test('a dropped world finds every backpack (small worlds scan right away)', async () => {
        await drop(Object.keys(files));
        eq(await evaluate(`__t.CTX.regionDone.size`), 2, 'region files scanned');
        const list = await evaluate(`__t.bpList().map((b) => ({ uuid: b.uuid, id: b.item.full, locs: b.locs.length, n: b.contents.length, total: b.total }))`);
        if (process.env.DEBUG) console.log(JSON.stringify(list), await evaluate('JSON.stringify(__t.CTX.raw.map((f) => f.file))'));
        eq(list.length, 4, 'backpacks: 3 stored + 1 dropped without storage');
        const b1 = list.find((b) => b.uuid === U1S), b2 = list.find((b) => b.uuid === U2S);
        assert(b1 && b1.n === 2 && b1.total === 13, 'backpack 1 contents: ' + JSON.stringify(b1));
        assert(b2 && b2.id === 'sophisticatedbackpacks:iron_backpack' && b2.locs === 1, 'placed iron backpack: ' + JSON.stringify(b2));
        assert(list.some((b) => !b.uuid && b.id === 'sophisticatedbackpacks:gold_backpack'), 'dropped gold backpack missing');
        assert(!(await evaluate(`document.querySelector('#bpBtn').classList.contains('hide')`)), 'Backpacks button hidden');
    });

    await test('Backpacks page lists them with their locations', async () => {
        await click('#bpBtn');
        eq(await evaluate(`__t.view`), 'backpacks', 'view');
        const h = await html('#b-list');
        assert(/Chest at <span class="mono">5, 64, 7<\/span>/.test(h), 'chest location missing');
        assert(/Placed at <span class="mono">10, 70, 12<\/span>/.test(h), 'placed location missing');
        assert(/Dropped item at <span class="mono">17, 65, -4<\/span>/.test(h), 'dropped item location missing');
        assert(/Inside another backpack/.test(h), 'nested backpack location missing');
        eq(await evaluate(`document.querySelectorAll('#b-list .bprows .fitem').length`), 4, 'rows');
    });

    await test('clicking a backpack shows contents, upgrades, colour and who opened it', async () => {
        await click(`#b-list .fitem[data-b="${U2S}"]`);
        let h = await html('#b-sel');
        assert(/Last opened by/.test(h) && /Steve/.test(h), 'last opener (newest log record) missing');
        assert(/Access log/.test(h) && /Alex/.test(h), 'access log missing');
        eq(await evaluate(`document.querySelectorAll('#b-sel .slots .slot').length`), 54, 'iron backpack slots');
        await click(`#b-list .fitem[data-b="${U1S}"]`);
        h = await html('#b-sel');
        assert(/#ff0000/.test(h), 'main colour missing');
        assert(/Upgrades/.test(h), 'upgrade grid missing');
        assert(/Backpack storage entry/.test(h) && /Container: Chest/.test(h), 'raw trees missing');
        eq(await evaluate(`document.querySelectorAll('#b-sel .slots .slot.has').length`), 3, 'filled slots (2 items + 1 upgrade)');
        // the nested backpack links back to its parent
        const inner = await evaluate(`__t.bpList().find((b) => b.locs.some((l) => l.kind === 'inside')).key`);
        await click(`#b-list .fitem[data-b="${inner}"]`);
        await click(`#b-sel .linkish[data-b="${U1S}"]`);
        eq(await evaluate(`document.querySelector('#b-list .fitem.on').dataset.b`), U1S, 'parent link');
    });

    await test('search filters the backpack list', async () => {
        await search('golden apple');
        await sleep(200);
        eq(await evaluate(`[...document.querySelectorAll('#b-list .bprows .fitem')].filter((e) => getComputedStyle(e).display !== 'none').length`), 1, 'rows matching');
        await search('');
        await sleep(150);
    });

    await test('World & server lists raw world data and region chunks, seed stays hidden', async () => {
        await click('#srvBtn');
        eq(await evaluate(`__t.view`), 'server', 'view');
        const h = await html('#w-data');
        assert(/world\/level\.dat/.test(h) && /sophisticatedbackpacks\.dat/.test(h), 'raw world files missing');
        await evaluate(`[...document.querySelectorAll('#w-data .fileblk')].forEach((d) => (d.open = true)); true`);
        await sleep(100);
        if (process.env.DEBUG) console.log((await html('#w-data')).slice(0, 1200));
        assert(/<span class="k">Data<\/span>/.test(await html('#w-data')), 'level.dat tree did not open');
        // the search box looks through world files, but never at the seed
        await search('seed');
        await sleep(200);
        const hits = await html('#hits');
        assert(/matches/.test(hits), 'no search results box');
        assert(!hits.includes(String(SEED)), 'seed shown in search');
        await search('Test world');
        await sleep(200);
        assert(/LevelName/.test(await html('#hits')), 'search did not find LevelName');
        await search('');
        await sleep(100);
        assert(!(await evaluate(`document.body.innerText.includes('${SEED}')`)), 'seed visible on the page');
        // region file -> chunk list -> chunk tree
        await evaluate(`document.querySelectorAll('#w-region details').forEach((d) => { d.open = true; }); true`);
        await sleep(300);
        const r = await html('#w-region');
        assert(/Chunk 0, 0/.test(r) && /Chunk 1, 1/.test(r), 'chunk list missing: ' + r.slice(0, 300));
        await evaluate(`document.querySelectorAll('#w-region [data-chunk]').forEach((d) => { d.open = true; }); true`);
        await sleep(300);
        assert(/block_entities/.test(await html('#w-region')), 'chunk NBT did not open');
    });

    await test('big worlds wait for the Scan button', async () => {
        await openPage(base);
        // pretend the region files are huge so they are not scanned on drop
        await evaluate(`Object.defineProperty(File.prototype, 'size', { get() { return 80e6; } }); true`);
        await drop(['world/data/sophisticatedbackpacks.dat', 'world/region/r.0.0.mca', 'world/entities/r.0.0.mca']);
        eq(await evaluate(`__t.CTX.regionDone.size`), 0, 'scanned before the button');
        await click('#bpBtn');
        assert(/Scan world/.test(await html('#b-list')), 'Scan button missing');
        await click('#b-list [data-act="scanmca"]');
        for (let i = 0; i < 100 && (await evaluate(`__t.CTX.regionDone.size`)) < 2; i++) await sleep(50);
        await sleep(100);
        eq(await evaluate(`__t.CTX.regionDone.size`), 2, 'scanned after the button');
        assert(/Placed at/.test(await html('#b-list')), 'locations after scan');
    });

    await test('no page errors', async () => { eq(pageErrors.length, 0, 'errors: ' + pageErrors.join(' | ')); });

    await stopChrome();
    server.close();
    try { fs.rmSync(profile, { recursive: true, force: true }); } catch (e) { }
    const failed = results.filter((r) => !r[0]).length;
    console.log(failed ? `${failed} of ${results.length} failed` : `all ${results.length} passed`);
    process.exit(failed ? 1 : 0);
})().catch(async (e) => { console.error(e); try { await stopChrome(); } catch (er) { } process.exit(1); });
