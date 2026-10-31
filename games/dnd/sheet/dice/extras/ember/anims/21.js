// Ember Cage exits 21: nat 1 Shatter (the die's own faces crack apart and dim), nat 20 Supernova (it implodes to a point, then its faces blast out in gold), mid Twist out (it wrings itself into a fading swirl).
let FX = null;
function fx(T) {
    if (FX) return FX;
    const c = document.createElement('canvas'); c.width = c.height = 64;
    const g = c.getContext('2d'), gr = g.createRadialGradient(32, 32, 0, 32, 32, 32);
    gr.addColorStop(0, 'rgba(255,255,255,1)'); gr.addColorStop(0.35, 'rgba(255,255,255,.6)'); gr.addColorStop(1, 'rgba(255,255,255,0)');
    g.fillStyle = gr; g.fillRect(0, 0, 64, 64);
    const r = document.createElement('canvas'); r.width = r.height = 128;
    const h = r.getContext('2d'), rg = h.createRadialGradient(64, 64, 40, 64, 64, 64);
    rg.addColorStop(0, 'rgba(255,255,255,0)'); rg.addColorStop(0.7, 'rgba(255,255,255,1)'); rg.addColorStop(1, 'rgba(255,255,255,0)');
    h.fillStyle = rg; h.fillRect(0, 0, 128, 128);
    return (FX = { soft: new T.CanvasTexture(c), ring: new T.CanvasTexture(r), plane: new T.PlaneGeometry(1, 1) });
}
const add = (x, o) => (x.scene.add(o), x.own.push(o), o);
const sprite = (x, color) => { const s = add(x, new x.THREE.Sprite(new x.THREE.SpriteMaterial({ map: fx(x.THREE).soft, color, transparent: true, depthWrite: false, blending: x.THREE.AdditiveBlending }))); s.position.copy(x.mesh.position); return s; };
const flat = (x, color) => { const m = add(x, new x.THREE.Mesh(fx(x.THREE).plane, new x.THREE.MeshBasicMaterial({ map: fx(x.THREE).ring, color, transparent: true, depthWrite: false, blending: x.THREE.AdditiveBlending }))); m.rotation.x = -Math.PI / 2; m.position.set(x.mesh.position.x, 0.06, x.mesh.position.z); return m; };
// n points that spiral around the die; spin(k) lays them out
function swirl(x, n, color, size) {
    const T = x.THREE, pos = new Float32Array(n * 3), geo = new T.BufferGeometry(), o = x.mesh.position.clone(), a0 = [], r0 = [];
    for (let i = 0; i < n; i++) { a0.push(Math.random() * 6.283); r0.push(0.3 + Math.random() * 0.9); }
    geo.setAttribute('position', new T.BufferAttribute(pos, 3));
    const p = add(x, new T.Points(geo, new T.PointsMaterial({ map: fx(T).soft, color, size, sizeAttenuation: false, transparent: true, depthWrite: false, blending: T.AdditiveBlending })));
    p.frustumCulled = false;
    p.spin = (k) => { for (let i = 0; i < n; i++) { const a = a0[i] + k * (5 + (i % 3) * 1.5), r = r0[i] * (1 + k * 2.2); pos.set([o.x + Math.cos(a) * r, o.y + 0.3 + k * 1.5, o.z + Math.sin(a) * r], i * 3); } geo.attributes.position.needsUpdate = true; };
    return p;
}
// a copy of a die's geometry with every triangle split into 4^n (face groups scale with it)
function subdivide(geo, n) {
    let g = geo.index ? geo.toNonIndexed() : geo.clone();
    for (let l = 0; l < n; l++) {
        const out = new g.constructor();
        for (const name of Object.keys(g.attributes)) {
            const a = g.attributes[name], s = a.itemSize, src = a.array, dst = new Float32Array(src.length * 4);
            let o = 0;
            for (let t = 0; t < a.count; t += 3) {
                const P = (i) => src.subarray((t + i) * s, (t + i + 1) * s), A = P(0), B = P(1), C = P(2);
                const M = (u, v) => Array.from(u, (x, j) => (x + v[j]) / 2), ab = M(A, B), bc = M(B, C), ca = M(C, A);
                for (const tri of [[A, ab, ca], [ab, B, bc], [ca, bc, C], [ab, bc, ca]]) for (const p of tri) { dst.set(p, o); o += s; }
            }
            out.setAttribute(name, new a.constructor(dst, s));
        }
        for (const gr of g.groups) out.addGroup(gr.start * 4, gr.count * 4, gr.materialIndex);
        if (g !== geo) g.dispose(); g = out;
    }
    return g;
}
const ease = (k) => 1 - (1 - k) ** 3;
const at = (k, a, b) => Math.max(0, Math.min(1, (k - a) / (b - a)));

