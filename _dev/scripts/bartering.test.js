// Mirrors the formulas in minecraft/tools/bartering/index.php.   usage: node _dev/scripts/bartering.test.js
const assert = require('assert');
const LOOT = [[1,1,5],[1,1,8],[1,1,8],[1,1,8],[1,1,10],[1,1,40],[1,1,40],[1,3,40],[2,4,10],[3,9,20],[2,4,40],[5,12,20],[2,8,40],[2,8,40],[10,36,10],[6,12,40],[8,16,40],[8,16,40]];
const calc = (p) => {
    let items = 0, hoppers = 0;
    for (const [min, max, w] of LOOT) { const h = (min + max) / 2 * w / 459 * 600 * p; items += h; hoppers += Math.ceil(h / 9000); }
    return { items, hoppers, gold: p * 600 };
};
const r = calc(768);
assert.strictEqual(Math.round(r.items), 2599153);
assert.strictEqual(r.gold, 460800);
assert.strictEqual(Math.ceil(9000 / 9000), 1); assert.strictEqual(Math.ceil(9001 / 9000), 2);
console.log('bartering ok', r);
