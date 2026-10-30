// Ember Cage exit 37: nat 1 Frostbite. A spiky ring of frost creeps across the table toward the die, ice crawls over it and its fire goes icy blue,
// it freezes solid, a crack runs across, and it shatters into ice shards that skid outward across the table and melt away.
let FX = null;
function fx(T) {
    if (FX) return FX;
    const rnd = Math.random, mk = (w, f) => { const c = document.createElement('canvas'); c.width = c.height = w; f(c.getContext('2d'), w); return new T.CanvasTexture(c); };
    // frost: a faint disc with a ring of long and short spikes around it
    const frost = mk(256, (g, w) => {
        g.translate(w / 2, w / 2); g.fillStyle = 'rgba(255,255,255,.18)'; g.beginPath(); g.arc(0, 0, w * 0.3, 0, 7); g.fill();
        g.fillStyle = 'rgba(255,255,255,.9)';
        for (let a = 0, i = 0; a < 6.283; i++) { const d = 0.05 + rnd() * 0.07, L = w * (i % 3 ? 0.36 + rnd() * 0.08 : 0.46); g.beginPath(); g.moveTo(Math.cos(a) * w * 0.27, Math.sin(a) * w * 0.27); g.lineTo(Math.cos(a + d) * w * 0.3, Math.sin(a + d) * w * 0.3); g.lineTo(Math.cos(a + d / 2) * L, Math.sin(a + d / 2) * L); g.fill(); a += d * 1.4; }
    });
    // crack: a jagged line from edge to edge with a few side branches
    const crack = mk(256, (g, w) => {
        g.strokeStyle = '#fff'; g.lineCap = 'round'; g.lineWidth = 5; g.beginPath(); g.moveTo(0, w / 2);
        let y = w / 2; for (let x = 16; x <= w; x += 16) { y = w / 2 + (rnd() - 0.5) * 36; g.lineTo(x, y); }
        g.stroke(); g.lineWidth = 2;
        for (let i = 0; i < 6; i++) { const x = 30 + rnd() * 190, s = rnd() < 0.5 ? -1 : 1; g.beginPath(); g.moveTo(x, w / 2); g.lineTo(x + 14, w / 2 + s * (20 + rnd() * 20)); g.stroke(); }
    });
    const soft = mk(64, (g) => { const r = g.createRadialGradient(32, 32, 0, 32, 32, 32); r.addColorStop(0, 'rgba(255,255,255,1)'); r.addColorStop(1, 'rgba(255,255,255,0)'); g.fillStyle = r; g.fillRect(0, 0, 64, 64); });
    const sh = new T.Shape(); sh.moveTo(0, 0.5); sh.lineTo(-0.3, -0.3); sh.lineTo(0.35, -0.2); sh.closePath();
    return (FX = { frost, crack, soft, plane: new T.PlaneGeometry(1, 1), shard: new T.ShapeGeometry(sh) });
}
const ease = (k) => 1 - (1 - k) ** 3;
const at = (k, a, b) => Math.max(0, Math.min(1, (k - a) / (b - a)));
const add = (x, o) => (x.scene.add(o), x.own.push(o), o);
const basic = (T, o) => new T.MeshBasicMaterial(Object.assign({ transparent: true, depthWrite: false, blending: T.AdditiveBlending }, o));
// a flat plane lying on the table at the die's spot
const flat = (x, map, color) => { const m = add(x, new x.THREE.Mesh(fx(x.THREE).plane, basic(x.THREE, { map, color }))); m.rotation.x = -Math.PI / 2; m.position.set(x.p0.x, 0.06, x.p0.z); return m; };

