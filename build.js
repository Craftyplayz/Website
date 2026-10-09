// Re-inserts the shared partials (partials/*.html) into every page between
//   <!--#name-->  ...  <!--#/name-->
// markers. Run with: node build.js
const fs = require('fs');
const path = require('path');

const root = __dirname;
const partials = {};
for (const f of fs.readdirSync(path.join(root, 'partials'))) {
    partials[path.basename(f, '.html')] = fs.readFileSync(path.join(root, 'partials', f), 'utf8').trimEnd();
}

const skip = new Set(['.git', 'node_modules', 'partials', 'adarkroom', 'Factorio']);
function* walk(dir) {
    for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
        if (e.isDirectory()) {
            if (!skip.has(e.name) && !e.name.startsWith('L0laapk3')) yield* walk(path.join(dir, e.name));
        } else if (e.name.endsWith('.html')) {
            yield path.join(dir, e.name);
        }
    }
}

let changed = 0;
for (const file of walk(root)) {
    const src = fs.readFileSync(file, 'utf8');
    const out = src.replace(/<!--#(\w+)-->[\s\S]*?<!--#\/\1-->/g, (m, name) =>
        name in partials ? `<!--#${name}-->\n${partials[name]}\n<!--#/${name}-->` : m);
    if (out !== src) {
        fs.writeFileSync(file, out);
        changed++;
    }
}
console.log(`updated ${changed} page(s)`);

// Directory manifests (replace PHP scandir/glob). Output: data/manifests/<name>.json
const manifests = {
    'slideshow-homepage': 'images/SlideShows/homepage',
    'slideshow-homepagemobile': 'images/SlideShows/homepagemobile',
    'quotes': 'images/quotes',
};
fs.mkdirSync(path.join(root, 'data', 'manifests'), { recursive: true });
for (const [name, dir] of Object.entries(manifests)) {
    const files = fs.readdirSync(path.join(root, dir), { withFileTypes: true })
        .filter(e => e.isFile()).map(e => e.name).sort();
    fs.writeFileSync(path.join(root, 'data', 'manifests', name + '.json'), JSON.stringify(files, null, 1) + '\n');
}
