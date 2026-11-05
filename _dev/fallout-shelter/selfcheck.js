#!/usr/bin/env node
// Fallout Shelter Save Editor self-check. Usage: node _dev/fallout-shelter/selfcheck.js [encrypted save paths...]
// With no paths it uses only the synthetic fixture next to this file. For real saves, pass temporary copies kept outside the repo.
// For each file: serialise(parse(text)) === text, and re-encrypting gives byte-identical Base64. Then the lock, hostile-file and edit tests.
const fs = require('fs');
const path = require('path');
const assert = require('assert');
const C = require('../../tools/fallout-shelter/js/core.js');
const M = require('../../tools/fallout-shelter/js/model.js');

let passed = 0;
const ok = (name, fn) => Promise.resolve().then(fn).then(() => { passed++; }, (e) => { console.error('FAILED: ' + name + '\n  ' + e.message); process.exitCode = 1; });

async function roundTrip(file) {
    const raw = fs.readFileSync(file);
    const { text, b64 } = await C.decodeFile(new Uint8Array(raw));
    assert.strictEqual(C.serialise(C.parse(text)), text, 'lossless JSON');
    assert.strictEqual(await C.encodeText(text), raw.toString('latin1'), 'byte-identical Base64');
    assert.strictEqual(b64, raw.toString('latin1'));
    // never print a name, a device or a value from a real file: sizes only
    console.log('ok ' + path.basename(file) + ' (' + text.length + ' characters)');
}

const code = async (u) => { try { await C.decodeFile(u); return 'none'; } catch (e) { return e.code; } };
const bytes = (s) => new Uint8Array(Buffer.from(s, 'latin1'));
const fixtureText = fs.readFileSync(path.join(__dirname, 'fixture.json'), 'utf8').replace(/\r?\n$/, '');
const fresh = () => new C.Doc('fixture.sav', fixtureText, '');
const assertLocked = (fn) => assert.throws(fn, (e) => e.code === 'locked');

