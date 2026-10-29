// 3D dice for the homebrew sheet: three.js draws them, cannon-es throws them, and the face that lands up is the result.
// Loaded by sheet.js on the first roll. throwDice([[sides, count], ...], { body, ink }) -> Promise<[[sides, [values]], ...]>
const [THREE, CANNON] = await Promise.all([
    import('https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.min.js'),
    import('https://cdn.jsdelivr.net/npm/cannon-es@0.20.0/dist/cannon-es.js'),
]);

// ---- shapes: triangles grouped into faces by their normal, each face numbered ----
const V = (x, y, z) => new THREE.Vector3(x, y, z);
function trisOf(geo) {
    const g = geo.toNonIndexed(), p = g.attributes.position, out = [];
    for (let i = 0; i < p.count; i += 3) out.push([V(p.getX(i), p.getY(i), p.getZ(i)), V(p.getX(i + 1), p.getY(i + 1), p.getZ(i + 1)), V(p.getX(i + 2), p.getY(i + 2), p.getZ(i + 2))]);
    return out;
}
// pentagonal trapezohedron (d10): ring height solved so each kite is flat
function d10Tris() {
    const h = 1.15, ang = (i) => (i * Math.PI) / 5;
    const kite = (k) => { const T = V(0, h, 0), U0 = V(1, k, 0), U1 = V(Math.cos(ang(2)), k, Math.sin(ang(2))), L0 = V(Math.cos(ang(1)), -k, Math.sin(ang(1)));
        return U0.clone().sub(T).cross(U1.clone().sub(T)).dot(L0.clone().sub(T)); };
    let lo = 0.001, hi = h; for (let i = 0; i < 60; i++) { const m = (lo + hi) / 2; (kite(lo) > 0) === (kite(m) > 0) ? (lo = m) : (hi = m); }
    const k = (lo + hi) / 2, T = V(0, h, 0), B = V(0, -h, 0), ring = Array.from({ length: 10 }, (_, i) => V(Math.cos(ang(i)), i % 2 ? -k : k, Math.sin(ang(i))));
    const out = [];
    for (let j = 0; j < 10; j += 2) { const U0 = ring[j], L = ring[j + 1], U1 = ring[(j + 2) % 10]; out.push([T, U0, L], [T, L, U1]); }
    for (let j = 1; j < 10; j += 2) { const L0 = ring[j], U = ring[(j + 1) % 10], L1 = ring[(j + 2) % 10]; out.push([B, L0, U], [B, U, L1]); }
    return out;
}
const SHAPES = {
    4: () => trisOf(new THREE.TetrahedronGeometry(1.2)), 6: () => trisOf(new THREE.BoxGeometry(1.45, 1.45, 1.45)), 8: () => trisOf(new THREE.OctahedronGeometry(1.15)),
    10: d10Tris, 12: () => trisOf(new THREE.DodecahedronGeometry(1.1)), 20: () => trisOf(new THREE.IcosahedronGeometry(1.15)),
};
function faces(tris) {
    const list = [];
    for (const t of tris) {
        let n = t[1].clone().sub(t[0]).cross(t[2].clone().sub(t[0])).normalize();
        const c = t[0].clone().add(t[1]).add(t[2]).divideScalar(3);
        if (n.dot(c) < 0) { n.negate(); t.reverse(); }
        const f = list.find((x) => x.n.dot(n) > 0.999);
        f ? f.tris.push(t) : list.push({ n, tris: [t] });
    }
    for (const f of list) {
        const pts = []; for (const t of f.tris) for (const p of t) if (!pts.some((q) => q.distanceTo(p) < 1e-4)) pts.push(p);
        f.c = pts.reduce((a, p) => a.add(p), V(0, 0, 0)).divideScalar(pts.length);
        f.u = pts[0].clone().sub(f.c).normalize(); f.v = f.n.clone().cross(f.u);
        // corners in order around the face, for the physics shape
        f.pts = pts.sort((a, b) => Math.atan2(a.clone().sub(f.c).dot(f.v), a.clone().sub(f.c).dot(f.u)) - Math.atan2(b.clone().sub(f.c).dot(f.v), b.clone().sub(f.c).dot(f.u)));
        f.r = Math.max(...pts.map((p) => p.distanceTo(f.c)));
    }
    return list;
}
// ---- styles: a surface painted behind the numbers, number colour, and how shiny or metal it is ----
const grad = (a, b) => (g, S) => { const r = g.createRadialGradient(S * 0.35, S * 0.3, S * 0.05, S / 2, S / 2, S * 0.75); r.addColorStop(0, a); r.addColorStop(1, b); g.fillStyle = r; g.fillRect(0, 0, S, S); };
// a small seeded random, so a face looks the same every time it's drawn
const rnd = (seed) => () => ((seed = (seed * 16807) % 2147483647) / 2147483647);
const wood = (g, S, seed) => {
    const r = rnd(seed + 7); g.fillStyle = '#8a5a2b'; g.fillRect(0, 0, S, S);
    for (let i = 0; i < 26; i++) { g.strokeStyle = `rgba(${60 + r() * 40},${30 + r() * 20},10,${0.25 + r() * 0.35})`; g.lineWidth = 2 + r() * 6; g.beginPath(); const y = r() * S;
        g.moveTo(0, y); for (let x = 0; x <= S; x += 32) g.lineTo(x, y + Math.sin(x / 40 + i) * 8 + (r() - 0.5) * 6); g.stroke(); }
};
const marble = (g, S, seed) => {
    const r = rnd(seed + 3); g.fillStyle = '#eceae6'; g.fillRect(0, 0, S, S);
    for (let i = 0; i < 7; i++) { g.strokeStyle = `rgba(90,95,110,${0.2 + r() * 0.35})`; g.lineWidth = 1 + r() * 3; g.beginPath(); let x = r() * S, y = 0; g.moveTo(x, y);
        while (y < S) { x += (r() - 0.5) * 40; y += 12 + r() * 20; g.lineTo(x, y); } g.stroke(); }
};
export const STYLES = {
    purple: { name: 'Purple', paint: null, ink: '#ffffff', metal: 0.05, rough: 0.45 },
    gold: { name: 'Gold', paint: grad('#ffe39a', '#b07d22'), ink: '#3b2905', metal: 0.9, rough: 0.32 },
    silver: { name: 'Silver', paint: grad('#f2f4f7', '#8f99a5'), ink: '#1c222b', metal: 0.9, rough: 0.28 },
    obsidian: { name: 'Obsidian', paint: grad('#2c2b33', '#0c0c10'), ink: '#e8c35c', metal: 0.3, rough: 0.12 },
    ruby: { name: 'Ruby', paint: grad('#d23048', '#5c0a18'), ink: '#ffe7a6', metal: 0.15, rough: 0.18 },
    wood: { name: 'Wood', paint: wood, ink: '#2a1606', metal: 0, rough: 0.75 },
    marble: { name: 'Marble', paint: marble, ink: '#1f2a44', metal: 0, rough: 0.25 },
    ivory: { name: 'Ivory', paint: grad('#fbf5e6', '#d8ccb0'), ink: '#2a2a2a', metal: 0, rough: 0.5 },
};
// a face's picture: its number (or, on a d4, the three corner numbers)
// corners: the face's corners in canvas pixels, so a style can draw along its edges; st.bump paints a height map the same way
function faceTexture(labels, st, d4, seed, corners) {
    const S = 256, canvas = () => { const c = document.createElement('canvas'); c.width = c.height = S; return c; }, cv = canvas();
    const g = cv.getContext('2d');
    if (st.paint) st.paint(g, S, seed, corners, d4); else { g.fillStyle = st.body; g.fillRect(0, 0, S, S); }
    let bump = null;
    if (st.bump) { const bc = canvas(), bg = bc.getContext('2d'); bg.fillStyle = '#000'; bg.fillRect(0, 0, S, S); st.bump(bg, S, seed, corners, d4); bump = new THREE.CanvasTexture(bc); }
    g.fillStyle = st.ink; g.textAlign = 'center'; g.textBaseline = 'middle';
    const draw = (txt, x, y, size, rot) => {
        g.save(); g.translate(x, y); g.rotate(rot); g.font = `700 ${size}px ${st.font || 'system-ui, sans-serif'}`;
        // a thin dark edge keeps light numbers readable on shiny faces
        g.lineWidth = size * 0.05; g.strokeStyle = 'rgba(0,0,0,.35)'; g.strokeText(txt, 0, 0); g.fillText(txt, 0, 0);
        if (txt === '6' || txt === '9') g.fillRect(-size * 0.22, size * 0.42, size * 0.44, size * 0.07);
        g.restore();
    };
    if (d4) labels.forEach(([txt, x, y]) => draw(txt, S / 2 + (x - 0.5) * S * 0.62, S / 2 + (y - 0.5) * S * 0.62, 60, Math.atan2(y - 0.5, x - 0.5) + Math.PI / 2));
    else draw(labels, S / 2, S / 2, labels.length > 1 ? 92 : 120, 0);
    const t = new THREE.CanvasTexture(cv); t.colorSpace = THREE.SRGBColorSpace; t.anisotropy = 4;
    return { t, bump };
}
// builds one die type: mesh with a material per face, the physics shape, and how to read it
const built = {};
function buildDie(kind, st) {
    const key = kind + st.key;
    if (built[key]) return built[key];
    const sides = kind === 't' || kind === 'u' ? 10 : kind;
    const fs = faces(SHAPES[sides]()), d4 = sides === 4;
    const pos = [], uv = [], mats = [];
    // d4: each corner has a value; a face shows the three values of its corners
    const verts = []; if (d4) for (const f of fs) for (const p of f.pts) if (!verts.some((q) => q.distanceTo(p) < 1e-4)) verts.push(p);
    const geo = new THREE.BufferGeometry();
    fs.forEach((f, i) => {
        const start = pos.length / 3;
        const toUV = (p) => { const d = p.clone().sub(f.c); return [0.5 + d.dot(f.u) / (2 * f.r) * 0.98, 0.5 + d.dot(f.v) / (2 * f.r) * 0.98]; };
        for (const t of f.tris) for (const p of t) { pos.push(p.x, p.y, p.z); uv.push(...toUV(p)); }
        geo.addGroup(start, f.tris.length * 3, i);
        let label;
        if (d4) label = f.pts.map((p) => { const [x, y] = toUV(p); return [String(verts.findIndex((q) => q.distanceTo(p) < 1e-4) + 1), x, 1 - y]; });
        else label = kind === 't' ? (i === 0 ? '00' : i * 10 + '') : kind === 'u' ? String(i) : String(i + 1);
        f.value = d4 ? null : kind === 't' ? i * 10 : kind === 'u' ? i : i + 1;
        const { t, bump } = faceTexture(label, st, d4, i * 31 + sides, f.pts.map((p) => { const [x, y] = toUV(p); return [x * 256, (1 - y) * 256]; }));
        // glow: the face lights itself (dark parts stay dark); mat: extra material settings, physical for glass, clearcoat and the like
        mats.push(new (st.physical ? THREE.MeshPhysicalMaterial : THREE.MeshStandardMaterial)({ map: t, roughness: st.rough, metalness: st.metal, flatShading: true,
            ...(bump && { bumpMap: bump, bumpScale: st.bumpScale ?? 3 }), ...(st.glow && { emissive: 0xffffff, emissiveMap: t, emissiveIntensity: st.glow }), ...st.mat }));
    });
    geo.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
    geo.setAttribute('uv', new THREE.Float32BufferAttribute(uv, 2));
    geo.computeVertexNormals();
    const uniq = []; for (const f of fs) for (const p of f.pts) if (!uniq.some((q) => q.distanceTo(p) < 1e-4)) uniq.push(p);
    const shape = new CANNON.ConvexPolyhedron({
        vertices: uniq.map((p) => new CANNON.Vec3(p.x, p.y, p.z)),
        faces: fs.map((f) => f.pts.map((p) => uniq.findIndex((q) => q.distanceTo(p) < 1e-4))),
    });
    return (built[key] = { geo, mats, shape, fs, verts, d4 });
}
// the value showing: the face pointing most up (a d4: the corner pointing most up)
const UP = V(0, 1, 0);
function readDie(d) {
    const q = new THREE.Quaternion(d.body.quaternion.x, d.body.quaternion.y, d.body.quaternion.z, d.body.quaternion.w);
    if (d.t.d4) { let best = -9, v = 1; d.t.verts.forEach((p, i) => { const y = p.clone().applyQuaternion(q).y; if (y > best) { best = y; v = i + 1; } }); return v; }
    let best = -9, v = 1; for (const f of d.t.fs) { const y = f.n.clone().applyQuaternion(q).dot(UP); if (y > best) { best = y; v = f.value; } }
    return v;
}

