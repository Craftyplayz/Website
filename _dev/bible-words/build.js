// Builds one Bible's data for /extras/bible-words/:
//   words-<id>.txt        "word<tab>count<tab>first verse index", one per line
//   verses/<id>-<n>.json  {index: [reference, text]} for every first verse, index / 2000 = n
// Sources (public domain): https://openbible.com/textfiles/<id>.txt, BSB from https://bereanbible.com/bsb.txt
// Usage: node build.js kjv.txt ../../extras/bible-words kjv
const fs = require('fs');
const [, , src, out, id] = process.argv;
const SHARD = 2000; // must match the page
const count = new Map(), first = new Map(), verses = [];
const add = (w, vi) => { count.set(w, (count.get(w) || 0) + 1); if (!first.has(w)) first.set(w, vi); };
for (const l of fs.readFileSync(src, 'utf8').split(/\r?\n/)) {
  const m = l.match(/^(.+ \d+:\d+)\t(.*)$/); // "Genesis 1:1<tab>text"; skips header lines
  if (!m) continue;
  const vi = verses.push([m[1], m[2].trim()]) - 1;
  for (const w of m[2].toLowerCase().replace(/[‘’]/g, "'").match(/[a-z]+(?:'[a-z]+)*/g) || []) {
    add(w, vi);
    if (w.endsWith("'s")) add(w.slice(0, -2), vi); // "lord's" also counts as "lord"
  }
}
fs.writeFileSync(`${out}/words-${id}.txt`, [...count.keys()].sort().map((w) => `${w}\t${count.get(w)}\t${first.get(w)}`).join('\n') + '\n');
const shards = {};
for (const vi of new Set(first.values())) (shards[Math.floor(vi / SHARD)] ??= {})[vi] = verses[vi];
fs.mkdirSync(`${out}/verses`, { recursive: true });
for (const n in shards) fs.writeFileSync(`${out}/verses/${id}-${n}.json`, JSON.stringify(shards[n]));
// self-check: every word's first verse really contains it
for (const [w, vi] of first) if (!verses[vi][1].toLowerCase().replace(/[‘’]/g, "'").includes(w)) throw new Error(`${w} not in ${verses[vi][0]}`);
console.log(id, count.size, 'words,', Object.keys(shards).length, 'verse files');