const nat1 = {
    key: 'a37-frostbite', name: 'Frostbite', dur: 3.4,
    blurb: 'Frost creeps in toward the die in a spiky ring, ice crawls over it and snuffs the fire, then it freezes, cracks and shatters into shards that melt away.',
    start(x) {
        const T = x.THREE, F = fx(T), m = x.mesh;
        x.own = []; x.p0 = m.position.clone(); x.vis0 = m.visible; x.mat0 = m.material; x.kids = m.children.map((c) => [c, c.visible]);
        // our own tinted copies of the die's materials, so the shared ones are never touched
        x.mats = [].concat(m.material).map((a) => a.clone()); m.material = Array.isArray(m.material) ? x.mats : x.mats[0];
        x.c0 = x.mats.map((a) => a.color.clone()); x.e0 = x.mats.map((a) => a.emissive?.clone());
        m.children.forEach((c) => (c.visible = false)); // the fire churn and sparks go out
        x.cast0 = m.castShadow; m.castShadow = false;
        x.ring = flat(x, F.frost, 0xaee4ff); x.ring.scale.setScalar(16); x.ring.material.opacity = 0; x.ring.rotation.z = Math.random() * 6.28;
        x.ice = add(x, new T.Mesh(m.geometry, basic(T, { color: 0xbfeaff, opacity: 0 }))); x.ice.scale.setScalar(1.03);
        x.flash = add(x, new T.Sprite(basic(T, { map: F.soft, color: 0xd8f4ff, opacity: 0 }))); x.flash.position.copy(x.p0);
        x.crack = flat(x, F.crack, 0xffffff); x.crack.position.y = 0.2; x.crack.rotation.z = Math.random() * 3.14; x.crack.material.opacity = 0;
        // shards: flat triangles with their own heading, speed and spin
        x.shards = Array.from({ length: 16 }, () => {
            const s = add(x, new T.Mesh(F.shard, basic(T, { color: 0xcdeeff, opacity: 0 }))), a = Math.random() * 6.283, sz = 0.5 + Math.random() * 0.9;
            s.rotation.x = -Math.PI / 2; s.visible = false; s.scale.setScalar(sz);
            return { s, vx: Math.cos(a), vz: Math.sin(a), v: 4 + Math.random() * 7, w: (Math.random() - 0.5) * 14, r: Math.random() * 6.28 };
        });
    },
    tick(k, x) {
        const m = x.mesh, creep = at(k, 0, 0.42), cold = at(k, 0.12, 0.45), shat = k > 0.6, t = at(k, 0.6, 1);
        // frost ring contracts onto the die, then fades as the shards fly
        x.ring.scale.setScalar(16 - 11.5 * ease(creep)); x.ring.material.opacity = 0.9 * at(k, 0, 0.15) * (1 - at(k, 0.7, 0.95));
        // ice overlay on the die's own geometry; the fire colour dims to blue
        x.mats.forEach((a, i) => { a.color.copy(x.c0[i]).lerp(new x.THREE.Color(0x6aaee0), cold); a.emissive?.copy(x.e0[i]).lerp(new x.THREE.Color(0x103a70), cold); });
        x.ice.position.copy(m.position); x.ice.quaternion.copy(m.quaternion); x.ice.material.opacity = 0.65 * cold * (shat ? 0 : 1);
        x.ice.visible = !shat; m.visible = !shat;
        // frozen glint, then the crack runs across just before the break
        x.flash.scale.setScalar(2 + 6 * at(k, 0.4, 0.62)); x.flash.material.opacity = 0.7 * Math.sin(3.14 * at(k, 0.4, 0.5)) + 0.9 * Math.sin(3.14 * at(k, 0.58, 0.72));
        const c = at(k, 0.46, 0.6); x.crack.scale.set(0.1 + 3.2 * c, 0.1 + 3.2 * c, 1); x.crack.material.opacity = k < 0.6 ? c : 0;
        // shards skid outward with friction, spinning, and melt
        for (const p of x.shards) {
            p.s.visible = shat; if (!shat) continue;
            const d = (1 - (1 - t) ** 2.5) * p.v * 0.5;
            p.s.position.set(x.p0.x + p.vx * d, 0.1, x.p0.z + p.vz * d); p.s.rotation.z = p.r + p.w * ease(t) * 0.4;
            p.s.material.opacity = 0.95 * (1 - at(t, 0.45, 0.85));
        }
    },
    end(x) {
        const m = x.mesh;
        m.material = x.mat0; m.visible = x.vis0; m.castShadow = x.cast0; m.position.copy(x.p0);
        x.kids.forEach(([c, v]) => (c.visible = v)); x.mats.forEach((a) => a.dispose());
        for (const o of x.own) { x.scene.remove(o); o.material?.dispose(); } // geometry and textures are shared or cached
        x.own = [];
    },
};

export default { nat1 };
