<?php
$site_page = ['components' => true, 'ui' => true, 'css' => ['/assets/css/pages/tools-qr-code.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>

    <main class="page" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">QR Code Generator</h1>
        <p class="page-lede">Make a QR code for a link, Wi-Fi network, contact and more. It's made in your browser, so nothing you type leaves this page.</p>

        <div class="qr-wrap">
            <form class="qr-controls" id="qrForm" autocomplete="off">
                <section class="card">
                    <h2>What's in it</h2>
                    <div class="stabs" id="qrTypes">
                        <button type="button" class="on" data-type="text" aria-pressed="true">Link or text</button>
                        <button type="button" data-type="wifi" aria-pressed="false">Wi-Fi</button>
                        <button type="button" data-type="email" aria-pressed="false">Email</button>
                        <button type="button" data-type="phone" aria-pressed="false">Phone</button>
                        <button type="button" data-type="sms" aria-pressed="false">SMS</button>
                        <button type="button" data-type="contact" aria-pressed="false">Contact</button>
                    </div>

                    <div class="qr-fields" data-for="text">
                        <label class="wide">Link or text <textarea class="field" name="text" rows="3" placeholder="https://example.com"></textarea></label>
                    </div>
                    <div class="qr-fields" data-for="wifi" hidden>
                        <label>Network name <input class="field" name="ssid" spellcheck="false"></label>
                        <label>Password <input class="field" name="wpass" spellcheck="false"></label>
                        <label>Security
                            <select class="field" name="wsec">
                                <option value="WPA">WPA / WPA2 / WPA3</option>
                                <option value="WEP">WEP</option>
                                <option value="nopass">None (open)</option>
                            </select>
                        </label>
                        <label class="qr-check"><input type="checkbox" name="whidden"> Hidden network</label>
                    </div>
                    <div class="qr-fields" data-for="email" hidden>
                        <label>To <input class="field" type="email" name="to" placeholder="name@example.com"></label>
                        <label>Subject <input class="field" name="subject"></label>
                        <label class="wide">Message <textarea class="field" name="ebody" rows="3"></textarea></label>
                    </div>
                    <div class="qr-fields" data-for="phone" hidden>
                        <label>Phone number <input class="field" type="tel" name="tel" placeholder="+44 7700 900000"></label>
                    </div>
                    <div class="qr-fields" data-for="sms" hidden>
                        <label>Phone number <input class="field" type="tel" name="smsto" placeholder="+44 7700 900000"></label>
                        <label class="wide">Message <textarea class="field" name="smsbody" rows="3"></textarea></label>
                    </div>
                    <div class="qr-fields" data-for="contact" hidden>
                        <label>First name <input class="field" name="first"></label>
                        <label>Last name <input class="field" name="last"></label>
                        <label>Phone <input class="field" type="tel" name="ctel"></label>
                        <label>Email <input class="field" type="email" name="cmail"></label>
                        <label>Company <input class="field" name="org"></label>
                        <label>Website <input class="field" type="url" name="curl" placeholder="https://"></label>
                    </div>
                </section>

                <section class="card">
                    <h2>Look</h2>
                    <div class="qr-fields qr-colors">
                        <label>Dots <input class="field qr-swatch" type="color" name="fg" value="#000000"></label>
                        <label>Corners <input class="field qr-swatch" type="color" name="eyec" value="#000000"></label>
                        <label>Background <input class="field qr-swatch" type="color" name="bg" value="#ffffff"></label>
                        <label class="qr-check wide"><input type="checkbox" name="clear"> Transparent background</label>
                    </div>
                    <div class="qr-fields">
                        <label>Dot shape
                            <select class="field" name="dot">
                                <option value="square">Square</option>
                                <option value="rounded">Rounded</option>
                                <option value="dots">Dots</option>
                            </select>
                        </label>
                        <label>Corner shape
                            <select class="field" name="eye">
                                <option value="square">Square</option>
                                <option value="rounded">Rounded</option>
                                <option value="circle">Circle</option>
                            </select>
                        </label>
                    </div>
                    <div class="qr-fields">
                        <div class="qr-logo">
                            <img id="logoThumb" alt="" hidden>
                            <button class="btn small" type="button" id="logoPick">Add a logo</button>
                            <button class="btn small" type="button" id="logoDrop" hidden>Remove</button>
                            <input type="file" id="logoFile" accept="image/*" hidden>
                        </div>
                        <label id="logoSizeRow" hidden>Logo size <output id="logoSizeOut">22%</output>
                            <input type="range" name="logosize" min="10" max="30" value="22">
                        </label>
                    </div>
                </section>

                <details class="card qr-adv">
                    <summary>Advanced</summary>
                    <div class="qr-fields">
                        <label>Error correction
                            <select class="field" name="ecl">
                                <option value="L">Low (7% can be damaged)</option>
                                <option value="M" selected>Medium (15%)</option>
                                <option value="Q">Quartile (25%)</option>
                                <option value="H">High (30%)</option>
                            </select>
                        </label>
                        <label>Size (version)
                            <select class="field" name="version"><option value="0">Auto (smallest that fits)</option></select>
                        </label>
                        <label>Encoding
                            <select class="field" name="mode">
                                <option value="">Auto</option>
                                <option value="Numeric">Numeric (0-9 only)</option>
                                <option value="Alphanumeric">Alphanumeric (A-Z 0-9 $%*+-./: and space)</option>
                                <option value="Byte">Byte (any text, UTF-8)</option>
                            </select>
                        </label>
                        <label>Download size
                            <select class="field" name="px">
                                <option value="256">256 px</option>
                                <option value="512">512 px</option>
                                <option value="1024" selected>1024 px</option>
                                <option value="2048">2048 px</option>
                                <option value="4096">4096 px</option>
                            </select>
                        </label>
                        <label>Quiet zone (border) <output id="marginOut">4 modules</output>
                            <input type="range" name="margin" min="0" max="10" value="4">
                        </label>
                        <p class="qr-hint muted">Higher error correction makes a bigger code that still scans when scratched or covered. Most scanners want a quiet zone of at least 4.</p>
                    </div>
                </details>
            </form>

            <section class="card qr-preview" aria-label="Preview">
                <div class="qr-box" id="qrBox"><p>Type something to see your QR code.</p></div>
                <p class="muted qr-meta" id="qrMeta"></p>
                <p class="err" id="qrErr" hidden></p>
                <p class="note" id="qrWarn" hidden></p>
                <div class="qr-actions">
                    <button class="btn primary" type="button" id="dlPng" disabled>Download PNG</button>
                    <button class="btn" type="button" id="dlSvg" disabled>SVG</button>
                    <button class="btn" type="button" id="copyImg" disabled>Copy</button>
                </div>
            </section>
        </div>
    </main>

    <?php site_footer(null, false); ?>

    <script src="<?= asset('/tools/qr-code/qrcode.js') ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const { $, $$, toast, debounce } = UI;
            qrcode.stringToBytes = qrcode.stringToBytesFuncs['UTF-8'];

            const form = $('#qrForm'), box = $('#qrBox'), meta = $('#qrMeta'), errBox = $('#qrErr'), warnBox = $('#qrWarn');
            const el = form.elements, v = n => el[n].value;
            const btns = [$('#dlPng'), $('#dlSvg'), $('#copyImg')];
            let type = 'text', logo = null, current = null;

            for (let i = 1; i <= 40; i++) el.version.add(new Option(`Version ${i} (${17 + 4 * i} × ${17 + 4 * i})`, i));
            if (!window.ClipboardItem) $('#copyImg').hidden = true;

            /* ---------- content: each type builds the text the QR code holds ---------- */
            const wesc = s => s.replace(/([\\;,:"])/g, '\\$1');
            const vesc = s => s.replace(/([\\;,])/g, '\\$1').replace(/\r?\n/g, '\\n');
            const digits = s => s.replace(/[^\d+]/g, '');
            const query = o => {
                const q = Object.entries(o).filter(([, x]) => x).map(([k, x]) => k + '=' + encodeURIComponent(x)).join('&');
                return q ? '?' + q : '';
            };
            const builders = {
                text: () => v('text'),
                wifi: () => v('ssid') && `WIFI:T:${v('wsec')};S:${wesc(v('ssid'))};` +
                    (v('wsec') === 'nopass' ? '' : `P:${wesc(v('wpass'))};`) + (el.whidden.checked ? 'H:true;' : '') + ';',
                email: () => v('to') && 'mailto:' + v('to').trim() + query({ subject: v('subject'), body: v('ebody') }),
                phone: () => digits(v('tel')) && 'tel:' + digits(v('tel')),
                sms: () => digits(v('smsto')) && `SMSTO:${digits(v('smsto'))}:${v('smsbody')}`,
                contact: () => {
                    const f = v('first').trim(), l = v('last').trim();
                    if (!(f || l || v('ctel') || v('cmail') || v('org'))) return '';
                    return ['BEGIN:VCARD', 'VERSION:3.0', `N:${vesc(l)};${vesc(f)};;;`, `FN:${vesc((f + ' ' + l).trim() || v('org'))}`,
                        v('org') && 'ORG:' + vesc(v('org')), v('ctel') && 'TEL:' + v('ctel').trim(),
                        v('cmail') && 'EMAIL:' + v('cmail').trim(), v('curl') && 'URL:' + v('curl').trim(), 'END:VCARD'].filter(Boolean).join('\r\n');
                }
            };

            /* ---------- geometry: one set of SVG paths, used by the preview, the SVG and the PNG ---------- */
            const f = n => +n.toFixed(2);
            const rr = (x, y, w, h, r) => r
                ? `M${f(x + r)},${f(y)}h${f(w - 2 * r)}a${r},${r} 0 0 1 ${r},${r}v${f(h - 2 * r)}a${r},${r} 0 0 1 -${r},${r}` +
                  `h${f(2 * r - w)}a${r},${r} 0 0 1 -${r},-${r}v${f(2 * r - h)}a${r},${r} 0 0 1 ${r},-${r}z`
                : `M${f(x)},${f(y)}h${w}v${h}h-${w}z`;
            const DOT = { square: (x, y) => rr(x, y, 1, 1, 0), rounded: (x, y) => rr(x, y, 1, 1, .3), dots: (x, y) => rr(x + .1, y + .1, .8, .8, .4) };
            const EYE = { square: [0, 0, 0], rounded: [2, 1, .8], circle: [3.5, 2.5, 1.5] }; // outer ring, its hole, centre

            function geometry(qr) {
                const n = qr.getModuleCount(), m = +v('margin'), dot = DOT[v('dot')], R = EYE[v('eye')];
                const isEye = (r, c) => (r < 7 && c < 7) || (r < 7 && c >= n - 7) || (r >= n - 7 && c < 7);
                let hole = null;
                if (logo) {
                    let s = Math.round(n * v('logosize') / 100);
                    if ((n - s) % 2) s++; // keep the hole centred on whole modules
                    hole = { o: (n - s) / 2, s };
                }
                const inHole = (r, c) => hole && r >= hole.o && r < hole.o + hole.s && c >= hole.o && c < hole.o + hole.s;
                let dots = '', eyes = '';
                for (let r = 0; r < n; r++) for (let c = 0; c < n; c++)
                    if (qr.isDark(r, c) && !isEye(r, c) && !inHole(r, c)) dots += dot(c + m, r + m);
                for (const [x, y] of [[0, 0], [n - 7, 0], [0, n - 7]])
                    eyes += rr(x + m, y + m, 7, 7, R[0]) + rr(x + m + 1, y + m + 1, 5, 5, R[1]) + rr(x + m + 2, y + m + 2, 3, 3, R[2]);
                return { n, size: n + 2 * m, dots, eyes, logo: hole && { x: hole.o + m + .5, w: hole.s - 1 } };
            }

            // the logo box fitted to the image's own shape
            const logoRect = g => {
                const { x, w } = g.logo, k = logo.w / logo.h;
                const dw = k >= 1 ? w : w * k, dh = k >= 1 ? w / k : w;
                return { x: x + (w - dw) / 2, y: x + (w - dh) / 2, w: dw, h: dh };
            };

            function svgOf(g) {
                const bg = el.clear.checked ? '' : `<rect width="${g.size}" height="${g.size}" fill="${v('bg')}"/>`;
                let img = '';
                if (g.logo) {
                    const r = logoRect(g);
                    img = `<image href="${logo.url}" x="${f(r.x)}" y="${f(r.y)}" width="${f(r.w)}" height="${f(r.h)}"/>`;
                }
                return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${g.size} ${g.size}" role="img" aria-label="QR code">${bg}` +
                    `<path fill="${v('fg')}" d="${g.dots}"/><path fill="${v('eyec')}" fill-rule="evenodd" d="${g.eyes}"/>${img}</svg>`;
            }

            function pngOf(g) {
                const px = +v('px'), c = document.createElement('canvas'), x = c.getContext('2d');
                c.width = c.height = px;
                x.scale(px / g.size, px / g.size);
                if (!el.clear.checked) { x.fillStyle = v('bg'); x.fillRect(0, 0, g.size, g.size); }
                x.fillStyle = v('fg'); x.fill(new Path2D(g.dots));
                x.fillStyle = v('eyec'); x.fill(new Path2D(g.eyes), 'evenodd');
                if (g.logo) { const r = logoRect(g); x.drawImage(logo.img, r.x, r.y, r.w, r.h); }
                return new Promise(ok => c.toBlob(ok, 'image/png'));
            }

            /* ---------- warnings ---------- */
            const lum = hex => {
                const [r, g, b] = [1, 3, 5].map(i => parseInt(hex.substr(i, 2), 16) / 255).map(c => c <= .03928 ? c / 12.92 : ((c + .055) / 1.055) ** 2.4);
                return .2126 * r + .7152 * g + .0722 * b;
            };
            function warning() {
                if (el.clear.checked) return 'Transparent background: put the code on something light, or scanners may miss it.';
                const b = lum(v('bg')), d = Math.max(lum(v('fg')), lum(v('eyec')));
                if (d > b) return 'Light dots on a dark background don\'t scan in every app.';
                if ((b + .05) / (d + .05) < 4) return 'Low contrast between the dots and background. Some scanners may struggle.';
                return '';
            }

            /* ---------- render ---------- */
            const autoMode = t => /^\d+$/.test(t) ? 'Numeric' : /^[0-9A-Z $%*+\-./:]+$/.test(t) ? 'Alphanumeric' : 'Byte';
            const MODE_ERR = { Numeric: 'Numeric encoding only fits the digits 0-9.', Alphanumeric: 'Alphanumeric encoding only fits A-Z (capitals), 0-9, space and $%*+-./:' };

            function fail(msg) {
                current = null;
                box.innerHTML = '<p>No QR code yet.</p>';
                meta.textContent = '';
                errBox.textContent = msg; errBox.hidden = !msg;
                warnBox.hidden = true;
                btns.forEach(b => b.disabled = true);
            }

            function render() {
                const text = builders[type]();
                if (!text) { fail(''); box.innerHTML = '<p>Type something to see your QR code.</p>'; return; }
                const mode = v('mode') || autoMode(text);
                if (MODE_ERR[mode] && autoMode(text) !== mode && !(mode === 'Alphanumeric' && autoMode(text) === 'Numeric')) return fail(MODE_ERR[mode]);
                let qr;
                try {
                    qr = qrcode(+v('version'), v('ecl'));
                    qr.addData(text, mode);
                    qr.make();
                } catch (e) {
                    const ecl = el.ecl.selectedOptions[0].text.split(' ')[0].toLowerCase();
                    return fail(+v('version')
                        ? `That doesn't fit in version ${v('version')} with ${ecl} error correction. Pick a bigger size or Auto.`
                        : `That's too much for one QR code with ${ecl} error correction. Shorten it or lower the error correction.`);
                }
                current = geometry(qr);
                box.innerHTML = svgOf(current);
                meta.textContent = `Version ${(current.n - 17) / 4} · ${current.n} × ${current.n} modules · ${mode} · error correction ${v('ecl')}`;
                errBox.hidden = true;
                const w = warning();
                warnBox.textContent = w; warnBox.hidden = !w;
                btns.forEach(b => b.disabled = false);
            }
            const later = debounce(render, 80);

            form.addEventListener('input', e => {
                if (e.target.name === 'margin') $('#marginOut').textContent = `${v('margin')} module${v('margin') === '1' ? '' : 's'}`;
                if (e.target.name === 'logosize') $('#logoSizeOut').textContent = v('logosize') + '%';
                later();
            });
            form.addEventListener('submit', e => e.preventDefault());

            $('#qrTypes').addEventListener('click', e => {
                const b = e.target.closest('button');
                if (!b) return;
                type = b.dataset.type;
                $$('#qrTypes button').forEach(x => { x.classList.toggle('on', x === b); x.setAttribute('aria-pressed', x === b); });
                $$('.qr-fields[data-for]').forEach(x => x.hidden = x.dataset.for !== type);
                $(`.qr-fields[data-for="${type}"] .field`).focus();
                render();
            });

            /* ---------- logo: shrunk to 512px so the SVG stays small ---------- */
            $('#logoPick').addEventListener('click', () => $('#logoFile').click());
            $('#logoFile').addEventListener('change', e => {
                const file = e.target.files[0];
                if (!file) return;
                const img = new Image(), src = URL.createObjectURL(file);
                img.onload = () => {
                    const k = Math.min(1, 512 / Math.max(img.width, img.height)), c = document.createElement('canvas');
                    c.width = Math.round(img.width * k) || 512; c.height = Math.round(img.height * k) || 512;
                    c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
                    URL.revokeObjectURL(src);
                    logo = { img: c, url: c.toDataURL('image/png'), w: c.width, h: c.height };
                    $('#logoThumb').src = logo.url;
                    $('#logoThumb').hidden = $('#logoDrop').hidden = $('#logoSizeRow').hidden = false;
                    $('#logoPick').textContent = 'Change logo';
                    if (v('ecl') !== 'H') { el.ecl.value = 'H'; toast('Error correction set to High so the code still scans with a logo.', 'note'); }
                    render();
                };
                img.onerror = () => { URL.revokeObjectURL(src); toast('Couldn\'t read that image. Try a PNG, JPG or SVG.', 'err'); };
                img.src = src;
                e.target.value = '';
            });
            $('#logoDrop').addEventListener('click', () => {
                logo = null;
                $('#logoThumb').hidden = $('#logoDrop').hidden = $('#logoSizeRow').hidden = true;
                $('#logoPick').textContent = 'Add a logo';
                render();
            });

            /* ---------- export ---------- */
            const save = (blob, name) => {
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob); a.download = name; a.click();
                setTimeout(() => URL.revokeObjectURL(a.href), 1000);
            };
            $('#dlPng').addEventListener('click', async () => current && save(await pngOf(current), 'qr-code.png'));
            $('#dlSvg').addEventListener('click', () => current && save(new Blob([svgOf(current)], { type: 'image/svg+xml' }), 'qr-code.svg'));
            $('#copyImg').addEventListener('click', async e => {
                if (!current) return;
                try {
                    await navigator.clipboard.write([new ClipboardItem({ 'image/png': pngOf(current) })]);
                    e.target.textContent = 'Copied!';
                    setTimeout(() => e.target.textContent = 'Copy', 1000);
                } catch (err) { toast('Couldn\'t copy the image. Use Download PNG instead.', 'err'); }
            });

            render();
        });
    </script>
</body>

</html>
