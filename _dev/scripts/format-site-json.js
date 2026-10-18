// Re-format data/site.json: normal 4-space indentation, but each page on one line so the
// file stays easy to scan and edit by hand.  usage: node _dev/scripts/format-site-json.js
const fs = require('fs');
const path = require('path');
const file = path.join(__dirname, '..', '..', 'data', 'site.json');
const data = JSON.parse(fs.readFileSync(file, 'utf8'));

const inline = (v) => Array.isArray(v)
    ? '[' + v.map(inline).join(', ') + ']'
    : v && typeof v === 'object'
        ? '{ ' + Object.entries(v).map(([k, x]) => JSON.stringify(k) + ': ' + inline(x)).join(', ') + ' }'
        : JSON.stringify(v);

function fmt(v, indent, key) {
    const pad = ' '.repeat(indent);
    if ((key === 'pages' || key === 'links') && Array.isArray(v)) {
        return '[\n' + v.map((p) => pad + '    ' + inline(p)).join(',\n') + '\n' + pad + ']';
    }
    if (Array.isArray(v)) return '[\n' + v.map((x) => pad + '    ' + fmt(x, indent + 4)).join(',\n') + '\n' + pad + ']';
    if (v && typeof v === 'object') {
        return '{\n' + Object.entries(v).map(([k, x]) => pad + '    ' + JSON.stringify(k) + ': ' + fmt(x, indent + 4, k)).join(',\n') + '\n' + pad + '}';
    }
    return JSON.stringify(v);
}

fs.writeFileSync(file, fmt(data, 0) + '\n');
JSON.parse(fs.readFileSync(file, 'utf8'));
console.log('formatted ' + file);
