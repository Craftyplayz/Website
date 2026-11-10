// Self-check for tools/pokerogue/prsv.js. Usage: node _dev/pokerogue/selfcheck.js [decrypted-save.json]
const assert = require('assert');
const fs = require('fs');
const P = require('../../tools/pokerogue/prsv.js');

const save = process.argv[2] ? JSON.parse(fs.readFileSync(process.argv[2], 'utf8')) : {
    trainerId: 1,
    dexData: { 25: { $sa: 0, $ca: 0, $na: 0, $s: 0, $c: 0, $hc: 0, $i: [0, 0, 0, 0, 0, 0] }, 29: { $sa: 8 | 128, $ca: 1 | 8 | 16 | 128 | 256, $na: 16, $s: 3, $c: 1, $hc: 0, $i: [5, 5, 5, 5, 5, 5] } },
    starterData: { 25: { $em: 0, $x: 3, $a: 0, $pa: 0, $vr: 0 }, 29: { $em: 1, $x: 0, $a: 1, $pa: 2, $vr: 1 } },
};
assert(P.isSave(save));
assert(!P.isSave({ dexData: {} }));

for (const id of Object.keys(save.starterData)) {
    const genders = Number(save.dexData[id].$ca) & (P.DEX.MALE | P.DEX.FEMALE);
    const forms = P.forms(save.dexData[id]);
    P.unlock(save, id);
    const dex = save.dexData[id], st = save.starterData[id];
    assert(P.has(dex.$ca, P.DEX.NON_SHINY) && P.has(dex.$ca, P.DEX.SHINY) && P.has(dex.$ca, P.DEX.DEFAULT_FORM));
    assert.strictEqual(Number(dex.$sa) & dex.$ca, dex.$ca, 'seen covers caught');
    assert.strictEqual(dex.$ca & (P.DEX.MALE | P.DEX.FEMALE), genders || P.DEX.MALE, 'genders kept');
    assert(forms.every(n => P.forms(dex).includes(n)), 'forms kept');
    assert.strictEqual(dex.$na & P.ALL_NATURES, P.ALL_NATURES);
    assert.deepStrictEqual(dex.$i, [31, 31, 31, 31, 31, 31]);
    assert.strictEqual(st.$a, 7); assert.strictEqual(st.$em, 15); assert.strictEqual(st.$vr, 2);
    assert(P.has(st.$pa, P.PASSIVE.UNLOCKED));
}
assert.strictEqual(P.set(5, 4, false), 1);
assert.strictEqual(P.set(1, 4, true), 5);
assert.deepStrictEqual(P.forms({ $ca: 128 | 512, $sa: 256 }), [0, 1, 2]);
console.log('pokerogue selfcheck ok,', Object.keys(save.starterData).length, 'starters');
