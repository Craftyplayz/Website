// Re-render the 3D item previews (assets/*.jpg) from their .glb with model-viewer in headless Chrome.
// models.json's own previews draw some models with layers missing, so rerun this after new 3D items are added.
// Usage: node _dev/scripts/render-model-previews.js minecraft/unlimitedsmp/models   (ONLY=<name> or CAT=<category> to limit; ORBIT="215deg 65deg 110%" to override the angle)
const http = require('http'), fs = require('fs'), path = require('path'), os = require('os');
const { spawn } = require('child_process');
const DIR = path.resolve(process.argv[2]);
const items = JSON.parse(fs.readFileSync(path.join(DIR, 'models.json'), 'utf8')).items.filter(i => i.is_3d && i.model_3d && i.image && (!process.env.ONLY || i.name === process.env.ONLY) && (!process.env.CAT || i.category === process.env.CAT));
const page = `<!doctype html><body style="margin:0;background:#000">
<script type="module">
await import('https://ajax.googleapis.com/ajax/libs/model-viewer/4.0.0/model-viewer.min.js');
const items = ${JSON.stringify(items.map(i => ({ glb: i.model_3d, jpg: i.image, orbit: process.env.ORBIT || ((i.category === 'Rat' ? '35' : '215') + 'deg 65deg 110%') })))};
// background colour from the corner of an existing preview, so new and old match
const ref = new Image(); ref.src = '/' + items[0].jpg; await ref.decode();
const c0 = document.createElement('canvas'); c0.width = c0.height = 1; const x0 = c0.getContext('2d'); x0.drawImage(ref, 0, 0, 1, 1, 0, 0, 1, 1);
const [r, g, b] = x0.getImageData(0, 0, 1, 1).data; const bg = 'rgb(' + r + ',' + g + ',' + b + ')';
for (const it of items) {
  const mv = document.createElement('model-viewer');
  mv.style.cssText = 'width:256px;height:256px;display:block';
  mv.setAttribute('camera-orbit', it.orbit); // rats face +Z, chess pieces and statues face -Z
  mv.setAttribute('interaction-prompt', 'none');
  mv.setAttribute('shadow-intensity', '0');
  const loaded = new Promise((ok, no) => { mv.addEventListener('load', ok, { once: true }); mv.addEventListener('error', no, { once: true }); });
  mv.src = '/' + it.glb; document.body.append(mv);
  try { await loaded; } catch (e) { await fetch('/done?err=' + encodeURIComponent(it.glb)); mv.remove(); continue; }
  mv.model.materials.forEach(m => [m.pbrMetallicRoughness.baseColorTexture, m.emissiveTexture].forEach(t => { const s = t && t.texture && t.texture.sampler; if (s) { s.setMagFilter(9728); s.setMinFilter(9728); } }));
  await new Promise(r => setTimeout(r, 800));
  const blob = await mv.toBlob({ mimeType: 'image/png', idealAspect: true });
  const img = await createImageBitmap(blob);
  const c = document.createElement('canvas'); c.width = c.height = 256; const x = c.getContext('2d');
  x.fillStyle = bg; x.fillRect(0, 0, 256, 256); const k = Math.min(256 / img.width, 256 / img.height), w = img.width * k, h = img.height * k; // keep proportions
  x.drawImage(img, (256 - w) / 2, (256 - h) / 2, w, h);
  const jpg = await new Promise(r => c.toBlob(r, 'image/jpeg', 0.9));
  await fetch('/save?f=' + encodeURIComponent(it.jpg), { method: 'POST', body: jpg });
  mv.remove();
}
await fetch('/done');
</script>`;
const srv = http.createServer((q, s) => {
    const u = new URL(q.url, 'http://x');
    if (u.pathname === '/') return s.end(page);
    if (u.pathname === '/save') {
        const f = path.join(DIR, u.searchParams.get('f'));
        if (!f.startsWith(DIR + path.sep) || !f.endsWith('.jpg')) return s.end('no');
        const parts = []; q.on('data', d => parts.push(d)).on('end', () => { fs.writeFileSync(f, Buffer.concat(parts)); console.log('saved', path.basename(f)); s.end('ok'); });
        return;
    }
    if (u.pathname === '/done') { s.end('ok'); if (u.searchParams.get('err')) return console.log('FAILED', u.searchParams.get('err')); console.log('done'); chrome.kill(); srv.close(); return; }
    const f = path.join(DIR, decodeURIComponent(u.pathname));
    if (!f.startsWith(DIR + path.sep) || !fs.existsSync(f)) { s.statusCode = 404; return s.end(); }
    s.setHeader('Content-Type', f.endsWith('.glb') ? 'model/gltf-binary' : f.endsWith('.png') ? 'image/png' : 'image/jpeg');
    fs.createReadStream(f).pipe(s);
}).listen(8231, '127.0.0.1');
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'mdl-render-'));
const chrome = spawn('C:/Program Files/Google/Chrome/Application/chrome.exe', ['--headless=new', `--user-data-dir=${profile}`, '--no-first-run', '--enable-unsafe-swiftshader', '--use-angle=swiftshader', '--ignore-gpu-blocklist', '--window-size=400,400', '--force-device-scale-factor=2', 'http://127.0.0.1:8231/'], { stdio: 'ignore' });
setTimeout(() => { console.log('timeout'); chrome.kill(); process.exit(1); }, 180000);
srv.on('close', () => process.exit(0));
