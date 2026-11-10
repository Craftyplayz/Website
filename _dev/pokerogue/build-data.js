// Builds tools/pokerogue/data.json (species names + sprite ids, egg move names, natures) for the PokeRogue save editor.
// Usage: node _dev/pokerogue/build-data.js <srcDir>
// <srcDir> holds, from pagefaultgames/pokerogue (beta): species-id.ts, move-id.ts, nature.ts, egg-moves.ts
// and from PokeAPI/pokeapi data/v2/csv: pokemon_species_names.csv, pokemon.csv, move_names.csv, moves.csv
const fs = require('fs');
const path = require('path');
const src = process.argv[2];
if (!src) throw new Error('Pass the source folder');
const read = f => fs.readFileSync(path.join(src, f), 'utf8');

// TypeScript enum -> { NAME: value }, following implicit increments
function parseEnum(text) {
  const out = {};
  let next = 0;
  for (const m of text.matchAll(/^\s+([A-Z][A-Z0-9_]*)\s*(?:=\s*(\d+))?\s*,?\s*$/gm)) {
    const v = m[2] !== undefined ? +m[2] : next;
    out[m[1]] = v;
    next = v + 1;
  }
  return out;
}
const csv = f => read(f).trim().split(/\r?\n/).slice(1).map(l => l.split(','));
const title = s => s.toLowerCase().split('_').map(w => w[0].toUpperCase() + w.slice(1)).join(' ');

const speciesEnum = parseEnum(read('species-id.ts'));
const moveEnum = parseEnum(read('move-id.ts'));
const natures = Object.keys(parseEnum(read('nature.ts'))).map(title);
if (natures.length !== 25) throw new Error('Expected 25 natures, got ' + natures.length);

const speciesName = {};
for (const r of csv('pokemon_species_names.csv')) if (r[1] === '9') speciesName[+r[0]] = r[2];
const pokemonId = {};
for (const r of csv('pokemon.csv')) pokemonId[r[1]] = +r[0];
const apiIdent = {};
for (const [ident, id] of Object.entries(pokemonId)) if (id < 10000) apiIdent[id] = ident;
const moveIdByIdent = {};
for (const r of csv('moves.csv')) moveIdByIdent[r[1]] = +r[0];
const moveName = {};
for (const r of csv('move_names.csv')) if (r[1] === '9') moveName[+r[0]] = r.slice(2).join(',');

const PREFIX = { ALOLA: ['Alolan', 'alola'], GALAR: ['Galarian', 'galar'], HISUI: ['Hisuian', 'hisui'], PALDEA: ['Paldean', 'paldea'] };
const SPECIAL = {
  BLOODMOON_URSALUNA: ['Bloodmoon Ursaluna', 'ursaluna-bloodmoon'],
  ETERNAL_FLOETTE: ['Eternal Floette', 'floette-eternal'],
  BATTLE_BOND_GRENINJA: ['Battle Bond Greninja', 'greninja-battle-bond'],
  PALDEA_TAUROS: ['Paldean Tauros', 'tauros-paldea-combat-breed'],
  GALAR_DARMANITAN: ['Galarian Darmanitan', 'darmanitan-galar-standard'],
  HISUI_BASCULIN: ['White-Striped Basculin', 'basculin-white-striped'],
};
const byEnumName = {};
for (const [name, id] of Object.entries(speciesEnum)) byEnumName[name] = id;

const species = {};
for (const [name, id] of Object.entries(speciesEnum)) {
  if (id < 2000) { species[id] = [speciesName[id] || title(name), id]; continue; }
  let label, ident, base;
  if (SPECIAL[name]) {
    [label, ident] = SPECIAL[name];
    base = byEnumName[name.split('_').pop()];
  } else {
    const [pre, ...rest] = name.split('_');
    const baseName = rest.join('_');
    base = byEnumName[baseName];
    label = `${PREFIX[pre][0]} ${speciesName[base] || title(baseName)}`;
    ident = `${apiIdent[base]}-${PREFIX[pre][1]}`;
  }
  species[id] = [label, pokemonId[ident] || base];
}

const eggMoves = {};
for (const m of read('egg-moves.ts').matchAll(/\[SpeciesId\.(\w+)\]:\s*\[([^\]]*)\]/g)) {
  const names = m[2].split(',').map(s => s.trim().replace('MoveId.', '')).filter(Boolean).map(n => {
    const mid = moveIdByIdent[n.toLowerCase().replace(/_/g, '-')];
    return moveName[mid] || title(n);
  });
  if (names.length !== 4) throw new Error('Egg moves not 4 for ' + m[1]);
  eggMoves[speciesEnum[m[1]]] = names;
}

console.assert(species[1][0] === 'Bulbasaur' && species[122][0] === 'Mr. Mime', 'species names');
console.assert(species[2019][0] === 'Alolan Rattata' && species[2019][1] > 10000, 'regional form');
console.assert(eggMoves[1][0] === 'Giga Drain', 'egg moves');
const out = path.join(__dirname, '../../tools/pokerogue/data.json');
fs.writeFileSync(out, JSON.stringify({ species, eggMoves, natures }));
console.log('species', Object.keys(species).length, 'eggMoves', Object.keys(eggMoves).length, 'bytes', fs.statSync(out).size);
