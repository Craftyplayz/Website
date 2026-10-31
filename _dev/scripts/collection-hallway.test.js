// Tests for the collection hallway chest check (minecraft/projects/collection/hallway/check.js).
//   usage: node _dev/scripts/collection-hallway.test.js   (Node 18+)
// Builds the same chests as Sponge v2 and v3 .schem, .litematic and structure .nbt files, gzips them like the game does,
// checks them against a small layout, and writes them to _dev/samples/collection-hallway/ to drop on the page by hand.
const assert = require('assert');
const fs = require('fs');
const path = require('path');
const zlib = require('zlib');
const H = require('../../minecraft/projects/collection/hallway/check.js');

/* ---------- a tiny NBT writer: values are [type, value] ---------- */
const T = { byte: 1, short: 2, int: 3, long: 4, byteArray: 7, string: 8, list: 9, compound: 10, intArray: 11 };
function payload([t, v]) {
    switch (t) {
        case 'byte': return Buffer.from([v & 255]);
        case 'short': { const b = Buffer.alloc(2); b.writeInt16BE(v); return b; }
        case 'int': { const b = Buffer.alloc(4); b.writeInt32BE(v); return b; }
        case 'long': { const b = Buffer.alloc(8); b.writeBigInt64BE(BigInt(v)); return b; }
        case 'string': { const s = Buffer.from(v), b = Buffer.alloc(2); b.writeUInt16BE(s.length); return Buffer.concat([b, s]); }
        case 'byteArray': { const b = Buffer.alloc(4); b.writeInt32BE(v.length); return Buffer.concat([b, Buffer.from(v)]); }
        case 'intArray': { const b = Buffer.alloc(4 + v.length * 4); b.writeInt32BE(v.length); v.forEach((x, i) => b.writeInt32BE(x, 4 + i * 4)); return b; }
        case 'list': { const [et, a] = v, h = Buffer.alloc(5); h[0] = T[et]; h.writeInt32BE(a.length, 1); return Buffer.concat([h, ...a.map((x) => payload([et, x]))]); }
        case 'compound': return Buffer.concat([...Object.entries(v).map(([k, x]) => Buffer.concat([Buffer.from([T[x[0]]]), payload(['string', k]), payload(x)])), Buffer.from([0])]);
    }
}
const file = (rootTag) => { const b = zlib.gzipSync(Buffer.concat([Buffer.from([10]), payload(['string', '']), payload(rootTag)])); return b.buffer.slice(b.byteOffset, b.byteOffset + b.length); };
const C = (o) => ['compound', o], I = (v) => ['int', v], Str = (v) => ['string', v], IA = (v) => ['intArray', v], B = (v) => ['byte', v], S16 = (v) => ['short', v];
const list = (items) => ['list', ['compound', items.map((x) => x[1])]];

// items in both shapes: before 1.20.5 {Slot, id, Count: byte, tag}, after {Slot, id, count: int, components}
const oldItem = (slot, id, n, tag) => C({ Slot: B(slot), id: Str('minecraft:' + id), Count: B(n), ...(tag ? { tag } : {}) });
const newItem = (slot, id, n, components) => C({ Slot: B(slot), id: Str('minecraft:' + id), count: I(n), ...(components ? { components } : {}) });
const potion = (p) => C({ 'minecraft:potion_contents': C({ potion: Str('minecraft:' + p) }) });
const RED = 11743532, redRocket = C({ Fireworks: C({ Flight: B(1), Explosions: list([C({ Type: B(0), Colors: IA([RED]) })]) }) });
const range = (n, f) => Array.from({ length: n }, (_, i) => f(i));

const entries = [
    { name: 'Acacia Boat', id: 'acacia_boat', need: [], stack: 1 },
    { name: 'Acacia Button', id: 'acacia_button', need: [], stack: 64 },
    { name: 'Arrow of Healing', id: 'tipped_arrow', need: [':"minecraft:healing"'], stack: 64 },
    { name: 'Arrow of Healing II', id: 'tipped_arrow', need: [':"minecraft:strong_healing"'], stack: 64 },
    { name: 'Firework Rocket', id: 'firework_rocket', need: [], stack: 64 },
    { name: 'Firework Rocket Red', id: 'firework_rocket', need: ['[' + RED], stack: 64 },
    { name: 'Oak Log', id: 'oak_log', need: [], stack: 64 },
    { name: 'Stone', id: 'stone', need: [], stack: 64 },
];
const L = (i, east) => { const p = H.layoutPos(i); return [p.x + (east ? 1 : 0), p.y, p.z]; };

