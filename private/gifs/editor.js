// Meme GIF editor (index.php). Splits a GIF into frames with ImageDecoder, lets you place text boxes per frame,
// then uploads the frames as PNGs plus template.json through save.php. gifs/render.php draws the same thing server-side.
document.addEventListener('DOMContentLoaded', () => {
    const { $, esc, toast, copy } = UI;
    const cv = $('#cv'), ctx = cv.getContext('2d'), slider = $('#frame');
    let tpl = null, frames = [], cur = 0, sel = 0, playing = false, timer = 0, loaded = null, framesDirty = false;
    const accent = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim();

    // ---- the same maths as gifs/render.php ----
    function pos(box, f) {
        let a = box.keys[0], b = null;
        for (const k of box.keys) {
            if (k.f > f) { b = k; break; }
            a = k;
        }
        if (a.hide) return null;
        if (box.motion !== 'smooth' || !b || b.hide || a.f > f) return [a.x, a.y];
        const t = (f - a.f) / (b.f - a.f);
        return [a.x + (b.x - a.x) * t, a.y + (b.y - a.y) * t];
    }
    // {name} or {name = fallback}; same pattern as GIF_SLOT in render.php
    const SLOT = /\{\s*([^}=]*?)\s*(?:=\s*([^}]*?)\s*)?\}/g;
    function slots() {   // [{name, fallback}] in URL order; a name used twice shares one URL part
        const out = [];
        for (const box of tpl.boxes) for (const m of box.text.matchAll(SLOT)) {
            if (!out.some((s) => s.name === m[1])) out.push({ name: m[1], fallback: m[2] });
        }
        return out;
    }
    const text = (box) => box.text.replace(SLOT, (m, name, fb) => tries[name] ?? fb ?? '').replace(/ {2,}/g, ' ').trim();
    const tries = {};   // test values typed in the URL parts list
    // widest the text may be: the max width, and never past the picture's edges (text is centred on x)
    const fitWidth = (box, x) => Math.min(box.maxW > 0 ? box.maxW : Infinity, 2 * Math.min(x, tpl.w - x) - 8);

    // ---- fonts: the editor previews with the same .ttf files the server uses ----
    const fontLoads = {};
    function font(file) {
        fontLoads[file] ??= new FontFace('gif-' + file, `url(/gifs/fonts/${encodeURIComponent(file)})`).load()
            .then((f) => { document.fonts.add(f); draw(); }, () => toast(`Couldn't load font ${file}`, 'err'));
        return `"gif-${file}"`;
    }

    function draw() {
        if (!tpl) return;
        ctx.drawImage(frames[cur], 0, 0);
        tpl.boxes.forEach((box, i) => {
            const p = pos(box, cur), s = text(box);
            if (!p || !s) return;
            let size = box.size;
            ctx.font = `${size}px ${font(box.font)}`;
            const w = ctx.measureText(s).width, fit = fitWidth(box, p[0]);
            if (w > fit) { size *= fit / w; ctx.font = `${size}px ${font(box.font)}`; }
            ctx.textAlign = 'center';
            ctx.textBaseline = 'alphabetic';
            if (box.strokeW > 0) {
                ctx.lineWidth = box.strokeW * 2;
                ctx.lineJoin = 'round';
                ctx.strokeStyle = box.stroke;
                ctx.strokeText(s, p[0], p[1]);
            }
            ctx.fillStyle = box.color;
            ctx.fillText(s, p[0], p[1]);
            if (i === sel) {   // max-width guide on the baseline of the selected text
                ctx.save();
                ctx.strokeStyle = accent;
                ctx.lineWidth = 1;
                ctx.setLineDash([4, 4]);
                ctx.strokeRect(p[0] - box.maxW / 2, p[1] - size, box.maxW, size * 1.2);
                ctx.restore();
            }
        });
        slider.value = cur;
        $('#frameNo').textContent = `${cur + 1} / ${frames.length}`;
    }

    // ---- playback ----
    function setPlaying(on) {
        playing = on;
        clearTimeout(timer);
        $('#play').textContent = on ? 'Pause' : 'Play';
        if (on) tick();
    }
    function tick() {
        const d = tpl.delays[cur];
        timer = setTimeout(() => { cur = (cur + 1) % frames.length; draw(); tick(); }, d <= 1 ? 100 : d * 10);   // browsers treat 0-1 as 10
    }
    $('#play').addEventListener('click', () => setPlaying(!playing));
    slider.addEventListener('input', () => { setPlaying(false); cur = +slider.value; draw(); renderKeys(); });

    // ---- loading ----
    function newBox() {
        return {
            text: `{text${tpl.boxes.length + 1}}`, font: GIFS.fonts[0], size: Math.round(tpl.h / 8), color: '#ffffff', stroke: '#000000', strokeW: 2,
            maxW: Math.round(tpl.w * 0.9), motion: 'step', keys: [{ f: 0, x: Math.round(tpl.w / 2), y: Math.round(tpl.h * 0.92) }],
        };
    }
    function open(t, fr, slug) {
        setPlaying(false);
        tpl = t; frames = fr; cur = 0; sel = 0; loaded = slug;
        cv.width = tpl.w; cv.height = tpl.h;
        cv.style.aspectRatio = `${tpl.w} / ${tpl.h}`;
        slider.max = frames.length - 1;
        const [g, n] = (slug || '/').split('/');
        if (slug) { $('#group').value = g; $('#name').value = n; }
        $('#editor').classList.remove('hide');
        renderBoxes();
        draw();
    }

    async function fromGif(file) {
        if (!('ImageDecoder' in window)) return toast("This browser can't split GIFs. Use Chrome or Edge.", 'err');
        try {
            const dec = new ImageDecoder({ data: await file.arrayBuffer(), type: 'image/gif' });
            await dec.tracks.ready;
            await dec.completed;
            const n = dec.tracks.selectedTrack.frameCount, fr = [], delays = [];
            for (let i = 0; i < n; i++) {
                const { image } = await dec.decode({ frameIndex: i });
                fr.push(await createImageBitmap(image));
                delays.push(Math.max(0, Math.round((image.duration ?? 100000) / 10000)));   // microseconds -> GIF hundredths
                image.close();
            }
            dec.close();
            tpl = { w: fr[0].width, h: fr[0].height, delays, boxes: [] };
            tpl.boxes.push(newBox());
            framesDirty = true;
            open(tpl, fr, null);
            $('#name').value = file.name.replace(/\.gif$/i, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
            $('#group').focus();
        } catch (e) {
            toast(`Couldn't read that GIF: ${e.message}`, 'err');
        }
    }

    async function fromTemplate(slug) {
        const v = GIFS.templates[slug], base = `/gifs/templates/${slug}`;
        try {
            const t = await (await fetch(`${base}/template.json?v=${v}`)).json();
            const fr = await Promise.all(t.delays.map(async (d, i) => {
                const r = await fetch(`${base}/frames/${String(i).padStart(3, '0')}.png?v=${v}`);
                if (!r.ok) throw new Error(`frame ${i} is missing`);
                return createImageBitmap(await r.blob());
            }));
            framesDirty = false;
            open(t, fr, slug);
        } catch (e) {
            toast(`Couldn't open ${slug}: ${e.message}`, 'err');
        }
    }

    // ---- text boxes ----
    const fields = { bText: 'text', bFont: 'font', bSize: 'size', bMaxW: 'maxW', bColor: 'color', bStroke: 'stroke', bStrokeW: 'strokeW', bMotion: 'motion' };
    const numeric = ['size', 'maxW', 'strokeW'];
    const tabName = (b, i) => b.text.length > 24 ? b.text.slice(0, 22) + '…' : b.text || `Text ${i + 1}`;
    function renderBoxes() {
        $('#boxTabs').innerHTML = tpl.boxes.map((b, i) =>
            `<button type="button" data-i="${i}" class="${i === sel ? 'on' : ''}">${esc(tabName(b, i))}</button>`).join('');
        const box = tpl.boxes[sel];
        $('#boxForm').classList.toggle('hide', !box);
        $('#delBox').disabled = !box;
        if (box) for (const [id, key] of Object.entries(fields)) $('#' + id).value = box[key];
        renderKeys();
        renderSlots();
    }
    $('#boxTabs').addEventListener('click', (e) => {
        const b = e.target.closest('button');
        if (b) { sel = +b.dataset.i; renderBoxes(); draw(); }
    });
    $('#boxForm').addEventListener('input', (e) => {
        const key = fields[e.target.id], box = tpl.boxes[sel];
        if (!key || !box) return;
        box[key] = numeric.includes(key) ? Math.max(0, +e.target.value || 0) : e.target.value;
        if (key === 'text') {
            $(`#boxTabs [data-i="${sel}"]`).textContent = tabName(box, sel);
            renderSlots();
        }
        draw();
    });
    $('#addSlot').addEventListener('click', () => {
        const inp = $('#bText'), names = slots().map((s) => s.name);
        let n = 1;
        while (names.includes(`text${n}`)) n++;
        const at = inp.selectionStart ?? inp.value.length;
        inp.value = inp.value.slice(0, at) + `{text${n}}` + inp.value.slice(inp.selectionEnd ?? at);
        inp.dispatchEvent(new Event('input', { bubbles: true }));
        inp.focus();
    });

    // ---- URL parts: one row per slot with a box to try a value ----
    function renderSlots() {
        const list = slots();
        $('#slotCount').textContent = list.length || '';
        $('#slotHelp').classList.toggle('hide', !list.length);
        $('#slots').innerHTML = list.length ? list.map((s, i) => `
            <div class="rowi">
                <span class="badge">${i + 1}</span>
                <span class="grow">${esc(s.name || 'unnamed')} <small class="muted">${s.fallback != null ? `falls back to "${esc(s.fallback)}"` : 'blank if left out'}</small></span>
                <input class="field gif-try" data-slot="${esc(s.name)}" value="${esc(tries[s.name] ?? '')}" placeholder="try it" aria-label="Try a value for ${esc(s.name)}">
            </div>`).join('') : '<p class="muted">No slots: this GIF always shows the same text.</p>';
        renderLink();
    }
    $('#slots').addEventListener('input', (e) => {
        const name = e.target.dataset.slot;
        if (name == null) return;
        if (e.target.value) tries[name] = e.target.value; else delete tries[name];
        renderLink();
        draw();
    });
    $('#addBox').addEventListener('click', () => { tpl.boxes.push(newBox()); sel = tpl.boxes.length - 1; renderBoxes(); draw(); });
    $('#delBox').addEventListener('click', () => {
        if (!confirm('Remove this text?')) return;
        tpl.boxes.splice(sel, 1);
        sel = Math.max(0, sel - 1);
        renderBoxes();
        draw();
    });

    // ---- positions (keyframes) ----
    function renderKeys() {
        const box = tpl.boxes[sel];
        $('#keyCount').textContent = box ? box.keys.length : '';
        $('#keys').innerHTML = !box ? '' : box.keys.map((k, i) => `
            <div class="rowi${k.f === cur ? ' gif-on' : ''}">
                <button class="btn small" type="button" data-act="go" data-i="${i}">Frame ${k.f + 1}</button>
                <span class="grow mono">${Math.round(k.x)}, ${Math.round(k.y)}</span>
                <label class="muted"><input type="checkbox" data-act="hide" data-i="${i}"${k.hide ? ' checked' : ''}> Hidden</label>
                <button class="btn small" type="button" data-act="del" data-i="${i}" aria-label="Delete frame ${k.f + 1} position"${box.keys.length < 2 ? ' disabled' : ''}>&times;</button>
            </div>`).join('');
    }
    $('#keys').addEventListener('click', (e) => {
        const el = e.target.closest('[data-act]'), box = tpl.boxes[sel];
        if (!el) return;
        const k = box.keys[+el.dataset.i];
        if (el.dataset.act === 'go') { setPlaying(false); cur = k.f; }
        if (el.dataset.act === 'hide') k.hide = el.checked;
        if (el.dataset.act === 'del') box.keys.splice(+el.dataset.i, 1);
        renderKeys();
        draw();
    });

    function place(e) {
        const box = tpl.boxes[sel];
        if (!box) return;
        const r = cv.getBoundingClientRect();
        const x = Math.round((e.clientX - r.left) * tpl.w / r.width), y = Math.round((e.clientY - r.top) * tpl.h / r.height);
        let k = box.keys.find((k) => k.f === cur);
        if (!k) { k = { f: cur }; box.keys.push(k); box.keys.sort((a, b) => a.f - b.f); }
        Object.assign(k, { x, y, hide: false });
        renderKeys();
        draw();
    }
    cv.addEventListener('pointerdown', (e) => { setPlaying(false); cv.setPointerCapture(e.pointerId); place(e); });
    cv.addEventListener('pointermove', (e) => { if (cv.hasPointerCapture(e.pointerId)) place(e); });

    // ---- link and saving ----
    const slug = () => `${$('#group').value.trim()}/${$('#name').value.trim()}`;
    function renderLink() {
        const parts = slots().map((s) => tries[s.name] == null ? '-' : encodeURIComponent(tries[s.name].replace(/ /g, '_')));
        while (parts.at(-1) === '-') parts.pop();   // trailing skipped parts aren't needed
        const url = `${location.origin}/gifs/${slug()}${parts.length ? '/' + parts.join('/') : ''}.gif`;
        $('#link').href = url;
        $('#link').textContent = url;
    }
    $('#group').addEventListener('input', renderLink);
    $('#name').addEventListener('input', renderLink);
    $('#copy').addEventListener('click', (e) => copy($('#link').href, e.currentTarget));

    async function post(s, frame, body) {
        const r = await fetch(`save.php?t=${encodeURIComponent(s)}${frame == null ? '' : `&frame=${frame}`}`, { method: 'POST', headers: { 'X-Gifs': '1' }, body });
        if (!r.ok) throw new Error((await r.text()) || `save failed (${r.status})`);
    }
    $('#save').addEventListener('click', async (e) => {
        const s = slug(), btn = e.currentTarget;
        if (!/^[a-z0-9-]+\/[a-z0-9-]+$/.test(s)) return toast('Group and name may only use a-z, 0-9 and -', 'err');
        if (!tpl.boxes.length) return toast('Add a text first', 'err');
        if (s !== loaded && GIFS.templates[s] && !confirm(`Replace the existing ${s}?`)) return;
        btn.disabled = true;
        try {
            if (framesDirty || s !== loaded) {
                const c = new OffscreenCanvas(tpl.w, tpl.h), x = c.getContext('2d');
                for (let i = 0; i < frames.length; i++) {
                    btn.textContent = `Uploading ${i + 1}/${frames.length}`;
                    x.clearRect(0, 0, tpl.w, tpl.h);
                    x.drawImage(frames[i], 0, 0);
                    await post(s, i, await c.convertToBlob({ type: 'image/png' }));
                }
            }
            btn.textContent = 'Saving...';
            await post(s, null, JSON.stringify(tpl));
            if (!GIFS.templates[s]) $('#list').insertAdjacentHTML('beforeend', `<a class="rowi" href="?t=${esc(s)}" data-t="${esc(s)}"><span class="grow mono">${esc(s)}</span></a>`);
            GIFS.templates[s] = Date.now();
            loaded = s;
            framesDirty = false;
            history.replaceState(null, '', `?t=${s}`);
            toast('Saved. The link is ready.', 'ok');
        } catch (err) {
            toast(`Couldn't save: ${err.message}`, 'err');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Save';
        }
    });

    // ---- sidebar ----
    $('#list').addEventListener('click', (e) => {
        const a = e.target.closest('[data-t]');
        if (!a) return;
        e.preventDefault();
        history.replaceState(null, '', `?t=${a.dataset.t}`);
        fromTemplate(a.dataset.t);
    });
    const drop = $('#drop'), input = $('#file');
    $('#pick').addEventListener('click', () => input.click());
    input.addEventListener('change', () => input.files[0] && fromGif(input.files[0]));
    drop.addEventListener('dragover', (e) => { e.preventDefault(); drop.classList.add('over'); });
    drop.addEventListener('dragleave', () => drop.classList.remove('over'));
    drop.addEventListener('drop', (e) => {
        e.preventDefault();
        drop.classList.remove('over');
        const f = e.dataTransfer.files[0];
        if (f) fromGif(f);
    });

    const start = new URLSearchParams(location.search).get('t');
    if (start && GIFS.templates[start]) fromTemplate(start);
});
