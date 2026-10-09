// Re-inserts the shared partials (partials/*.html) into every page between
//   <!--#name-->  ...  <!--#/name-->
// markers. Run with: node build.js
const fs = require('fs');
const { execFileSync } = require('child_process');
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

// <!--#lastmod-->..<!--#/lastmod--> = date of the file's last git commit (replaces PHP filemtime)
function lastModified(file) {
    try {
        const iso = execFileSync('git', ['log', '-1', '--format=%cs', '--', file], { cwd: root }).toString().trim();
        if (!iso) return null;
        const [y, m, d] = iso.split('-').map(Number);
        const sfx = (d % 10 === 1 && d !== 11) ? 'st' : (d % 10 === 2 && d !== 12) ? 'nd' : (d % 10 === 3 && d !== 13) ? 'rd' : 'th';
        const month = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'][m - 1];
        return `${d}${sfx} ${month} ${y}`;
    } catch (e) {
        return null;
    }
}

let changed = 0;
for (const file of walk(root)) {
    const src = fs.readFileSync(file, 'utf8');
    const out = src.replace(/<!--#(\w+)-->[\s\S]*?<!--#\/\1-->/g, (m, name) => {
        if (name === 'lastmod') {
            const date = lastModified(path.relative(root, file));
            return date ? `<!--#lastmod-->${date}<!--#/lastmod-->` : m;
        }
        return name in partials ? `<!--#${name}-->\n${partials[name]}\n<!--#/${name}-->` : m;
    });
    if (out !== src) {
        fs.writeFileSync(file, out);
        changed++;
    }
}
console.log(`updated ${changed} page(s)`);

// Directory manifests (replace PHP scandir/glob). Output: data/manifests/<name>.json
// name -> [directory, options]; options: files (files only), exclude (names to skip),
// images (image files only), reverse, dirs (nested { subdir: [files] })
const IMAGE_EXT = /\.(jpe?g|png|gif|webp|svg|bmp)$/i;
const manifests = {
    'slideshow-homepage': ['images/SlideShows/homepage'],
    'slideshow-homepagemobile': ['images/SlideShows/homepagemobile'],
    'quotes': ['images/quotes'],
    'code': ['Code', { files: true, exclude: ['index.php', 'index.html'] }],
    'code-downloads': ['Code/downloads', { files: true, exclude: ['index.php', 'index.html'] }],
    'code-bs': ['Code/bs', { exclude: ['index.php', 'index.html'] }],
    'code-minecraft': ['Code/minecraft', { exclude: ['index.php', 'index.html'] }],
    'code-items': ['Code/minecraft/items_1.21.11', { images: true }],
    'items': ['minecraft/items_1.21.11', { images: true }],
    'itemlist': ['minecraft/itemlist', { images: true }],
    'markers': ['images/markers', { files: true }],
    'mapart': ['images/minecraft/mapart', { files: true }],
    'skins': ['images/minecraft/skins', { files: true, png: true }],
    'screenshots': ['images/minecraft/screenshots', { files: true, reverse: true }],
    'life': ['minecraft/life', { dirs: true }],
    'games': ['games', { onlyDirs: true }],
    'guesswho-atla': ['games/guesswho/atla/assets/imgs/people', { files: true }],
    'guesswho-template': ['games/guesswho/template/assets/imgs/people', { files: true }],
    'pets': ['images/galleries/pets', { dirs: true, exclude: ['!videos', 'raw', 'Raw'] }],
};
fs.mkdirSync(path.join(root, 'data', 'manifests'), { recursive: true });
for (const [name, [dir, o = {}]] of Object.entries(manifests)) {
    const abs = path.join(root, dir);
    if (!fs.existsSync(abs)) continue;
    const list = d => fs.readdirSync(d, { withFileTypes: true })
        .filter(e => !(o.exclude || []).includes(e.name))
        .filter(e => !o.files || e.isFile())
        .filter(e => !o.onlyDirs || e.isDirectory())
        .filter(e => !o.images || IMAGE_EXT.test(e.name))
        .filter(e => !o.png || /\.png$/.test(e.name))
        .map(e => e.name).sort();
    let result;
    if (o.dirs) {
        result = {};
        for (const e of fs.readdirSync(abs, { withFileTypes: true })) {
            if (e.isDirectory()) result[e.name] = list(path.join(abs, e.name));
        }
    } else {
        result = list(abs);
        if (o.reverse) result.reverse();
    }
    fs.writeFileSync(path.join(root, 'data', 'manifests', name + '.json'), JSON.stringify(result) + '\n');
}