// splits the die into separate meshes made of its own triangles (lists[i] = triangle numbers of piece i), each centred on itself, with cloned materials
function pieces(x, g, lists) {
    const T = x.THREE, P = g.attributes.position, N = g.attributes.normal, U = g.attributes.uv, tm = [];
    for (const gr of g.groups) for (let t = gr.start / 3; t < (gr.start + gr.count) / 3; t++) tm[t] = gr.materialIndex;
    const mats = Array.isArray(x.mesh.material) ? x.mesh.material.map((m) => m.clone()) : x.mesh.material.clone();
    x.mats = [].concat(mats); x.parts = [];
    for (const l of lists) {
        const ts = g.groups.length ? l.slice().sort((a, b) => tm[a] - tm[b]) : l, pos = [], nor = [], uv = [], c = new T.Vector3(), geo = new T.BufferGeometry();
        for (const t of ts) for (let i = 0; i < 3; i++) { const j = t * 3 + i; pos.push(P.getX(j), P.getY(j), P.getZ(j)); N && nor.push(N.getX(j), N.getY(j), N.getZ(j)); U && uv.push(U.getX(j), U.getY(j)); }
        for (let i = 0; i < pos.length; i += 3) c.add(new T.Vector3(pos[i], pos[i + 1], pos[i + 2]));
        c.divideScalar(pos.length / 3);
        for (let i = 0; i < pos.length; i += 3) { pos[i] -= c.x; pos[i + 1] -= c.y; pos[i + 2] -= c.z; }
        geo.setAttribute('position', new T.Float32BufferAttribute(pos, 3));
        if (N) geo.setAttribute('normal', new T.Float32BufferAttribute(nor, 3));
        if (U) geo.setAttribute('uv', new T.Float32BufferAttribute(uv, 2));
        if (g.groups.length) for (let i = 0; i < ts.length; ) { let j = i; while (j < ts.length && tm[ts[j]] === tm[ts[i]]) j++; geo.addGroup(i * 3, (j - i) * 3, tm[ts[i]]); i = j; }
        const m = add(x, new T.Mesh(geo, mats)); m.visible = false;
        // c in table space (relative to the die), the way out sideways, and a tumble axis
        const cw = c.clone().applyQuaternion(x.q0), d = new T.Vector3(cw.x, 0, cw.z);
        if (d.lengthSq() < 1e-4) d.set(Math.random() - 0.5, 0, Math.random() - 0.5);
        x.parts.push({ m, geo, c: cw, d: d.normalize(), ax: new T.Vector3(Math.random() - 0.5, Math.random() - 0.5, Math.random() - 0.5).normalize(), sp: 0.6 + Math.random() * 0.8 });
    }
}
// place a piece at the die's spot, moved by off (table space), tumbled ang about its axis, scaled s
function place(x, p, off, ang, s) {
    p.m.position.copy(x.p0).addScaledVector(p.c, s).add(off); p.m.scale.setScalar(s);
    p.m.quaternion.setFromAxisAngle(p.ax, ang).multiply(x.q0);
}
function dim(x, c, e, o) {
    for (const m of x.mats) { m.color.setRGB(c[0], c[1], c[2]); if (m.emissive) m.emissive.setRGB(e[0], e[1], e[2]); if (o < 1) { if (!m.transparent) { m.transparent = true; m.needsUpdate = true; } m.opacity = o; } }
}
function save(x) { x.own = []; x.p0 = x.mesh.position.clone(); x.q0 = x.mesh.quaternion.clone(); x.s0 = x.mesh.scale.clone(); x.vis0 = x.mesh.visible; }
function cleanup(x) {
    x.mesh.position.copy(x.p0); x.mesh.quaternion.copy(x.q0); x.mesh.scale.copy(x.s0); x.mesh.visible = x.vis0;
    if (x.geo0) { x.mesh.geometry = x.geo0; x.overlays.forEach((c) => (c.geometry = x.geo0)); x.geo.dispose(); x.geo0 = null; }
    for (const o of x.own) { x.scene.remove(o); if (o.isPoints) o.geometry.dispose(); if (!o.parts && o.material && !Array.isArray(o.material) && !x.mats?.includes(o.material)) o.material.dispose(); }
    for (const p of x.parts || []) p.geo.dispose();
    for (const m of x.mats || []) m.dispose();
    x.own = []; x.parts = x.mats = null;
}

