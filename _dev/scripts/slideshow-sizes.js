// Build the WebP copies the slideshow (homepage, /minecraft/, BRB) loads from the raw screenshots,
// plus media/minecraft/screenshots-sizes/manifest.json. Run it after adding raw screenshots.
//   usage: npm install sharp   (anywhere, then point NODE_PATH at it)
//          node _dev/scripts/slideshow-sizes.js
// Output: media/minecraft/screenshots-sizes/<width>/<name>.webp for each width below that fits in
// the original. The 960 copy is the low-quality placeholder shown on first paint; the rest are the
// full-quality copies (quality 90 with full-resolution colour, which keeps block edges sharp). The raw PNGs
// (often 20 MB+) are never sent to the slideshow.
const fs = require('fs');
const path = require('path');
const sharp = require('sharp');

const root = path.join(__dirname, '..', '..');
const srcDir = path.join(root, 'media', 'minecraft', 'screenshots-raw');
const outDir = path.join(root, 'media', 'minecraft', 'screenshots-sizes');
const SIZES = [
    { w: 960, quality: 60 },   // low quality placeholder, ~25 KB
    { w: 1280, quality: 90 },
    { w: 1920, quality: 90 },
    { w: 2560, quality: 90 },
    { w: 3840, quality: 90 },
];

(async () => {
    const files = fs.readdirSync(srcDir).filter((f) => /\.(png|webp|jpe?g)$/i.test(f)).sort();
    const manifest = {};
    for (const file of files) {
        const src = path.join(srcDir, file);
        const { width, height } = await sharp(src).metadata();
        const name = file.replace(/\.[^.]+$/, '');
        const entry = { w: width, h: height, sizes: [] };
        for (const { w, quality } of SIZES) {
            if (w > width) continue;
            const dir = path.join(outDir, String(w));
            const out = path.join(dir, name + '.webp');
            fs.mkdirSync(dir, { recursive: true });
            if (!fs.existsSync(out) || fs.statSync(out).mtimeMs < fs.statSync(src).mtimeMs) {
                await sharp(src).resize({ width: w }).webp({ quality, smartSubsample: true, effort: 6 }).toFile(out);
            }
            entry.sizes.push(w);
        }
        manifest[name] = entry;
        console.log(file, width + 'x' + height, entry.sizes.join(' '));
    }
    fs.writeFileSync(path.join(outDir, 'manifest.json'), JSON.stringify(manifest, null, 1) + '\n');
    console.log(files.length + ' images');
})();
