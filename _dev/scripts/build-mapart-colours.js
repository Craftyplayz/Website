// Builds shop/mapart/colours.json from MapartCraft's colour data (GPL-3.0, https://github.com/rebane2001/mapartcraft):
// every map colour set with its four tones and the 1.20 blocks, plus the two block palettes Castle Crafty offers.
// Usage: node _dev/scripts/build-mapart-colours.js <path to a mapartcraft checkout>
const fs = require('fs'), path = require('path');
const src = path.join(process.argv[2] || '', 'src/components/mapart/json');
const colours = JSON.parse(fs.readFileSync(path.join(src, 'coloursJSON.json'), 'utf8'));
const presets = JSON.parse(fs.readFileSync(path.join(src, 'defaultPresets.json'), 'utf8'));
const VERSION = '1.20';

// the owner's "better colour" palette, as a MapartCraft preset link (?preset=...)
const BETTER = '0R1R3R4R5Q6X7Q8R9QbRcUdTeTfTgThTiTjTkTlTmTnToTpTqTrTsTtQuRvQwQxRyRzQ10Q11Q12Q13Q14Q15Q16Q17Q18Q19Q1aQ1bQ1cQ1dQ1eQ1gS1iR1jS1oR';

// same decoding as MapartCraft's URLToPreset: base-36 colour set id, then the block's presetIndex in base 26 written with Q-Z for 0-9 and A-P for 10-25
function decode(preset) {
    const out = [];
    const re = /([0-9a-z]+)(?=([Q-ZA-P]+))/g;
    let m;
    while ((m = re.exec(preset))) {
        const set = parseInt(m[1], 36);
        const idx = parseInt(m[2].replace(/[Q-Z]/g, c => String(c.charCodeAt(0) - 81)).toLowerCase(), 26);
        out.push([set, idx]);
    }
    return out;
}

// a block's 1.20 NBT name ("&1.13.2" means "same as 1.13.2")
function nbtName(block) {
    let v = block.validVersions[VERSION];
    while (typeof v === 'string' && v.startsWith('&')) v = block.validVersions[v.slice(1)];
    return v && v.NBTName;
}

const sets = {};
for (const [id, set] of Object.entries(colours)) {
    const blocks = {};
    for (const [bid, b] of Object.entries(set.blocks)) {
        const nbt = nbtName(b);
        if (!nbt) continue; // not in 1.20
        blocks[b.presetIndex] = { name: b.displayName, nbt, support: !!b.supportBlockMandatory };
    }
    sets[id] = { tones: set.tonesRGB, blocks };
}

// a palette = { colourSetId: presetIndex } using only blocks that exist in 1.20
const palette = list => Object.fromEntries(list.filter(([s, i]) => sets[s] && sets[s].blocks[i]).map(([s, i]) => [s, i]));
const carpets = presets.find(p => p.localeKey === 'BLOCK-SELECTION/PRESETS/CARPETS').blocks;
const out = {
    source: 'MapartCraft by rebane2001 (GPL-3.0), https://github.com/rebane2001/mapartcraft',
    version: VERSION,
    palettes: { carpet: palette(carpets), better: palette(decode(BETTER)) },
    // passed to MapartCraft's worker as-is
    worker: Object.fromEntries(['mapModes', 'whereSupportBlocksModes', 'ditherMethods'].map(n => [n, JSON.parse(fs.readFileSync(path.join(src, n + '.json'), 'utf8'))])),
    sets,
};
fs.writeFileSync(path.join(__dirname, '../../shop/mapart/colours.json'), JSON.stringify(out));
console.log(`${Object.keys(sets).length} colour sets; carpet ${Object.keys(out.palettes.carpet).length} colours, better ${Object.keys(out.palettes.better).length} colours`);
