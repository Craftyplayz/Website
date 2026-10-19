// Shulker Schematic Calculator: reads .litematic, .schem, .schematic and structure .nbt files and turns
// the blocks into an item list. No DOM here, so _dev/scripts/shulker-calculator.test.js can run it in Node.
(function (root) {
    class NBTReader {
        constructor(buf) { this.v = new DataView(buf); this.p = 0; this.decoder = new TextDecoder(); }
        u8() { return this.v.getUint8(this.p++); }
        i32() { const n = this.v.getInt32(this.p); this.p += 4; return n; }
        len() { const n = this.i32(); if (n < 0 || n > 1e8) throw Error('Invalid array length'); return n; }
        str() { const n = this.v.getUint16(this.p); this.p += 2; const s = this.decoder.decode(new Uint8Array(this.v.buffer, this.p, n)); this.p += n; return s; }
        payload(t) {
            const v = this.v;
            switch (t) {
                case 1: return v.getInt8(this.p++);
                case 2: { const n = v.getInt16(this.p); this.p += 2; return n; }
                case 3: return this.i32();
                case 4: { const n = v.getBigInt64(this.p); this.p += 8; return n; }
                case 5: { const n = v.getFloat32(this.p); this.p += 4; return n; }
                case 6: { const n = v.getFloat64(this.p); this.p += 8; return n; }
                case 7: { const n = this.len(), a = new Uint8Array(v.buffer, this.p, n); this.p += n; return a; }
                case 8: return this.str();
                case 9: { const et = this.u8(), n = this.len(), a = []; for (let i = 0; i < n; i++) a.push(this.payload(et)); return a; }
                case 10: { const o = {}; for (let et; (et = this.u8()) !== 0;) { const name = this.str(); o[name] = this.payload(et); } return o; }
                case 11: { const n = this.len(), a = new Int32Array(n); for (let i = 0; i < n; i++) a[i] = this.i32(); return a; }
                // long arrays become 32-bit words, low half first, so packed block data reads as one little-endian bit stream
                case 12: { const n = this.len(), w = new Uint32Array(n * 2); for (let i = 0; i < n; i++) { w[i * 2 + 1] = v.getUint32(this.p); w[i * 2] = v.getUint32(this.p + 4); this.p += 8; } return w; }
                default: throw Error('Unknown NBT tag ' + t);
            }
        }
        read() { if (this.u8() !== 10) throw Error('File does not contain a root NBT compound'); this.str(); return this.payload(10); }
    }

    // "minecraft:oak_door[half=upper,facing=east]" -> { id, props }
    function parseState(s) {
        const i = s.indexOf('[');
        if (i < 0) return { id: s, props: {} };
        const props = {};
        for (const kv of s.slice(i + 1, -1).split(',')) { const [k, v] = kv.split('='); props[k] = v; }
        return { id: s.slice(0, i), props };
    }
    const fromCompound = (p) => ({ id: p.Name || p.name || 'unknown:block', props: p.Properties || p.properties || {} });

    // blocks that are never an item, and blocks whose item has a different name
    const NO_ITEM = new Set(['air', 'cave_air', 'void_air', 'water', 'lava', 'fire', 'soul_fire', 'bubble_column', 'nether_portal', 'end_portal', 'end_gateway', 'moving_piston', 'piston_head']);
    const RENAME = {
        redstone_wire: 'redstone', tripwire: 'string', cocoa: 'cocoa_beans', carrots: 'carrot', potatoes: 'potato',
        beetroots: 'beetroot_seeds', wheat: 'wheat_seeds', melon_stem: 'melon_seeds', attached_melon_stem: 'melon_seeds',
        pumpkin_stem: 'pumpkin_seeds', attached_pumpkin_stem: 'pumpkin_seeds', sweet_berry_bush: 'sweet_berries',
        cave_vines: 'glow_berries', cave_vines_plant: 'glow_berries', kelp_plant: 'kelp', weeping_vines_plant: 'weeping_vines',
        twisting_vines_plant: 'twisting_vines', big_dripleaf_stem: 'big_dripleaf', bamboo_sapling: 'bamboo',
        torchflower_crop: 'torchflower_seeds', pitcher_crop: 'pitcher_pod', powder_snow: 'powder_snow_bucket',
        water_cauldron: 'cauldron', lava_cauldron: 'cauldron', powder_snow_cauldron: 'cauldron', tall_seagrass: 'seagrass',
        potted_azalea_bush: 'azalea', potted_flowering_azalea_bush: 'flowering_azalea',
    };

    // one block state -> [[itemId, count], ...]
    function itemsFor({ id, props }) {
        const [ns, name] = id.includes(':') ? id.split(':') : ['minecraft', id];
        if (ns !== 'minecraft') return [[id, 1]];
        if (NO_ITEM.has(name) || props.half === 'upper' || props.part === 'head') return [];
        if (name.startsWith('potted_')) return [['minecraft:flower_pot', 1], ['minecraft:' + (RENAME[name] || name.slice(7)), 1]];
        const item = RENAME[name] || name.replace(/(^|_)wall_(?=torch|sign|hanging_sign|banner|head|skull|fan)/, '$1');
        const n = props.type === 'double' ? 2 : Number(props.candles || props.pickles || props.eggs || props.layers || props.flower_amount || props.segment_amount) || 1;
        return [['minecraft:' + item, n]];
    }

    // count of each palette index -> Map(itemId -> count)
    function toItems(states, indexCounts) {
        const counts = new Map();
        indexCounts.forEach((n, i) => {
            if (!n) return;
            for (const [item, k] of itemsFor(states[i] || { id: 'unknown:block', props: {} })) counts.set(item, (counts.get(item) || 0) + n * k);
        });
        return counts;
    }

    function parseLitematic(rootTag) {
        const all = new Map();
        const regions = Object.values(rootTag.Regions || rootTag.regions);
        for (const reg of regions) {
            const s = reg.Size, total = Math.abs(s.x * s.y * s.z), pal = reg.BlockStatePalette, words = reg.BlockStates;
            if (!total || !pal || !words) continue;
            // Litematica packs values tightly: one can start in one long and end in the next
            const bits = Math.max(2, Math.ceil(Math.log2(pal.length))), mask = bits === 32 ? 0xFFFFFFFF : (1 << bits) - 1;
            if (words.length * 32 < total * bits) throw Error('Block data is shorter than the region size.');
            const idx = new Array(pal.length).fill(0);
            for (let i = 0, bit = 0; i < total; i++, bit += bits) {
                const w = bit >>> 5, o = bit & 31;
                let val = words[w] >>> o;
                if (o + bits > 32) val |= words[w + 1] << (32 - o);
                const k = val & mask;
                if (k < idx.length) idx[k]++;
            }
            for (const [k, v] of toItems(pal.map(fromCompound), idx)) all.set(k, (all.get(k) || 0) + v);
        }
        const e = rootTag.Metadata && rootTag.Metadata.EnclosingSize;
        const dims = e ? [e.x, e.y, e.z] : regions.length === 1 ? ['x', 'y', 'z'].map((k) => Math.abs(regions[0].Size[k])) : null;
        return { dims, counts: all, regions: regions.length };
    }

    function parseSponge(rootTag) {
        const s = rootTag.Schematic || rootTag, blocks = s.Blocks && s.Blocks.Palette ? s.Blocks : s;
        const dims = [s.Width & 0xFFFF, s.Height & 0xFFFF, s.Length & 0xFFFF], total = dims[0] * dims[1] * dims[2];
        const pal = blocks.Palette, data = blocks.BlockData || blocks.Data;
        if (!total || !pal || !data) throw Error('No Sponge block palette or block data found.');
        const states = [];
        for (const [name, i] of Object.entries(pal)) states[i] = parseState(name);
        const idx = new Array(states.length).fill(0);
        let value = 0, shift = 0, seen = 0;
        for (let i = 0; i < data.length && seen < total; i++) {
            const b = data[i];
            value |= (b & 127) << shift;
            if (b & 128) { shift += 7; if (shift > 28) throw Error('Invalid block data.'); continue; }
            if (value < idx.length) idx[value]++;
            seen++; value = 0; shift = 0;
        }
        if (seen < total) throw Error('Block data ended early; the file may use an unsupported schematic format.');
        return { dims, counts: toItems(states, idx) };
    }

    // vanilla structure block files
    function parseStructure(rootTag) {
        const pal = rootTag.palette || (rootTag.palettes && rootTag.palettes[0]);
        if (!pal) throw Error('Structure file has no palette.');
        const idx = new Array(pal.length).fill(0);
        for (const b of rootTag.blocks) idx[b.state]++;
        return { dims: rootTag.size, counts: toItems(pal.map(fromCompound), idx) };
    }

    // pre-1.13 MCEdit files only store numeric IDs; the common ones are named, the rest stay as legacy:block_<id>
    const LEGACY = { 1: 'stone', 2: 'grass_block', 3: 'dirt', 4: 'cobblestone', 5: 'oak_planks', 12: 'sand', 13: 'gravel', 17: 'oak_log', 20: 'glass', 35: 'white_wool', 41: 'gold_block', 42: 'iron_block', 45: 'bricks', 49: 'obsidian', 54: 'chest', 57: 'diamond_block', 58: 'crafting_table', 61: 'furnace', 65: 'ladder', 67: 'cobblestone_stairs', 76: 'redstone_torch' };
    function parseLegacy(rootTag) {
        const s = rootTag.Schematic || rootTag, counts = new Map();
        for (const b of s.Blocks) if (b) { const id = LEGACY[b] ? 'minecraft:' + LEGACY[b] : 'legacy:block_' + b; counts.set(id, (counts.get(id) || 0) + 1); }
        return { dims: [s.Width, s.Height, s.Length], counts, legacy: true };
    }

    function analyze(rootTag) {
        const s = rootTag.Schematic || rootTag;
        if (rootTag.Regions || rootTag.regions) return parseLitematic(rootTag);
        if (s.Palette || (s.Blocks && s.Blocks.Palette)) return parseSponge(rootTag);
        if (rootTag.blocks && (rootTag.palette || rootTag.palettes)) return parseStructure(rootTag);
        if (s.Blocks) return parseLegacy(rootTag);
        throw Error('Unrecognised format. Use a .litematic, Sponge .schem, legacy .schematic or structure .nbt file.');
    }

    async function readFile(buf) {
        const u = new Uint8Array(buf);
        if (u[0] === 0x1f && u[1] === 0x8b) buf = await new Response(new Blob([buf]).stream().pipeThrough(new DecompressionStream('gzip'))).arrayBuffer();
        return analyze(new NBTReader(buf).read());
    }

    // Java Edition 1.21 stack sizes
    const STACK16 = new Set(['ender_pearl', 'snowball', 'egg', 'blue_egg', 'brown_egg', 'bucket', 'honey_bottle', 'armor_stand']);
    const STACK1 = new Set(['totem_of_undying', 'saddle', 'cake', 'elytra', 'shield', 'trident', 'bow', 'crossbow', 'fishing_rod', 'flint_and_steel', 'shears', 'carrot_on_a_stick', 'warped_fungus_on_a_stick', 'mace', 'brush', 'spyglass', 'goat_horn', 'enchanted_book', 'written_book', 'writable_book', 'knowledge_book', 'suspicious_stew', 'mushroom_stew', 'rabbit_stew', 'beetroot_soup', 'potion', 'splash_potion', 'lingering_potion', 'debug_stick', 'wolf_armor']);
    function stackSize(id) {
        const name = id.replace(/^minecraft:/, '');
        if (STACK1.has(name) || /_(sword|pickaxe|axe|shovel|hoe|helmet|chestplate|leggings|boots|bed|boat|raft|horse_armor|bucket|banner_pattern)$|minecart$|shulker_box$|bundle$|^music_disc_/.test(name)) return 1;
        if (STACK16.has(name) || /_(sign|banner)$/.test(name)) return 16;
        return 64;
    }

    // how many whole copies fit in `slots`, packing each item into its own stacks
    function fit(counts, slots) {
        const req = [...counts].map(([id, n]) => [n, stackSize(id)]);
        const used = (copies) => req.reduce((a, [n, s]) => a + Math.ceil(n * copies / s), 0);
        let lo = 0, hi = slots * 64 + 1; // a copy needs at least one item, so this is the most that could fit
        while (lo + 1 < hi) { const mid = (lo + hi) >> 1; if (used(mid) <= slots) lo = mid; else hi = mid; }
        return { copies: lo, used: used(lo), perCopy: used(1) };
    }

    const api = { NBTReader, analyze, readFile, itemsFor, parseState, stackSize, fit };
    if (typeof module !== 'undefined') module.exports = api; else root.Schematic = api;
})(typeof window !== 'undefined' ? window : globalThis);