// something for metal dice to reflect: a dark room with a bright sky and two soft lights
function roomEnv(r) {
    const room = new THREE.Scene(), sky = new THREE.Mesh(new THREE.SphereGeometry(50, 32, 16), new THREE.MeshBasicMaterial({ side: THREE.BackSide, vertexColors: true }));
    const sp = sky.geometry.attributes.position, col = [];
    for (let i = 0; i < sp.count; i++) { const t = (sp.getY(i) / 50 + 1) / 2; col.push(0.08 + t * 0.9, 0.08 + t * 0.88, 0.1 + t * 0.85); }
    sky.geometry.setAttribute('color', new THREE.Float32BufferAttribute(col, 3)); room.add(sky);
    for (const [x, z] of [[-20, -10], [18, 12]]) { const lamp = new THREE.Mesh(new THREE.PlaneGeometry(18, 18), new THREE.MeshBasicMaterial({ color: 0xffffff, side: THREE.DoubleSide })); lamp.position.set(x, 30, z); lamp.lookAt(0, 0, 0); room.add(lamp); }
    return new THREE.PMREMGenerator(r).fromScene(room, 0.04).texture;
}
// ---- the scene: a see-through layer over the page, an invisible table that takes shadows, walls at the screen edges ----
let scene, camera, renderer, world = null, cv, live = [], throws = [], raf = 0, fadeT = [];
function setup() {
    cv = document.createElement('canvas'); cv.id = 'hb-throw'; cv.setAttribute('aria-hidden', 'true'); document.body.appendChild(cv);
    renderer = new THREE.WebGLRenderer({ canvas: cv, alpha: true, antialias: true });
    renderer.shadowMap.enabled = true; renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    scene = new THREE.Scene();
    scene.add(new THREE.HemisphereLight(0xffffff, 0x404040, 1.6));
    const sun = new THREE.DirectionalLight(0xffffff, 2.2); sun.position.set(-8, 30, -6); sun.castShadow = true;
    Object.assign(sun.shadow.camera, { left: -40, right: 40, top: 40, bottom: -40, near: 1, far: 80 }); sun.shadow.mapSize.set(2048, 2048);
    scene.add(sun);
    const table = new THREE.Mesh(new THREE.PlaneGeometry(400, 400), new THREE.ShadowMaterial({ opacity: 0.35 }));
    table.rotation.x = -Math.PI / 2; table.receiveShadow = true; scene.add(table);
    scene.environment = roomEnv(renderer);
    // straight down with no perspective, so a die looks the same anywhere on the screen
    camera = new THREE.OrthographicCamera(-1, 1, 1, -1, 1, 200);
}
// ---- sound: every hit in the physics makes a knock, louder and lower the harder it lands ----
let audio = null, noise = null;
function knock(speed, onTable, st) {
    const vol = Math.min(1, speed / 14) ** 1.4;
    if (!audio || vol < 0.02) return;
    const t = audio.currentTime, out = audio.out;
    // a style with its own sound makes it instead
    if (st?.sound?.hit) return st.sound.hit({ ac: audio, out, noise, vol, onTable, t });
    // the click: a burst of noise through a band-pass, higher for dice on dice
    const src = audio.createBufferSource(); src.buffer = noise; src.playbackRate.value = 0.8 + Math.random() * 0.4;
    const bp = audio.createBiquadFilter(); bp.type = 'bandpass'; bp.frequency.value = onTable ? 700 + Math.random() * 500 : 2400 + Math.random() * 1600; bp.Q.value = onTable ? 1.1 : 2.5;
    const g = audio.createGain(); g.gain.setValueAtTime(vol * (onTable ? 0.9 : 0.6), t); g.gain.exponentialRampToValueAtTime(0.001, t + (onTable ? 0.14 : 0.07));
    src.connect(bp).connect(g).connect(out); src.start(t, Math.random() * 0.2, 0.2);
    // the weight: a short low thump when it hits the table
    if (onTable) {
        const o = audio.createOscillator(), og = audio.createGain();
        o.frequency.setValueAtTime(130 + Math.random() * 40, t); o.frequency.exponentialRampToValueAtTime(55, t + 0.09);
        og.gain.setValueAtTime(vol * 0.8, t); og.gain.exponentialRampToValueAtTime(0.001, t + 0.12);
        o.connect(og).connect(out); o.start(t); o.stop(t + 0.13);
    }
}
function startAudio() {
    if (!audio) {
        const A = window.AudioContext || window.webkitAudioContext; if (!A) return;
        audio = new A();
        audio.out = audio.createDynamicsCompressor(); audio.out.connect(audio.destination);
        noise = audio.createBuffer(1, audio.sampleRate * 0.4, audio.sampleRate);
        const d = noise.getChannelData(0); for (let i = 0; i < d.length; i++) d[i] = Math.random() * 2 - 1;
    }
    if (audio.state === 'suspended') audio.resume();
}
function walls(hw, hd) {
    world = new CANNON.World({ gravity: new CANNON.Vec3(0, -50, 0), allowSleep: true });
    world.defaultContactMaterial.friction = 0.3; world.defaultContactMaterial.restitution = 0.35;
    const plane = (pos, axis, ang) => { const b = new CANNON.Body({ mass: 0, shape: new CANNON.Plane() }); b.position.set(...pos); b.quaternion.setFromAxisAngle(new CANNON.Vec3(...axis), ang); world.addBody(b); };
    plane([0, 0, 0], [1, 0, 0], -Math.PI / 2);
    plane([-hw, 0, 0], [0, 1, 0], Math.PI / 2); plane([hw, 0, 0], [0, 1, 0], -Math.PI / 2);
    plane([0, 0, -hd], [0, 0, 1], 0); plane([0, 0, hd], [0, 1, 0], Math.PI);
}
// takes a die off the table, and anything its style added to the scene
const drop = (d) => { scene.remove(d.mesh); d.fx?.dispose?.(); };
function clear() {
    cancelAnimationFrame(raf); raf = 0;
    for (const d of live) drop(d);
    live = []; world = null; exiting = false;
}
function fade() { cv.style.transition = 'opacity .3s'; cv.style.opacity = '0'; fadeT.push(setTimeout(() => { fadeT = []; clear(); renderer.clear(); }, 320)); }

