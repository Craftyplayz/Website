// Collection Hallway: a walkable 3D copy of the collection hallway (one double chest per item, A to Z),
// with search that teleports, a details drawer per chest and a schematic check (check.js) that colours every chest.
const { $, $$, esc, store, debounce, copy, toast } = UI;
const E = window.HW.entries, N = E.length, X0 = -629, HALL_Z = -625.5, KEY = 'hw-check';
const ICON = (id) => `/minecraft/tools/item-list/minecraft_${id}.png`;
const css = (v) => getComputedStyle(document.documentElement).getPropertyValue(v).trim();
const STATUS = { complete: 'Complete', partial: 'Not full', wrong: 'Wrong item', missing: 'Missing' };

// pattern from the owner: 1 north bottom, 2 north top, 3 south bottom, 4 south top, then 3 blocks east; x is the west half
const chests = E.map((e, i) => {
    const s = Math.floor(i / 4), r = i % 4;
    return { ...e, i, x: X0 + s * 3, y: 36 + (r % 2) * 2, z: r < 2 ? -630 : -622, north: r < 2 };
});
const SECTIONS = Math.ceil(N / 4), X_END = X0 + (SECTIONS - 1) * 3 + 2;

let result = store.get(KEY, null);
if (result && (!result.chests || result.chests.length !== N)) result = null; // the list changed since this check
let filter = '', hits = [], sel = -1, look = -1;
const statusOf = (i) => (result ? result.chests[i].s : '');

/* ---------- 3D ---------- */
let THREE, ren, scene, cam, inst, frames, atlas, ag;
const pos = { x: X0 - 2, y: 37.62, z: HALL_Z }, view = { yaw: -Math.PI / 2, pitch: 0 }, keys = new Set();
const cv = $('#hw-c');

function chestTextures() {
    const tex = (w, h, draw) => {
        const c = document.createElement('canvas'); c.width = w; c.height = h;
        draw(c.getContext('2d'), w, h);
        const t = new THREE.CanvasTexture(c);
        t.magFilter = t.minFilter = THREE.NearestFilter; t.colorSpace = THREE.SRGBColorSpace;
        return t;
    };
    // shortcut: drawn oak grain, not the game's chest texture; swap in entity/chest/normal_left.png + normal_right.png when the owner sends them
    const PL = ['#a87a32', '#b0823a', '#9c6e2b', '#b88a3e', '#8f6326'];
    const grain = (g, w, h, seed) => {
        let s = 97 + seed * 31; const r = (n) => (s = (s * 16807) % 2147483647) % n;
        for (let y = 0; y < h; y++) for (let x = 0; x < w;) { const run = 2 + r(6); g.fillStyle = PL[r(10) < 6 ? r(2) : 2 + r(3)]; g.fillRect(x, y, run, 1); x += run; }
        g.fillStyle = '#2b1f10'; g.fillRect(0, 0, w, 1); g.fillRect(0, h - 1, w, 1); g.fillRect(0, 0, 1, h); g.fillRect(w - 1, 0, 1, h);
    };
    const seam = (g, w) => { g.fillStyle = '#2b1f10'; g.fillRect(0, 4, w, 2); }; // the lid is the top 5 of 14 px
    const front = tex(30, 14, (g, w, h) => {
        grain(g, w, h, 1); seam(g, w);
        g.fillStyle = '#d8d8d8'; g.fillRect(14, 2, 2, 4); g.fillStyle = '#a0a0a0'; g.fillRect(15, 2, 1, 4); g.fillStyle = '#5e5e5e'; g.fillRect(14, 5, 2, 1);
    });
    const back = tex(30, 14, (g, w, h) => { grain(g, w, h, 2); seam(g, w); });
    const side = tex(14, 14, (g, w, h) => { grain(g, w, h, 3); seam(g, w); });
    const top = tex(30, 14, (g, w, h) => grain(g, w, h, 4));
    const m = (t) => new THREE.MeshLambertMaterial({ map: t });
    return [m(side), m(side), m(top), m(top), m(front), m(back)]; // +x -x +y -y +z -z; the front faces +z before rotating
}