// cracks: the die is already its own chunks, they shudder and open, glow leaks through, then they fly apart, tumbling and dimming
const nat1 = {
    key: 'a21-shatter', name: 'Shatter', impact: 0.6, dur: 2.4,
    blurb: 'Cracks split the die into chunks of its own faces that fly apart, tumble down onto the table, go dark and fade.',
    start(x) {
        save(x);
        const T = x.THREE, g = subdivide(x.mesh.geometry, 1), tri = g.attributes.position.count / 3, P = g.attributes.position, cen = [], seeds = [];
        for (let t = 0; t < tri; t++) cen.push(new T.Vector3().fromBufferAttribute(P, t * 3).add(new T.Vector3().fromBufferAttribute(P, t * 3 + 1)).add(new T.Vector3().fromBufferAttribute(P, t * 3 + 2)).divideScalar(3));
        while (seeds.length < 11) seeds.push(cen[Math.floor(Math.random() * tri)]);
        const lists = seeds.map(() => []);
        cen.forEach((c, t) => { let b = 0, bd = 1e9; seeds.forEach((s, i) => { const d = c.distanceToSquared(s); if (d < bd) { bd = d; b = i; } }); lists[b].push(t); });
        pieces(x, g, lists.filter((l) => l.length)); g.dispose();
        x.parts.forEach((p) => (p.m.visible = true)); x.mesh.visible = false;
        x.glow = sprite(x, 0xff5a10); x.glow.scale.setScalar(0.1); x.heat = sprite(x, 0xffb050); x.heat.scale.setScalar(0.1);
    },
    tick(k, x) {
        const crack = at(k, 0, 0.25), b = at(k, 0.25, 1), e = ease(at(k, 0.25, 0.7)), t = Math.max(0, k - 0.25) * 2.4, off = new x.THREE.Vector3();
        x.R ??= (x.mesh.geometry.computeBoundingSphere(), x.mesh.geometry.boundingSphere.radius);
        x.parts.forEach((p, i) => {
            // cracking: shudder in place while the seams open a little; then the burst
            const shake = (1 - b) * 0.04 * Math.sin(k * 120 + i * 2.1);
            off.copy(p.d).multiplyScalar(crack * 0.12 + e * (1.5 + 2.5 * p.sp) + shake);
            // a little hop, then gravity brings every piece down onto the table, where it stops tumbling
            p.vy ??= 1 + 2.5 * Math.random();
            const y0 = x.p0.y + p.c.y, y = Math.max(x.R * 0.25, y0 + p.vy * t - 14 * t * t);
            off.y = y - y0;
            place(x, p, off, e * 7 * p.sp, 1);
        });
        // they go dark as they fly, and fade only once they have all landed
        dim(x, [1 - 0.85 * b, 1 - 0.9 * b, 1 - 0.9 * b], [0.5 * (1 - b), 0.1 * (1 - b), 0], 1 - at(k, 0.72, 0.97));
        x.glow.scale.setScalar(1.5 + crack * 2 + e * 5); x.glow.material.opacity = 0.7 * crack * (1 - at(k, 0.45, 0.9));
        x.heat.scale.setScalar(1 + e * 3); x.heat.material.opacity = 0.9 * Math.sin(Math.PI * at(k, 0.25, 0.55));
    },
    end: cleanup,
};