// ---- exits: how each die leaves the table, picked by what it rolled ----
// a style's exits map keys to animations, and the most specific key wins: 'd20:20' (one number), 'd20:2-19' (a range), 'd20' (that die), '*' (any die).
// The percentile dice are 'd100' (tens: 0, 10 ... 90) and 'd100u' (units: 0-9). A style without exits fades out as before.
// An animation is { dur (seconds), start(x), tick(k, x) with k from 0 to 1, end(x) }; x is { THREE, scene, mesh, body, die, value, audio, sec, dt },
// and an animation can keep its own things on x. The die is hidden when its animation ends.
const dieName = (kind) => (kind === 't' ? 'd100' : kind === 'u' ? 'd100u' : 'd' + kind);
function exitFor(st, d) {
    const x = st?.exits, die = dieName(d.kind), v = d.value;
    if (!x || v == null) return null;
    if (x[die + ':' + v]) return x[die + ':' + v];
    for (const k in x) { const m = /^(d\w+):(\d+)-(\d+)$/.exec(k); if (m && m[1] === die && v >= +m[2] && v <= +m[3]) return x[k]; }
    return x[die] || x['*'] || null;
}
let exiting = false;
// after the pause: start every die's exit, or fade straight away when none has one
function leave() {
    for (const d of live) { const a = exitFor(d.st, d);
        if (a) d.exit = { a, age: 0, x: { THREE, scene, mesh: d.mesh, body: d.body, die: dieName(d.kind), value: d.value, audio: d.sound && audio ? { ac: audio, out: audio.out, noise } : null } }; }
    exiting = live.some((d) => d.exit);
    if (!exiting) fade();
}
// one step of every running exit; false once they have all finished
function runExits(sec, dt) {
    let busy = false;
    for (const d of live) {
        const e = d.exit; if (!e || e.done) continue;
        Object.assign(e.x, { sec, dt });
        if (!e.started) { e.started = true; e.a.start?.(e.x); }
        e.age += dt; const k = Math.min(1, e.age / (e.a.dur || 1));
        e.a.tick?.(k, e.x);
        if (k >= 1) { e.done = true; e.a.end?.(e.x); d.mesh.visible = false; } else busy = true;
    }
    return busy;
}
// a new throw cuts running exits short and takes their dice off the table
function endExits() {
    if (!exiting) return;
    for (const d of live) if (d.exit) { if (!d.exit.done) { d.exit.done = true; d.exit.a.end?.(d.exit.x); } drop(d); world.removeBody(d.body); }
    live = live.filter((d) => !d.exit); exiting = false;
}
// the table size for this screen; kept while any dice are on it
let hw = 0, hd = 0;
function table() {
    // about D&D Beyond's size: a d20 is roughly 130 px across on a laptop, smaller on a phone
    const W = innerWidth, H = innerHeight, ppu = Math.max(28, Math.min(56, Math.min(W, H) / 16));
    renderer.setPixelRatio(Math.min(devicePixelRatio || 1, 2)); renderer.setSize(W, H, false);
    hd = H / ppu / 2; hw = W / ppu / 2;
    Object.assign(camera, { left: -hw, right: hw, top: hd, bottom: -hd });
    camera.position.set(0, 60, 0); camera.up.set(0, 0, -1); camera.lookAt(0, 0, 0); camera.updateProjectionMatrix();
    walls(hw - 0.3, hd - 0.3);
}
const asleep = (d) => d.body.sleepState === CANNON.Body.SLEEPING || (d.body.velocity.length() < 0.15 && d.body.angularVelocity.length() < 0.15);
// one loop for every throw on the table; each throw resolves when its own dice stop, the table clears when all have
// how many times faster than real time the table runs: the same 1/120 s physics steps, just more of them per frame
let speed = 1;
export function setSpeed(x) { speed = Math.max(1, Math.min(16, +x || 1)); }
function frame(now) {
    const real = Math.min(0.05, (now - (frame.last || now)) / 1000), dt = real * speed; frame.last = now;
    world.step(1 / 120, dt, Math.ceil(8 * speed));
    for (const d of live) { d.mesh.position.copy(d.body.position); d.mesh.quaternion.copy(d.body.quaternion); d.fx?.tick?.(now / 1000, dt, d.body); }
    // exits run in real time, after the physics placed the die, so they can move, scale or hide it
    if (exiting && !runExits(now / 1000, real)) { exiting = false; fade(); }
    renderer.render(scene, camera);
    for (const t of throws) {
        // both counted in table time, so they hold at any speed
        t.age += dt; t.quiet = t.dice.every(asleep) ? t.quiet + dt : 0;
        if (t.quiet > 0.25 || t.age > 7) { t.done = true; t.resolve(t.results()); if (audio) t.st.sound?.settle?.({ ac: audio, out: audio.out, noise, t: audio.currentTime, count: t.dice.length }); }
    }
    throws = throws.filter((t) => !t.done);
    // leave them on the table a moment, then their exits (or a fade)
    if (!throws.length && !fadeT.length && !exiting) fadeT.push(setTimeout(leave, 1400));
    if (world) raf = requestAnimationFrame(frame);
}

