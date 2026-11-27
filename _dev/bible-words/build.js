// Builds the KJV word list: one lowercase word per line.
// Source: https://openbible.com/textfiles/kjv.txt (public domain). Usage: node build.js kjv.txt ../../extras/bible-words/words.txt
const fs = require('fs');
const lines = fs.readFileSync(process.argv[2], 'utf8').split(/\r?\n/).slice(2);
const set = new Set();
for (const l of lines) {
  const t = l.split('\t')[1];
  if (!t) continue;
  for (const w of t.toLowerCase().replace(/[’]/g, "'").match(/[a-z]+(?:'[a-z]+)*/g) || []) {
    set.add(w);
    if (w.endsWith("'s")) set.add(w.slice(0, -2));
  }
}
fs.writeFileSync(process.argv[3], [...set].sort().join('\n') + '\n');
console.log(set.size);