// item frames: one map per chest at y 37; facing the wall, the left frame is the bottom chest and the right frame the top one
const CELL = 80, ATLAS = 4096, COLS = Math.floor(ATLAS / CELL), loaded = new Set();
function frameCell(i, img) {
    const x = (i % COLS) * CELL, y = Math.floor(i / COLS) * CELL;
    ag.fillStyle = '#d9cf9c'; ag.fillRect(x, y, CELL, CELL);
    ag.fillStyle = '#a3492a'; ag.fillRect(x + 5, y + 5, CELL - 10, CELL - 10);
    ag.fillStyle = '#b8573a'; ag.fillRect(x + 8, y + 8, CELL - 16, CELL - 16);
    if (img) ag.drawImage(img, x + 8, y + 8, 64, 64);
    else { ag.fillStyle = '#f1d9b0'; ag.font = 'bold 15px sans-serif'; ag.textAlign = 'center'; ag.fillText(chests[i].name.slice(0, 6), x + CELL / 2, y + CELL / 2 + 5); }
}
// icons load only for frames near you, so walking in doesn't fetch 1,400 images
function loadIcons() {
    const s0 = Math.max(0, Math.floor((pos.x - X0 - 45) / 3)), s1 = Math.min(SECTIONS, Math.ceil((pos.x - X0 + 45) / 3));
    for (let i = s0 * 4; i < Math.min(N, s1 * 4); i++) {
        if (loaded.has(i) || !chests[i].icon) continue;
        loaded.add(i);
        const im = new Image();
        im.onload = () => { frameCell(i, im); atlas.needsUpdate = true; };
        im.src = ICON(chests[i].icon);
    }
}

function buildScene() {
    ren = new THREE.WebGLRenderer({ canvas: cv, antialias: true });
    ren.setPixelRatio(Math.min(devicePixelRatio, 2));
    scene = new THREE.Scene();
    const bg = new THREE.Color(css('--sunken'));
    scene.background = bg; scene.fog = new THREE.Fog(bg, 20, 70);
    scene.add(new THREE.HemisphereLight(0xffffff, 0x555555, 2.2));
    cam = new THREE.PerspectiveCamera(70, 1, 0.05, 120);
    cam.rotation.order = 'YXZ';

    // grey slab floor, top at y 36 so the bottom chests stand on it
    const floor = new THREE.Mesh(new THREE.BoxGeometry(X_END - X0 + 9, 0.5, 11), new THREE.MeshLambertMaterial({ color: 0x8b8b8b }));
    floor.position.set((X0 + X_END + 1) / 2, 35.75, HALL_Z);
    scene.add(floor);

    inst = new THREE.InstancedMesh(new THREE.BoxGeometry(30 / 16, 14 / 16, 14 / 16), chestTextures(), N);
    const m4 = new THREE.Matrix4(), q = new THREE.Quaternion(), up = new THREE.Vector3(0, 1, 0), one = new THREE.Vector3(1, 1, 1);
    for (const c of chests) {
        q.setFromAxisAngle(up, c.north ? 0 : Math.PI); // north wall faces +z into the hall, south wall faces -z
        inst.setMatrixAt(c.i, m4.compose(new THREE.Vector3(c.x + 1, c.y + 7 / 16, c.z + 0.5), q, one));
    }
    scene.add(inst);

    const ac = document.createElement('canvas'); ac.width = ac.height = ATLAS;
    ag = ac.getContext('2d'); ag.imageSmoothingEnabled = false;
    atlas = new THREE.CanvasTexture(ac);
    atlas.magFilter = THREE.NearestFilter; atlas.minFilter = THREE.LinearMipmapLinearFilter;
    atlas.colorSpace = THREE.SRGBColorSpace; atlas.flipY = false; atlas.anisotropy = ren.capabilities.getMaxAnisotropy();
    chests.forEach((c) => frameCell(c.i));
    const P = [], U = [], S = 0.375; // 12/16 of a block
    for (const c of chests) {
        const bottom = c.y === 36, rx = c.north ? 1 : -1; // your right, facing the wall
        const cx = c.north === bottom ? c.x + 0.5 : c.x + 1.5, cz = c.north ? -629.97 : -621.03;
        const u0 = (c.i % COLS) * CELL / ATLAS, v0 = Math.floor(c.i / COLS) * CELL / ATLAS, d = CELL / ATLAS;
        const p = (sx, sy) => [cx + sx * S * rx, 37.5 + sy * S, cz];
        const quad = [p(-1, 1), p(1, 1), p(1, -1), p(-1, -1)], uv = [[u0, v0], [u0 + d, v0], [u0 + d, v0 + d], [u0, v0 + d]];
        for (const k of [0, 1, 2, 0, 2, 3]) { P.push(...quad[k]); U.push(...uv[k]); }
    }
    const g = new THREE.BufferGeometry();
    g.setAttribute('position', new THREE.Float32BufferAttribute(P, 3));
    g.setAttribute('uv', new THREE.Float32BufferAttribute(U, 2));
    frames = new THREE.Mesh(g, new THREE.MeshBasicMaterial({ map: atlas, side: THREE.DoubleSide }));
    scene.add(frames);
}