// the world: [x, y, z], block entity id ('' = none stored), items
const world = [
    [L(0), 'minecraft:chest', range(27, (i) => oldItem(i, 'acacia_boat', 1))],                     // complete: 54 boats over both halves
    [L(0, 1), 'minecraft:chest', range(27, (i) => newItem(i, 'acacia_boat', 1))],
    [L(1), 'minecraft:chest', [...range(10, (i) => newItem(i, 'acacia_button', 64)), newItem(10, 'acacia_button', 5)]], // partial
    [L(1, 1), 'minecraft:chest', []],
    [L(2), 'minecraft:trapped_chest', [...range(5, (i) => newItem(i, 'tipped_arrow', 64, potion('healing'))), newItem(5, 'tipped_arrow', 3, potion('strong_healing'))]], // wrong
    [L(2, 1), 'minecraft:trapped_chest', [oldItem(0, 'stone', 64, C({ RepairCost: ['long', 1n << 40n] }))]],
    [L(3), 'minecraft:chest', []],                                                                   // empty
    [L(4), 'minecraft:furnace', [oldItem(0, 'coal', 64)]],                                           // not a chest, so none
    [L(5), '', [oldItem(0, 'firework_rocket', 64, redRocket), oldItem(1, 'firework_rocket', 64, redRocket)]], // partial, no id stored
    [[-640, 36, -640], 'minecraft:chest', [newItem(0, 'firework_rocket', 16), newItem(1, 'dirt', 64)]], // extra, outside the layout
];
const A = H.ANCHOR, min = [0, 1, 2].map((k) => Math.min(...world.map((b) => b[0][k]))), max = [0, 1, 2].map((k) => Math.max(...world.map((b) => b[0][k])));
const size = [0, 1, 2].map((k) => max[k] - min[k] + 1), rel = (p, o) => p.map((v, k) => v - o[k]);
const items = (b) => list(b[2]);

// Sponge needs a palette and block data; all air is enough here
const spongeBlocks = { Width: S16(size[0]), Height: S16(size[1]), Length: S16(size[2]) };
const spongeData = ['byteArray', new Array(size[0] * size[1] * size[2]).fill(0)];

// origin = where the copy was made; the chests always sit at the same place relative to it
function spongeV2(origin) {
    const we = rel(min, A);
    return C({ Schematic: C({
        Version: I(2), DataVersion: I(3953), ...spongeBlocks,
        Offset: IA(we.map((v, k) => v + origin[k])),
        Metadata: C({ WEOffsetX: I(we[0]), WEOffsetY: I(we[1]), WEOffsetZ: I(we[2]) }),
        PaletteMax: I(1), Palette: C({ 'minecraft:air': I(0) }), BlockData: spongeData,
        BlockEntities: list(world.map((b) => C({ Pos: IA(rel(b[0], min)), ...(b[1] ? { Id: Str(b[1]) } : {}), Items: items(b) }))),
    }) });
}
const spongeV3 = () => C({ Schematic: C({
    Version: I(3), DataVersion: I(3953), ...spongeBlocks,
    Offset: IA(rel(min, A)),
    Metadata: C({ WorldEdit: C({ Origin: IA(A) }) }),
    Blocks: C({
        Palette: C({ 'minecraft:air': I(0) }), Data: spongeData,
        BlockEntities: list(world.map((b) => C({ Pos: IA(rel(b[0], min)), ...(b[1] ? { Id: Str(b[1]) } : {}), Data: C({ Items: items(b) }) }))),
    }),
}) });

// Litematica: two regions; "east" is given from its far corner with a negative size
function litematic() {
    const xyz = (v) => C({ x: I(v[0]), y: I(v[1]), z: I(v[2]) });
    const inA = (b) => b[0][0] >= -629 && b[0][0] <= -628;
    const region = (bs, pos, sz) => {
        const m = pos.map((p, k) => p + (sz[k] < 0 ? sz[k] + 1 : 0)); // region min, relative to the schematic origin
        return C({ Position: xyz(pos), Size: xyz(sz), TileEntities: list(bs.map((b) => { const r = rel(rel(b[0], A), m); return C({ x: I(r[0]), y: I(r[1]), z: I(r[2]), ...(b[1] ? { id: Str(b[1]) } : {}), Items: items(b) }); })) });
    };
    const bA = world.filter(inA), bB = world.filter((b) => !inA(b));
    const lo = (bs) => [0, 1, 2].map((k) => Math.min(...bs.map((b) => b[0][k] - A[k]))), hi = (bs) => [0, 1, 2].map((k) => Math.max(...bs.map((b) => b[0][k] - A[k])));
    const aLo = lo(bA), aHi = hi(bA), bLo = lo(bB), bHi = hi(bB);
    return C({
        MinecraftDataVersion: I(3953), Version: I(6),
        Regions: C({
            west: region(bA, aLo, aHi.map((v, k) => v - aLo[k] + 1)),
            east: region(bB, bHi, bLo.map((v, k) => v - bHi[k] - 1)),
        }),
    });
}

