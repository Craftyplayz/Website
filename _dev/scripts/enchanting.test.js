// Tests for the Enchanting Guide solver: node _dev/scripts/enchanting.test.js
const assert = require('assert');
const E = require('../../minecraft/tools/enchanting/enchanting.js');

const item = (id) => E.ITEMS.find((i) => i.id === id);
const solveItem = (id, picks = [], off) => E.solve(E.chosen(item(id), picks, off || item(id).off));

// Independent brute force: try every merge order (any target + any book sacrifice), minimum total levels.
function brute(ids) {
    let best = Infinity;
    const val = (p) => p.ench.reduce((s, id) => s + E.ENCH[id][1] * E.ENCH[id][2], 0);
    (function go(pieces, total) {
        if (total >= best) return;
        if (pieces.length === 1) { best = total; return; }
        for (let a = 0; a < pieces.length; a++) for (let b = 0; b < pieces.length; b++) {
            if (a === b || pieces[b].item) continue;
            const A = pieces[a], B = pieces[b];
            const cost = val(B) + 2 ** A.w - 1 + 2 ** B.w - 1;
            if (cost >= 40) continue;
            const rest = pieces.filter((_, i) => i !== a && i !== b);
            go(rest.concat({ item: A.item, ench: A.ench.concat(B.ench), w: Math.max(A.w, B.w) + 1 }), total + cost);
        }
    })([{ item: true, ench: [], w: 0 }].concat(ids.map((id) => ({ ench: [id], w: 0 }))), 0);
    return best;
}

// Known results: chestplate = Thorns onto item (12), Unbreaking+Mending book (2), merge (7), Protection (7).
const chest = solveItem('chestplate');
assert.strictEqual(chest.total, 28);
assert.deepStrictEqual(chest.steps.map((s) => s.cost), [12, 2, 7, 7]);
assert.strictEqual(solveItem('elytra').total, 6); // Unbreaking 3 then Mending 2 + penalty 1

// Matches the brute force, and beats or ties the owner's old sheet.
const sheet = { helmet: 51, chestplate: 31, leggings: 52, boots: 86 };
for (const id of ['chestplate', 'leggings', 'helmet', 'sword', 'trident']) {
    const ids = E.chosen(item(id), [], item(id).off);
    assert.strictEqual(E.solve(ids).total, brute(ids), id + ' vs brute force');
}
for (const [id, old] of Object.entries(sheet)) assert.ok(solveItem(id).total <= old, id);

// Step costs, work penalties and totals add up; nothing too expensive in the default sets.
for (const it of E.ITEMS) {
    const r = solveItem(it.id);
    assert.strictEqual(r.steps.reduce((s, x) => s + x.cost, 0), r.total, it.id);
    assert.strictEqual(r.tooExpensive, 0, it.id);
    assert.strictEqual(r.steps.length, E.chosen(it, [], it.off).length, it.id);
}

// Conflicting enchants can't be chosen together: every pick of every item is conflict-free,
// and the conflict check does catch a bad pair.
for (const it of E.ITEMS) {
    const combos = it.choices.reduce((acc, opts) => acc.flatMap((p) => [-1, ...opts.keys()].map((i) => p.concat(i))), [[]]);
    for (const picks of combos) assert.deepStrictEqual(E.conflicts(E.chosen(it, picks, [])), [], it.id + ' ' + picks);
}
assert.strictEqual(E.conflicts(['fortune', 'silk_touch', 'mending']).length, 1);
assert.strictEqual(E.conflicts(['depth_strider', 'frost_walker']).length, 1);
assert.deepStrictEqual(E.chosen(item('pickaxe'), [1], []).sort(), ['efficiency', 'mending', 'silk_touch', 'unbreaking']);
assert.deepStrictEqual(E.chosen(item('trident'), [0], []).sort(), ['channeling', 'impaling', 'loyalty', 'mending', 'unbreaking']);

// XP points from level 0.
assert.strictEqual(E.xpFor(16), 352);
assert.strictEqual(E.xpFor(30), 1395);
assert.strictEqual(E.xpFor(39), 2727);

console.log('enchanting: all tests passed');
for (const id of Object.keys(sheet)) console.log(`  ${id}: ${solveItem(id).total} levels (old sheet ${sheet[id]})`);