// the die squeezes into a bright point, holds, then its faces burst out as an expanding gold shell and shockwave
const nat20 = {
    key: 'a21-supernova', name: 'Supernova', impact: 1.2, dur: 2.2,
    blurb: 'The die shrinks to a tiny bright point, pauses, then blasts outward as a shell of its own golden faces.',
    start(x) {
        save(x);
        const g = subdivide(x.mesh.geometry, 1), n = g.attributes.position.count / 3;
        pieces(x, g, Array.from({ length: n }, (_, t) => [t])); g.dispose();
        dim(x, [1, 0.85, 0.4], [1, 0.65, 0.1], 1);
        x.star = sprite(x, 0xfff0b0); x.star.scale.setScalar(0.1); x.flash = sprite(x, 0xffc040); x.flash.scale.setScalar(0.1); x.ring = flat(x, 0xffd060); x.ring.scale.setScalar(0.1);
    },
    tick(k, x) {
        const T = x.THREE, s = at(k, 0, 0.5), b = at(k, 0.55, 1), e = ease(b), off = new T.Vector3(), hold = k < 0.55;
        // implosion: shrinks while spinning faster, the glow gathers
        x.mesh.visible = hold;
        if (hold) {
            x.mesh.scale.setScalar(Math.max(0.04, 1 - ease(s) * 0.97));
            x.mesh.quaternion.setFromAxisAngle(new T.Vector3(0, 1, 0), s * s * 14).multiply(x.q0);
        }
        x.star.scale.setScalar(hold ? 0.4 + s * 1.2 + 0.25 * Math.sin(k * 60) * at(k, 0.45, 0.55) : 0.1); x.star.material.opacity = hold ? 0.3 + s * 0.7 : 0;
        x.parts.forEach((p) => {
            p.m.visible = !hold;
            if (!hold) { off.copy(p.d).multiplyScalar(e * (5 + 3 * p.sp)); place(x, p, off, e * 4 * p.sp, 0.2 + e * 0.8); }
        });
        dim(x, [1, 0.85 - 0.3 * b, 0.4], [1 - 0.5 * b, 0.65 - 0.5 * b, 0.1], 1 - at(k, 0.8, 1));
        x.flash.scale.setScalar(2 + e * 14); x.flash.material.opacity = b > 0 ? 1 - at(k, 0.55, 0.8) : 0;
        x.ring.scale.setScalar(0.5 + e * 16); x.ring.material.opacity = b > 0 ? 1 - at(k, 0.55, 1) : 0;
    },
    end: cleanup,
};

// twist out: every height turns a different amount around the vertical axis, so the die wrings into a spiral that thins away in a swirl of sparks
const mid = {
    key: 'a21-twist-out', name: 'Twist out', dur: 1.2,
    blurb: 'The die wrings itself around the vertical axis and thins into a fading swirl of embers.',
    start(x) {
        save(x);
        x.geo0 = x.mesh.geometry; x.geo = subdivide(x.geo0, 2); x.mesh.geometry = x.geo;
        x.overlays = x.mesh.children.filter((c) => c.isMesh); x.overlays.forEach((c) => (c.geometry = x.geo));
        const p = x.geo.attributes.position, v = new x.THREE.Vector3();
        x.qi = x.q0.clone().invert(); x.w0 = []; let lo = Infinity, hi = -Infinity;
        for (let i = 0; i < p.count; i++) { v.fromBufferAttribute(p, i).applyQuaternion(x.q0); x.w0.push(v.clone()); lo = Math.min(lo, v.y); hi = Math.max(hi, v.y); }
        x.lo = lo; x.tall = hi - lo || 1;
        x.sw = swirl(x, 18, 0xff8a30, 5); x.heat = sprite(x, 0xff6a10); x.heat.scale.setScalar(0.1);
    },
    tick(k, x) {
        const m = ease(at(k, 0, 0.85)), p = x.geo.attributes.position, v = new x.THREE.Vector3();
        for (let i = 0; i < p.count; i++) {
            const w = x.w0[i], h = (w.y - x.lo) / x.tall, a = m * (3 + 6 * h), c = Math.cos(a), s = Math.sin(a), r = Math.max(0.02, 1 - m * (0.65 + 0.35 * h));
            v.set((w.x * c - w.z * s) * r, w.y + m * (h - 0.3) * x.tall * 0.4, (w.x * s + w.z * c) * r).applyQuaternion(x.qi);
            p.setXYZ(i, v.x, v.y, v.z);
        }
        p.needsUpdate = true; x.geo.computeVertexNormals(); x.geo.computeBoundingSphere();
        x.mesh.scale.setScalar(Math.max(0.001, 1 - at(k, 0.8, 1)));
        x.sw.spin(k); x.sw.material.opacity = Math.sin(Math.PI * at(k, 0.05, 1));
        x.heat.scale.setScalar(1 + m * 2.5); x.heat.material.opacity = 0.45 * Math.sin(Math.PI * at(k, 0, 0.9));
    },
    end: cleanup,
};

export default { nat1, nat20, mid };