let TINT;
function paint() {
    if (inst) {
        const hit = new Set(hits);
        for (const c of chests) inst.setColorAt(c.i, c.i === sel ? TINT.sel : hit.has(c.i) && (hits.length < N) ? TINT.hit : c.i === look ? TINT.look : TINT[statusOf(c.i)] || TINT.none);
        inst.instanceColor.needsUpdate = true;
    }
    drawMap();
}

const ray = { r: null, v: null };
function pick(x, y) {
    ray.r.setFromCamera(ray.v.set(x, y), cam); ray.r.far = 40;
    const h = ray.r.intersectObjects([inst, frames])[0];
    return !h ? -1 : h.object === inst ? h.instanceId : Math.floor(h.faceIndex / 2);
}

function teleport(i) {
    const c = chests[i];
    sel = i;
    Object.assign(pos, { x: c.x + 1, y: 37.62, z: HALL_Z });
    view.yaw = c.north ? 0 : Math.PI; view.pitch = c.y > 36 ? 0.25 : -0.35;
    loadIcons(); paint(); target(i);
}

/* ---------- strip map: one column per section; rows top to bottom: north top, south top, south bottom, north bottom ---------- */
const map = $('#hw-map'), mg = map.getContext('2d');
map.width = SECTIONS; map.height = 4;
let MAPC;
function drawMap() {
    if (!MAPC) MAPC = { complete: css('--ok-line'), partial: css('--series-2'), wrong: css('--danger-line'), missing: css('--line-strong'), '': css('--card2'), sel: css('--accent'), hit: css('--highlight') };
    const hit = hits.length < N ? new Set(hits) : new Set();
    for (const c of chests) {
        mg.fillStyle = c.i === sel ? MAPC.sel : hit.has(c.i) ? MAPC.hit : MAPC[statusOf(c.i)];
        mg.fillRect(Math.floor(c.i / 4), [3, 0, 2, 1][c.i % 4], 1, 1);
    }
}
map.addEventListener('click', (e) => {
    const r = map.getBoundingClientRect(), s = Math.floor((e.clientX - r.left) / r.width * SECTIONS);
    if (inst) teleport(Math.min(N - 1, Math.max(0, s) * 4));
});

