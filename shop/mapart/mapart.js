// Castle Crafty map art quotes. The picture is turned into map art by MapartCraft's own worker (worker.js, GPL-3.0,
// rebane2001); this file is the page around it: floors, settings, price, materials and the request popup.
(function () {
    'use strict';
    const $ = id => document.getElementById(id);
    // owner's prices (2026-11-24): 64 diamonds per map for flat carpet; better colour ×1.5 and staircase ×2, multiplied
    const PRICE = { perMap: 64, better: 1.5, stairs: 2 };
    const FLOORS = ['Picture', 'Size', 'Blocks and build', 'Quote'];
    const num = n => Math.round(n).toLocaleString('en-GB');
    const dia = n => `${num(n)} diamond${n === 1 ? '' : 's'}`;
    const val = n => document.querySelector(`input[name=${n}]:checked`).value;

    let DATA, CJ, img, picked = false, picName = '', worker, timer, result = null;

    // ---------- floors ----------
    const secs = [...document.querySelectorAll('[data-floor]')], shaft = $('shaft'), lift = $('lift');
    let cur = 0;
    const seen = new Set([0]);
    FLOORS.forEach((n, i) => {
        const b = document.createElement('button');
        b.type = 'button'; b.className = 'fl';
        b.innerHTML = `<span class="win"></span><span class="n">${i + 1}</span><span class="t">${n}</span>`;
        b.setAttribute('aria-label', `Floor ${i + 1}, ${n}`);
        b.onclick = () => go(i);
        shaft.appendChild(b);
    });
    const fls = [...shaft.querySelectorAll('.fl')];
    function go(i) {
        cur = Math.max(0, Math.min(FLOORS.length - 1, i));
        seen.add(cur);
        secs.forEach((s, k) => (s.hidden = k !== cur));
        fls.forEach((b, k) => { k === cur ? b.setAttribute('aria-current', 'step') : b.removeAttribute('aria-current'); b.classList.toggle('seen', seen.has(k) && k !== cur); });
        $('prev').style.visibility = cur ? 'visible' : 'hidden';
        $('next').style.visibility = cur < FLOORS.length - 1 ? 'visible' : 'hidden';
        placeLift();
    }
    function placeLift() {
        const b = fls[cur];
        if (getComputedStyle(shaft).flexDirection === 'row') { lift.style.left = (b.offsetLeft + b.offsetWidth / 2 - 11) + 'px'; lift.style.top = ''; }
        else { lift.style.top = (b.offsetTop + b.offsetHeight / 2 - 11) + 'px'; lift.style.left = ''; }
    }
    addEventListener('resize', () => { placeLift(); placeGrid(); });
    $('prev').onclick = () => { go(cur - 1); $('panel').focus({ preventScroll: true }); };
    $('next').onclick = () => { go(cur + 1); $('panel').focus({ preventScroll: true }); };

    // ---------- picture ----------
    function loadUrl(url, name, isPick) {
        const im = new Image();
        im.onload = () => { img = im; picked = isPick; picName = name; sched(); };
        im.onerror = () => { $('fname').textContent = "That file couldn't be opened as a picture. Try a PNG or JPG."; };
        im.src = url;
    }
    function loadFile(f) {
        if (!f || !/^image\//.test(f.type)) { $('fname').textContent = 'Choose a picture file (PNG, JPG, GIF or WebP).'; return; }
        $('fname').textContent = `Using ${f.name}.`;
        loadUrl(URL.createObjectURL(f), f.name, true);
    }
    $('file').onchange = () => loadFile($('file').files[0]);
    const drop = $('drop');
    ['dragenter', 'dragover'].forEach(t => drop.addEventListener(t, e => { e.preventDefault(); drop.classList.add('over'); }));
    ['dragleave', 'drop'].forEach(t => drop.addEventListener(t, e => { e.preventDefault(); drop.classList.remove('over'); }));
    drop.addEventListener('drop', e => loadFile(e.dataTransfer.files[0]));

    // ---------- settings ----------
    [['br', 'brn'], ['co', 'con'], ['sa', 'san']].forEach(([a, b]) => {
        $(a).oninput = () => { $(b).value = $(a).value; sched(); };
        $(b).oninput = () => { $(a).value = Math.max(0, Math.min(200, +$(b).value || 0)); sched(); };
    });
    $('pp').onchange = () => { $('ppbox').hidden = !$('pp').checked; sched(); };
    document.querySelectorAll('input[name=crop]').forEach(r => r.addEventListener('change', () => { $('manual').hidden = val('crop') !== '2'; }));
    ['w', 'h', 'zoom', 'cx', 'cy', 'dither', 'better'].forEach(i => $(i).addEventListener('input', sched));
    document.querySelectorAll('input[type=radio]').forEach(r => r.addEventListener('change', sched));
    $('showgrid').addEventListener('change', placeGrid);

    function settings() {
        const clamp = v => Math.max(1, Math.min(10, Math.round(+v) || 1));
        return {
            w: clamp($('w').value), h: clamp($('h').value), crop: +val('crop'), zoom: +$('zoom').value, cx: +$('cx').value, cy: +$('cy').value,
            blocks: val('blocks'), stairs: val('build') === 'stairs', better: $('better').checked, dither: +$('dither').value,
            pp: $('pp').checked, br: +$('br').value, co: +$('co').value, sa: +$('sa').value,
        };
    }
    function sched() { clearTimeout(timer); timer = setTimeout(convert, 120); }

    // ---------- conversion ----------
    const src = document.createElement('canvas');
    function drawSource(s) {
        // same steps as MapartCraft's mapPreview.updateCanvas_source (crop, then brightness / contrast / saturation)
        src.width = s.w * 128; src.height = s.h * 128;
        const c = src.getContext('2d', { willReadFrequently: true });
        c.imageSmoothingEnabled = true; c.imageSmoothingQuality = 'high';
        c.clearRect(0, 0, src.width, src.height);
        c.filter = s.pp ? `brightness(${s.br}%) contrast(${s.co}%) saturate(${s.sa}%)` : 'none';
        if (s.crop === 0) c.drawImage(img, 0, 0, src.width, src.height);
        else {
            const zoom = s.crop === 2 ? s.zoom : 10, px = s.crop === 2 ? s.cx : 50, py = s.crop === 2 ? s.cy : 50;
            const iw = img.width, ih = img.height;
            let sw, sh;
            if (iw * s.h > ih * s.w) { sw = Math.floor((10 * ih * s.w) / (s.h * zoom)); sh = Math.floor((10 * ih) / zoom); }
            else { sw = Math.floor((10 * iw) / zoom); sh = Math.floor((10 * iw * s.h) / (s.w * zoom)); }
            c.drawImage(img, Math.floor((px * (iw - sw)) / 100), Math.floor((py * (ih - sh)) / 100), sw, sh, 0, 0, src.width, src.height);
        }
        c.filter = 'none';
        return c.getImageData(0, 0, src.width, src.height);
    }
    function selected(blocks) {
        const sel = {};
        for (const id of Object.keys(DATA.sets)) sel[id] = '-1';
        for (const [id, key] of Object.entries(DATA.palettes[blocks])) sel[id] = String(key);
        return sel;
    }
    function convert() {
        if (!DATA || !img) return;
        const s = settings();
        $('dims').textContent = `${s.w} × ${s.h} map${s.w * s.h > 1 ? 's' : ''} = ${num(s.w * 128)} × ${num(s.h * 128)} blocks`;
        const data = drawSource(s);
        if (worker) worker.terminate(); // a newer setting wins
        worker = new Worker('worker.js?v=1');
        $('busy').hidden = false; $('bar').style.width = '0';
        worker.onmessage = e => {
            if (e.data.head === 'PROGRESS_REPORT') { $('bar').style.width = (e.data.body * 100) + '%'; return; }
            $('busy').hidden = true;
            const pv = $('pv');
            pv.width = data.width; pv.height = data.height;
            pv.getContext('2d').putImageData(e.data.body.pixels, 0, 0);
            result = { s, maps: e.data.body.maps };
            placeGrid();
            quote();
        };
        worker.onerror = () => { $('busy').hidden = true; $('psub').textContent = "The preview couldn't be made in this browser."; };
        worker.postMessage({ head: 'PROCESS', body: {
            coloursJSON: CJ, MapModes: DATA.worker.mapModes, WhereSupportBlocksModes: DATA.worker.whereSupportBlocksModes, DitherMethods: DATA.worker.ditherMethods,
            canvasImageData: data, selectedBlocks: selected(s.blocks),
            optionValue_modeNBTOrMapdat: 0, // always a schematic (NBT): built block by block
            optionValue_mapSize_x: s.w, optionValue_mapSize_y: s.h,
            optionValue_staircasing: s.stairs ? 1 : 0, // OFF or CLASSIC
            optionValue_whereSupportBlocks: 2, // MapartCraft's default: all, optimised
            optionValue_transparency: false, optionValue_transparencyTolerance: 128,
            optionValue_betterColour: s.better, optionValue_dithering: s.dither,
        } });
    }
    function placeGrid() {
        const g = $('grid'), pv = $('pv');
        if (!result || !$('showgrid').checked) { g.style.display = 'none'; return; }
        const f = pv.getBoundingClientRect(), p = pv.parentElement.getBoundingClientRect();
        Object.assign(g.style, {
            display: 'block', left: (f.left - p.left) + 'px', top: (f.top - p.top) + 'px', width: f.width + 'px', height: f.height + 'px',
            backgroundImage: 'linear-gradient(90deg,rgba(217,165,78,.8) 1px,transparent 1px),linear-gradient(rgba(217,165,78,.8) 1px,transparent 1px)',
            backgroundSize: `calc(100% / ${result.s.w}) calc(100% / ${result.s.h})`,
        });
    }

    // ---------- price and materials ----------
    function price(s) {
        const maps = s.w * s.h, base = maps * PRICE.perMap;
        const total = Math.round(base * (s.blocks === 'better' ? PRICE.better : 1) * (s.stairs ? PRICE.stairs : 1));
        return { maps, base, total, deposit: Math.ceil(total / 2) };
    }
    function materials() {
        const per = {};
        let support = 0;
        result.maps.forEach(row => row.forEach(m => {
            support += m.supportBlockCount;
            for (const [id, n] of Object.entries(m.materials)) if (n) per[id] = (per[id] || 0) + n;
        }));
        const pal = DATA.palettes[result.s.blocks];
        const rows = Object.entries(per).map(([id, n]) => ({ name: DATA.sets[id].blocks[pal[id]].name, rgb: DATA.sets[id].tones.normal, n })).sort((a, b) => b.n - a.n);
        return { rows, support };
    }
    function quote() {
        const s = result.s, p = price(s), m = materials();
        result.price = p; result.mats = m;
        $('price').textContent = dia(p.total);
        const blocks = m.rows.reduce((t, r) => t + r.n, 0) + m.support;
        $('psub').textContent = `${p.maps} map${p.maps > 1 ? 's' : ''} · ${num(blocks)} blocks`;
        const lines = [[`${p.maps} map${p.maps > 1 ? 's' : ''} × ${PRICE.perMap} diamonds`, num(p.base)]];
        if (s.blocks === 'better') lines.push(['Better colour × 1.5', `+${num(p.base * (PRICE.better - 1))}`]);
        if (s.stairs) lines.push(['Staircase × 2', `+${num(p.total - p.base * (s.blocks === 'better' ? PRICE.better : 1))}`]);
        $('bd').innerHTML = lines.map(([a, b]) => `<dt>${a}</dt><dd>${b}</dd>`).join('') + `<dt class="tot">Total</dt><dd class="tot">${dia(p.total)}</dd><dt>Deposit before building (50%)</dt><dd>${dia(p.deposit)}</dd>`;
        $('mat-n').textContent = `${m.rows.length} kinds, ${num(blocks)} blocks`;
        const li = (rgb, name, n, cls) => `<li${cls ? ` class="${cls}"` : ''}><i style="background:rgb(${rgb})"></i><span>${name}</span><b>${num(n)}</b><small>${num(Math.ceil(n / 64))} stacks</small></li>`;
        $('mats').innerHTML = m.rows.map(r => li(r.rgb, r.name, r.n)).join('') + (m.support ? li([112, 112, 112], 'Support blocks (any solid block)', m.support, 'sup') : '');
        $('req').disabled = !picked;
        $('req').textContent = picked ? 'Request this map art' : 'Choose a picture first';
    }

    // ---------- request ----------
    const dlg = $('dlg');
    $('req').onclick = () => {
        if (!result || !picked) return;
        $('dimg').src = $('pv').toDataURL();
        $('dprice').textContent = dia(result.price.total);
        $('dnote').textContent = `I'll start building once you've paid a 50% deposit: ${dia(result.price.deposit)}.`;
        $('rf').hidden = false; $('done').hidden = true; $('mcerr').hidden = true; $('sendmsg').textContent = ''; $('sendmsg').className = 'status';
        $('send').disabled = false; $('send').textContent = 'Send request';
        dlg.showModal();
    };
    $('cancel').onclick = () => dlg.close();
    $('close').onclick = () => dlg.close();
    const blob = (canvas, type, q) => new Promise(ok => canvas.toBlob(ok, type, q));
    $('rf').onsubmit = async e => {
        e.preventDefault();
        const mc = $('mc').value.trim();
        const okName = /^[A-Za-z0-9_]{3,16}$/.test(mc);
        $('mcerr').hidden = okName;
        $('mc').setAttribute('aria-invalid', okName ? 'false' : 'true');
        if (!okName) { $('mc').focus(); return; }
        $('send').disabled = true; $('send').textContent = 'Sending...';
        const s = result.s, p = result.price;
        // a smaller copy of the original picture, so Crafty can redo it in MapartCraft
        const o = document.createElement('canvas'), k = Math.min(1, 1024 / Math.max(img.width, img.height));
        o.width = Math.max(1, Math.round(img.width * k)); o.height = Math.max(1, Math.round(img.height * k));
        o.getContext('2d').drawImage(img, 0, 0, o.width, o.height);
        const fd = new FormData();
        fd.append('map', await blob($('pv'), 'image/png'), 'mapart.png');
        fd.append('original', await blob(o, 'image/jpeg', 0.85), 'original.jpg');
        fd.append('request', JSON.stringify({
            mc, discord: $('dc').value.trim(), notes: $('nt').value.trim(), picture: picName,
            size: [s.w, s.h], blocks: s.blocks, stairs: s.stairs, betterMatching: s.better,
            dither: $('dither').selectedOptions[0].textContent, crop: ['Off', 'Centre', 'Manual'][s.crop], manual: s.crop === 2 ? [s.zoom, s.cx, s.cy] : null,
            preprocessing: s.pp ? [s.br, s.co, s.sa] : null,
            price: p.total, deposit: p.deposit,
            materials: result.mats.rows.map(r => [r.name, r.n]).concat(result.mats.support ? [['Support blocks', result.mats.support]] : []),
        }));
        try {
            const r = await fetch('request.php', { method: 'POST', body: fd });
            const j = await r.json().catch(() => ({}));
            if (!r.ok || !j.ok) throw Error(j.error || `The server answered ${r.status}.`);
            $('rf').hidden = true; $('done').hidden = false; $('close').focus();
        } catch (err) {
            $('sendmsg').className = 'status err';
            $('sendmsg').textContent = `Your request wasn't sent. ${err.message} Try again, or message Crafty on Discord.`;
            $('send').disabled = false; $('send').textContent = 'Send request';
        }
    };

    // ---------- start ----------
    go(0);
    $('blocks-hint').textContent = 'Carpet uses the 16 carpets. Better colour uses full blocks such as concrete, terracotta and planks, and costs 50% more.';
    fetch('colours.json?v=1').then(r => r.json()).then(d => {
        DATA = d;
        // the worker's colour table: tones, and for each block whether it needs a block under it
        CJ = {};
        for (const [id, set] of Object.entries(d.sets)) {
            CJ[id] = { tonesRGB: set.tones, blocks: Object.fromEntries(Object.entries(set.blocks).map(([k, b]) => [k, { supportBlockMandatory: b.support }])) };
        }
        loadUrl('pick-image.png?v=1', '', false);
    }).catch(() => { $('psub').textContent = "The block colours couldn't be loaded. Refresh the page to try again."; });
})();
