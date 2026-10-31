// Builds the data files for /minecraft/projects/collection/hallway/ from the collection list.
//   usage: node _dev/scripts/build-collection-hallway.js   (Node 18+, downloads Minecraft recipe data once)
// hallway.json         chest order (A to Z), real item ids, stack sizes and how to tell variants apart (need)
// hallway-recipes.json one crafting recipe per chest and the raw-material tree for a full chest
const fs = require('fs'), path = require('path'), os = require('os');
const ROOT = path.join(__dirname, '../..'), OUT = path.join(ROOT, 'minecraft/projects/collection/hallway');
const ICONS = path.join(ROOT, 'minecraft/tools/item-list');
const MC = '26.1', SRC = `https://raw.githubusercontent.com/PrismarineJS/minecraft-data/master/data/pc/${MC}/`;

async function mcData(file) {
    const cache = path.join(os.tmpdir(), `craftyplayz-mcdata-${MC}-${file}`);
    if (!fs.existsSync(cache)) {
        const r = await fetch(SRC + file);
        if (!r.ok) throw Error(`Couldn't download ${file}: ${r.status}`);
        fs.writeFileSync(cache, await r.text());
    }
    return JSON.parse(fs.readFileSync(cache, 'utf8'));
}

// list names that aren't an item id on their own -> [real id, substrings the item's data must contain]
const DYE = { white: 15790320, orange: 15435844, magenta: 12801229, light_blue: 6719955, yellow: 14602026, lime: 4312372, pink: 14188952, gray: 4408131, light_gray: 11250603, cyan: 2651799, purple: 8073150, blue: 2437522, brown: 5320730, green: 3887386, red: 11743532, black: 1973019 };
const PATTERN = { 'Bordure Indented': 'bordure_indented', 'Creeper Charge': 'creeper', 'Field Masoned': 'field_masoned', Flow: 'flow', 'Flower Charge': 'flower', Globe: 'globe', Gust: 'guster', 'Skull Charge': 'skull', Snout: 'piglin', Thing: 'mojang' };
const MAP = { 'Buried Treasure': 'buried_treasure', 'Desert Village': 'village_desert', 'Plains Village': 'village_plains', 'Savanna Village': 'village_savanna', 'Snowy Village': 'village_snowy', 'Taiga Village': 'village_taiga', 'Jungle Explorer': 'explorer_jungle', 'Swamp Explorer': 'explorer_swamp', 'Woodland Explorer': 'mansion', 'Ocean Explorer': 'monument', 'Trial Explorer': 'trial_chambers' };
const FIXED = { 'Gold Nautilus Armor': 'golden_nautilus_armor', 'Minecart with Furnace': 'furnace_minecart', 'Petrified Oak Slab': 'petrified_oak_slab', 'Short Grass (Grass)': 'short_grass', 'Spectral Arrow': 'spectral_arrow' };
const slug = (s) => s.toLowerCase().replace(/^the /, '').replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
function variant(name, id) {
    let m;
    if ((m = name.match(/^Arrow of (.+?)( \+| II| IV)?( \[JE\])?$/))) {
        const p = m[1] === 'Splashing' ? 'water' : slug(m[1]);
        return ['tipped_arrow', [`:"minecraft:${m[2] === ' +' ? 'long_' : m[2] ? 'strong_' : ''}${p}"`]];
    }
    if ((m = name.match(/^Tipped Arrow \((.+)\)$/))) return ['tipped_arrow', [`:"minecraft:${slug(m[1])}"`]];
    if ((m = name.match(/^Banner Pattern \((.+)\)$/))) return [PATTERN[m[1]] + '_banner_pattern', []];
    if ((m = name.match(/^Firework (Rocket|Star) (.+)$/))) return ['firework_' + m[1].toLowerCase(), [String(DYE[slug(m[2])])]];
    if ((m = name.match(/^Goat Horn \((.+)\)$/))) return ['goat_horn', [slug(m[1]) + '_goat_horn']];
    if ((m = name.match(/^Music Disc \((.+)\)$/))) return ['music_disc_' + (m[1] === 'Precipe' ? 'precipice' : slug(m[1])), []];
    if ((m = name.match(/^(.+) Map$/)) && MAP[m[1]]) return ['filled_map', ['filled_map.' + MAP[m[1]]]];
    // level I is the plain bottle, so it's also where a bottle without a stored level goes
    if ((m = name.match(/^Ominous Bottle( II| III| IV| V)?$/))) return ['ominous_bottle', m[1] ? [`ominous_bottle_amplifier":${['II', 'III', 'IV', 'V'].indexOf(m[1].trim()) + 1}`] : []];
    if (name === 'Lodestone Compass') return ['compass', ['lodestone']];
    if (name === 'Ominous Banner') return ['white_banner', ['ominous_banner']];
    const base = name.replace(/ \[JE\]$/, '');
    if (FIXED[base]) return [FIXED[base], []];
    return [id || '', []];
}

