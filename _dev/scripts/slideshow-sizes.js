// Build the resized copies the homepage slideshow loads, plus media/slideshow/manifest.json.
// Run it after adding or removing images in media/slideshow/desktop/.
//   usage: npm install sharp   (anywhere, then point NODE_PATH at it)
//          node _dev/scripts/slideshow-sizes.js
// Output: media/slideshow/sizes/<width>/<name>.webp for each width below that is smaller than
// the original. The 640 copy is the low-quality placeholder shown on first paint.
// Images missing from the manifest still work: the page just serves the original.
const fs = require('fs');
const path = require('path');
const sharp = require('sharp');

const root = path.join(__dirname, '..', '..');
const srcDir = path.join(root, 'media', 'slideshow', 'desktop');
const outDir = path.join(root, 'media', 'slideshow', 'sizes');
const SIZES = [
    { w: 640, quality: 40 },   // low quality placeholder
    { w: 1280, quality: 78 },
    { w: 1920, quality: 80 },
    { w: 2560, quality: 80 },
];

(async () => {
    const files = fs.readdirSync(srcDir).filter((f) => /\.(webp|jpe?g|png)$/i.test(f)).sort();
    const manifest = {};
    for (const file of files) {
        const src = path.join(srcDir, file);
        const { width, height } = await sharp(src).metadata();
        const name = file.replace(/\.[^.]+$/, '') + '.webp';
        const entry = { w: width, h: height, sizes: [] };
        for (const { w, quality } of SIZES) {
            if (w >= width) continue;
            const dir = path.join(outDir, String(w));
            const out = path.join(dir, name);
            fs.mkdirSync(dir, { recursive: true });
            if (!fs.existsSync(out) || fs.statSync(out).mtimeMs < fs.statSync(src).mtimeMs) {
                await sharp(src).resize({ width: w }).webp({ quality, effort: 6 }).toFile(out);
            }
            entry.sizes.push(w);
        }
        manifest[file] = entry;
        console.log(file, width + 'x' + height, entry.sizes.join(' '));
    }
    fs.writeFileSync(path.join(root, 'media', 'slideshow', 'manifest.json'), JSON.stringify(manifest, null, 1) + '\n');
    console.log(files.length + ' images');
})();
