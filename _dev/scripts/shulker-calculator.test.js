// Tests for the Shulker Schematic Calculator parser (minecraft/tools/shulker-calculator/schematic.js).
//   usage: node _dev/scripts/shulker-calculator.test.js   (Node 18+)
// Builds .litematic, .schem and structure .nbt files in memory, gzips them like the game does, and checks the item counts.
const assert = require('assert');
const zlib = require('zlib');
const S = require('../../minecraft/tools/shulker-calculator/schematic.js');

/* ---------- a tiny NBT writer: values are [type, value] ---------- */
const T = { byte: 1, short: 2, int: 3, byteArray: 7, string: 8, list: 9, compound: 10, longArray: 12 };
function payload([t, v]) {
    switch (t) {
        case 'byte': return Buffer.from([v & 255]);
        case 'short': { const b = Buffer.alloc(2); b.writeInt16BE(v); return b; }
        case 'int': { const b = Buffer.alloc(4); b.writeInt32BE(v); return b; }
        case 'string': { const s = Buffer.from(v), b = Buffer.alloc(2); b.writeUInt16BE(s.length); return Buffer.concat([b, s]); }
        case 'byteArray': { const b = Buffer.alloc(4); b.writeInt32BE(v.length); return Buffer.concat([b, Buffer.from(v)]); }
        case 'longArray': { const b = Buffer.alloc(4 + v.length * 8); b.writeInt32BE(v.length); v.forEach((x, i) => b.writeBigInt64BE(BigInt.asIntN(64, x), 4 + i * 8)); return b; }
        case 'list': { const [et, a] = v, h = Buffer.alloc(5); h[0] = T[et]; h.writeInt32BE(a.length, 1); return Buffer.concat([h, ...a.map((x) => payload([et, x]))]); }
        case 'compound': return Buffer.concat([...Object.entries(v).map(([k, x]) => Buffer.concat([Buffer.from([T[x[0]]]), payload(['string', k]), payload(x)])), Buffer.from([0])]);
    }
}
const file = (rootTag) => { const b = zlib.gzipSync(Buffer.concat([Buffer.from([10]), payload(['string', '']), payload(rootTag)])); return b.buffer.slice(b.byteOffset, b.byteOffset + b.length); };
const C = (o) => ['compound', o], I = (v) => ['int', v], Str = (v) => ['string', v];

// pack values the way Litematica does: tightly, a value may straddle two longs
function packTight(values, bits) {
    const longs = new Array(Math.ceil(values.length * bits / 64)).fill(0n);
    values.forEach((v, i) => {
        const bit = i * bits, w = Math.floor(bit / 64), o = bit % 64;
        longs[w] |= (BigInt(v) << BigInt(o)) & ((1n << 64n) - 1n);
        if (o + bits > 64) longs[w + 1] |= BigInt(v) >> BigInt(64 - o);
    });
    return longs;
}
const varints = (values) => values.flatMap((v) => { const out = []; while (v > 127) { out.push((v & 127) | 128); v >>>= 7; } out.push(v); return out; });

(async () => {
    // .litematic: 5 palette entries = 3 bits, so values at index 21 and 42 cross a long boundary
    const pal = [
        C({ Name: Str('minecraft:air') }),
        C({ Name: Str('minecraft:stone') }),
        C({ Name: Str('minecraft:oak_door'), Properties: C({ half: Str('upper') }) }),
        C({ Name: Str('minecraft:oak_door'), Properties: C({ half: Str('lower') }) }),
        C({ Name: Str('minecraft:oak_slab'), Properties: C({ type: Str('double') }) }),
    ];
    const values = Array.from({ length: 64 }, (_, i) => i % 5);
    const per = (v) => values.filter((x) => x === v).length;
    const lit = await S.readFile(file(C({
        Metadata: C({ EnclosingSize: C({ x: I(4), y: I(4), z: I(4) }) }),
        Regions: C({ main: C({
            Size: C({ x: I(4), y: I(-4), z: I(4) }),
            BlockStatePalette: ['list', ['compound', pal.map((p) => p[1])]],
            BlockStates: ['longArray', packTight(values, 3)],
        }) }),
    })));
    assert.deepStrictEqual(lit.dims, [4, 4, 4]);
    assert.strictEqual(lit.counts.get('minecraft:stone'), per(1));
    assert.strictEqual(lit.counts.get('minecraft:oak_door'), per(3), 'upper door halves are not counted');
    assert.strictEqual(lit.counts.get('minecraft:oak_slab'), per(4) * 2, 'a double slab is two slabs');
    assert.ok(!lit.counts.has('minecraft:air'));

    // .schem (Sponge v2): 200 palette entries, so indexes above 127 take two varint bytes
    const palette = {};
    for (let i = 0; i < 200; i++) palette['minecraft:block_' + i] = I(i);
    palette['minecraft:block_0'] = I(0);
    palette['minecraft:wall_torch[facing=north]'] = I(200);
    const blocks = [199, 5, 200, 150, 199];
    const schem = await S.readFile(file(C({ Width: ['short', 5], Height: ['short', 1], Length: ['short', 1], Palette: C(palette), BlockData: ['byteArray', varints(blocks)] })));
    assert.deepStrictEqual(schem.dims, [5, 1, 1]);
    assert.strictEqual(schem.counts.get('minecraft:block_199'), 2);
    assert.strictEqual(schem.counts.get('minecraft:torch'), 1, 'wall torches become torches');

    // structure .nbt
    const nbt = await S.readFile(file(C({
        size: ['list', ['int', [2, 1, 1]]],
        palette: ['list', ['compound', [{ Name: Str('minecraft:redstone_wire') }, { Name: Str('minecraft:potted_poppy') }]]],
        blocks: ['list', ['compound', [{ state: I(0) }, { state: I(1) }]]],
    })));
    assert.strictEqual(nbt.counts.get('minecraft:redstone'), 1);
    assert.strictEqual(nbt.counts.get('minecraft:flower_pot'), 1);
    assert.strictEqual(nbt.counts.get('minecraft:poppy'), 1);

    // item names and stack sizes
    assert.deepStrictEqual(S.itemsFor(S.parseState('minecraft:white_bed[part=head]')), []);
    assert.deepStrictEqual(S.itemsFor(S.parseState('minecraft:sea_pickle[pickles=3]')), [['minecraft:sea_pickle', 3]]);
    assert.deepStrictEqual(S.itemsFor(S.parseState('minecraft:oak_wall_hanging_sign')), [['minecraft:oak_hanging_sign', 1]]);
    assert.deepStrictEqual(S.itemsFor(S.parseState('minecraft:cobblestone_wall')), [['minecraft:cobblestone_wall', 1]]);
    for (const [id, n] of [['minecraft:stone', 64], ['minecraft:item_frame', 64], ['minecraft:oak_sign', 16], ['minecraft:bucket', 16], ['minecraft:water_bucket', 1], ['minecraft:red_bed', 1], ['minecraft:shulker_box', 1], ['minecraft:diamond_pickaxe', 1], ['minecraft:tipped_arrow', 64]]) {
        assert.strictEqual(S.stackSize(id), n, id);
    }

    // fit: one stone block fits 27 stacks of 64, not 27
    assert.strictEqual(S.fit(new Map([['minecraft:stone', 1]]), 27).copies, 27 * 64);
    const big = S.fit(new Map([['minecraft:stone', 64 * 30]]), 27);
    assert.strictEqual(big.copies, 0);
    assert.strictEqual(big.perCopy, 30);

    console.log('shulker-calculator: all tests passed');
})().catch((e) => { console.error(e); process.exit(1); });