/* ---------- looking-at card ---------- */
function target(i) {
    const box = $('#hw-target');
    if (i < 0) { box.hidden = true; return; }
    const c = chests[i], s = statusOf(i), r = result && result.chests[i];
    box.hidden = false;
    box.innerHTML = `${c.icon ? `<img src="${ICON(c.icon)}" alt="" width="32" height="32">` : ''}<span class="hw-tn"><b>${esc(c.name)}</b>`
        + `<small>Chest ${(i + 1).toLocaleString()} · <span class="mono">${c.x} ${c.y} ${c.z}</span>${s ? ` · <span class="hw-s ${s}">${STATUS[s]}${s === 'partial' ? ` ${r.n.toLocaleString()} / ${r.of.toLocaleString()}` : ''}</span>` : ''}</small></span>`;
}

/* ---------- search, filters and stepping ---------- */
const qi = $('#hw-q'), res = $('#hw-res');
let act = 0;
function search() {
    const t = qi.value.trim().toLowerCase();
    let list = chests;
    if (filter) list = list.filter((c) => statusOf(c.i) === filter);
    if (/^\d+$/.test(t)) list = list.filter((c) => c.i === +t - 1);
    else if (t) list = list.filter((c) => c.name.toLowerCase().includes(t));
    hits = t || filter ? list.map((c) => c.i) : [];
    act = 0;
    res.innerHTML = t ? list.slice(0, 50).map((c, k) => row(c, k === 0)).join('') + (list.length > 50 ? `<li class="muted" role="presentation">${(list.length - 50).toLocaleString()} more, keep typing</li>` : '') + (list.length ? '' : '<li class="muted" role="presentation">No chests match</li>') : '';
    $('#hw-step').hidden = !hits.length || !filter && !t;
    stepLabel();
    paint();
}
const row = (c, on) => `<li role="option" data-i="${c.i}" aria-selected="${on}"><span class="mono">${c.i + 1}</span>${c.icon ? `<img src="${ICON(c.icon)}" alt="" width="20" height="20" loading="lazy">` : '<i></i>'}<span class="grow">${esc(c.name)}</span>${statusOf(c.i) ? `<i class="hw-dot ${statusOf(c.i)}" title="${STATUS[statusOf(c.i)]}"></i>` : ''}</li>`;
function stepLabel() {
    const k = hits.indexOf(sel);
    $('#hw-stepn').textContent = `${k < 0 ? '-' : (k + 1).toLocaleString()} of ${hits.length.toLocaleString()}`;
}
function step(d) {
    if (!hits.length) return;
    const k = hits.indexOf(sel);
    go(hits[k < 0 ? (d > 0 ? 0 : hits.length - 1) : (k + d + hits.length) % hits.length], false);
}
function go(i, open = true) {
    if (inst) teleport(i);
    else sel = i;
    stepLabel();
    if (open) openDrawer(i);
}
qi.addEventListener('input', debounce(search));
qi.addEventListener('keydown', (e) => {
    const li = $$('li[data-i]', res);
    if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && li.length) {
        e.preventDefault();
        li[act].setAttribute('aria-selected', 'false');
        act = (act + (e.key === 'ArrowDown' ? 1 : li.length - 1)) % li.length;
        li[act].setAttribute('aria-selected', 'true'); li[act].scrollIntoView({ block: 'nearest' });
    }
    if (e.key === 'Enter' && li.length) { res.innerHTML = ''; go(+li[act].dataset.i); }
    if (e.key === 'Escape') { qi.value = ''; search(); }
});
res.addEventListener('click', (e) => { const li = e.target.closest('li[data-i]'); if (li) { res.innerHTML = ''; go(+li.dataset.i); } });
$('#hw-step').addEventListener('click', (e) => { const b = e.target.closest('[data-step]'); if (b) step(+b.dataset.step); });
$('#hw-stats').addEventListener('click', (e) => {
    const b = e.target.closest('.hw-f'); if (!b) return;
    filter = filter === b.dataset.f ? '' : b.dataset.f;
    $$('.hw-f').forEach((x) => x.setAttribute('aria-pressed', String(x.dataset.f === filter)));
    search();
    if (filter && hits.length) step(1);
});

