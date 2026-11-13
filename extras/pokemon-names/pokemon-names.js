// Pokémon Baby Names: search, sort, filter and the hover card. PHP renders the first view.
document.addEventListener('DOMContentLoaded', () => {
    const { $, esc, store, debounce } = UI;

    // The reasoning for each score is kept in the data but not shown. Set to true to show it in the hover card.
    const SHOW_REASONING = false;

    const main = $('.pbn');
    const D = JSON.parse($('#pbnData').textContent);
    const BANDS = JSON.parse(main.dataset.bands);
    const REG = { 1: 'Kanto', 2: 'Johto', 3: 'Hoenn', 4: 'Sinnoh', 5: 'Unova', 6: 'Kalos', 7: 'Alola', 8: 'Galar', 9: 'Paldea' };
    const ROMAN = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX'];

    const band = (s) => BANDS.find((b) => s >= b.min && s <= b.max) || BANDS[BANDS.length - 1];
    const pad = (n) => '#' + String(n).padStart(4, '0');
    const fold = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/’/g, "'");
    const pos = (n) => `-${((n - 1) % 32) * 64}px -${Math.floor((n - 1) / 32) * 64}px`;

    D.forEach((p) => { p.key = fold(p.name); p.band = band(p.score); });
    const byNum = new Map(D.map((p) => [p.number, p]));

    const saved = store.get('pbn-view', {});
    const st = { q: '', sort: saved.sort || 'num', gen: saved.gen || 0, band: saved.band || '' };
    const qEl = $('#pbnQ'), sortEl = $('#pbnSort'), listEl = $('#pbnList'), tip = $('#pbnTip');
    if ([...sortEl.options].some((o) => o.value === st.sort)) sortEl.value = st.sort; else st.sort = 'num';

    const SORTS = {
        'num': (a, b) => a.number - b.number,
        'score-desc': (a, b) => b.score - a.score || a.number - b.number,
        'score-asc': (a, b) => a.score - b.score || a.number - b.number,
        'gen': (a, b) => a.generation - b.generation || a.number - b.number,
        'name': (a, b) => a.key.localeCompare(b.key),
        'len-asc': (a, b) => a.name.length - b.name.length || a.key.localeCompare(b.key),
        'len-desc': (a, b) => b.name.length - a.name.length || a.key.localeCompare(b.key),
    };

    const query = () => fold(st.q).replace(/^#0*/, '');
    const matchesQ = (p) => {
        const q = query();
        if (!q) return true;
        return /^\d+$/.test(q) ? String(p.number).startsWith(q) : p.key.includes(q);
    };

    const highlight = (name) => {
        const q = query();
        if (!q || /^\d+$/.test(q)) return esc(name);
        const i = fold(name).indexOf(q);
        if (i < 0) return esc(name);
        return esc(name.slice(0, i)) + '<mark>' + esc(name.slice(i, i + q.length)) + '</mark>' + esc(name.slice(i + q.length));
    };

    const tile = (p) =>
        `<button type="button" class="pbn-tile" data-n="${p.number}">` +
        `<span class="pbn-top"><span class="mono pbn-num">${pad(p.number)}</span><span class="pbn-score pbn-${p.band.id}">${p.score}</span></span>` +
        `<span class="pbn-spr" style="background-position:${pos(p.number)}" aria-hidden="true"></span>` +
        `<span class="pbn-name">${highlight(p.name)}</span>` +
        `<span class="pbn-meter pbn-${p.band.id}" aria-hidden="true"><i style="width:${p.score * 10}%"></i></span></button>`;

    const setChips = (box, attr, current, counts) => {
        let total = 0;
        box.querySelectorAll('button').forEach((b) => {
            const v = b.dataset[attr];
            const on = String(current) === v;
            b.classList.toggle('on', on);
            b.setAttribute('aria-pressed', on);
            if (v !== '0' && v !== '') { b.querySelector('.n').textContent = counts[v] || 0; total += counts[v] || 0; }
        });
        box.querySelector('button').querySelector('.n').textContent = total;
    };

    function render() {
        hideTip();
        const base = D.filter(matchesQ);
        const gc = {}, bc = {};
        base.forEach((p) => {
            if (!st.band || p.band.id === st.band) gc[p.generation] = (gc[p.generation] || 0) + 1;
            if (!st.gen || p.generation === st.gen) bc[p.band.id] = (bc[p.band.id] || 0) + 1;
        });
        setChips($('#pbnGens'), 'gen', st.gen, gc);
        setChips($('#pbnBands'), 'band', st.band, bc);

        const list = base.filter((p) => (!st.gen || p.generation === st.gen) && (!st.band || p.band.id === st.band)).sort(SORTS[st.sort]);
        $('#pbnCount').textContent = list.length === D.length
            ? `Showing all ${D.length.toLocaleString()} Pokémon`
            : `Showing ${list.length.toLocaleString()} of ${D.length.toLocaleString()} Pokémon`;

        if (!list.length) {
            listEl.innerHTML = '<p class="muted pbn-empty">No Pokémon match. Clear the search or pick another generation or verdict.</p>';
            return;
        }
        const grouped = st.sort === 'gen' || st.sort === 'score-desc' || st.sort === 'score-asc';
        if (!grouped) {
            listEl.innerHTML = '<div class="pbn-grid">' + list.map(tile).join('') + '</div>';
            return;
        }
        const groups = [];
        list.forEach((p) => {
            const k = st.sort === 'gen' ? p.generation : p.score;
            if (!groups.length || groups[groups.length - 1].k !== k) groups.push({ k, items: [] });
            groups[groups.length - 1].items.push(p);
        });
        listEl.innerHTML = groups.map((g) => {
            const title = st.sort === 'gen' ? `Generation ${ROMAN[g.k]} · ${REG[g.k]}` : `Score ${g.k} · ${band(g.k).label}`;
            return `<h2 class="sec-h">${title} <span class="n">${g.items.length}</span></h2><div class="pbn-grid">${g.items.map(tile).join('')}</div>`;
        }).join('');
    }

    // Verdict card: hover or keyboard focus shows it, a tap pins it until the next tap or Esc
    let pinned = null;
    function showTip(el) {
        const p = byNum.get(+el.dataset.n);
        tip.innerHTML =
            `<div class="pbn-tip-head"><span class="pbn-spr" style="background-position:${pos(p.number)}"></span>` +
            `<div class="pbn-tip-name"><b>${esc(p.name)}</b><span>${pad(p.number)} · Gen ${ROMAN[p.generation]} · ${REG[p.generation]}</span></div>` +
            `<div class="pbn-tip-score"><b>${p.score}</b><span> / 10</span></div></div>` +
            `<span class="pbn-score pbn-${p.band.id}">${esc(p.band.label)}</span>` +
            `<span class="pbn-meter pbn-${p.band.id}"><i style="width:${p.score * 10}%"></i></span>` +
            (SHOW_REASONING ? `<p>${esc(p.reasoning)}</p>` : '');
        tip.hidden = false;
        const r = el.getBoundingClientRect(), t = tip.getBoundingClientRect();
        const vw = document.documentElement.clientWidth, vh = window.innerHeight;
        let x = r.right + 10, y = r.top;
        if (x + t.width > vw - 16) x = r.left - t.width - 10;
        if (x < 16) {
            x = Math.max(16, Math.min(vw - t.width - 16, r.left + r.width / 2 - t.width / 2));
            y = r.bottom + 10;
            if (y + t.height > vh - 16) y = r.top - t.height - 10;
        }
        y = Math.max(16, Math.min(y, vh - t.height - 16));
        tip.style.left = x + 'px';
        tip.style.top = y + 'px';
    }
    function hideTip() {
        tip.hidden = true;
        if (pinned) pinned.classList.remove('on');
        pinned = null;
    }
    const canHover = matchMedia('(hover: hover)').matches;
    listEl.addEventListener('mouseover', (e) => { const t = e.target.closest('.pbn-tile'); if (t && canHover && !pinned) showTip(t); });
    listEl.addEventListener('mouseout', (e) => { const t = e.target.closest('.pbn-tile'); if (t && !pinned && !t.contains(e.relatedTarget)) tip.hidden = true; });
    listEl.addEventListener('focusin', (e) => { const t = e.target.closest('.pbn-tile'); if (t && !pinned) showTip(t); });
    listEl.addEventListener('focusout', () => { if (!pinned) tip.hidden = true; });
    listEl.addEventListener('click', (e) => {
        const t = e.target.closest('.pbn-tile');
        if (!t) return;
        if (pinned === t) { hideTip(); return; }
        if (pinned) pinned.classList.remove('on');
        pinned = t;
        t.classList.add('on');
        showTip(t);
    });
    document.addEventListener('click', (e) => { if (pinned && !e.target.closest('.pbn-tile')) hideTip(); });
    window.addEventListener('scroll', () => { if (!tip.hidden) hideTip(); }, { passive: true });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') hideTip();
        if (e.key === '/' && !/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) { e.preventDefault(); qEl.focus(); }
    });

    // Controls
    const save = () => store.set('pbn-view', { sort: st.sort, gen: st.gen, band: st.band });
    qEl.addEventListener('input', debounce(() => { st.q = qEl.value.trim(); render(); }, 80));
    sortEl.addEventListener('change', () => { st.sort = sortEl.value; save(); render(); });
    $('.pbn-bar').addEventListener('click', (e) => {
        const b = e.target.closest('.stabs button');
        if (!b) return;
        if (b.dataset.gen !== undefined) st.gen = +b.dataset.gen;
        if (b.dataset.band !== undefined) st.band = b.dataset.band;
        save();
        render();
    });

    // Re-render only if a saved view differs from the PHP first view
    if (st.sort !== 'num' || st.gen || st.band) render();
});
