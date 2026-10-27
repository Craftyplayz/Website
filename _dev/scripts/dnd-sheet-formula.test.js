// Checks games/dnd/sheet/formula.js.   usage: node _dev/scripts/dnd-sheet-formula.test.js
const assert = require('assert');
require('../../games/dnd/sheet/formula.js');
const calc = globalThis.hbCalc;
const vars = { dex: 3, prof: 2, level: 5, perception: 4 };
assert.strictEqual(calc('10 + DEX', vars).v, 13);
assert.strictEqual(calc('ceil(LEVEL / 4) + 1', vars).v, 3);
assert.strictEqual(calc('8 + prof + -dex * 2', vars).v, 4);
assert.strictEqual(calc('LEVEL / 2', vars).v, 2);
assert.strictEqual(calc('max(DEX, 1, 2)', vars).v, 3);
assert.strictEqual(calc('', vars).v, 0);
assert.strictEqual(calc('10 + Perception', vars).v, 14);
assert.throws(() => calc('10 + WIS', vars), /Unknown name WIS/);
assert.throws(() => calc('(1 + 2', vars), /Missing \)/);
assert.throws(() => calc('constructor', vars), /Unknown name/);
console.log('dnd sheet formula ok');
