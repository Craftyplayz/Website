// Ember Cage exits 31, Slow Melt: a calm, real-looking melt. Heat shimmer, magma floods the faces (numbers sink before anything bends), a very slow sag into a thin-skinned pool, a dull red glow under a hardening crust, then ash fades away.
let FX = null;
const rng = (s) => () => ((s = (s * 16807) % 2147483647) / 2147483647);
// shared once: a soft blob, a plane, and two tileable plate textures (magma plates with bright seams, and glowing cracks alone)
function fx(T) {
    if (FX) return FX;
    const mk = (draw) => { const c = document.createElement('canvas'); c.width = c.height = 128; draw(c.getContext('2d'), c); return c; };
    const soft = new T.CanvasTexture(mk((g) => { const gr = g.createRadialGradient(64, 64, 0, 64, 64, 64); gr.addColorStop(0, 'rgba(255,255,255,1)'); gr.addColorStop(0.35, 'rgba(255,255,255,.6)'); gr.addColorStop(1, 'rgba(255,255,255,0)'); g.fillStyle = gr; g.fillRect(0, 0, 128, 128); }));
    // voronoi cells on a wrapping 128 square: d2 - d1 is small along the seams
    const r = rng(77), pts = Array.from({ length: 26 }, () => [r() * 128, r() * 128, 0.35 + r() * 0.65]), N = 128;
    const cell = (x, y) => { let d1 = 1e9, d2 = 1e9, t = 0; for (const p of pts) { const dx = Math.min(Math.abs(x - p[0]), N - Math.abs(x - p[0])), dy = Math.min(Math.abs(y - p[1]), N - Math.abs(y - p[1])), d = dx * dx + dy * dy; if (d < d1) { d2 = d1; d1 = d; t = p[2]; } else if (d < d2) d2 = d; } return [Math.sqrt(d2) - Math.sqrt(d1), t]; };
    const plates = mk((g) => { const im = g.createImageData(N, N); for (let y = 0; y < N; y++) for (let x = 0; x < N; x++) { const [e, t] = cell(x, y), seam = Math.max(0, 1 - e / 5), i = (y * N + x) * 4, n = 0.8 + 0.2 * Math.sin(x * 0.35) * Math.cos(y * 0.29);
        im.data[i] = 255 * Math.min(1, (0.55 + 0.35 * t) * n + seam * 0.5); im.data[i + 1] = 255 * Math.min(1, (0.3 + 0.3 * t) * n + seam * 0.7); im.data[i + 2] = 255 * (0.1 * t + seam * 0.45); im.data[i + 3] = 255; } g.putImageData(im, 0, 0); });
    const cracks = mk((g) => { const im = g.createImageData(N, N); for (let y = 0; y < N; y++) for (let x = 0; x < N; x++) { const [e] = cell(x, y), seam = Math.max(0, 1 - e / 3) ** 2, i = (y * N + x) * 4;
        im.data[i] = 255 * seam; im.data[i + 1] = 70 * seam * seam; im.data[i + 2] = 10 * seam; im.data[i + 3] = 255; } g.putImageData(im, 0, 0); });
    const tex = (c) => { const t = new T.CanvasTexture(c); t.wrapS = t.wrapT = T.RepeatWrapping; return t; };
    return (FX = { soft, plane: new T.PlaneGeometry(1, 1), plates: tex(plates), cracks: tex(cracks) });
}
// a copy of a die's geometry with every triangle split into 4^n, so it bends smoothly; face groups only scale by 4^n
function subdivide(geo, n) {
    let g = geo.index ? geo.toNonIndexed() : geo.clone();
    for (let l = 0; l < n; l++) {
        const out = new g.constructor(), names = Object.keys(g.attributes);
        for (const name of names) {
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
const at = (k, a, b) => Math.max(0, Math.min(1, (k - a) / (b - a)));
const ss = (k, a, b) => { const t = at(k, a, b); return t * t * (3 - 2 * t); };
const lerp = (a, b, t) => a + (b - a) * t;
// colour of the skin: hot yellow-orange, to deep dull red, to black stone, to grey ash
const HOT = [1, 0.78, 0.45], RED = [0.62, 0.1, 0.03], STONE = [0.09, 0.11, 0.14], ASH = [0.42, 0.43, 0.46];
const mix = (a, b, t) => a.map((v, i) => lerp(v, b[i], t));

const melt = {
    key: 'a31-melt-slow', name: 'Slow Melt', blurb: 'A calm, real melt: the die shimmers, floods with magma, sags into a thin-skinned pool, crusts over with a dull red glow beneath, and fades to ash.',
    dur: 4,
    start(x) {
        const T = x.THREE, F = fx(T);
        x.own = [];
        // the materials and geometry are shared by every die of this kind, so work on copies and put the originals back in end()
        x.mats0 = x.mesh.material; x.mats = [].concat(x.mats0).map((m) => m.clone()); x.mesh.material = Array.isArray(x.mats0) ? x.mats : x.mats[0];
        x.geo0 = x.mesh.geometry; x.geo = subdivide(x.geo0, 2); x.mesh.geometry = x.geo;
        x.overlays = x.mesh.children.filter((c) => c.isMesh); x.overlays.forEach((c) => (c.geometry = x.geo));
        x.vis0 = x.overlays.map((c) => c.visible);
        // every vertex in table space; the widest reach sizes the pool, the lowest and highest points the sag
        const p = x.geo.attributes.position, q = x.mesh.quaternion, v = new T.Vector3();
        x.qi = q.clone().invert(); x.w0 = []; let lo = Infinity, hi = -Infinity, R = 0;
        for (let i = 0; i < p.count; i++) { v.fromBufferAttribute(p, i).applyQuaternion(q); x.w0.push(v.clone()); lo = Math.min(lo, v.y); hi = Math.max(hi, v.y); R = Math.max(R, Math.hypot(v.x, v.z)); }
        x.lo = lo; x.tall = hi - lo; x.R = R;
        // the skin: same bending vertices (shared position attribute), top-down texture coordinates, a shaded vertex colour
        const sk = new T.BufferGeometry(), uv = new Float32Array(p.count * 2), col = new Float32Array(p.count * 3).fill(1), span = R * 4.4;
        for (let i = 0; i < p.count; i++) { uv[i * 2] = x.w0[i].x / span + 0.5; uv[i * 2 + 1] = x.w0[i].z / span + 0.5; }
        sk.setAttribute('position', p); sk.setAttribute('uv', new T.BufferAttribute(uv, 2)); sk.setAttribute('color', new T.BufferAttribute(col, 3)); x.col = col; x.skg = sk;
        const mat = (map, add) => new T.MeshBasicMaterial({ map, transparent: true, opacity: 0, depthWrite: false, polygonOffset: true, polygonOffsetFactor: add ? -3 : -2, polygonOffsetUnits: -2, vertexColors: !add, blending: add ? T.AdditiveBlending : T.NormalBlending });
        x.skin = new T.Mesh(sk, mat(F.plates, false)); x.crk = new T.Mesh(sk, mat(F.cracks, true));
        for (const s of [x.skin, x.crk]) { s.frustumCulled = false; s.renderOrder = 3; x.mesh.add(s); }
        const add = (o) => { x.scene.add(o); x.own.push(o); return o; };
        // the glow on the table under the pool, dull and red
        x.pool = add(new T.Mesh(F.plane, new T.MeshBasicMaterial({ map: F.soft, color: 0xff6010, transparent: true, depthWrite: false, blending: T.AdditiveBlending, opacity: 0 })));
        x.pool.rotation.x = -Math.PI / 2; x.pool.position.set(x.mesh.position.x, 0.05, x.mesh.position.z); x.pool.scale.setScalar(R * 2);
        // heat shimmer: a pale warm haze that breathes around the die
        x.haze = add(new T.Sprite(new T.SpriteMaterial({ map: F.soft, color: 0xffb070, transparent: true, depthWrite: false, blending: T.AdditiveBlending, opacity: 0 }))); x.haze.position.copy(x.mesh.position);
        // steam: a few soft grey puffs that drift outward and thin out
        x.n = 14; const sp = new Float32Array(x.n * 3); x.sp = sp; x.sv = [];
        const r = rng(5 + Math.round(x.mesh.position.x * 97) % 100); for (let i = 0; i < x.n; i++) { const a = r() * 6.283; x.sv.push([Math.cos(a), Math.sin(a), 0.4 + r() * 0.6, r() * 0.35]); }
        const sg = new T.BufferGeometry(); sg.setAttribute('position', new T.BufferAttribute(sp, 3));
        x.steam = add(new T.Points(sg, new T.PointsMaterial({ map: F.soft, color: 0xcfc8c2, size: 22, sizeAttenuation: false, transparent: true, depthWrite: false, opacity: 0 }))); x.steam.frustumCulled = false;
        x.o = x.mesh.position.clone();
    },
    tick(k, x) {
        // a see-through die still casts a shadow, so it stops once the pool is flat and fading
        x.mesh.castShadow = k < 0.8;
        // the set's own sparks would float on alone at the end
        for (const c of x.mesh.children) if (c.isPoints) c.visible = k < 0.85;
        const T = x.THREE, p = x.geo.attributes.position, col = x.col, v = new T.Vector3(), R = x.R;
        const heat = ss(k, 0, 0.14), flood = ss(k, 0.07, 0.25), sag = ss(k, 0.3, 0.66), cool = ss(k, 0.5, 0.8), fade = ss(k, 0.82, 0.96), ph = k * 5;
        // the numbers sink first: the die's own faces heat up, then the skin floods over them before the shape bends
        for (const m of x.mats) { if (m.emissive) m.emissive.setRGB(heat * 0.9, heat * 0.3, 0.03); m.transparent = true; m.opacity = 1 - flood; }
        // skin colour: bright plates while hot, deep red, black stone, ash; shifts by cooling only
        const c = mix(mix(mix(HOT, RED, ss(cool, 0, 0.55)), STONE, ss(cool, 0.35, 1)), ASH, fade * 0.8);
        x.skin.material.color.setRGB(c[0], c[1], c[2]); x.skin.material.opacity = flood * (1 - fade);
        // glowing cracks open as the crust forms and dim slowly
        x.crk.material.opacity = ss(cool, 0.1, 0.5) * (1 - ss(k, 0.7, 0.92)) * 0.55;
        x.overlays.forEach((o, i) => (o.visible = x.vis0[i] && flood < 0.5));
        for (let i = 0; i < p.count; i++) {
            const w = x.w0[i], h = (w.y - x.lo) / x.tall, a = Math.atan2(w.z, w.x);
            // each spot sags at its own slow pace (taken from where it is, so split vertices stay joined) and the top goes first
            const wob = 0.8 + 0.2 * Math.sin(w.x * 4.1 + w.z * 3.3 + ph * 0.5) * Math.cos(w.z * 3.7 - w.x * 2.1);
            const s = Math.min(1, sag * wob * (0.75 + 0.35 * h));
            // seen from above, the sag reads as the outline spreading in a few unhurried lobes; thin pool, so the spread is modest
            const lobe = 1 + 0.2 * Math.sin(3 * a + 1.3 + ph * 0.15) + 0.1 * Math.sin(5 * a + 0.4 - ph * 0.1);
            const nh = h * (1 - s) ** 1.5 * (1 - cool * 0.85), spread = 1 + s * (1.05 - nh) * 1.0 * lobe + cool * 0.15 * lobe;
            // heat shimmer: a tiny ripple on the surface while it is hot
            const sh = 1 + 0.012 * Math.sin(w.x * 14 + w.z * 11 + ph * 6) * heat * (1 - cool);
            v.set(w.x * spread * sh, x.lo + nh * x.tall + 0.01, w.z * spread * sh).applyQuaternion(x.qi);
            p.setXYZ(i, v.x, v.y, v.z);
            // shading: the low rim of the pool is darker than the thick middle
            const b = 0.7 + 0.3 * Math.min(1, nh * 1.4 + (1 - Math.hypot(w.x, w.z) / (R * 1.8)) * 0.4); col[i * 3] = col[i * 3 + 1] = col[i * 3 + 2] = b;
        }
        p.needsUpdate = true; x.skg.attributes.color.needsUpdate = true; x.geo.computeVertexNormals(); x.geo.computeBoundingSphere();
        // table glow: warm while liquid, then a dull red that outlasts the heat
        x.pool.material.opacity = (0.15 + 0.45 * heat) * (1 - 0.35 * cool) * (1 - fade);
        x.pool.material.color.setRGB(1, 0.5 - 0.3 * cool, 0.1 - 0.07 * cool); x.pool.scale.setScalar(R * (2.4 + 3.4 * sag + 0.6 * cool));
        x.haze.scale.setScalar(R * (3.2 + 0.25 * Math.sin(ph * 9) + 1.5 * sag)); x.haze.material.opacity = 0.22 * heat * (1 - cool) * (1 + 0.25 * Math.sin(ph * 11));
        // steam: starts as the crust forms, drifts outward and rises, gone by the end
        const st = at(k, 0.5, 1), sp = x.sp;
        for (let i = 0; i < x.n; i++) { const d = x.sv[i], t = Math.max(0, st - d[3]), r = R * (0.5 + 1.8 * t * d[2]); sp[i * 3] = x.o.x + d[0] * r; sp[i * 3 + 1] = x.o.y + 0.5 + t * 2; sp[i * 3 + 2] = x.o.z + d[1] * r; }
        x.steam.geometry.attributes.position.needsUpdate = true; x.steam.material.opacity = 0.16 * ss(k, 0.5, 0.65) * (1 - ss(k, 0.85, 0.97));
    },
    end(x) {
        x.mesh.castShadow = true; for (const c of x.mesh.children) if (c.isPoints) c.visible = true;
        x.mesh.geometry = x.geo0; x.overlays.forEach((c, i) => { c.geometry = x.geo0; c.visible = x.vis0[i]; }); x.mesh.material = x.mats0;
        x.mats.forEach((m) => m.dispose()); x.geo.dispose(); x.skg.dispose(); x.mesh.scale.setScalar(1);
        for (const s of [x.skin, x.crk]) { x.mesh.remove(s); s.material.dispose(); }
        for (const o of x.own || []) { x.scene.remove(o); o.material.dispose(); if (o.isPoints) o.geometry.dispose(); }
        x.own = [];
    },
};

export default { mid: melt };