/* ---------- drawer ---------- */
const drawer = $('#hw-drawer'), detail = $('#hw-detail');
let R = null, rLoad = null, open = -1;
const loadRecipes = () => rLoad || (rLoad = fetch(window.HW.recipes).then((r) => { if (!r.ok) throw Error(); return r.json(); }).then((d) => (R = d)));
const label = (id) => (R && R.names[id]) || id.replace(/_/g, ' ').replace(/\b\w/, (m) => m.toUpperCase());
const img = (id, size = 20) => `<img src="${ICON(id)}" alt="" width="${size}" height="${size}" loading="lazy">`;
// items without an icon file keep their space but show nothing
document.addEventListener('error', (e) => { if (e.target.tagName === 'IMG' && e.target.closest('.hw, .hw-drawer')) e.target.style.visibility = 'hidden'; }, true);
const tpCmd = (c) => `/tp @s ${c.x + 1}.0 36 ${HALL_Z} ${c.north ? 180 : 0} ${c.y > 36 ? -15 : 25}`; // yaw 180 faces north, 0 south
const whatName = (m, id) => (m >= 0 ? chests[m].name : label(id));

function contents(i) {
    if (!result) return '';
    const r = result.chests[i], s = r.s;
    let body;
    if (r.found === 'none') body = '<p class="muted">There is no chest here in the schematic.</p>';
    else if (r.found === 'empty') body = '<p class="muted">The chest is empty.</p>';
    else body = `<div class="rows">${r.items.map(([m, id, n]) => `<div class="rowi">${img(m >= 0 && chests[m].icon ? chests[m].icon : id, 24)}<span class="grow">${esc(whatName(m, id))}`
        + `${m === i ? '' : `<small class="hw-wrongin">${m >= 0 ? `Belongs in chest ${(m + 1).toLocaleString()}` : 'Not in the collection list'}</small>`}</span><small>${n.toLocaleString()}</small></div>`).join('')}</div>`
        + `<p class="muted hw-slots">${r.slots} of 54 slots used</p>`;
    return `<section class="hw-sec"><h3>Inside this chest <span class="hw-s ${s}">${STATUS[s]}${s === 'partial' ? ` ${r.n.toLocaleString()} / ${r.of.toLocaleString()}` : ''}</span></h3>${body}</section>`;
}

function node(n, top) {
    const [id, q, how, kids] = n;
    const line = `${img(id)}<b>${q.toLocaleString()}</b><span class="grow">${esc(label(id))}${blocks(id, q)}</span><span class="hw-how ${how}">${how}</span>`;
    return kids ? `<details class="hw-node"${top ? ' open' : ''}><summary>${line}</summary>${kids.map((k) => node(k)).join('')}</details>` : `<div class="hw-leaf">${line}</div>`;
}
const blocks = (id, q) => (R.pack[id] && q >= 9 ? ` <small class="muted">= ${Math.floor(q / 9).toLocaleString()} ${esc(label(R.pack[id]))}${q % 9 ? ` + ${q % 9}` : ''}</small>` : '');
const leaves = (n, acc = {}) => { if (!n[3]) acc[n[0]] = (acc[n[0]] || 0) + n[1]; else n[3].forEach((k) => leaves(k, acc)); return acc; };

function recipeHtml(i) {
    const c = chests[i], x = R.recs[i];
    let h = '<section class="hw-sec"><h3>Recipe</h3>';
    if (!x.r) h += `<p class="muted">No crafting recipe. ${x.t ? 'Made in a furnace, see below.' : 'Found, traded, brewed or dropped.'}</p>`;
    else {
        const g = x.r.s ? [...x.r.g, ...Array(9 - x.r.g.length).fill('')] : x.r.g;
        h += `<div class="hw-craft"><div class="hw-grid3">${g.map((id) => `<span class="hw-slot">${id ? `<span title="${esc(label(id))}">${img(id, 32)}</span>` : ''}</span>`).join('')}</div><span class="hw-arrow" aria-hidden="true">&rarr;</span>`
            + `<span class="hw-slot hw-out">${img(c.icon || c.id, 32)}${x.r.c > 1 ? `<b>${x.r.c}</b>` : ''}</span></div>${x.r.s ? '<p class="muted">Shapeless: any arrangement works.</p>' : ''}`;
    }
    h += '</section>';
    if (x.t) {
        h += `<details class="hw-sec hw-raw"><summary>Raw materials for a full chest <span class="muted">${x.t[1].toLocaleString()} ${esc(c.name)}</span></summary>`
            + `<div class="rows">${Object.entries(leaves(x.t)).sort((a, b) => b[1] - a[1]).map(([id, q]) => `<div class="rowi">${img(id, 24)}<span class="grow">${esc(label(id))}${blocks(id, q)}</span><small>${q.toLocaleString()}</small></div>`).join('')}</div>`
            + `<h3>Crafting steps</h3>${node(x.t, true)}</details>`;
    }
    return h;
}