// empties the table now instead of after the usual pause and fade (the dice test page uses it between rolls)
export function clearTable() {
    fadeT.forEach(clearTimeout); fadeT = [];
    if (world) endExits();
    if (scene && !throws.length) { clear(); renderer.clear(); }
}

// a style is a key of STYLES or a style object (the dice page's sets in dice/sets/*.js); key names its built dice
const styleOf = (style, accent) => { const base = typeof style === 'object' ? style : STYLES[style] || STYLES.purple;
    return { ...base, body: accent, key: (base.key || style) + accent }; };
// moving parts: a style's decorate() adds things to each die (children of its mesh, or to the scene) and returns { tick(sec, dt, body), dispose() }
const decorate = (st, d) => st.decorate?.({ THREE, scene, mesh: d.mesh, kind: d.kind, sides: d.kind === 't' || d.kind === 'u' ? 10 : d.kind, faces: d.t.fs, rnd }) || null;

// style: see styleOf; accent: the purple style's colour; sound: knock as they land
export async function throwDice(groups, { style = 'purple', accent = '#5b3f8f', sound = true } = {}) {
    // dice already on the table stay; a fade that had started stops
    fadeT.forEach(clearTimeout); fadeT = [];
    if (!scene) setup();
    if (!world) table();
    endExits();
    if (sound) startAudio();
    // shortcut: past 40 dice the oldest settled ones are taken off so the page stays smooth
    while (live.length > 40 && !throws.some((t) => t.dice.includes(live[0]))) { drop(live[0]); world.removeBody(live[0].body); live.shift(); }
    const st = styleOf(style, accent);
    // a d100 is a tens die and a units die
    const kinds = groups.flatMap(([n, c]) => Array.from({ length: c }, () => (n === 100 ? ['t', 'u'] : [n])).flat());
    // each throw comes in from a random spot on the screen edge: a random direction out from the middle, stopped at the edge
    const a = Math.random() * Math.PI * 2, cx = Math.cos(a), cz = Math.sin(a), s = Math.min(hw / Math.abs(cx || 1e-9), hd / Math.abs(cz || 1e-9));
    const fit = (v, h) => Math.max(-h + 1.5, Math.min(h - 1.5, v));
    const dice = kinds.map((kind, i) => {
        const t = buildDie(kind, st), mesh = new THREE.Mesh(t.geo, t.mats);
        mesh.castShadow = true; scene.add(mesh);
        const b = new CANNON.Body({ mass: 1, shape: t.shape, sleepSpeedLimit: 0.25, sleepTimeLimit: 0.3, angularDamping: 0.15, linearDamping: 0.1 });
        // spread along the edge (i % 4) and inward (i % 3), then thrown at a spot near the middle
        const along = ((i % 4) - 1.5) * 1.6, inward = 2 + (i % 3) * 1.2;
        const x = fit(cx * s - cx * inward - cz * along, hw), z = fit(cz * s - cz * inward + cx * along, hd);
        b.position.set(x, 3 + Math.floor(i / 4) * 1.8, z);
        const sp = 0.9 + Math.random() * 0.5, tx = (Math.random() - 0.5) * hw * 0.5, tz = (Math.random() - 0.5) * hd * 0.5;
        b.velocity.set((tx - x) * 1.2 * sp, -2, (tz - z) * 1.2 * sp);
        b.angularVelocity.set((Math.random() - 0.5) * 30, (Math.random() - 0.5) * 30, (Math.random() - 0.5) * 30);
        b.quaternion.setFromEuler(Math.random() * 6, Math.random() * 6, Math.random() * 6);
        // each hit knocks once: dice on dice is handled by the lower id, and a die can't knock again within 30 ms
        if (sound) b.addEventListener('collide', (e) => {
            const other = e.body, now = performance.now();
            if (other.mass && other.id < b.id) return;
            if (now - (b.lastKnock || 0) < 30) return;
            b.lastKnock = now; knock(Math.abs(e.contact.getImpactVelocityAlongNormal()), !other.mass, st);
        });
        world.addBody(b);
        const d = { kind, t, mesh, body: b, st, sound }; d.fx = decorate(st, d); live.push(d); return d;
    });
    if (sound && audio) st.sound?.throw?.({ ac: audio, out: audio.out, noise, t: audio.currentTime, count: dice.length });
    cv.style.transition = 'none'; cv.style.opacity = '1';
    return new Promise((resolve) => {
        const results = () => {
            // each die keeps its value, which picks its exit
            const out = [], vals = dice.map((d) => (d.value = readDie(d)));
            for (const [n, c] of groups) {
                const r = [];
                for (let j = 0; j < c; j++) {
                    if (n === 100) { const tens = vals.shift(), units = vals.shift(); r.push(tens + units || 100); }
                    else r.push(vals.shift());
                }
                out.push([n, r]);
            }
            return out;
        };
        throws.push({ dice, resolve, results, st: sound ? st : {}, age: 0, quiet: 0 });
        if (!raf) { frame.last = 0; raf = requestAnimationFrame(frame); }
    });
}

