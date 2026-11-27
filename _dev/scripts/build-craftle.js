// Builds games/craftle/recipes.json from a mcmeta "data-json" checkout.
// Usage: node _dev/scripts/build-craftle.js <dir containing data/ and version.json>
// Tags/alternatives: the first listed item is used (planks -> oak_planks), nested tags resolved first-value.
// Puzzles: one recipe per result; no one-ingredient blobs (unless a 3-8 slot shape); one colour/wood/copper variant per family.
const fs = require('fs'), path = require('path'), assert = require('assert');
const src = process.argv[2];
if (!src) { console.error('usage: node build-craftle.js <mcmeta dir>'); process.exit(1); }
const root = path.resolve(__dirname, '..', '..');
const icons = new Set(fs.readdirSync(path.join(root, 'minecraft/tools/item-list')).filter(f => /^minecraft_.+\.png$/.test(f)).map(f => f.slice(10, -4)));
const rd = p => JSON.parse(fs.readFileSync(p, 'utf8'));
const rdir = path.join(src, 'data/minecraft/recipe'), tdir = path.join(src, 'data/minecraft/tags/item');
const strip = s => s.replace(/^#?minecraft:/, '');

function pick(ing, depth = 0) { // ingredient -> one item id or null
  if (Array.isArray(ing)) return pick(ing[0], depth);
  if (typeof ing !== 'string' || depth > 5) return null;
  if (ing[0] !== '#') return strip(ing);
  const f = path.join(tdir, strip(ing) + '.json');
  if (!fs.existsSync(f)) return null;
  for (const v of rd(f).values) { const r = pick(typeof v === 'string' ? v : v.id, depth + 1); if (r) return r; }
  return null;
}

const COL = 'white|orange|magenta|light_blue|yellow|lime|pink|gray|light_gray|cyan|purple|blue|brown|green|red|black';
const WOOD = 'oak|spruce|birch|jungle|acacia|dark_oak|mangrove|cherry|pale_oak|bamboo|crimson|warped';
const famKey = id => {
  if (/_dye$/.test(id)) return null; // dyes flood
  if (/^(waxed_|exposed_|weathered_|oxidized_)/.test(id)) return null;
  return id.replace(new RegExp('^(' + COL + ')_'), 'COLOUR_').replace(new RegExp('^(' + WOOD + ')_'), 'WOOD_');
};
const prio = id => (/^(white|oak)_/.test(id) ? 0 : 1);

const all = [], names = new Set();
for (const f of fs.readdirSync(rdir).filter(f => f.endsWith('.json'))) {
  const j = rd(path.join(rdir, f));
  const t = strip(j.type || '');
  if (t !== 'crafting_shaped' && t !== 'crafting_shapeless') continue;
  const res = strip(j.result && j.result.id || '');
  if (!icons.has(res)) continue;
  let grid = Array(9).fill(null), ok = true;
  if (t === 'crafting_shaped') {
    j.pattern.forEach((row, y) => [...row].forEach((c, x) => {
      if (c === ' ') return;
      const id = pick(j.key[c]); if (!id || !icons.has(id)) ok = false; else grid[y * 3 + x] = id;
    }));
  } else {
    j.ingredients.forEach((g, i) => { const id = pick(g); if (!id || !icons.has(id)) ok = false; else grid[i] = id; });
  }
  if (!ok) continue;
  names.add(res);
  const n = grid.filter(Boolean).length, distinct = new Set(grid.filter(Boolean)).size;
  const boring = distinct === 1 && !(t === 'crafting_shaped' && n >= 3 && n <= 8);
  if (boring || n < 1) continue;
  all.push({ r: res, g: grid, shaped: t === 'crafting_shaped' });
}
all.sort((a, b) => prio(a.r) - prio(b.r) || a.r.localeCompare(b.r));
const seenRes = new Set(), seenFam = new Set(), recipes = [];
for (const x of all) {
  const fk = famKey(x.r);
  if (!fk || seenRes.has(x.r) || seenFam.has(fk)) continue;
  seenRes.add(x.r); seenFam.add(fk);
  recipes.push({ r: x.r, g: x.g });
}
recipes.sort((a, b) => a.r.localeCompare(b.r)); // stable order so the daily pick is reproducible

// self-check
assert(recipes.length >= 150, 'too few recipes: ' + recipes.length);
for (const x of recipes) {
  assert(icons.has(x.r), 'no icon for result ' + x.r);
  assert(x.g.length === 9 && x.g.some(Boolean), 'empty recipe ' + x.r);
  x.g.forEach(i => assert(i === null || icons.has(i), 'no icon for ' + i + ' in ' + x.r));
}
const out = { version: rd(path.join(src, 'version.json')).id, names: [...names].sort(), recipes };
fs.mkdirSync(path.join(root, 'games/craftle'), { recursive: true });
fs.writeFileSync(path.join(root, 'games/craftle/recipes.json'), JSON.stringify(out));
console.log('recipes', recipes.length, 'names', out.names.length, 'version', out.version);
