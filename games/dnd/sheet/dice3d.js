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
// a face's picture: its number (or, on a d4, the three corner numbers)
function faceTexture(labels, body, ink, d4) {
    const S = 256, cv = document.createElement('canvas'); cv.width = cv.height = S;
    const g = cv.getContext('2d');
    g.fillStyle = body; g.fillRect(0, 0, S, S);
    g.fillStyle = ink; g.textAlign = 'center'; g.textBaseline = 'middle';
    const draw = (txt, x, y, size, rot) => {
        g.save(); g.translate(x, y); g.rotate(rot); g.font = `700 ${size}px system-ui, sans-serif`; g.fillText(txt, 0, 0);
        if (txt === '6' || txt === '9') g.fillRect(-size * 0.22, size * 0.42, size * 0.44, size * 0.07);
        g.restore();
    };
    if (d4) labels.forEach(([txt, x, y]) => draw(txt, S / 2 + (x - 0.5) * S * 0.62, S / 2 + (y - 0.5) * S * 0.62, 60, Math.atan2(y - 0.5, x - 0.5) + Math.PI / 2));
    else draw(labels, S / 2, S / 2, labels.length > 1 ? 92 : 120, 0);
    const t = new THREE.CanvasTexture(cv); t.colorSpace = THREE.SRGBColorSpace; t.anisotropy = 4;
    return t;
}
// builds one die type: mesh with a material per face, the physics shape, and how to read it
const built = {};
function buildDie(kind, body, ink) {
    const key = kind + body + ink;
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
        mats.push(new THREE.MeshStandardMaterial({ map: faceTexture(label, body, ink, d4), roughness: 0.45, metalness: 0.05, flatShading: true }));
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

// ---- the scene: a see-through layer over the page, an invisible table that takes shadows, walls at the screen edges ----
let scene, camera, renderer, world, cv, live = [], finish = null, raf = 0, fadeT = [];
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
    camera = new THREE.PerspectiveCamera(32, 1, 1, 200);
}
function walls(hw, hd) {
    world = new CANNON.World({ gravity: new CANNON.Vec3(0, -50, 0), allowSleep: true });
    world.defaultContactMaterial.friction = 0.3; world.defaultContactMaterial.restitution = 0.35;
    const plane = (pos, axis, ang) => { const b = new CANNON.Body({ mass: 0, shape: new CANNON.Plane() }); b.position.set(...pos); b.quaternion.setFromAxisAngle(new CANNON.Vec3(...axis), ang); world.addBody(b); };
    plane([0, 0, 0], [1, 0, 0], -Math.PI / 2);
    plane([-hw, 0, 0], [0, 1, 0], Math.PI / 2); plane([hw, 0, 0], [0, 1, 0], -Math.PI / 2);
    plane([0, 0, -hd], [0, 0, 1], 0); plane([0, 0, hd], [0, 1, 0], Math.PI);
}
function clear() {
    cancelAnimationFrame(raf);
    for (const d of live) scene.remove(d.mesh);
    live = [];
}

export async function throwDice(groups, { body = '#5b3f8f', ink = '#ffffff' } = {}) {
    // a new throw ends the one still rolling with what it shows now
    if (finish) finish();
    fadeT.forEach(clearTimeout); fadeT = [];
    if (!scene) setup();
    clear();
    const W = innerWidth, H = innerHeight, ppu = Math.max(22, Math.min(34, Math.min(W, H) / 20));
    renderer.setPixelRatio(Math.min(devicePixelRatio || 1, 2)); renderer.setSize(W, H, false);
    const hd = H / ppu / 2, hw = W / ppu / 2;
    camera.aspect = W / H; camera.position.set(0, hd / Math.tan((camera.fov * Math.PI) / 360), 0); camera.lookAt(0, 0, 0); camera.up.set(0, 0, -1); camera.lookAt(0, 0, 0); camera.updateProjectionMatrix();
    walls(hw - 0.3, hd - 0.3);
    // a d100 is a tens die and a units die
    const kinds = groups.flatMap(([n, c]) => Array.from({ length: c }, () => (n === 100 ? ['t', 'u'] : [n])).flat());
    kinds.forEach((kind, i) => {
        const t = buildDie(kind, body, ink), mesh = new THREE.Mesh(t.geo, t.mats);
        mesh.castShadow = true; scene.add(mesh);
        const b = new CANNON.Body({ mass: 1, shape: t.shape, sleepSpeedLimit: 0.25, sleepTimeLimit: 0.3, angularDamping: 0.15, linearDamping: 0.1 });
        // thrown from the bottom-left corner, where the dice button is
        b.position.set(-hw + 2 + (i % 4) * 1.6, 3 + Math.floor(i / 4) * 1.8, hd - 2 - (i % 3) * 1.2);
        const sp = 0.9 + Math.random() * 0.5;
        b.velocity.set(hw * 1.25 * sp, -2, -hd * 1.1 * sp);
        b.angularVelocity.set((Math.random() - 0.5) * 30, (Math.random() - 0.5) * 30, (Math.random() - 0.5) * 30);
        b.quaternion.setFromEuler(Math.random() * 6, Math.random() * 6, Math.random() * 6);
        world.addBody(b);
        live.push({ kind, t, mesh, body: b });
    });
    cv.style.transition = 'none'; cv.style.opacity = '1';
    return new Promise((resolve) => {
        let last = performance.now(), start = last, quiet = 0, done = false;
        const results = () => {
            const out = [], vals = live.map((d) => [d.kind, readDie(d)]);
            for (const [n, c] of groups) {
                const r = [];
                for (let j = 0; j < c; j++) {
                    if (n === 100) { const tens = vals.shift()[1], units = vals.shift()[1]; r.push(tens + units || 100); }
                    else r.push(vals.shift()[1]);
                }
                out.push([n, r]);
            }
            return out;
        };
        finish = () => { if (done) return; done = true; finish = null; resolve(results()); };
        const frame = (now) => {
            const dt = Math.min(0.05, (now - last) / 1000); last = now;
            world.step(1 / 120, dt, 8);
            for (const d of live) { d.mesh.position.copy(d.body.position); d.mesh.quaternion.copy(d.body.quaternion); }
            renderer.render(scene, camera);
            const still = live.every((d) => d.body.sleepState === CANNON.Body.SLEEPING || (d.body.velocity.length() < 0.15 && d.body.angularVelocity.length() < 0.15));
            quiet = still ? quiet + dt : 0;
            if (!done && (quiet > 0.25 || now - start > 7000)) {
                finish();
                // leave them on the table a moment, then fade
                fadeT.push(setTimeout(() => { cv.style.transition = 'opacity .3s'; cv.style.opacity = '0'; fadeT.push(setTimeout(() => { clear(); renderer.clear(); }, 320)); }, 1400));
            }
            if (!done || now - start < 60000) raf = requestAnimationFrame(frame);
        };
        raf = requestAnimationFrame(frame);
    });
}