// ---- previews: one still picture per style, the whole set in a row (d4 d6 d8 d10 d12 d20 and the percentile die) ----
let pv = null;
export function previewStyle(style, accent = '#5b3f8f', w = 336, h = 56) {
    if (!pv) {
        const r = new THREE.WebGLRenderer({ alpha: true, antialias: true, preserveDrawingBuffer: true });
        const s = new THREE.Scene();
        s.add(new THREE.HemisphereLight(0xffffff, 0x404040, 1.6));
        const sun = new THREE.DirectionalLight(0xffffff, 2.2); sun.position.set(-8, 30, -6); s.add(sun);
        s.environment = roomEnv(r);
        pv = { r, s, cam: new THREE.OrthographicCamera(-1, 1, 1, -1, 1, 100), meshes: [] };
    }
    const st = styleOf(style, accent);
    const kinds = [4, 6, 8, 10, 12, 20, 't'], gap = 2.6, hw = (kinds.length * gap) / 2, hd = (hw * h) / w;
    pv.r.setPixelRatio(Math.min(devicePixelRatio || 1, 2)); pv.r.setSize(w, h, false);
    Object.assign(pv.cam, { left: -hw, right: hw, top: hd, bottom: -hd }); pv.cam.position.set(0, 50, 0); pv.cam.up.set(0, 0, -1); pv.cam.lookAt(0, 0, 0); pv.cam.updateProjectionMatrix();
    for (const m of pv.meshes) { pv.s.remove(m); m.fx?.dispose?.(); }
    pv.meshes = kinds.map((kind, i) => {
        const t = buildDie(kind, st), m = new THREE.Mesh(t.geo, t.mats);
        // the top number faces the camera, tipped a little so the edges show
        const top = t.d4 ? t.verts[3].clone().normalize() : t.fs[t.fs.length - 1].n;
        m.quaternion.setFromUnitVectors(top, UP).premultiply(new THREE.Quaternion().setFromEuler(new THREE.Euler(0.35, 0, -0.25)));
        m.position.set(-hw + gap / 2 + i * gap, 0, 0);
        // moving parts drawn once, part way into their motion
        pv.s.add(m); m.fx = st.decorate?.({ THREE, scene: pv.s, mesh: m, kind, sides: kind === 't' ? 10 : kind, faces: t.fs, rnd }) || null; m.fx?.tick?.(1.5, 0, null);
        return m;
    });
    pv.r.render(pv.s, pv.cam);
    return pv.r.domElement.toDataURL('image/png');
}