(async () => {
    const files = process.argv.slice(2);
    for (const f of files) await ok('round trip ' + path.basename(f), () => roundTrip(f));

    await ok('synthetic fixture round trip', async () => {
        assert.strictEqual(C.serialise(C.parse(fixtureText)), fixtureText);
        const b64 = await C.encodeText(fixtureText);
        const d = await C.decodeFile(bytes(b64));
        assert.strictEqual(d.text, fixtureText);
        assert.strictEqual(await C.encodeText(d.text), b64);
    });
    await ok('whitespace, duplicates, key order and BOM survive', () => {
        const t = '﻿ \n{ "b" : 1 ,\t"a":[ ] ,"a" : [ 1.50 , { } ],"1":2,"0":3 } \r\n';
        assert.strictEqual(C.serialise(C.parse(t)), t);
    });
    await ok('__proto__ is only data', () => {
        const d = fresh();
        assert.strictEqual({}.polluted, undefined);
        assert.strictEqual(C.val(C.get(d.root, '__proto__', 'polluted')), true);
        assert.strictEqual(d.text(), fixtureText);
    });
    await ok('large integers stay exact', () => {
        const n = C.get(fresh().root, 'time', 'timeSaveDate');
        assert.strictEqual(C.val(n), '639274211655260700');
        assert.strictEqual(C.numberLike('639274211655260700', '639274211655260701'), '639274211655260701');
    });
    await ok('new numbers copy the format of the field', () => {
        assert.strictEqual(C.numberLike('100.00', '5'), '5.00');
        assert.strictEqual(C.numberLike('100.00', 7.126), '7.13');
        assert.strictEqual(C.numberLike('3', '12'), '12');
        assert.throws(() => C.numberLike('3', '1.5'), /whole numbers/);
        assert.throws(() => C.numberLike('3', 'abc'), /Enter a number/);
    });

    await ok('corrupt files are told apart', async () => {
        assert.strictEqual(await code(bytes('not base64!!')), 'base64');
        assert.strictEqual(await code(bytes('')), 'base64');
        assert.strictEqual(await code(bytes(Buffer.alloc(18).toString('base64'))), 'blocks');
        assert.strictEqual(await code(bytes(Buffer.alloc(32).toString('base64'))), 'padding');
        const good = await C.encodeText('{"a":1}');
        assert.strictEqual(await code(bytes(good)), 'none');
        assert.strictEqual(await code(bytes(await C.encodeText('{"a":1'))), 'json');
        assert.strictEqual(await code(bytes(await C.encodeText('['.repeat(C.MAX_DEPTH + 1) + ']'.repeat(C.MAX_DEPTH + 1)))), 'depth');
        assert.strictEqual(await code(new Uint8Array(C.MAX_FILE_BYTES + 1)), 'size');
    });

    await ok('an edit changes only its value, and undo restores the exact text', () => {
        const d = fresh();
        const n = C.get(d.root, 'vault', 'storage', 'resources', 'Nuka');
        d.commit('Caps', [{ op: 'set', node: n, raw: C.numberLike(n.raw, 250) }]);
        assert.strictEqual(d.text(), fixtureText.replace('"Nuka":100.00', '"Nuka":250.00'));
        assert.ok(d.isChanged());
        assert.strictEqual(d.changes().length, 1);
        d.undo();
        assert.strictEqual(d.text(), fixtureText);
        assert.ok(!d.isChanged());
        d.redo();
        assert.strictEqual(d.text(), fixtureText.replace('"Nuka":100.00', '"Nuka":250.00'));
        d.revert(d.changes()[0]);
        assert.strictEqual(d.text(), fixtureText);
        assert.strictEqual(d.changes().length, 0);
    });
    await ok('raw edits must be one scalar of the same kind', () => {
        const d = fresh();
        const n = C.get(d.root, 'vault', 'storage', 'resources', 'StimPack');
        for (const raw of ['1,"isPremium":true', '{"a":1}', '"text"', 'true', ' 1', '1.50', '1e5']) {
            assert.throws(() => d.commit('x', [{ op: 'set', node: n, raw }]), undefined, raw);
        }
        assert.strictEqual(d.text(), fixtureText);
    });
    await ok('a failed commit changes nothing', () => {
        const d = fresh();
        const a = C.get(d.root, 'vault', 'storage', 'resources', 'StimPack');
        const b = C.get(d.root, 'seasonsData', 'S1', 'isPremium');
        assertLocked(() => d.commit('x', [{ op: 'set', node: a, raw: '9' }, { op: 'set', node: b, raw: 'true' }]));
        assert.strictEqual(d.text(), fixtureText);
    });

    await ok('Paid Entitlements are locked by every route', () => {
        const d = fresh();
        const r = d.root;
        const locked = [
            C.get(r, 'seasonsData', 'S1', 'isPremium'), C.get(r, 'seasonsData', 'S1', 'isPremiumPlus'),
            C.get(r, 'seasonsData', 'S1', 'premiumRewardsList', 0, 'claimed'), C.get(r, 'ShopWindow', 'isStarterPackPurchased'),
        ];
        for (const n of locked) {
            assert.ok(C.isLockedNode(n));
            assertLocked(() => d.commit('x', [{ op: 'set', node: n, raw: 'true' }]));
            assertLocked(() => d.commit('x', [{ op: 'set', node: n, raw: 'true' }], { internal: true }));
        }
        const entryOf = (...p) => C.get(r, ...p).up;
        // removing the field, a parent that holds it, or anything inside it
        for (const e of [entryOf('purchaseHistory'), entryOf('seasonsData'), entryOf('seasonsData', 'S1'), entryOf('seasonsData', 'S1', 'premiumRewardsList'), entryOf('vault', 'SeasonPassLunchboxClaims'), entryOf('vault'), entryOf('ShopWindow')]) {
            assertLocked(() => d.commit('x', [{ op: 'del', entry: e }]));
        }
        // adding a second copy of a locked key, or a new parent that holds one
        const season = C.get(r, 'seasonsData', 'S1');
        assertLocked(() => d.commit('x', [{ op: 'ins', own: season, entry: C.makeEntry(season, 'isPremium', 'true') }]));
        assertLocked(() => d.commit('x', [{ op: 'ins', own: season, entry: C.makeEntry(season, 'ISPREMIUM', 'true') }]));
        const seasons = C.get(r, 'seasonsData');
        assertLocked(() => d.commit('x', [{ op: 'ins', own: seasons, entry: C.makeEntry(seasons, 'S2', '{"isPremium":true}') }]));
        assertLocked(() => d.commit('x', [{ op: 'ins', own: d.root, entry: C.makeEntry(d.root, 'x', '{"a":[{"purchaseHistory":{}}]}') }]));
        const list = C.get(r, 'seasonsData', 'S1', 'premiumRewardsList');
        assertLocked(() => d.commit('x', [{ op: 'ins', own: list, entry: C.makeEntry(list, null, '{"claimed":true}') }]));
        // a key spelled with escapes is still the same key
        const sneaky = new C.Doc('x', '{"seasonsData":{"S":{"isPrem\\u0069um":false}}}', '');
        assertLocked(() => sneaky.commit('x', [{ op: 'set', node: C.get(sneaky.root, 'seasonsData', 'S', 'isPremium'), raw: 'true' }]));
        assert.strictEqual(d.text(), fixtureText);
        // free progress stays editable, and the advert timestamp isn't an entitlement
        const free = C.get(r, 'seasonsData', 'S1', 'freeRewardsList', 0, 'claimed');
        d.commit('x', [{ op: 'set', node: free, raw: 'true' }]);
        d.commit('x', [{ op: 'set', node: C.get(r, 'currentLevel'), raw: '5' }]);
        d.commit('x', [{ op: 'set', node: C.get(r, 'lastPremiumUpsellTime'), raw: '1' }]);
        d.commit('x', [{ op: 'set', node: C.get(r, 'ShopWindow', 'hasStarterPackPopupShown'), raw: 'false' }]);
    });

    await ok('moving a Dweller keeps both links in step and undoes exactly', () => {
        const d = fresh();
        let v = M.readVault(d);
        const base = M.checkVault(v);
        const res = M.moveOps(v, v.dwellerById.get(1), v.roomById.get(4));
        assert.ok(res.ops);
        d.commit('Move', res.ops);
        v = M.readVault(d);
        assert.deepStrictEqual(v.roomById.get(3).dwellerIds, []);
        assert.deepStrictEqual(v.roomById.get(4).dwellerIds, [1]);
        assert.strictEqual(v.dwellerById.get(1).savedRoom, 4);
        assert.deepStrictEqual(M.newProblems(base, M.checkVault(v)), []);
        d.undo();
        assert.strictEqual(d.text(), fixtureText);
    });
    await ok('moves that can\'t work say why', () => {
        const v = M.readVault(fresh());
        assert.ok(M.moveOps(v, v.dwellerById.get(2), v.roomById.get(4)).why, 'dead');
        assert.ok(M.moveOps(v, v.dwellerById.get(3), v.roomById.get(4)).why, 'wasteland');
        assert.ok(M.moveOps(v, v.dwellerById.get(1), v.roomById.get(2)).why, 'elevator');
    });
    await ok('checks report new problems, not ones the save came with', () => {
        const d = fresh();
        let v = M.readVault(d);
        const base = M.checkVault(v);
        // Dweller 3 is on a wasteland team with a saved room, which real saves do: not a problem
        assert.deepStrictEqual(base.filter((p) => p.level === 'error'), []);
        const own = C.get(d.root, 'vault', 'rooms', 3, 'dwellers');
        d.commit('Break', [{ op: 'ins', own, entry: C.makeEntry(own, null, '99') }]);
        v = M.readVault(d);
        const added = M.newProblems(base, M.checkVault(v));
        assert.strictEqual(added.length, 1);
        assert.strictEqual(added[0].level, 'error');
    });
    await ok('lunchbox counts stay in step with the lists', () => {
        const d = fresh();
        d.commit('Lunchboxes', M.lunchboxOps(d, 0, 5));
        assert.strictEqual(C.items(C.get(d.root, 'vault', 'LunchBoxesByType')).filter((e) => e.v.raw === '0').length, 5);
        assert.strictEqual(C.items(C.get(d.root, 'vault', 'LunchBoxDeckNames')).length, 6);
        assert.strictEqual(C.val(C.get(d.root, 'vault', 'LunchBoxesCount')), 6);
        d.commit('Lunchboxes', M.lunchboxOps(d, 2, 0));
        assert.strictEqual(C.val(C.get(d.root, 'vault', 'LunchBoxesCount')), 5);
        d.undo(); d.undo();
        assert.strictEqual(d.text(), fixtureText);
    });
    await ok('inventory add and remove undo exactly', () => {
        const d = fresh();
        d.commit('Add', M.addItemOps(d, 'Junk', 'AlarmClock'));
        assert.strictEqual(C.items(C.get(d.root, 'vault', 'inventory', 'items')).length, 2);
        assert.ok(d.text().includes('{"id":"AlarmClock","type":"Junk","hasBeenAssigned":false,"hasRandonWeaponBeenAssigned":false}'));
        d.undo();
        assert.strictEqual(d.text(), fixtureText);
    });

    if (!process.exitCode) console.log('All ' + passed + ' checks passed.');
})();
