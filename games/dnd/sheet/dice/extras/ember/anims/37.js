// Ember Cage exit 37: nat 1 Frostbite. The fire slowly goes out, frost grows from the middle of each face out to the corners until the die is
// frozen through, then the die's own model shatters into about 50 icy pieces that bounce across the table, melt into water and drain away.
let FX = null;
function fx(T) {
    if (FX) return FX;
    const mk = (f) => { const c = document.createElement('canvas'); c.width = c.height = 64; f(c.getContext('2d')); return new T.CanvasTexture(c); };
    const soft = mk((g) => { const r = g.createRadialGradient(32, 32, 0, 32, 32, 32); r.addColorStop(0, 'rgba(255,255,255,1)'); r.addColorStop(1, 'rgba(255,255,255,0)'); g.fillStyle = r; g.fillRect(0, 0, 64, 64); });
    // a soft pool of water; overlapping pools run together into one puddle
    const water = mk((g) => { const r = g.createRadialGradient(32, 32, 0, 32, 32, 31); r.addColorStop(0, 'rgba(150,200,245,.5)'); r.addColorStop(0.55, 'rgba(160,210,250,.42)');
        r.addColorStop(0.8, 'rgba(200,232,255,.2)'); r.addColorStop(1, 'rgba(200,232,255,0)'); g.fillStyle = r; g.fillRect(0, 0, 64, 64); });
    return (FX = { soft, water, plane: new T.PlaneGeometry(1, 1) });
}
// frost on the die's own surface: it covers what lies within `front` of the middle of each face (0 = face centres, 1 = corners),
// with a bright freezing edge, crystal streaks and a cold sheen
const VERT = `uniform vec3 uC; varying vec3 vP; varying vec3 vN;
void main(){ vP = position + uC; vN = normalize(normalMatrix * normal); gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.); }`;
const FRAG = `uniform float uFront, uR, uIn, uOp; varying vec3 vP; varying vec3 vN;
void main(){
    float rr = clamp((length(vP) - uIn) / (uR - uIn), 0., 1.);
    float cover = 1. - smoothstep(uFront - 0.1, uFront, rr), band = smoothstep(uFront - 0.14, uFront - 0.03, rr) * cover;
    vec3 n = normalize(vN); float fres = pow(1. - abs(n.z), 2.), lit = .55 + .45 * max(0., dot(n, normalize(vec3(-.3, .8, .5))));
    float f = sin(dot(vP, vec3(37., 11., 23.)) + 3. * sin(dot(vP, vec3(-13., 29., 7.)))), streak = smoothstep(.7, 1., f);
    vec3 c = mix(vec3(.5, .72, .92), vec3(.93, .98, 1.), .35 * lit + .4 * fres + .3 * streak) + band * vec3(.5, .8, 1.);
    gl_FragColor = vec4(c, uOp * cover * (.86 + .14 * band));
}`;
// a copy of the geometry with every triangle split into 4^n, so it can break into many pieces
function subdivide(geo, n) {
    let g = geo.index ? geo.toNonIndexed() : geo.clone();
    for (let l = 0; l < n; l++) {
        const out = new g.constructor();
        for (const name of Object.keys(g.attributes)) {
            const a = g.attributes[name], s = a.itemSize, src = a.array, dst = new Float32Array(src.length * 4);
            let o = 0;
            const put = (i, j) => { for (let c = 0; c < s; c++) dst[o++] = j < 0 ? src[i * s + c] : (src[i * s + c] + src[j * s + c]) / 2; };
            for (let t = 0; t < a.count; t += 3) { const A = t, B = t + 1, C = t + 2; put(A, -1); put(A, B); put(C, A); put(A, B); put(B, -1); put(B, C); put(C, A); put(B, C); put(C, -1); put(A, B); put(B, C); put(C, A); }
            out.setAttribute(name, new a.constructor(dst, s));
        }
        for (const gr of g.groups) out.addGroup(gr.start * 4, gr.count * 4, gr.materialIndex);
        if (g !== geo) g.dispose(); g = out;
    }
    return g;
}
// cut the geometry into n pieces: spread-out seed triangles, every triangle joins its nearest seed; each piece is centred on its own middle
function shatter(T, geo, n) {
    const P = geo.attributes.position, N = P.count / 3, cen = new Float32Array(N * 3), mat = new Int32Array(N);
    for (let t = 0; t < N; t++) for (let c = 0; c < 3; c++) cen[t * 3 + c] = (P.array[t * 9 + c] + P.array[t * 9 + 3 + c] + P.array[t * 9 + 6 + c]) / 3;
    for (const gr of geo.groups) for (let t = gr.start / 3; t < (gr.start + gr.count) / 3; t++) mat[t] = gr.materialIndex;
    const d2 = (t, s) => (cen[t * 3] - s[0]) ** 2 + (cen[t * 3 + 1] - s[1]) ** 2 + (cen[t * 3 + 2] - s[2]) ** 2;
    const seeds = [], best = new Float32Array(N).fill(Infinity), own = new Int32Array(N);
    let next = Math.random() * N | 0;
    for (let i = 0; i < n; i++) {
        const s = [cen[next * 3], cen[next * 3 + 1], cen[next * 3 + 2]]; seeds.push(s);
        let far = 0, fd = -1;
        for (let t = 0; t < N; t++) { const d = d2(t, s); if (d < best[t]) { best[t] = d; own[t] = i; } if (best[t] * (0.6 + Math.random() * 0.8) > fd) { fd = best[t] * (0.6 + Math.random() * 0.8); far = t; } }
        next = far;
    }
    return seeds.map((_, i) => {
        const tris = []; for (let t = 0; t < N; t++) if (own[t] === i) tris.push(t);
        if (!tris.length) return null;
        const g = new T.BufferGeometry(), c = new T.Vector3();
        for (const t of tris) c.x += cen[t * 3] / tris.length, c.y += cen[t * 3 + 1] / tris.length, c.z += cen[t * 3 + 2] / tris.length;
        for (const name of Object.keys(geo.attributes)) {
            const a = geo.attributes[name], s = a.itemSize, dst = new Float32Array(tris.length * 3 * s);
            tris.forEach((t, j) => dst.set(a.array.subarray(t * 3 * s, (t + 1) * 3 * s), j * 3 * s));
            if (name === 'position') for (let v = 0; v < dst.length; v += 3) { dst[v] -= c.x; dst[v + 1] -= c.y; dst[v + 2] -= c.z; }
            g.setAttribute(name, new T.BufferAttribute(dst, s));
        }
        let r = 0; for (let v = 0; v < g.attributes.position.array.length; v += 3) r = Math.max(r, Math.hypot(...g.attributes.position.array.subarray(v, v + 3)));
        if (geo.groups.length) for (let j = 0, s = 0; j <= tris.length; j++) if (j === tris.length || mat[tris[j]] !== mat[tris[s]]) { g.addGroup(s * 3, (j - s) * 3, mat[tris[s]]); s = j; }
        return { g, c, r };
    }).filter(Boolean);
}
const ease = (k) => 1 - (1 - k) ** 3;
const at = (k, a, b) => Math.max(0, Math.min(1, (k - a) / (b - a)));
const rnd = (a, b) => a + Math.random() * (b - a);
const add = (x, o) => (x.scene.add(o), x.own.push(o), o);
const NP = 50, BREAK = 0.56;

