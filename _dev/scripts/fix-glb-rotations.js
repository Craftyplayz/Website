// export_models.py skips the newer Minecraft element rotation {"x":..,"y":..,"z":..,"origin":[..]} (it only does
// {"angle","axis"}), so those parts come out unrotated. This rotates them in an exported .glb, using the source model JSON.
// Usage: node _dev/scripts/fix-glb-rotations.js <model.json> <model.glb>
// shortcut: x-axis only (all the gargoyle needs); add y/z when a model uses them.
const fs = require('fs');
const [jsonPath, glbPath] = process.argv.slice(2);
const model = JSON.parse(fs.readFileSync(jsonPath, 'utf8'));
const glb = fs.readFileSync(glbPath);
const jsonLen = glb.readUInt32LE(12), bin = 20 + jsonLen + 8;
const gltf = JSON.parse(glb.slice(20, 20 + jsonLen));
const prim = gltf.meshes[0].primitives[0];
const acc = i => gltf.accessors[i], off = a => bin + (gltf.bufferViews[a.bufferView].byteOffset || 0) + (a.byteOffset || 0);
const pos = acc(prim.attributes.POSITION), nrm = acc(prim.attributes.NORMAL);
if (pos.count !== model.elements.length * 24) throw new Error('vertex count does not match 24 per element');

// the exporter centres x and z on the elements' bounding box and divides by 16 (checked against the arm's 27.5° rotation)
const lo = [Infinity, Infinity, Infinity], hi = [-Infinity, -Infinity, -Infinity];
model.elements.forEach(e => [0, 2].forEach(k => { lo[k] = Math.min(lo[k], e.from[k], e.to[k]); hi[k] = Math.max(hi[k], e.from[k], e.to[k]); }));
const cx = (lo[0] + hi[0]) / 2, cz = (lo[2] + hi[2]) / 2;

let fixed = 0;
model.elements.forEach((e, n) => {
    const r = e.rotation;
    if (!r || r.angle !== undefined || !(r.x || r.y || r.z)) return;
    if (r.y || r.z) throw new Error(`element ${n}: y/z rotation not supported yet`);
    const a = r.x * Math.PI / 180, c = Math.cos(a), s = Math.sin(a);
    const rot = (y, z) => [y * c - z * s, y * s + z * c]; // same direction as {"angle","axis":"x"}
    for (let v = n * 24; v < n * 24 + 24; v++) {
        const p = off(pos) + v * 12, q = off(nrm) + v * 12;
        const y = glb.readFloatLE(p + 4) * 16 - r.origin[1], z = glb.readFloatLE(p + 8) * 16 + cz - r.origin[2];
        const [ny, nz] = rot(y, z);
        glb.writeFloatLE((ny + r.origin[1]) / 16, p + 4); glb.writeFloatLE((nz + r.origin[2] - cz) / 16, p + 8);
        const [my, mz] = rot(glb.readFloatLE(q + 4), glb.readFloatLE(q + 8));
        glb.writeFloatLE(my, q + 4); glb.writeFloatLE(mz, q + 8);
    }
    fixed++;
});
// refresh the position bounds the glTF declares
const mn = [Infinity, Infinity, Infinity], mx = [-Infinity, -Infinity, -Infinity];
for (let v = 0; v < pos.count; v++) for (let k = 0; k < 3; k++) { const x = glb.readFloatLE(off(pos) + v * 12 + k * 4); mn[k] = Math.min(mn[k], x); mx[k] = Math.max(mx[k], x); }
pos.min = mn; pos.max = mx;
let json = Buffer.from(JSON.stringify(gltf)); json = Buffer.concat([json, Buffer.alloc((4 - json.length % 4) % 4, 0x20)]);
const rest = glb.slice(20 + jsonLen);
const head = Buffer.alloc(20); head.write('glTF', 0); head.writeUInt32LE(2, 4); head.writeUInt32LE(20 + json.length + rest.length, 8);
head.writeUInt32LE(json.length, 12); head.write('JSON', 16);
fs.writeFileSync(glbPath, Buffer.concat([head, json, rest]));
console.log(`rotated ${fixed} element(s); centre x ${cx}, z ${cz}`);