function openDrawer(i) {
    const c = chests[i];
    open = i; sel = i; paint();
    const wiki = 'https://minecraft.wiki/w/' + encodeURIComponent((c.id && R && R.names[c.id] && !c.need.length ? R.names[c.id] : c.name.replace(/\s*(\+|II|IV|\[JE\])$/, '')).replace(/ /g, '_'));
    detail.innerHTML = `<div class="hw-head">${c.icon ? img(c.icon, 48) : ''}<div><h2>${esc(c.name)}</h2><p class="muted">Chest ${(i + 1).toLocaleString()} · <span class="mono">${c.x} ${c.y} ${c.z}</span> · ${c.north ? 'north' : 'south'} wall, ${c.y > 36 ? 'top' : 'bottom'}</p></div></div>`
        + `<div class="hw-acts"><button type="button" class="btn primary" data-act="tp">Copy TP command</button><a class="btn" href="${wiki}" target="_blank" rel="noopener">Wiki page</a></div>`
        + `<code class="hw-tp mono">${esc(tpCmd(c))}</code>${contents(i)}<div id="hw-rec">${R ? recipeHtml(i) : '<p class="muted">Loading the recipe...</p>'}</div>`;
    drawer.classList.add('open'); drawer.setAttribute('aria-hidden', 'false');
    if (document.pointerLockElement) document.exitPointerLock();
    drawer.focus({ preventScroll: true });
    if (!R) loadRecipes().then(() => { if (open === i) $('#hw-rec').innerHTML = recipeHtml(i); }).catch(() => { if (open === i) $('#hw-rec').innerHTML = '<p class="err">Couldn\'t load the recipes. Try again in a minute.</p>'; });
}
function closeDrawer() { drawer.classList.remove('open'); drawer.setAttribute('aria-hidden', 'true'); open = -1; cv.focus({ preventScroll: true }); }
$('#hw-close').addEventListener('click', closeDrawer);
detail.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-act="tp"]'); if (!b) return;
    if (!(await copy(tpCmd(chests[open]), b))) { getSelection().selectAllChildren($('.hw-tp', detail)); toast('Press Ctrl+C to copy the command.'); }
});
addEventListener('keydown', (e) => { if (e.key === 'Escape' && open >= 0 && !document.pointerLockElement) closeDrawer(); });