const nat1 = {
    key: 'a37-frostbite', name: 'Frostbite', dur: 4.8,
    blurb: 'The fire slowly dies and the die freezes from the inside out, then its own model shatters into fifty icy pieces that melt into water and drain away.',
    start(x) {
        const T = x.THREE, F = fx(T), m = x.mesh;
        x.own = []; x.p0 = m.position.clone(); x.q0 = m.quaternion.clone(); x.vis0 = m.visible; x.cast0 = m.castShadow; x.kb = -1;
        // our own copies of the die's materials and its churn and spark overlays, so the shared ones are never touched
        x.mat0 = m.material; x.mats = [].concat(m.material).map((a) => { const c = a.clone(); c.side = T.DoubleSide; return c; });
        m.material = Array.isArray(m.material) ? x.mats : x.mats[0];
        x.c0 = x.mats.map((a) => a.color.clone()); x.e0 = x.mats.map((a) => a.emissive?.clone()); x.ei0 = x.mats.map((a) => a.emissiveIntensity);
        x.kids = m.children.map((c) => { const cm = c.material.clone(); cm.transparent = true; cm.op0 = cm.opacity; const k = { c, m0: c.material, cm }; c.material = cm; return k; });
        // how far the die reaches from its middle: to its corners (uR) and to its face centres (uIn)
        const P = m.geometry.attributes.position, idx = m.geometry.index, v = [new T.Vector3(), new T.Vector3(), new T.Vector3()], pl = new T.Plane();
        let R = 0, In = Infinity; const nt = (idx ? idx.count : P.count) / 3;
        for (let t = 0; t < nt; t++) { for (let j = 0; j < 3; j++) v[j].fromBufferAttribute(P, idx ? idx.getX(t * 3 + j) : t * 3 + j); pl.setFromCoplanarPoints(...v);
            if (pl.normal.lengthSq() > 0.5) In = Math.min(In, Math.abs(pl.constant)); for (const p of v) R = Math.max(R, p.length()); }
        x.R = R;
        const ice = (c) => new T.ShaderMaterial({ vertexShader: VERT, fragmentShader: FRAG, transparent: true, depthWrite: false, side: T.DoubleSide,
            polygonOffset: true, polygonOffsetFactor: -1, polygonOffsetUnits: -1, uniforms: { uFront: { value: -0.2 }, uR: { value: R }, uIn: { value: In }, uOp: { value: 1 }, uC: { value: c } } });
        x.frost = new T.Mesh(m.geometry, ice(new T.Vector3())); x.frost.renderOrder = 1; m.add(x.frost);
        x.glint = add(x, new T.Sprite(new T.SpriteMaterial({ map: F.soft, color: 0xe8f8ff, transparent: true, opacity: 0, depthWrite: false, blending: T.AdditiveBlending })));
        x.glint.position.copy(x.p0).add(new T.Vector3(rnd(-0.3, 0.3) * R, R, rnd(-0.3, 0.3) * R));
        // the pieces, cut now from a fine copy of the die, each with its own frost; they wait hidden until the break
        const tris = nt; let L = 0; while (tris * 4 ** L < NP * 14 && L < 4) L++;
        x.fine = subdivide(m.geometry, L);
        x.bits = shatter(T, x.fine, NP).map(({ g, c, r }) => {
            const p = add(x, new T.Mesh(g, m.material)), f = new T.Mesh(g, ice(c)); f.renderOrder = 1; f.material.uniforms.uFront.value = 2; p.add(f); p.visible = false; p.castShadow = true;
            const w = c.clone().applyQuaternion(x.q0), out = new T.Vector3(w.x, 0, w.z); if (out.lengthSq() < 1e-4) out.set(rnd(-1, 1), 0, rnd(-1, 1)); out.normalize();
            const pool = add(x, new T.Mesh(F.plane, new T.MeshBasicMaterial({ map: F.water, transparent: true, opacity: 0, depthWrite: false })));
            pool.rotation.x = -Math.PI / 2; pool.visible = false;
            return { p, f, g, w, r, pool, v: out.multiplyScalar(rnd(1.5, 4.5)).setY(rnd(2, 5)), ax: new T.Vector3(rnd(-1, 1), rnd(-1, 1), rnd(-1, 1)).normalize(), spin: rnd(4, 12), lag: rnd(0, 0.06) };
        });
        x.flash = add(x, new T.Sprite(new T.SpriteMaterial({ map: F.soft, color: 0xdff4ff, transparent: true, opacity: 0, depthWrite: false, blending: T.AdditiveBlending })));
        x.flash.position.copy(x.p0); x.dq = new T.Quaternion();
    },
    tick(k, x) {
        const m = x.mesh, T = x.THREE, dt = Math.max(0, k - Math.max(0, x.kb)) * nat1.dur; x.kb = k;
        // the fire slowly goes out: the glow and churn fade and the iron turns cold blue-grey
        const cool = at(k, 0, 0.3), cold = new T.Color(0x5a6f86);
        x.mats.forEach((a, i) => { a.color.copy(x.c0[i]).lerp(cold, cool * 0.6); if (a.emissive) a.emissiveIntensity = x.ei0[i] * (1 - cool); });
        for (const kd of x.kids) kd.cm.opacity = kd.cm.op0 * (1 - at(k, 0, 0.22));
        // the frost grows from the middle of each face out to the corners
        const fr = at(k, 0.06, 0.5); x.frost.material.uniforms.uFront.value = -0.15 + 1.35 * fr * fr * (3 - 2 * fr);
        // frozen through: a glint, a shiver, then it breaks
        x.glint.scale.setScalar(x.R * 2.2 * Math.sin(Math.PI * at(k, 0.47, 0.55))); x.glint.material.opacity = Math.sin(Math.PI * at(k, 0.47, 0.55));
        const shiver = at(k, 0.51, BREAK) * 0.04 * x.R; m.position.set(x.p0.x + (Math.random() - 0.5) * shiver, x.p0.y, x.p0.z + (Math.random() - 0.5) * shiver); m.quaternion.copy(x.q0);
        const broke = k >= BREAK; m.visible = !broke; m.castShadow = !broke && x.cast0;
        x.flash.scale.setScalar(x.R * (1.5 + 5 * at(k, BREAK, BREAK + 0.08))); x.flash.material.opacity = 0.8 * (broke ? 1 - at(k, BREAK, BREAK + 0.08) : 0);
        if (!broke) return;
        const melt = (b) => at(k, 0.7 + b.lag, 0.85 + b.lag), drain = at(k, 0.84, 0.97);
        for (const b of x.bits) {
            if (!b.p.visible && !b.started) { b.started = true; b.p.visible = true; b.p.position.copy(x.p0).add(b.w); b.p.quaternion.copy(x.q0); }
            // fly, fall, bounce, skid to a stop
            const floor = b.r * 0.35 * (1 - melt(b));
            b.v.y -= 22 * dt; b.p.position.addScaledVector(b.v, dt);
            if (b.p.position.y < floor) { b.p.position.y = floor; if (b.v.y < 0) { b.v.y *= -0.3; b.v.x *= 0.6; b.v.z *= 0.6; b.spin *= 0.5; } }
            if (b.p.position.y <= floor + 0.01) { const fr = Math.max(0, 1 - 4 * dt); b.v.x *= fr; b.v.z *= fr; b.spin *= fr; }
            b.p.quaternion.premultiply(x.dq.setFromAxisAngle(b.ax, b.spin * dt));
            // melt: the piece shrinks away as a pool of water spreads under it
            const mt = melt(b); b.p.scale.setScalar(Math.max(0.001, 1 - ease(mt))); b.p.visible = mt < 1; b.f.material.uniforms.uOp.value = 1 - 0.5 * mt;
            if (mt > 0 && !b.pool.visible) { b.pool.visible = true; b.px = b.p.position.x; b.pz = b.p.position.z; }
            if (!b.pool.visible) continue;
            // drain: every pool slides into a swirl toward where the die stood and goes down
            const d = ease(drain), a = drain * 2.4, dx = b.px - x.p0.x, dz = b.pz - x.p0.z, s = 1 - d;
            b.pool.position.set(x.p0.x + (dx * Math.cos(a) - dz * Math.sin(a)) * s, 0.04, x.p0.z + (dx * Math.sin(a) + dz * Math.cos(a)) * s);
            b.pool.scale.setScalar(b.r * 4.5 * Math.sqrt(mt) * (1 - 0.75 * d) + 0.001); b.pool.material.opacity = 0.75 * Math.min(1, mt * 2) * (1 - at(k, 0.9, 0.97));
        }
    },
    end(x) {
        const m = x.mesh;
        m.remove(x.frost); x.frost.material.dispose();
        m.material = x.mat0; m.visible = x.vis0; m.castShadow = true; m.position.copy(x.p0); m.quaternion.copy(x.q0);
        for (const kd of x.kids) { kd.c.material = kd.m0; kd.cm.dispose(); }
        for (const b of x.bits) { b.g.dispose(); b.f.material.dispose(); }
        x.mats.forEach((a) => a.dispose()); x.fine.dispose();
        for (const o of x.own) { x.scene.remove(o); if (o.material !== m.material && !Array.isArray(o.material)) o.material?.dispose(); } // the pieces share x.mats, already gone
        x.own = [];
    },
};

export default { nat1 };
