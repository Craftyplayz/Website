// Collection hallway check: reads the chests out of a .schem, .litematic or structure .nbt file and compares them
// with the fixed chest layout. No DOM here, so _dev/scripts/collection-hallway.test.js can run it in Node.
// Needs minecraft/tools/shulker-calculator/schematic.js (for its NBTReader) loaded first in the browser.
(function (root) {
    const ANCHOR = [-636, 36, -626]; // the owner always makes the schematic standing here
    const BAD = "This file isn't a .schem, .litematic or structure .nbt schematic.";
    const nbtReader = () => (root.Schematic || require('../../../tools/shulker-calculator/schematic.js')).NBTReader;

    // chest i's west half; the east half is x + 1, same y and z
    function layoutPos(i) {
        const s = Math.floor(i / 4), r = i % 4;
        return { x: -629 + 3 * s, y: 36 + 2 * (r % 2), z: r < 2 ? -630 : -622 };
    }

    // NBTReader gives BigInt for longs and typed arrays for arrays; JSON.stringify can't take either as-is
    const json = (v) => JSON.stringify(v, (k, x) => typeof x === 'bigint' ? String(x) : ArrayBuffer.isView(x) ? Array.from(x) : x);

    // old items: {Slot, id, Count: byte, tag}; 1.20.5+: {Slot, id, count: int, components}
    const readItems = (list) => (Array.isArray(list) ? list : []).map((it) => ({
        slot: Number(it.Slot) || 0,
        id: String(it.id || '').replace(/^minecraft:/, ''),
        count: it.count !== undefined ? Number(it.count) : it.Count !== undefined ? Number(it.Count) : 1,
        data: it.components || it.tag ? json(it.components || it.tag) : '',
    }));

    // keeps chests, trapped chests and copper chests (not ender chests), or anything without an id that holds Items
    function add(out, x, y, z, id, data) {
        id = String(id || '');
        if (id ? !/chest$/.test(id) || /ender_chest$/.test(id) : !Array.isArray(data && data.Items)) return;
        out.push({ x, y, z, id, items: readItems(data && data.Items) });
    }

    const same = (a, b) => a[0] === b[0] && a[1] === b[1] && a[2] === b[2];
    const noteFor = (origin, anchor) => same(origin, anchor) ? '' : `The file says it was copied at ${origin[0]} ${origin[1]} ${origin[2]}; positions use ${anchor.join(' ')}.`;

    async function readContainers(buf, anchor = ANCHOR) {
        const Reader = nbtReader();
        let tag;
        try {
            const u = new Uint8Array(buf);
            if (u[0] === 0x1f && u[1] === 0x8b) buf = await new Response(new Blob([buf]).stream().pipeThrough(new DecompressionStream('gzip'))).arrayBuffer();
            tag = new Reader(buf).read();
        } catch (e) { throw Error(BAD); }

        const [ax, ay, az] = anchor, out = [], s = tag.Schematic || tag;
        let format, note = '';
        if (tag.Regions) {
            // tile entity x,y,z are relative to the region's min corner; Position is one corner, Size may point back from it
            format = 'litematic';
            for (const r of Object.values(tag.Regions)) {
                const m = ['x', 'y', 'z'].map((k) => r.Position[k] + (r.Size[k] < 0 ? r.Size[k] + 1 : 0));
                for (const t of r.TileEntities || []) add(out, ax + m[0] + t.x, ay + m[1] + t.y, az + m[2] + t.z, t.id, t);
            }
        } else if (Array.isArray(tag.blocks) && (tag.palette || tag.palettes)) {
            format = 'structure';
            for (const b of tag.blocks) if (b.nbt) add(out, ax + b.pos[0], ay + b.pos[1], az + b.pos[2], b.nbt.id, b.nbt);
        } else if (s.Version === 3 || (s.Blocks && s.Blocks.Palette)) {
            // WorldEdit v3: Offset = region min - copy origin
            format = 'sponge3';
            const o = s.Offset || [0, 0, 0], we = s.Metadata && s.Metadata.WorldEdit;
            for (const e of (s.Blocks && s.Blocks.BlockEntities) || []) add(out, ax + o[0] + e.Pos[0], ay + o[1] + e.Pos[1], az + o[2] + e.Pos[2], e.Id, e.Data);
            if (we && we.Origin) note = noteFor(Array.from(we.Origin), anchor);
        } else if ((s.Version === 1 || s.Version === 2 || s.Palette) && !s.Materials) {
            // WorldEdit v2: Offset = absolute region min, WEOffset = region min - copy origin; without WEOffset, Offset is taken as relative
            format = 'sponge2';
            const o = s.Offset || [0, 0, 0], md = s.Metadata || {};
            const we = md.WEOffsetX !== undefined ? [md.WEOffsetX, md.WEOffsetY, md.WEOffsetZ] : null, min = we || o;
            for (const e of s.BlockEntities || s.TileEntities || []) add(out, ax + min[0] + e.Pos[0], ay + min[1] + e.Pos[1], az + min[2] + e.Pos[2], e.Id, e);
            if (we) note = noteFor([o[0] - we[0], o[1] - we[1], o[2] - we[2]], anchor);
        } else throw Error(BAD);
        return { format, containers: out, note };
    }

    // world saves: the hallway's box for n chests, and the region files (r.X.Z.mca, 512 x 512 blocks) it crosses
    function bounds(n) { return { x0: -629, x1: layoutPos(n - 1).x + 1, y0: 36, y1: 39, z0: -630, z1: -622 }; }
    function regionsFor(n) {
        const b = bounds(n), out = [];
        for (let rx = Math.floor(b.x0 / 512); rx <= Math.floor(b.x1 / 512); rx++) for (let rz = Math.floor(b.z0 / 512); rz <= Math.floor(b.z1 / 512); rz++) out.push([rx, rz]);
        return out;
    }
    // a region file: 1,024 four-byte slots (sector offset, sector count), then each chunk as length, compression type, NBT
    // only the chunks under the hallway are unpacked; block entity x, y, z are world positions
    async function readRegion(buf, rx, rz, n) {
        const Reader = nbtReader(), v = new DataView(buf), b = bounds(n), out = [], zip = { 1: 'gzip', 2: 'deflate' };
        if (buf.byteLength < 8192) throw Error(`r.${rx}.${rz}.mca isn't a region file.`);
        for (let cx = Math.max(b.x0 >> 4, rx * 32); cx <= Math.min(b.x1 >> 4, rx * 32 + 31); cx++) {
            for (let cz = Math.max(b.z0 >> 4, rz * 32); cz <= Math.min(b.z1 >> 4, rz * 32 + 31); cz++) {
                const off = (v.getUint32(4 * ((cx & 31) + (cz & 31) * 32)) >>> 8) * 4096;
                if (!off) continue; // never generated
                const len = v.getUint32(off), type = v.getUint8(off + 4);
                if (type === 4) throw Error("This world saves chunks with LZ4, which the browser can't read. Set region-file-compression=deflate in server.properties, or open the world in single player once.");
                if (!zip[type] && type !== 3) throw Error(`Chunk ${cx} ${cz} in r.${rx}.${rz}.mca uses an unknown compression (${type}).`);
                let data = buf.slice(off + 5, off + 4 + len);
                if (zip[type]) data = await new Response(new Blob([data]).stream().pipeThrough(new DecompressionStream(zip[type]))).arrayBuffer();
                const tag = new Reader(data).read(), level = tag.Level || tag; // before 1.18 everything sat under Level
                for (const t of level.block_entities || level.TileEntities || []) {
                    if (t.x >= b.x0 && t.x <= b.x1 && t.y >= b.y0 && t.y <= b.y1 && t.z >= b.z0 && t.z <= b.z1) add(out, t.x, t.y, t.z, t.id, t);
                }
            }
        }
        return out;
    }

    function check(containers, entries) {
        const key = (x, y, z) => x + ',' + y + ',' + z;
        const at = new Map(containers.map((c) => [key(c.x, c.y, c.z), c]));

        // per item id, entries with the longest need list first: the first whose needs all appear wins, need: [] comes last
        const byId = new Map();
        entries.forEach((e, i) => { if (e.id) { if (!byId.has(e.id)) byId.set(e.id, []); byId.get(e.id).push(i); } });
        for (const l of byId.values()) l.sort((a, b) => entries[b].need.length - entries[a].need.length);
        const cache = new Map();
        function match(it) {
            const k = it.id + '\n' + it.data;
            let m = cache.get(k);
            if (m === undefined) {
                m = -1;
                for (const i of byId.get(it.id) || []) if (entries[i].need.every((n) => it.data.includes(n))) { m = i; break; }
                cache.set(k, m);
            }
            return m;
        }
        // items -> [[match, id, count]], biggest count first
        function sum(list) {
            const m = new Map();
            for (const it of list) {
                const mt = match(it), k = mt + '\n' + it.id, a = m.get(k);
                if (a) a[2] += it.count; else m.set(k, [mt, it.id, it.count]);
            }
            return [...m.values()].sort((a, b) => b[2] - a[2]);
        }

        const used = new Set(), counts = { complete: 0, partial: 0, wrong: 0, missing: 0 };
        const chests = entries.map((e, i) => {
            const p = layoutPos(i), kw = key(p.x, p.y, p.z), ke = key(p.x + 1, p.y, p.z), w = at.get(kw), east = at.get(ke);
            used.add(kw); used.add(ke);
            const list = (w ? w.items : []).concat(east ? east.items : []); // west slots 0-26, east 27-53
            const items = sum(list), wrong = items.filter((a) => a[0] !== i);
            const n = items.reduce((t, a) => t + (a[0] === i ? a[2] : 0), 0);
            const found = !w && !east ? 'none' : list.length ? 'chest' : 'empty';
            const s = found !== 'chest' ? 'missing' : wrong.length ? 'wrong'
                : list.length === 54 && list.every((it) => it.count === e.stack) ? 'complete' : 'partial';
            counts[s]++;
            return { s, n, of: 54 * e.stack, found, slots: list.length, items, wrong };
        });
        const extra = containers.filter((c) => !used.has(key(c.x, c.y, c.z))).map((c) => ({ x: c.x, y: c.y, z: c.z, id: c.id, items: sum(c.items) }));
        return { chests, extra, counts };
    }

    const api = { readContainers, readRegion, regionsFor, check, layoutPos, ANCHOR };
    if (typeof module !== 'undefined') module.exports = api; else root.HallwayCheck = api;
})(typeof window !== 'undefined' ? window : globalThis);
