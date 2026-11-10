// PokeRogue system save: bit flags and edits, shared by the page and _dev/pokerogue/selfcheck.js
(function (root) {
    // The game's fixed save key (crypto-js AES passphrase, "Salted__" OpenSSL format)
    const KEY = 'x0i2O7WRiANTqPmZ';
    // dexData $ca/$sa (DexAttr)
    const DEX = { NON_SHINY: 1, SHINY: 2, MALE: 4, FEMALE: 8, VARIANT_1: 16, VARIANT_2: 32, VARIANT_3: 64, DEFAULT_FORM: 128 };
    // starterData $a (AbilityAttr) and $pa (Passive)
    const ABILITY = { ABILITY_1: 1, ABILITY_2: 2, HIDDEN: 4 };
    const PASSIVE = { UNLOCKED: 1, ENABLED: 2 };
    const ALL_NATURES = ((1 << 25) - 1) * 2; // nature n is bit 1 << (n + 1)

    const has = (v, bit) => (Number(v) & bit) !== 0;
    const set = (v, bit, on) => on ? (Number(v) | bit) : (Number(v) & ~bit);

    // Form bits are 128 << n; return the n values present in caught or seen
    function forms(dex) {
        const out = [];
        const all = Number(dex.$ca) | Number(dex.$sa);
        for (let n = 0; n < 24; n++) if (all & (128 * 2 ** n)) out.push(n);
        return out;
    }

    // Everything a starter can have, keeping its existing genders and forms (setting the wrong gender or a missing form breaks sprites)
    function unlock(save, id) {
        const dex = save.dexData[id], st = save.starterData[id];
        if (!dex || !st) return;
        let ca = Number(dex.$ca) | DEX.NON_SHINY | DEX.SHINY | DEX.VARIANT_1 | DEX.DEFAULT_FORM;
        if (!has(ca, DEX.MALE | DEX.FEMALE)) ca |= DEX.MALE;
        dex.$ca = ca;
        dex.$sa = Number(dex.$sa) | ca;
        dex.$na = Number(dex.$na) | ALL_NATURES;
        dex.$i = [31, 31, 31, 31, 31, 31];
        if (!dex.$s) dex.$s = 1;
        if (!dex.$c) dex.$c = 1;
        st.$a = ABILITY.ABILITY_1 | ABILITY.ABILITY_2 | ABILITY.HIDDEN;
        st.$pa = Number(st.$pa) | PASSIVE.UNLOCKED;
        st.$em = 15;
        st.$vr = 2;
    }

    function isSave(o) {
        return !!(o && typeof o === 'object' && o.dexData && o.starterData && 'trainerId' in o);
    }

    const api = { KEY, DEX, ABILITY, PASSIVE, ALL_NATURES, has, set, forms, unlock, isSave };
    if (typeof module === 'object' && module.exports) module.exports = api;
    else root.PRSV = api;
})(this);