const structure = () => C({
    DataVersion: I(3953), size: ['list', ['int', size]],
    palette: list([C({ Name: Str('minecraft:chest') }), C({ Name: Str('minecraft:furnace') })]),
    blocks: list(world.map((b) => C({ pos: ['list', ['int', rel(b[0], A)]], state: I(b[1] === 'minecraft:furnace' ? 1 : 0), nbt: C({ ...(b[1] ? { id: Str(b[1]) } : {}), Items: items(b) }) }))),
});

function expectLayout(r, label) {
    const c = r.chests, m = (i, s, found, n, slots) => {
        assert.strictEqual(c[i].s, s, `${label} chest ${i} state`);
        assert.strictEqual(c[i].found, found, `${label} chest ${i} found`);
        if (n !== undefined) assert.strictEqual(c[i].n, n, `${label} chest ${i} n`);
        if (slots !== undefined) assert.strictEqual(c[i].slots, slots, `${label} chest ${i} slots`);
        assert.strictEqual(c[i].of, 54 * entries[i].stack);
    };
    m(0, 'complete', 'chest', 54, 54);
    m(1, 'partial', 'chest', 645, 11);
    m(2, 'wrong', 'chest', 320, 7);
    assert.deepStrictEqual(c[2].wrong, [[7, 'stone', 64], [3, 'tipped_arrow', 3]], label + ' stone belongs in chest 7, the strong healing arrow in chest 3');
    m(3, 'missing', 'empty', 0, 0);
    m(4, 'missing', 'none', 0, 0);
    m(5, 'partial', 'chest', 128, 2);
    m(6, 'missing', 'none');
    m(7, 'missing', 'none');
    assert.deepStrictEqual(r.extra, [{ x: -640, y: 36, z: -640, id: 'minecraft:chest', items: [[-1, 'dirt', 64], [4, 'firework_rocket', 16]] }]);
    assert.deepStrictEqual(r.counts, { complete: 1, partial: 2, wrong: 1, missing: 4 });
}

(async () => {
    const out = path.join(__dirname, '..', 'samples', 'collection-hallway');
    fs.mkdirSync(out, { recursive: true });
    const files = [['sample-v2.schem', 'sponge2', spongeV2(A)], ['sample-v3.schem', 'sponge3', spongeV3()], ['sample.litematic', 'litematic', litematic()], ['sample.nbt', 'structure', structure()]];
    for (const [name, format, tag] of files) {
        const buf = file(tag);
        fs.writeFileSync(path.join(out, name), Buffer.from(buf));
        const r = await H.readContainers(buf);
        assert.strictEqual(r.format, format, name);
        assert.strictEqual(r.note, '', name + ' note');
        assert.strictEqual(r.containers.length, world.length - 1, name + ': the furnace is skipped');
        const stone = r.containers.find((c) => c.items.some((it) => it.id === 'stone')).items[0];
        assert.deepStrictEqual(JSON.parse(stone.data), { RepairCost: String(1n << 40n) }, 'longs become strings');
        assert.ok(r.containers.find((c) => c.x === -626 && c.y === 38).items[0].data.includes(`"Colors":[${RED}]`), name + ': int arrays become plain arrays');
        expectLayout(H.check(r.containers, entries), name);
        console.log(`collection-hallway: ${format} (${name}) passed`);
    }

    // copied somewhere else: positions still use the anchor, the note says where
    const moved = await H.readContainers(file(spongeV2([-630, 36, -626])));
    assert.strictEqual(moved.note, 'The file says it was copied at -630 36 -626; positions use -636 36 -626.');
    expectLayout(H.check(moved.containers, entries), 'moved v2');
    console.log('collection-hallway: v2 copied at another spot gives a note');

    await assert.rejects(H.readContainers(file(C({ Hello: Str('world') }))), { message: "This file isn't a .schem, .litematic or structure .nbt schematic." });
    await assert.rejects(H.readContainers(new TextEncoder().encode('not nbt at all').buffer), /isn't a \.schem/);
    console.log('collection-hallway: unknown files are refused; all tests passed');
})().catch((e) => { console.error(e); process.exit(1); });
