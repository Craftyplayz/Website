// Unit Converter (/tools/convert/). Each unit is [symbol, name, factor to the category's base unit, extra aliases split by |].
// A factor can be [toBase, fromBase] functions for units that aren't a plain multiple (temperature).
document.addEventListener('DOMContentLoaded', () => {
    const { $, esc, copy, debounce } = UI;

    const CATS = [
        { id: 'length', name: 'Length', def: 'm', units: [
            ['nm', 'Nanometre', 1e-9], ['µm', 'Micrometre', 1e-6, 'um|micron|microns'], ['mm', 'Millimetre', 1e-3], ['cm', 'Centimetre', 1e-2],
            ['m', 'Metre', 1], ['km', 'Kilometre', 1e3, 'kms'], ['in', 'Inch', 0.0254, 'inches|"'], ['ft', 'Foot', 0.3048, "feet|'"],
            ['yd', 'Yard', 0.9144, 'yds'], ['mi', 'Mile', 1609.344], ['nmi', 'Nautical mile', 1852], ['ly', 'Light-year', 9460730472580800, 'light year|light years|lightyear'],
        ] },
        { id: 'weight', name: 'Weight', def: 'kg', units: [
            ['mg', 'Milligram', 1e-6], ['g', 'Gram', 1e-3, 'gramme|grammes'], ['kg', 'Kilogram', 1, 'kilo|kilos|kgs'], ['t', 'Tonne', 1e3, 'ton|tons|metric ton'],
            ['oz', 'Ounce', 0.028349523125], ['lb', 'Pound', 0.45359237, 'lbs'], ['st', 'Stone', 6.35029318, 'stones'],
            ['US ton', 'Short ton (US)', 907.18474, 'short ton|short tons'], ['UK ton', 'Long ton (UK)', 1016.0469088, 'long ton|long tons'],
        ] },
        { id: 'temp', name: 'Temperature', def: '°C', units: [
            ['°C', 'Celsius', [v => v, v => v], 'centigrade'],
            ['°F', 'Fahrenheit', [v => (v - 32) * 5 / 9, v => v * 9 / 5 + 32]],
            ['K', 'Kelvin', [v => v - 273.15, v => v + 273.15], 'kelvins'],
        ] },
        { id: 'time', name: 'Time', def: 's', note: 'Months and years are averages (30.44 and 365.24 days). A Minecraft day is 20 minutes.', units: [
            ['ms', 'Millisecond', 1e-3], ['s', 'Second', 1, 'sec|secs'], ['tick', 'Minecraft tick', 0.05, 'ticks'], ['min', 'Minute', 60, 'mins'],
            ['h', 'Hour', 3600, 'hr|hrs'], ['d', 'Day', 86400], ['MC day', 'Minecraft day', 1200, 'mc days|minecraft days'], ['wk', 'Week', 604800, 'wks'],
            ['mo', 'Month', 2629746], ['yr', 'Year', 31556952, 'y|yrs'], ['decade', 'Decade', 315569520], ['century', 'Century', 3155695200, 'centuries'],
        ] },
        { id: 'data', name: 'Data', def: 'GB', note: 'KB, MB, GB and TB are 1000-based. KiB, MiB, GiB and TiB are 1024-based, which is what Windows shows as KB, MB and GB.', units: [
            ['B', 'Byte', 1], ['KB', 'Kilobyte', 1e3], ['MB', 'Megabyte', 1e6], ['GB', 'Gigabyte', 1e9, 'gig|gigs'], ['TB', 'Terabyte', 1e12], ['PB', 'Petabyte', 1e15],
            ['KiB', 'Kibibyte', 1024], ['MiB', 'Mebibyte', 1024 ** 2], ['GiB', 'Gibibyte', 1024 ** 3], ['TiB', 'Tebibyte', 1024 ** 4],
            ['b', 'Bit', 0.125], ['Kb', 'Kilobit', 125], ['Mb', 'Megabit', 125e3], ['Gb', 'Gigabit', 125e6],
        ] },
        { id: 'rate', name: 'Internet speed', def: 'Mbps', note: 'Internet plans are sold in megabits (Mbps). Downloads show megabytes (MB/s): divide by 8.', units: [
            ['B/s', 'Bytes per second', 8], ['KB/s', 'Kilobytes per second', 8e3], ['MB/s', 'Megabytes per second', 8e6], ['GB/s', 'Gigabytes per second', 8e9],
            ['bps', 'Bits per second', 1, 'b/s|bit/s'], ['Kbps', 'Kilobits per second', 1e3, 'Kb/s|kbit/s'], ['Mbps', 'Megabits per second', 1e6, 'Mb/s|mbit/s|meg'],
            ['Gbps', 'Gigabits per second', 1e9, 'Gb/s|gbit/s|gig'],
        ] },
        { id: 'speed', name: 'Speed', def: 'km/h', units: [
            ['m/s', 'Metres per second', 1, 'mps'], ['km/h', 'Kilometres per hour', 1 / 3.6, 'kph|kmh|kmph'], ['mph', 'Miles per hour', 0.44704],
            ['kn', 'Knot', 1852 / 3600, 'knots|kt|kts'], ['ft/s', 'Feet per second', 0.3048, 'fps'],
        ] },
        { id: 'area', name: 'Area', def: 'm²', units: [
            ['mm²', 'Square millimetre', 1e-6], ['cm²', 'Square centimetre', 1e-4], ['m²', 'Square metre', 1], ['ha', 'Hectare', 1e4],
            ['km²', 'Square kilometre', 1e6], ['in²', 'Square inch', 0.00064516, 'square inches'], ['ft²', 'Square foot', 0.09290304, 'square feet'],
            ['yd²', 'Square yard', 0.83612736], ['ac', 'Acre', 4046.8564224], ['mi²', 'Square mile', 2589988.110336],
        ] },
        { id: 'volume', name: 'Volume', def: 'L', note: 'Plain pint and gallon are UK (imperial). Cup, fl oz, tsp and tbsp are US.', units: [
            ['mL', 'Millilitre', 1e-3], ['L', 'Litre', 1], ['m³', 'Cubic metre', 1e3], ['tsp', 'Teaspoon (US)', 0.00492892159375, 'teaspoon|teaspoons'],
            ['tbsp', 'Tablespoon (US)', 0.01478676478125, 'tablespoon|tablespoons'], ['fl oz', 'Fluid ounce (US)', 0.0295735295625, 'floz|fluid ounce|fluid ounces'],
            ['cup', 'Cup (US)', 0.2365882365, 'cups'], ['pt', 'Pint (UK)', 0.56826125, 'pint|pints'], ['US pt', 'Pint (US)', 0.473176473, 'us pint|us pints'],
            ['gal', 'Gallon (UK)', 4.54609, 'gallon|gallons'], ['US gal', 'Gallon (US)', 3.785411784, 'us gallon|us gallons'],
            ['in³', 'Cubic inch', 0.016387064, 'cubic inches'], ['ft³', 'Cubic foot', 28.316846592, 'cubic feet'],
        ] },
    ];


    // Lookup tables: exact spelling first (MB vs Mb), then lower case. The first unit to claim a key keeps it.
    const norm = s => s.replace(/°/g, '').replace(/^degrees?\s+/i, '').replace(/\s+/g, ' ').trim();
    const exact = new Map(), low = new Map();
    const claim = (map, k, u) => { if (!map.has(k)) map.set(k, u); };
    for (const cat of CATS) {
        cat.units = cat.units.map(([sym, name, f, extra = '']) => {
            const u = { sym, name, f, cat };
            const names = [name, ...extra.split('|').filter(Boolean)].map(norm);
            for (const k of [norm(sym), ...names]) claim(exact, k, u);
            claim(low, norm(sym).toLowerCase(), u);
            // names (not symbols) also match plural and US spellings: "ms" must stay milliseconds, not metres
            for (const k of names.map(n => n.toLowerCase())) {
                for (const v of [k, k.replace('metre', 'meter').replace('litre', 'liter')]) { claim(low, v, u); claim(low, v + 's', u); }
            }
            return u;
        });
    }

    function findUnit(s) {
        s = norm(s);
        if (exact.has(s)) return exact.get(s);
        const l = s.toLowerCase();
        if (low.has(l)) return low.get(l);
        const sq = l.replace(/^(sq|square)\.?\s*(.+)$/, '$2²').replace(/^(cu|cubic)\.?\s*(.+)$/, '$2³')
            .replace(/([a-z])\^?2$/, '$1²').replace(/([a-z])\^?3$/, '$1³');
        return low.get(sq);
    }

    const toBase = (u, v) => (typeof u.f === 'number' ? v * u.f : u.f[0](v));
    const fromBase = (u, v) => (typeof u.f === 'number' ? v / u.f : u.f[1](v));
    const convert = (v, a, b) => fromBase(b, toBase(a, v));

    // Sums: only digits, operators and brackets reach Function, so nothing else can run.
    function calc(e) {
        e = e.replace(/,/g, '').replace(/×/g, '*').replace(/÷/g, '/').replace(/\^/g, '**').trim();
        if (!e || !/^[\d\s+\-*/().]+$/.test(e)) return NaN;
        try { const n = Function('"use strict";return(' + e + ')')(); return typeof n === 'number' ? n : NaN; } catch { return NaN; }
    }

    // "2*3 ft" -> sum "2*3", unit "ft"
    function splitQty(s) {
        const m = s.trim().match(/^([\d\s+\-*/().,^×÷]+?)\s*([^\d\s+\-*/().,^×÷][^]*)?$/);
        return m ? { v: calc(m[1]), u: (m[2] || '').trim() } : null;
    }

    const clean = n => Number(n.toPrecision(12));
    function fmt(n) {
        if (!Number.isFinite(n)) return '—';
        const a = Math.abs(n);
        if (a && (a >= 1e15 || a < 1e-6)) return n.toExponential(6).replace(/\.?0+e/, 'e');
        return clean(n).toLocaleString(undefined, { maximumFractionDigits: 10 });
    }

    // Reads the quick box. Returns { calc } for a sum, { v, from?, to? } for a value, or { err }.
    function parse(q) {
        q = q.trim().replace(/(\d)\s*x\s*(?=[\d(])/gi, '$1*');
        if (!q) return null;
        if (/[+\-*/^×÷]/.test(q.replace(/^[-+]/, '')) && Number.isFinite(calc(q))) return { calc: calc(q), expr: q };
        // try every "to"/"in"/"=" so "5 in in cm" still works
        const sep = /\s+(?:to|in|into|as)\s+|\s*(?:=|->|→)\s*/gi;
        let m, bad = '';
        while ((m = sep.exec(q))) {
            sep.lastIndex = m.index + 1; // matches share spaces ("5 in in cm")
            const left = splitQty(q.slice(0, m.index)), right = q.slice(m.index + m[0].length).trim();
            if (!left || !right || !Number.isFinite(left.v)) continue;
            const from = findUnit(left.u), to = findUnit(right);
            if (from && to) return from.cat === to.cat ? { v: left.v, from, to } : { err: `Can't turn ${from.cat.name.toLowerCase()} into ${to.cat.name.toLowerCase()}.` };
            bad = !from ? left.u : right;
        }
        const one = splitQty(q);
        if (!bad && one && Number.isFinite(one.v)) {
            if (!one.u) return { v: one.v };
            const from = findUnit(one.u);
            if (from) return { v: one.v, from };
            bad = one.u;
        }
        return { err: bad ? `Don't know the unit “${bad}”.` : 'Try 5 ft to cm, 100 f in c or 12*3+4.' };
    }

    const q = $('#cvQ'), out = $('#cvOut'), cats = $('#cvCats'), val = $('#cvV'), sel = $('#cvU'), rows = $('#cvRows'), note = $('#cvNote');
    const state = { cat: CATS[0], v: 1, from: CATS[0].units.find(u => u.sym === CATS[0].def), to: null };

    cats.innerHTML = CATS.map(c => `<button type="button" role="tab" data-cat="${c.id}">${esc(c.name)}</button>`).join('');

    function drawPanel(keepValue) {
        const { cat } = state;
        for (const b of cats.children) { const on = b.dataset.cat === cat.id; b.classList.toggle('on', on); b.setAttribute('aria-selected', on); }
        if (sel.dataset.cat !== cat.id) {
            sel.innerHTML = cat.units.map((u, i) => `<option value="${i}">${esc(u.name)} (${esc(u.sym)})</option>`).join('');
            sel.dataset.cat = cat.id;
        }
        sel.value = cat.units.indexOf(state.from);
        if (!keepValue) val.value = String(clean(state.v));
        rows.innerHTML = cat.units.map((u, i) => {
            const n = convert(state.v, state.from, u);
            return `<button type="button" class="rowi cv-row${u === state.to ? ' on' : ''}" data-i="${i}" title="Copy">`
                + `<span class="grow">${esc(u.name)} <small>${esc(u.sym)}</small></span><b>${esc(fmt(n))}</b></button>`;
        }).join('');
        note.textContent = cat.note || '';
    }

    const saveUrl = debounce(() => {
        const u = new URL(location.href);
        if (q.value.trim()) u.searchParams.set('q', q.value.trim()); else u.searchParams.delete('q');
        history.replaceState(null, '', u);
    }, 400);

    let copyText = '';
    function run(keepValue) {
        const r = parse(q.value);
        copyText = '';
        if (!r) out.innerHTML = '';
        else if (r.err) out.innerHTML = `<span class="muted">${esc(r.err)}</span>`;
        else if ('calc' in r) {
            copyText = String(clean(r.calc));
            out.innerHTML = `<span>${esc(r.expr)} =</span> <b>${esc(fmt(r.calc))}</b>`;
        } else {
            if (r.from) Object.assign(state, { cat: r.from.cat, from: r.from, to: r.to || null });
            state.v = r.v;
            if (r.to) {
                const n = convert(r.v, r.from, r.to);
                copyText = String(clean(n));
                out.innerHTML = `<span>${esc(fmt(r.v))} ${esc(r.from.sym)} =</span> <b>${esc(fmt(n))} ${esc(r.to.sym)}</b>`;
            } else out.innerHTML = r.from ? '<span class="muted">Every unit is listed below. Add “to cm” for just one.</span>' : '';
            drawPanel(keepValue);
        }
        if (copyText) out.insertAdjacentHTML('beforeend', ' <button type="button" class="btn small" id="cvCopy">Copy</button>');
        saveUrl();
    }

    // The panel writes the quick box, so the box, the list and the URL always agree.
    function fromPanel() {
        const v = splitQty(val.value);
        if (!v || v.u || !Number.isFinite(v.v)) return;
        q.value = `${val.value.trim()} ${state.from.sym}${state.to ? ' to ' + state.to.sym : ''}`;
        run(true);
    }

    q.addEventListener('input', () => run(false));
    val.addEventListener('input', fromPanel);
    sel.addEventListener('change', () => { state.from = state.cat.units[sel.value]; if (state.to === state.from) state.to = null; fromPanel(); });
    cats.addEventListener('click', e => {
        const b = e.target.closest('[data-cat]');
        if (!b || b.dataset.cat === state.cat.id) return;
        const cat = CATS.find(c => c.id === b.dataset.cat);
        Object.assign(state, { cat, from: cat.units.find(u => u.sym === cat.def), to: null });
        fromPanel();
        drawPanel(true);
    });
    rows.addEventListener('click', e => {
        const b = e.target.closest('.cv-row');
        if (b) copy(String(clean(convert(state.v, state.from, state.cat.units[b.dataset.i]))), b.querySelector('b'));
    });
    out.addEventListener('click', e => { if (e.target.id === 'cvCopy') copy(copyText, e.target); });

    q.value = new URLSearchParams(location.search).get('q') || '';
    drawPanel(false);
    run(false);
    // focus only where a keyboard won't pop up over the page
    if (matchMedia('(pointer: fine)').matches) q.focus();
});
