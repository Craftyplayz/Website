// Minecraft Name Colours (/tools/name-colours/): gradients, colour codes and formatting for a name, all in the browser.
document.addEventListener('DOMContentLoaded', () => {
    'use strict';
    const { $, $$, esc, store, debounce, toast, copy } = UI;
    const { legacy: LEGACY, presets: PRESETS } = window.NC;
    const FORMATS = [['l', 'bold'], ['o', 'italic'], ['n', 'underlined'], ['m', 'strikethrough'], ['k', 'obfuscated']];
    const MAX_STOPS = 10, MAX_NAME = 48;
    const HEX = /^[0-9a-f]{6}$/;
    const OBF_POOL = 'ABCDEFGHJKLMNOPQRSTUVWXYZabcdefghkmnopqrstuvwxyz0123456789#$%&@';

    const DEFAULT = { name: 'CraftyPlayz_', stops: ['66c0f4', 'b388ff'], blend: 'oklab', skip: true, legacy: false, fmt: '', over: [], out: 'amp', cmd: '' };
    let S = { ...DEFAULT };

    // ---- colour maths ----
    const toRgb = (h) => [0, 2, 4].map((i) => parseInt(h.slice(i, i + 2), 16));
    const toHex = (c) => c.map((v) => Math.round(Math.min(255, Math.max(0, v))).toString(16).padStart(2, '0')).join('');
    const lin = (v) => ((v /= 255) <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4);
    const gam = (v) => 255 * (v <= 0.0031308 ? 12.92 * v : 1.055 * v ** (1 / 2.4) - 0.055);
    function oklab(rgb) {
        const [r, g, b] = rgb.map(lin);
        const l = Math.cbrt(0.4122214708 * r + 0.5363325363 * g + 0.0514459929 * b);
        const m = Math.cbrt(0.2119034982 * r + 0.6806995451 * g + 0.1073969566 * b);
        const s = Math.cbrt(0.0883024619 * r + 0.2817188376 * g + 0.6299787005 * b);
        return [0.2104542553 * l + 0.793617785 * m - 0.0040720468 * s, 1.9779984951 * l - 2.428592205 * m + 0.4505937099 * s, 0.0259040371 * l + 0.7827717662 * m - 0.808675766 * s];
    }
    function unlab([L, a, b]) {
        const l = (L + 0.3963377774 * a + 0.2158037573 * b) ** 3, m = (L - 0.1055613458 * a - 0.0638541728 * b) ** 3, s = (L - 0.0894841775 * a - 1.291485548 * b) ** 3;
        return [gam(4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s), gam(-1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s), gam(-0.0041960863 * l - 0.7034186147 * m + 1.707614701 * s)];
    }
    function hsl(rgb) {
        const [r, g, b] = rgb.map((v) => v / 255), mx = Math.max(r, g, b), mn = Math.min(r, g, b), l = (mx + mn) / 2, d = mx - mn;
        if (!d) return [0, 0, l];
        let h = mx === r ? ((g - b) / d) % 6 : mx === g ? (b - r) / d + 2 : (r - g) / d + 4;
        h *= 60;
        return [h < 0 ? h + 360 : h, d / (1 - Math.abs(2 * l - 1)), l];
    }
    function unhsl([h, s, l]) {
        const c = (1 - Math.abs(2 * l - 1)) * s, x = c * (1 - Math.abs(((h / 60) % 2) - 1)), m = l - c / 2;
        const p = h < 60 ? [c, x, 0] : h < 120 ? [x, c, 0] : h < 180 ? [0, c, x] : h < 240 ? [0, x, c] : h < 300 ? [x, 0, c] : [c, 0, x];
        return p.map((v) => (v + m) * 255);
    }
    function mix(a, b, t) {
        const A = toRgb(a), B = toRgb(b);
        if (S.blend === 'rgb') return toHex(A.map((v, i) => v + (B[i] - v) * t));
        if (S.blend === 'hue') {
            const p = hsl(A), q = hsl(B);
            // greys have no hue of their own, so borrow the other end's
            if (!p[1]) p[0] = q[0];
            if (!q[1]) q[0] = p[0];
            let dh = q[0] - p[0];
            if (dh > 180) dh -= 360;
            if (dh < -180) dh += 360;
            return toHex(unhsl([(p[0] + dh * t + 360) % 360, p[1] + (q[1] - p[1]) * t, p[2] + (q[2] - p[2]) * t]));
        }
        const p = oklab(A), q = oklab(B);
        return toHex(unlab(p.map((v, i) => v + (q[i] - v) * t)));
    }
    const LAB = LEGACY.map((l) => oklab(toRgb(l[1])));
    function nearest(hex) {
        const c = oklab(toRgb(hex));
        let best = 0, bd = Infinity;
        LAB.forEach((p, i) => { const d = (p[0] - c[0]) ** 2 + (p[1] - c[1]) ** 2 + (p[2] - c[2]) ** 2; if (d < bd) { bd = d; best = i; } });
        return LEGACY[best][1];
    }
    const legacyOf = (hex) => LEGACY.find((l) => l[1] === hex);
    const shadow = (hex) => toHex(toRgb(hex).map((v) => v / 4)); // Minecraft's text shadow is the colour at 25%

    // sample the gradient at t (0..1)
    function at(t) {
        const n = S.stops.length;
        if (n === 1) return S.stops[0];
        const x = Math.min(Math.max(t, 0), 1) * (n - 1), j = Math.min(Math.floor(x), n - 2);
        return mix(S.stops[j], S.stops[j + 1], x - j);
    }

    // one colour per letter (code point)
    function colours() {
        const chars = [...S.name], painted = chars.map((c) => !(S.skip && c === ' '));
        const m = painted.filter(Boolean).length;
        let k = 0;
        return chars.map((c, i) => {
            const t = m > 1 ? (painted[i] ? k++ : Math.max(0, k - 0.5)) / (m - 1) : 0;
            const hex = S.over[i] || at(t);
            return S.legacy ? nearest(hex) : hex;
        });
    }

    // ---- output ----
    function build(cols) {
        const chars = [...S.name], f = FORMATS.filter(([c]) => S.fmt.includes(c));
        if (!chars.length) return '';
        const deco = S.fmt.includes('n') || S.fmt.includes('m'); // underline and strikethrough show on spaces, so spaces need a colour too
        const needs = (c, h, last) => h !== last && (c !== ' ' || deco);
        let out = '', last = null;
        const fmt = S.out;

        if (fmt === 'json') {
            const parts = [];
            chars.forEach((c, i) => {
                const color = S.legacy ? legacyOf(cols[i])[3] : '#' + cols[i];
                const prev = parts[parts.length - 1];
                if (prev && (prev.color === color || (c === ' ' && !deco))) prev.text += c;
                else { const p = { text: c, color }; f.forEach(([, k]) => (p[k] = true)); parts.push(p); }
            });
            out = JSON.stringify(parts.length === 1 ? parts[0] : ['', ...parts]);
        } else if (fmt === 'mm' || fmt === 'mmg') {
            const tags = f.map(([, k]) => `<${k}>`).join('');
            const mmEsc = (s) => s.replace(/[\\<]/g, '\\$&');
            const gradient = fmt === 'mmg' && !S.legacy && !S.over.some(Boolean) && S.stops.length > 1;
            if (gradient) out = tags + `<gradient:${S.stops.map((s) => '#' + s).join(':')}>${mmEsc(S.name)}</gradient>`;
            else if (fmt === 'mmg' && new Set(cols).size === 1) out = tags + `<${S.legacy ? legacyOf(cols[0])[3] : '#' + cols[0]}>${mmEsc(S.name)}`;
            else {
                out = tags;
                chars.forEach((c, i) => {
                    if (needs(c, cols[i], last)) { out += `<${S.legacy ? legacyOf(cols[i])[3] : '#' + cols[i]}>`; last = cols[i]; }
                    out += mmEsc(c);
                });
            }
        } else {
            const p = fmt === 'sect' ? '§' : '&';
            const code = (h) => {
                if (S.legacy) return p + legacyOf(h)[0];
                if (fmt === 'amp') return '&#' + h;
                if (fmt === 'brace') return '{#' + h + '}';
                return p + 'x' + [...h].map((d) => p + d).join('');
            };
            chars.forEach((c, i) => {
                // a colour code clears formatting, so the formats follow every colour
                if (needs(c, cols[i], last)) { out += code(cols[i]) + f.map(([k]) => p + k).join(''); last = cols[i]; }
                out += c;
            });
        }
        return (S.cmd.trim() ? S.cmd.trim() + ' ' : '') + out;
    }

    // ---- import ----
    const NAMED = Object.fromEntries(LEGACY.map((l) => [l[3], l[1]]).concat([['grey', 'aaaaaa'], ['dark_grey', '555555']]));
    const MM_FMT = { bold: 'l', b: 'l', italic: 'o', i: 'o', em: 'o', underlined: 'n', u: 'n', strikethrough: 'm', st: 'm', obfuscated: 'k', obf: 'k' };
    const JSON_FMT = Object.fromEntries(FORMATS.map(([c, k]) => [k, c]));
    function parse(src) {
        src = src.trim();
        let cmd = '';
        const cm = src.match(/^(\/[\w:]+(?: @[a-z](?:\[[^\]]*\])?| \S+ meta setprefix)?)\s+/i);
        if (cm) { cmd = cm[1]; src = src.slice(cm[0].length); }
        const fmt = new Set();
        let name = '', cols = [];

        if (/^[[{"]/.test(src)) {
            let data;
            try { data = JSON.parse(src); } catch (e) { data = null; }
            if (data !== null) {
                const walk = (n, inh) => {
                    if (Array.isArray(n)) return n.forEach((x) => walk(x, inh));
                    if (typeof n !== 'object' || !n) n = { text: String(n ?? '') };
                    const st = { ...inh };
                    if (typeof n.color === 'string') st.color = n.color[0] === '#' ? n.color.slice(1).toLowerCase() : NAMED[n.color] || st.color;
                    for (const k in JSON_FMT) if (n[k]) fmt.add(JSON_FMT[k]);
                    for (const ch of String(n.text ?? '')) { name += ch; cols.push(HEX.test(st.color || '') ? st.color : null); }
                    if (Array.isArray(n.extra)) n.extra.forEach((x) => walk(x, st));
                };
                walk(data, {});
                return { name, cols, fmt, cmd };
            }
        }

        const g = src.match(/^((?:<\w+>)*)<gradient:(#[0-9a-f]{6}(?::#[0-9a-f]{6})+)>((?:[^<\\]|\\.)*)<\/gradient>$/i);
        if (g) {
            (g[1].match(/\w+/g) || []).forEach((t) => MM_FMT[t.toLowerCase()] && fmt.add(MM_FMT[t.toLowerCase()]));
            return { name: g[3].replace(/\\(.)/g, '$1'), cols: [], fmt, cmd, stops: g[2].toLowerCase().split(':').map((s) => s.slice(1)) };
        }

        let cur = null;
        for (let i = 0; i < src.length;) {
            const r = src.slice(i);
            let m;
            if ((m = r.match(/^[&§]x((?:[&§][0-9a-f]){6})/i))) cur = m[1].replace(/[&§]/g, '').toLowerCase();
            else if ((m = r.match(/^(?:[&§]#|<#|<c(?:olor)?:#|\{#)([0-9a-f]{6})[>}]?/i))) cur = m[1].toLowerCase();
            else if ((m = r.match(/^[&§]([0-9a-f])/i))) cur = LEGACY.find((l) => l[0] === m[1].toLowerCase())[1];
            else if ((m = r.match(/^[&§]([k-o])/i))) fmt.add(m[1].toLowerCase());
            else if ((m = r.match(/^[&§]r/i))) cur = null;
            else if ((m = r.match(/^<(\/?)(?:c(?:olor)?:)?(\w+)(?::[^>]*)?>/i))) {
                const t = m[2].toLowerCase();
                if (MM_FMT[t]) { if (!m[1]) fmt.add(MM_FMT[t]); }
                else if (NAMED[t] && !m[1]) cur = NAMED[t];
            } else {
                m = r.match(/^\\[\\<]/) || [String.fromCodePoint(r.codePointAt(0))];
                name += m[0].slice(-1);
                cols.push(cur);
            }
            i += m[0].length;
        }
        return { name, cols, fmt, cmd };
    }

    // ---- state in the link and storage ----
    const fmtStr = (set) => FORMATS.map(([c]) => c).filter((c) => set.has ? set.has(c) : set.includes(c)).join('');
    function clean(o) {
        o = o && typeof o === 'object' ? o : {};
        const hex = (v) => (typeof v === 'string' && HEX.test(v.toLowerCase()) ? v.toLowerCase() : null);
        const stops = (Array.isArray(o.stops) ? o.stops : []).map(hex).filter(Boolean).slice(0, MAX_STOPS);
        return {
            name: [...String(o.name ?? DEFAULT.name)].slice(0, MAX_NAME).join(''),
            stops: stops.length ? stops : [...DEFAULT.stops],
            blend: ['oklab', 'rgb', 'hue'].includes(o.blend) ? o.blend : 'oklab',
            skip: o.skip !== false,
            legacy: o.legacy === true,
            fmt: fmtStr(String(o.fmt || '')),
            over: (Array.isArray(o.over) ? o.over : []).slice(0, MAX_NAME).map(hex),
            out: [...$('#ncFormat').options].some((x) => x.value === o.out) ? o.out : 'amp',
            cmd: String(o.cmd || '').slice(0, 80),
        };
    }
    const encode = (o) => btoa(String.fromCharCode(...new TextEncoder().encode(JSON.stringify(o)))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    function decode(s) {
        try { return JSON.parse(new TextDecoder().decode(Uint8Array.from(atob(s.replace(/-/g, '+').replace(/_/g, '/')), (c) => c.charCodeAt(0)))); } catch (e) { return null; }
    }
    const saveState = debounce(() => {
        history.replaceState(null, '', '#' + encode(S));
        store.set('nc-last', S);
    }, 250);

    // ---- rendering ----
    const nameEl = $('#ncName'), lettersEl = $('#ncLetters'), stopsEl = $('#ncStops'), mc = $('#ncMc');

    function renderStops() {
        stopsEl.innerHTML = S.stops.map((h, i) => `<div class="nc-stop">
            <input type="color" value="#${h}" data-i="${i}" aria-label="Colour ${i + 1}">
            <input class="field mono" value="${h}" data-hex="${i}" maxlength="7" spellcheck="false" autocomplete="off" aria-label="Hex code for colour ${i + 1}">
            <button class="btn small" type="button" data-act="left" data-i="${i}" aria-label="Move colour ${i + 1} earlier" title="Move earlier"${i ? '' : ' disabled'}>&lsaquo;</button>
            <button class="btn small" type="button" data-act="del" data-i="${i}" aria-label="Remove colour ${i + 1}" title="Remove"${S.stops.length > 1 ? '' : ' disabled'}>&times;</button>
        </div>`).join('');
        $('[data-act="add"]').disabled = S.stops.length >= MAX_STOPS;
    }

    function renderLetters(cols) {
        lettersEl.innerHTML = [...S.name].map((c, i) => `<label class="nc-letter${S.over[i] ? ' set' : ''}${c === ' ' ? ' sp' : ''}" style="color:#${cols[i]}">` +
            `<input type="color" value="#${cols[i]}" data-i="${i}" aria-label="Colour of letter ${i + 1}, ${esc(c === ' ' ? 'space' : c)}">${c === ' ' ? '&middot;' : esc(c)}</label>`).join('');
        $('#ncResetLetters').hidden = !S.over.some(Boolean);
    }

    const mcSpans = (cols) => [...S.name].map((c, i) => `<span data-c="${esc(c)}" style="--c:#${cols[i]};--sh:#${shadow(cols[i])}">${esc(c)}</span>`).join('');

    let obfTimer = 0;
    function obfuscate() {
        clearInterval(obfTimer);
        if (!S.fmt.includes('k')) return;
        const scramble = () => $$('.nc-name span', mc).forEach((s) => { if (s.dataset.c !== ' ') s.textContent = OBF_POOL[(Math.random() * OBF_POOL.length) | 0]; });
        scramble();
        if (!matchMedia('(prefers-reduced-motion: reduce)').matches) obfTimer = setInterval(() => !document.hidden && scramble(), 70);
    }

    // everything but the stop and letter inputs, so an open colour picker stays open
    function update(cols = colours()) {
        const html = S.name ? mcSpans(cols) : '<em class="nc-empty">Type a name</em>';
        $$('.nc-name', mc).forEach((el) => (el.innerHTML = html));
        FORMATS.forEach(([c, k]) => mc.classList.toggle('nc-' + k, S.fmt.includes(c)));
        obfuscate();

        const steps = S.stops.length > 1 ? Array.from({ length: 24 }, (_, i) => '#' + at(i / 23)) : ['#' + S.stops[0], '#' + S.stops[0]];
        $('#ncBar').style.background = `linear-gradient(90deg, ${(S.legacy ? steps.map((s) => '#' + nearest(s.slice(1))) : steps).join(', ')})`;

        const out = build(cols);
        $('#ncOutput').value = out;
        const len = [...out].length, uniq = new Set(cols).size;
        $('#ncMeta').textContent = out ? `${len.toLocaleString()} characters · ${uniq} colour${uniq === 1 ? '' : 's'}${S.out === 'mmg' && out.includes('<gradient') ? ' · the server blends the colours, so it may differ slightly' : ''}` : '';
        $('#ncWarn').hidden = len <= 256 || S.out === 'json';
        $('#ncCopy').disabled = $('#ncLink').disabled = $('#ncSave').disabled = !out;
        saveState();
    }

    function renderAll() {
        nameEl.value = S.name;
        $('#ncBlend').value = S.blend;
        $('#ncSkip').checked = S.skip;
        $('#ncLegacy').checked = S.legacy;
        $('#ncFormat').value = S.out;
        $('#ncCmd').value = S.cmd;
        $$('[data-fmt]').forEach((c) => (c.checked = S.fmt.includes(c.dataset.fmt)));
        renderStops();
        const cols = colours();
        renderLetters(cols);
        update(cols);
    }
    const refresh = () => { const cols = colours(); renderLetters(cols); update(cols); };

    // ---- events ----
    nameEl.addEventListener('input', () => { S.name = [...nameEl.value].slice(0, MAX_NAME).join(''); refresh(); });

    stopsEl.addEventListener('input', (e) => {
        const t = e.target;
        if (t.type === 'color') {
            S.stops[t.dataset.i] = t.value.slice(1);
            $(`[data-hex="${t.dataset.i}"]`, stopsEl).value = S.stops[t.dataset.i];
        } else if (t.dataset.hex !== undefined) {
            let v = t.value.trim().replace(/^#/, '').toLowerCase();
            if (/^[0-9a-f]{3}$/.test(v)) v = [...v].map((d) => d + d).join('');
            const ok = HEX.test(v);
            t.setAttribute('aria-invalid', String(!ok));
            if (!ok) return;
            S.stops[t.dataset.hex] = v;
            $(`input[type="color"][data-i="${t.dataset.hex}"]`, stopsEl).value = '#' + v;
        } else return;
        refresh();
    });

    function addStop(hex) {
        if (S.stops.length >= MAX_STOPS) return toast(`You can use up to ${MAX_STOPS} colours.`, 'note');
        S.stops.push(hex);
        renderStops();
        refresh();
    }
    function randomStops() {
        const n = 2 + ((Math.random() * 2) | 0), h = Math.random() * 360, step = 40 + Math.random() * 100;
        return Array.from({ length: n }, (_, i) => toHex(unhsl([(h + i * step) % 360, 0.75 + Math.random() * 0.25, 0.5 + Math.random() * 0.2])));
    }
    document.querySelector('.nc-controls').addEventListener('click', (e) => {
        const b = e.target.closest('button');
        if (!b) return;
        const i = +b.dataset.i;
        if (b.dataset.preset) { S.stops = [...PRESETS[b.dataset.preset]]; S.over = []; }
        else if (b.dataset.hex) return addStop(b.dataset.hex);
        else if (b.dataset.act === 'add') return addStop(at(1));
        else if (b.dataset.act === 'reverse') S.stops.reverse();
        else if (b.dataset.act === 'random') { S.stops = randomStops(); S.over = []; }
        else if (b.dataset.act === 'del') S.stops.splice(i, 1);
        else if (b.dataset.act === 'left') [S.stops[i - 1], S.stops[i]] = [S.stops[i], S.stops[i - 1]];
        else if (b.id === 'ncResetLetters') S.over = [];
        else return;
        renderStops();
        refresh();
    });

    lettersEl.addEventListener('input', (e) => {
        const t = e.target, i = +t.dataset.i;
        S.over[i] = t.value.slice(1);
        for (let k = 0; k < i; k++) S.over[k] ??= null;
        const label = t.parentElement;
        label.classList.add('set');
        label.style.color = t.value;
        $('#ncResetLetters').hidden = false;
        update();
    });

    $('#ncBlend').addEventListener('change', (e) => { S.blend = e.target.value; refresh(); });
    $('#ncSkip').addEventListener('change', (e) => { S.skip = e.target.checked; refresh(); });
    $('#ncLegacy').addEventListener('change', (e) => { S.legacy = e.target.checked; refresh(); });
    $('#ncFmts').addEventListener('change', () => { S.fmt = fmtStr($$('[data-fmt]').filter((c) => c.checked).map((c) => c.dataset.fmt)); update(); });
    $('#ncFormat').addEventListener('change', (e) => { S.out = e.target.value; update(); });
    $('#ncCmd').addEventListener('input', (e) => { S.cmd = e.target.value.slice(0, 80); update(); });

    $('#ncBgs').addEventListener('click', (e) => {
        const b = e.target.closest('button');
        if (!b) return;
        $$('button', e.currentTarget).forEach((x) => { x.classList.toggle('on', x === b); x.setAttribute('aria-pressed', String(x === b)); });
        mc.dataset.bg = b.dataset.bg;
        store.set('nc-bg', b.dataset.bg);
    });
    const bg = store.get('nc-bg', 'dark');
    $(`#ncBgs [data-bg="${bg}"]`)?.click();

    $('#ncCopy').addEventListener('click', async (e) => { if (!(await copy($('#ncOutput').value, e.currentTarget))) toast("Couldn't copy. Select the code and copy it yourself.", 'err'); });
    $('#ncLink').addEventListener('click', async (e) => {
        history.replaceState(null, '', '#' + encode(S));
        if (!(await copy(location.href, e.currentTarget))) toast("Couldn't copy the link.", 'err');
    });
    $('#ncOutput').addEventListener('focus', (e) => e.target.select());

    // ---- saved designs ----
    const savedEl = $('#ncSaved');
    const getSaved = () => (Array.isArray(store.get('nc-saved', [])) ? store.get('nc-saved', []) : []).map(clean);
    function renderSaved() {
        const list = getSaved(), keep = S;
        $('#ncSavedCard').hidden = !list.length;
        $('#ncSavedN').textContent = list.length;
        savedEl.innerHTML = list.map((s, i) => {
            S = s;
            return `<div class="rowi"><button type="button" class="nc-saved grow" data-load="${i}" title="Load this design"><span class="nc-mini">${mcSpans(colours())}</span></button>` +
                `<button class="btn small" type="button" data-remove="${i}" aria-label="Delete saved design ${i + 1}" title="Delete">&times;</button></div>`;
        }).join('');
        S = keep;
    }
    $('#ncSave').addEventListener('click', () => {
        const list = getSaved(), key = JSON.stringify(S);
        if (list.some((s) => JSON.stringify(s) === key)) return toast('That design is already saved.', 'note');
        store.set('nc-saved', [clean(S), ...list].slice(0, 50));
        renderSaved();
        toast('Saved on this device.', 'ok');
    });
    savedEl.addEventListener('click', (e) => {
        const b = e.target.closest('button');
        if (!b) return;
        const list = getSaved();
        if (b.dataset.load !== undefined) { S = clean(list[b.dataset.load]); renderAll(); }
        else if (b.dataset.remove !== undefined) { list.splice(+b.dataset.remove, 1); store.set('nc-saved', list); renderSaved(); }
    });

    // ---- import ----
    $('#ncImportBtn').addEventListener('click', () => {
        const r = parse($('#ncImport').value);
        $('#ncImportErr').hidden = !!r.name;
        if (!r.name) return;
        const next = { ...S, name: r.name, fmt: fmtStr(r.fmt), cmd: r.cmd, legacy: false, over: [] };
        const used = [...new Set(r.cols.filter(Boolean))];
        if (r.stops) next.stops = r.stops;
        else if (used.length === 1) next.stops = used;
        else if (used.length > 1) { next.stops = [r.cols.find(Boolean), [...r.cols].reverse().find(Boolean)]; next.over = r.cols; }
        S = clean(next);
        renderAll();
        toast('Code loaded.', 'ok');
    });

    // ---- start: link first, then the last design on this device ----
    const fromHash = location.hash.length > 1 ? decode(location.hash.slice(1)) : null;
    S = clean(fromHash || store.get('nc-last', DEFAULT));
    renderAll();
    renderSaved();

    // self-check: import reads back what build writes
    if (location.search.includes('selftest')) {
        const keep = S;
        S = clean({ name: 'Ab c', stops: ['ff0000', '0000ff'], fmt: 'l' });
        const cols = colours();
        for (const out of ['amp', 'x', 'sect', 'brace', 'mm', 'json']) {
            S.out = out;
            const r = parse(build(cols));
            console.assert(r.name === 'Ab c' && r.fmt.has('l') && r.cols[0] === 'ff0000' && r.cols[3] === '0000ff', 'round trip failed: ' + out, r);
        }
        console.assert(nearest('fe5050') === 'ff5555', 'nearest colour');
        S = keep;
    }
});