// 1:1 furnace results, output -> input
const SMELT = {
    iron_ingot: 'raw_iron', gold_ingot: 'raw_gold', copper_ingot: 'raw_copper', netherite_scrap: 'ancient_debris',
    glass: 'sand', stone: 'cobblestone', smooth_stone: 'stone', brick: 'clay_ball', nether_brick: 'netherrack',
    charcoal: 'oak_log', smooth_sandstone: 'sandstone', smooth_red_sandstone: 'red_sandstone', smooth_quartz: 'quartz_block',
    smooth_basalt: 'basalt', cracked_stone_bricks: 'stone_bricks', terracotta: 'clay', deepslate: 'cobbled_deepslate',
    cooked_beef: 'beef', cooked_porkchop: 'porkchop', cooked_chicken: 'chicken', cooked_mutton: 'mutton', cooked_rabbit: 'rabbit',
    cooked_cod: 'cod', cooked_salmon: 'salmon', baked_potato: 'potato', dried_kelp: 'kelp', green_dye: 'cactus',
    popped_chorus_fruit: 'chorus_fruit', sponge: 'wet_sponge', lime_dye: 'sea_pickle',
};
for (const c of Object.keys(DYE)) SMELT[c + '_glazed_terracotta'] = c + '_terracotta';
// gathered from the world even though a recipe makes them
const RAW = new Set(['white_wool', 'string', 'slime_ball', 'honeycomb', 'snow_block', 'snowball', 'clay_ball', 'quartz', 'glowstone_dust', 'amethyst_shard', 'magma_cream', 'blaze_powder']);

(async () => {
    const list = JSON.parse(fs.readFileSync(path.join(ROOT, 'minecraft/projects/collection/list.json'), 'utf8'));
    const items = await mcData('items.json'), recipes = await mcData('recipes.json');
    const byName = {}, byId = {};
    for (const i of items) { byName[i.name] = i; byId[i.id] = i; }
    const nm = (id) => byId[id].name;
    const ings = (r) => (r.inShape ? r.inShape.flat() : r.ingredients || []).filter((x) => x != null);

    function recFor(name) {
        const it = byName[name];
        if (!it) return null;
        // skip "uncrafting" (one ingredient that gives several back and is itself packed from this item: block -> 9 ingots), except nuggets
        const unpack = (r) => { const u = [...new Set(ings(r))]; return u.length === 1 && r.result.count > 1 && (recipes[u[0]] || []).some((r2) => ings(r2).every((x) => x === it.id)); };
        return (recipes[it.id] || []).filter((r) => name.endsWith('_nugget') || !unpack(r))[0] || null;
    }
    // [id, qty, 'raw'|'craft'|'smelt', children]
    function tree(name, qty, seen = new Set()) {
        if (RAW.has(name) || seen.has(name) || seen.size > 8) return [name, qty, 'raw'];
        const s = new Set(seen).add(name);
        if (SMELT[name]) return [name, qty, 'smelt', [tree(SMELT[name], qty, s)]];
        const r = recFor(name);
        if (!r) return [name, qty, 'raw'];
        const crafts = Math.ceil(qty / r.result.count), need = {};
        for (const b of ings(r)) need[nm(b)] = (need[nm(b)] || 0) + crafts;
        return [name, qty, 'craft', Object.entries(need).map(([n, q]) => tree(n, q, s))];
    }

    const icon = (...ids) => ids.find((id) => id && fs.existsSync(path.join(ICONS, `minecraft_${id}.png`))) || '';
    const used = new Set();
    const all = [...list.items, ...list.templates, ...list.sherds].sort((a, b) => a.name.toLowerCase().localeCompare(b.name.toLowerCase()));
    const entries = [], recs = [];
    for (const e of all) {
        const name = e.name.replace(/‌/g, '').replace(/(\S)\[/, '$1 [');
        const [id, need] = variant(name, e.id), it = byName[id];
        entries.push({ name, id, need, stack: it ? it.stackSize : 64, icon: icon(e.id, id) });
        const r = it && recFor(id), out = {};
        if (r) {
            out.r = { g: r.inShape ? [0, 1, 2].flatMap((y) => [0, 1, 2].map((x) => r.inShape[y]?.[x] ?? null)).map((b) => (b == null ? '' : nm(b))) : ings(r).map(nm), s: !r.inShape, c: r.result.count };
            out.r.g.forEach((n) => n && used.add(n));
        }
        const t = it ? tree(id, 54 * it.stackSize) : null;
        if (t && t[2] !== 'raw') { out.t = t; (function walk(n) { used.add(n[0]); (n[3] || []).forEach(walk); })(t); }
        recs.push(out);
    }

    // ingot -> block, for "= N blocks" hints
    const pack = {};
    for (const [bid, rs] of Object.entries(recipes)) for (const r of rs) {
        const g = ings(r);
        if (byId[bid] && r.result.count === 1 && g.length === 9 && g.every((x) => x === g[0])) pack[nm(g[0])] ??= nm(+bid);
    }
    const names = {}, packs = {};
    for (const id of used) {
        if (byName[id]) names[id] = byName[id].displayName;
        if (pack[id]) { packs[id] = pack[id]; names[pack[id]] = byName[pack[id]].displayName; }
    }

    fs.writeFileSync(path.join(OUT, 'hallway.json'), JSON.stringify({ mc: MC, entries }));
    fs.writeFileSync(path.join(OUT, 'hallway-recipes.json'), JSON.stringify({ recs, names, pack: packs }));
    const kb = (f) => Math.round(fs.statSync(path.join(OUT, f)).size / 1024) + ' KB';
    console.log(`${entries.length} chests, ${recs.filter((r) => r.r).length} recipes · hallway.json ${kb('hallway.json')} · hallway-recipes.json ${kb('hallway-recipes.json')}`);
    const bad = entries.filter((e) => !byName[e.id]);
    if (bad.length) console.log('No item id for:', bad.map((e) => e.name).join(', '));
})().catch((e) => { console.error(e.message); process.exit(1); });
