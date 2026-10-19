// Tests for the Storage Sorter logic (minecraft/tools/storage-sorter/sorter.js).
//   usage: node _dev/scripts/storage-sorter.test.js   (Node 18+)
// Checks every name in items.txt turns into an item ID that has a texture in Item List, plus sheet matching and /give.
const assert = require('assert');
const fs = require('fs');
const path = require('path');
const S = require('../../minecraft/tools/storage-sorter/sorter.js');
const { stackSize } = require('../../minecraft/tools/shulker-calculator/schematic.js');

const tools = path.join(__dirname, '..', '..', 'minecraft', 'tools');
const ids = new Set(fs.readdirSync(path.join(tools, 'item-list')).filter((f) => /^minecraft_.*\.png$/.test(f)).map((f) => f.slice(10, -4)));
const names = fs.readFileSync(path.join(tools, 'storage-sorter', 'items.txt'), 'utf8').split(/\r?\n/).map((s) => s.trim()).filter(Boolean);

// every survival item gets a real ID (items newer than Item List's textures are allowed through)
const NEWER = ['Golden Dandelion'];
const bad = names.filter((n) => !NEWER.includes(n) && !ids.has(S.nameToId(n, ids).slice(10)));
assert.deepStrictEqual(bad, [], 'names without a matching item ID');
assert.strictEqual(S.nameToId('Block of Iron', ids), 'minecraft:iron_block');
assert.strictEqual(S.nameToId('Raw Iron', ids), 'minecraft:raw_iron');
assert.strictEqual(S.nameToId('Raw Beef', ids), 'minecraft:beef');

// sheet matching ignores case, quotes, extra columns and the wiki's [JE]/[BE] tags
const r = S.compare(['Dirt Path[BE]', 'Stone', 'Spectral Arrow‌ [JE]', 'Oak Planks'], 'stone\tBuilding\n"Dirt Path",Misc\nSTONE\n');
assert.deepStrictEqual(r.unsorted, ['Oak Planks', 'Spectral Arrow‌ [JE]']);
assert.deepStrictEqual(r.duplicates, [['stone', 2]]);
assert.strictEqual(r.matched, 2);

// /give: real stack sizes, 27 items per box, a new chest past 27 boxes
const cmds = S.giveCommands(['Stone', 'Water Bucket', 'Oak Sign'], ids, stackSize);
assert.strictEqual(cmds.length, 1);
assert.ok(cmds[0].includes('{id:"minecraft:stone",count:64}') && cmds[0].includes('{id:"minecraft:water_bucket",count:1}') && cmds[0].includes('{id:"minecraft:oak_sign",count:16}'));
const many = S.giveCommands(Array(27 * 28).fill('Stone'), ids, stackSize);
assert.strictEqual(many.length, 2, '28 boxes need two chests');
assert.ok(S.giveCommands(names, ids, stackSize).every((c) => c.length <= 32500));

console.log('storage-sorter: all tests passed');
