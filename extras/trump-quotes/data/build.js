// Rebuilds tweets.txt + tweets.idx from the Trump Twitter Archive export (tweets.json, 2009 to 8 Jan 2021):
//   https://archive.org/download/trump-tweets/tweets.json   (md5 073b99bfa3fc75a7757ac6edf5d0f767)
// Usage: node build.js path/to/tweets.json
// tweets.txt: one tweet per line, "date \t likes \t retweets \t device \t text" (newlines in text stored as \n)
// tweets.idx: the byte offset of each line as a big-endian uint32, so the page can pick one at random without reading the whole file
const fs = require('fs');
const path = require('path');
const src = process.argv[2];
if (!src) { console.error('usage: node build.js tweets.json'); process.exit(1); }

// the export double-escapes some entities ("&amp;amp;") and drops the ; from others ("&amp,")
const decode = (s) => { let prev; do { prev = s; s = s.replace(/&amp;?/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>'); } while (s !== prev); return s; };
const tweets = JSON.parse(fs.readFileSync(src, 'utf8'))
    .filter((t) => t.isRetweet === 'f' && !/^RT @/.test(t.text))                     // his own tweets only
    .filter((t) => t.text.replace(/https?:\/\/\S+/g, '').trim().length >= 20)          // skip link-only posts
    .sort((a, b) => a.date.localeCompare(b.date));

const lines = tweets.map((t) => [t.date, t.favorites | 0, t.retweets | 0, t.device || '', decode(t.text)]
    .map((v) => String(v).replace(/[\t\r]/g, ' ').replace(/\n/g, '\n')).join('\t') + '\n');

const idx = Buffer.alloc(lines.length * 4);
let offset = 0;
lines.forEach((l, i) => { idx.writeUInt32BE(offset, i * 4); offset += Buffer.byteLength(l); });
const txt = lines.join('');

// self-check: every index entry points at the start of its own line
const buf = Buffer.from(txt);
console.assert(buf.length === offset, 'size mismatch');
for (const i of [0, 1, lines.length >> 1, lines.length - 1]) {
    const o = idx.readUInt32BE(i * 4);
    console.assert(buf.toString('utf8', o, o + Buffer.byteLength(lines[i])) === lines[i], 'bad offset at ' + i);
}

fs.writeFileSync(path.join(__dirname, 'tweets.txt'), txt);
fs.writeFileSync(path.join(__dirname, 'tweets.idx'), idx);
console.log(lines.length + ' tweets, ' + offset + ' bytes');