/* ---------- schematic check ---------- */
const fmtDate = (ms) => new Date(ms).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
function showResult() {
    const has = !!result;
    $('#hw-stats').hidden = $('#hw-checked').hidden = $('#hw-clear').hidden = !has;
    $('#hw-note').hidden = !(has && result.note);
    if (has && result.note) $('#hw-note').textContent = result.note;
    $('.hw-help').hidden = has;
    if (!has) { $('#hw-lists').hidden = true; filter = ''; $$('.hw-f').forEach((x) => x.setAttribute('aria-pressed', 'false')); search(); return; }
    const when = $('#hw-checked');
    when.textContent = `Checked on ${fmtDate(result.when)} · ${result.file}`;
    when.title = new Date(result.when).toLocaleString('en-GB');
    for (const k of Object.keys(STATUS)) $(`[data-c="${k}"]`).textContent = result.counts[k].toLocaleString();

    const wrongRows = [];
    result.chests.forEach((r, i) => r.wrong.forEach(([m, id, n]) => wrongRows.push(`<tr><td><button type="button" class="btn small" data-go="${i}">${i + 1}</button> ${esc(chests[i].name)}</td><td>${esc(whatName(m, id))}</td><td class="num">${n.toLocaleString()}</td><td>${m >= 0 ? `<button type="button" class="btn small" data-go="${m}">${m + 1}</button>` : '<span class="muted">Not in the list</span>'}</td></tr>`)));
    $('#hw-wrong').hidden = !wrongRows.length;
    $('#hw-wrong-n').textContent = wrongRows.length.toLocaleString();
    $('#hw-wrong tbody').innerHTML = wrongRows.join('');
    $('#hw-extra').hidden = !result.extra.length;
    $('#hw-extra-n').textContent = result.extra.length.toLocaleString();
    $('#hw-extra tbody').innerHTML = result.extra.map((x) => `<tr><td class="mono">${x.x} ${x.y} ${x.z}</td><td>${x.items.length ? x.items.map(([m, id, n]) => `${n.toLocaleString()} ${esc(whatName(m, id))}`).join(', ') : '<span class="muted">Empty</span>'}</td></tr>`).join('');
    $('#hw-lists').hidden = !wrongRows.length && !result.extra.length;
    search();
}
$('#hw-lists').addEventListener('click', (e) => { const b = e.target.closest('[data-go]'); if (b) { go(+b.dataset.go); $('#hw-view').scrollIntoView({ behavior: 'smooth', block: 'center' }); } });
$('#hw-pick').addEventListener('click', () => $('#hw-file').click());
$('#hw-file').addEventListener('change', async (e) => {
    const f = e.target.files[0]; e.target.value = '';
    if (!f) return;
    const b = $('#hw-pick'); b.disabled = true; b.textContent = 'Checking...';
    try {
        const { format, containers, note } = await HallwayCheck.readContainers(await f.arrayBuffer());
        const r = HallwayCheck.check(containers, E);
        result = { when: Date.now(), file: f.name, format, note, ...r };
        store.set(KEY, result);
        showResult();
        toast(`Checked ${containers.length.toLocaleString()} chests: ${r.counts.complete.toLocaleString()} complete.`, 'ok');
    } catch (err) {
        toast(err.message || "Couldn't read that file.", 'err');
    } finally { b.disabled = false; b.textContent = 'Check a schematic'; }
});
$('#hw-clear').addEventListener('click', () => { result = null; try { localStorage.removeItem(KEY); } catch (e) { } showResult(); target(look >= 0 ? look : sel); });

/* ---------- controls ---------- */
const locked = () => document.pointerLockElement === cv;
const moveKeys = new Set(['KeyW', 'KeyA', 'KeyS', 'KeyD', 'Space', 'ShiftLeft', 'ControlLeft']);
addEventListener('keydown', (e) => {
    if (!locked() && document.activeElement !== cv) return;
    if (e.key === '/') { e.preventDefault(); document.exitPointerLock?.(); qi.focus(); return; }
    if (moveKeys.has(e.code)) { keys.add(e.code); e.preventDefault(); }
});
addEventListener('keyup', (e) => keys.delete(e.code));
addEventListener('blur', () => keys.clear());
const turn = (dx, dy) => { view.yaw -= dx * 0.0025; view.pitch = Math.max(-1.5, Math.min(1.5, view.pitch - dy * 0.0025)); };
addEventListener('mousemove', (e) => { if (locked()) turn(e.movementX, e.movementY); });
let drag = null, downAt = null;
cv.addEventListener('pointerdown', (e) => { downAt = [e.clientX, e.clientY]; if (!locked()) drag = [e.clientX, e.clientY]; });
addEventListener('pointermove', (e) => { if (!drag) return; turn(e.clientX - drag[0], e.clientY - drag[1]); drag = [e.clientX, e.clientY]; });
addEventListener('pointerup', () => (drag = null));
// click: while looking around, open the chest under the crosshair; otherwise the chest under the pointer, or start looking around
cv.addEventListener('click', (e) => {
    if (!inst || (downAt && Math.hypot(e.clientX - downAt[0], e.clientY - downAt[1]) > 5)) return;
    const r = cv.getBoundingClientRect();
    const i = locked() ? pick(0, 0) : pick((e.clientX - r.left) / r.width * 2 - 1, -(e.clientY - r.top) / r.height * 2 + 1);
    if (i >= 0) { openDrawer(i); return; }
    if (!locked() && matchMedia('(pointer: fine)').matches) { try { cv.requestPointerLock()?.catch?.(() => { }); } catch (err) { } }
});
$$('.hw-touch [data-key]').forEach((b) => {
    b.addEventListener('pointerdown', () => keys.add(b.dataset.key));
    for (const ev of ['pointerup', 'pointerleave', 'pointercancel']) b.addEventListener(ev, () => keys.delete(b.dataset.key));
});

