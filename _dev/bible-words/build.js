// Builds a Bible word list: one lowercase word per line.
// Sources (public domain): https://openbible.com/textfiles/<id>.txt, BSB from https://bereanbible.com/bsb.txt
// Usage: node build.js kjv.txt ../../extras/bible-words/words-kjv.txt
const fs = require('fs');
const set = new Set();
for (const l of fs.readFileSync(process.argv[2], 'utf8').split(/\r?\n/)) {
  const m = l.match(/^.+ \d+:\d+\t(.*)$/); // "Genesis 1:1<tab>text"; skips header lines
  if (!m) continue;
  for (const w of m[1].toLowerCase().replace(/[\u2018\u2019]/g, "'").match(/[a-z]+(?:'[a-z]+)*/g) || []) {
    set.add(w);
    if (w.endsWith("'s")) set.add(w.slice(0, -2));
  }
}
fs.writeFileSync(process.argv[3], [...set].sort().join('\n') + '\n');
console.log(process.argv[3], set.size);