/* ---------- start ---------- */
let visible = true;
new IntersectionObserver(([e]) => (visible = e.isIntersecting)).observe(cv);
function size() { const w = cv.clientWidth, h = cv.clientHeight; ren.setSize(w, h, false); cam.aspect = w / h; cam.updateProjectionMatrix(); }

showResult();
try {
    THREE = await import('https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.min.js');
    TINT = { none: new THREE.Color(1, 1, 1), complete: new THREE.Color(0.7, 1.25, 0.55), partial: new THREE.Color(1.45, 1.05, 0.35), wrong: new THREE.Color(1.5, 0.45, 0.4), missing: new THREE.Color(0.32, 0.32, 0.36), sel: new THREE.Color(0.55, 0.85, 1.6), hit: new THREE.Color(1.6, 1.35, 0.4), look: new THREE.Color(1.25, 1.25, 1.25) };
    ray.r = new THREE.Raycaster(); ray.v = new THREE.Vector2();
    buildScene();
    $('#hw-load').remove();
    addEventListener('resize', size); size();
    paint(); loadIcons();
    let last = performance.now(), frame = 0;
    (function loop(now) {
        requestAnimationFrame(loop);
        if (!visible || document.hidden) { last = now; return; }
        const dt = Math.min(0.05, (now - last) / 1000 || 0); last = now;
        const sp = (keys.has('ControlLeft') ? 20 : 5.6) * dt, fx = -Math.sin(view.yaw), fz = -Math.cos(view.yaw);
        if (keys.has('KeyW')) { pos.x += fx * sp; pos.z += fz * sp; }
        if (keys.has('KeyS')) { pos.x -= fx * sp; pos.z -= fz * sp; }
        if (keys.has('KeyD')) { pos.x -= fz * sp; pos.z += fx * sp; }
        if (keys.has('KeyA')) { pos.x += fz * sp; pos.z -= fx * sp; }
        if (keys.has('Space')) pos.y += sp;
        if (keys.has('ShiftLeft')) pos.y -= sp;
        pos.y = Math.max(37.62, pos.y); // stand on the slab
        cam.position.set(pos.x, pos.y, pos.z); cam.rotation.set(view.pitch, view.yaw, 0);
        if (++frame % 4 === 0) {
            const i = pick(0, 0);
            if (i !== look) { look = i; target(i >= 0 ? i : sel); paint(); }
            if (frame % 32 === 0) { loadIcons(); $('#hw-pos').textContent = `You: ${Math.floor(pos.x)} ${Math.floor(pos.y - 1.62)} ${Math.floor(pos.z)}`; }
        }
        ren.render(scene, cam);
    })(last);
    teleport(0);
} catch (err) {
    $('#hw-load').className = 'err hw-load';
    $('#hw-load').textContent = "Couldn't load the 3D view. Search and the schematic check still work; try reloading for the hallway.";
}
