// Cross Quiz player: typing, moving around, hints, checking, a timer and saved progress.
// The grid and clues are already in the page from PHP; window.CQ holds the answers and clue cells.
document.addEventListener('DOMContentLoaded', () => {
    'use strict';
    const { $, $$, esc, store, toast, copy } = UI;
    const P = window.CQ;
    const sol = atob(P.sol).split('').reverse().join('');
    const cols = P.cols;
    const grid = $('#cq-grid');
    const input = $('#cq-input');
    const cellEl = {};
    $$('.cq-c', grid).forEach((el) => (cellEl[el.dataset.i] = el));

    // which clue(s) each cell belongs to: [across, down]
    const owner = {};
    P.clues.forEach((c) => c.cells.forEach((i) => ((owner[i] ||= [null, null])[c.dir] = c)));
    const order = [...P.clues.filter((c) => !c.dir), ...P.clues.filter((c) => c.dir)];

    // saved progress, one entry per puzzle, newest 30 kept
    const SAVE_KEY = 'cross-quiz';
    const saves = store.get(SAVE_KEY, {});
    const saved = saves[P.key] || {};
    const fill = {};      // cell -> letter
    const marks = {};     // cell -> 'bad' | 'rev'
    Object.assign(fill, saved.f || {});
    Object.assign(marks, saved.m || {});
    let hints = saved.h || 0;
    let secs = saved.t || 0;
    let solved = !!saved.s;
    let cur = order[0].cells[0];
    let dir = order[0].dir;

    function save() {
        saves[P.key] = { f: fill, m: marks, h: hints, t: secs, s: solved, at: Date.now() };
        const keys = Object.keys(saves).sort((a, b) => (saves[b].at || 0) - (saves[a].at || 0));
        keys.slice(30).forEach((k) => delete saves[k]);
        store.set(SAVE_KEY, saves);
    }

    // ---------- timer: runs once you start typing, pauses while the tab is hidden ----------
    const timeEl = $('#cq-time');
    let ticking = false;
    const fmt = (s) => `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
    const showTime = () => (timeEl.textContent = fmt(secs));
    setInterval(() => {
        if (!ticking || solved || document.hidden) return;
        secs++;
        showTime();
        if (secs % 5 === 0) save();
    }, 1000);

    // ---------- drawing ----------
    const clueOf = (i, d) => (owner[i] || [])[d];
    const curClue = () => clueOf(cur, dir) || clueOf(cur, 1 - dir);

    function paint(i) {
        const el = cellEl[i];
        el.querySelector('b').textContent = fill[i] || '';
        el.classList.toggle('bad', marks[i] === 'bad');
        el.classList.toggle('rev', marks[i] === 'rev');
    }

    function render() {
        const c = curClue();
        dir = c.dir;
        $$('.cq-c.word, .cq-c.cur', grid).forEach((el) => el.classList.remove('word', 'cur'));
        c.cells.forEach((i) => cellEl[i].classList.add('word'));
        cellEl[cur].classList.add('cur');
        $$('.cq-list li.on').forEach((li) => li.classList.remove('on'));
        const li = $(`.cq-list li[data-clue="${c.id}"]`);
        if (li) li.classList.add('on');
        $('#cq-now-n').textContent = `${c.n} ${c.dir ? 'Down' : 'Across'}`;
        $('#cq-now-q').textContent = c.q;
        $('#cq-now-e').textContent = `(${c.e})`;
        $('#cq-now-meta').textContent = c.meta;
        const box = $('#cq-choices');
        if (box.dataset.clue !== c.id) { box.hidden = true; box.dataset.clue = ''; }
        // keep the hidden input over the current square so phones scroll to it
        const el = cellEl[cur];
        input.style.left = el.offsetLeft + 'px';
        input.style.top = el.offsetTop + 'px';
    }

    function go(i, d) {
        if (!(i in cellEl)) return;
        cur = +i;
        if (d !== undefined && clueOf(cur, d)) dir = d;
        render();
    }

    // ---------- moving ----------
    function stepInWord(delta) {
        const c = curClue();
        const k = c.cells.indexOf(cur) + delta;
        if (k >= 0 && k < c.cells.length) go(c.cells[k]);
    }

    function arrow(dx, dy) {
        const d = dx ? 0 : 1;
        if (dir !== d && clueOf(cur, d)) { dir = d; render(); return; }
        let x = cur % cols, y = Math.floor(cur / cols);
        const rows = sol.length / cols;
        for (;;) {
            x += dx; y += dy;
            if (x < 0 || y < 0 || x >= cols || y >= rows) return;
            const i = y * cols + x;
            if (i in cellEl) { go(i, d); return; }
        }
    }

    function nextClue(delta) {
        const k = (order.indexOf(curClue()) + delta + order.length) % order.length;
        const c = order[k];
        go(c.cells.find((i) => !fill[i]) ?? c.cells[0], c.dir);
    }

    // ---------- typing ----------
    function type(letter) {
        if (solved) return;
        ticking = true;
        if (marks[cur] !== 'rev') {
            fill[cur] = letter;
            delete marks[cur];
            paint(cur);
        }
        const c = curClue();
        const k = c.cells.indexOf(cur);
        const next = c.cells.slice(k + 1).find((i) => !fill[i]);
        if (next !== undefined) go(next); else stepInWord(1);
        afterChange();
    }

    function erase() {
        if (solved) return;
        if (!fill[cur] || marks[cur] === 'rev') stepInWord(-1);
        if (marks[cur] !== 'rev' && fill[cur]) {
            delete fill[cur];
            delete marks[cur];
            paint(cur);
            afterChange();
        }
    }

    function afterChange() {
        save();
        const cells = Object.keys(cellEl);
        if (cells.some((i) => !fill[i])) return;
        if (cells.every((i) => fill[i] === sol[i])) finish();
        else toast('Every square is filled, but some letters are wrong. Use "Check puzzle" to find them.', 'note');
    }

    function finish() {
        solved = true;
        save();
        grid.classList.add('done');
        $('#cq-done-text').textContent = `You solved it in ${fmt(secs)}` + (hints ? ` with ${hints} hint${hints === 1 ? '' : 's'}.` : ' without any hints.');
        $('#cq-done').showModal();
    }

    input.value = '';
    input.addEventListener('keydown', (e) => {
        if (e.ctrlKey || e.metaKey || e.altKey) return;
        const k = e.key;
        let done = true;
        if (/^[a-z]$/i.test(k)) type(k.toUpperCase());
        else if (k === 'Backspace' || k === 'Delete') erase();
        else if (k === 'ArrowLeft') arrow(-1, 0);
        else if (k === 'ArrowRight') arrow(1, 0);
        else if (k === 'ArrowUp') arrow(0, -1);
        else if (k === 'ArrowDown') arrow(0, 1);
        else if (k === 'Tab') nextClue(e.shiftKey ? -1 : 1);
        else if (k === 'Enter') nextClue(1);
        else if (k === ' ') { if (clueOf(cur, 1 - dir)) { dir = 1 - dir; render(); } }
        else if (k === 'Escape') input.blur();
        else done = false;
        if (done) e.preventDefault();
    });
    // phone keyboards often send no useful keydown, so read what lands in the field
    input.addEventListener('input', () => {
        const letters = input.value.replace(/[^a-z]/gi, '').toUpperCase();
        input.value = '';
        for (const l of letters) type(l);
    });
    input.addEventListener('focus', () => grid.classList.add('focus'));
    input.addEventListener('blur', () => grid.classList.remove('focus'));

    grid.addEventListener('click', (e) => {
        const el = e.target.closest('.cq-c');
        if (!el) return;
        const i = +el.dataset.i;
        if (i === cur && clueOf(i, 1 - dir)) dir = 1 - dir;
        go(i);
        input.focus({ preventScroll: true });
    });

    $('.cq-clues').addEventListener('click', (e) => {
        const li = e.target.closest('li[data-clue]');
        if (!li) return;
        const c = P.clues.find((x) => x.id === li.dataset.clue);
        go(c.cells.find((i) => !fill[i]) ?? c.cells[0], c.dir);
        input.focus({ preventScroll: true });
        grid.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    });

    // ---------- hints and checking ----------
    function reveal(cells) {
        let n = 0;
        cells.forEach((i) => {
            if (fill[i] === sol[i] && marks[i] !== 'bad') return;
            fill[i] = sol[i];
            marks[i] = 'rev';
            paint(i);
            n++;
        });
        return n;
    }

    function check(cells) {
        let bad = 0;
        cells.forEach((i) => {
            if (fill[i] && fill[i] !== sol[i]) { marks[i] = 'bad'; bad++; paint(i); }
        });
        save();
        return bad;
    }

    const allCells = () => Object.keys(cellEl).map(Number);
    const say = (n, what) => toast(n ? `${n} wrong letter${n === 1 ? '' : 's'} ${what}, shown in red.` : `No wrong letters ${what}.`, n ? 'note' : 'ok');

    document.addEventListener('click', (e) => {
        const b = e.target.closest('[data-act]');
        if (!b) return;
        const c = curClue();
        switch (b.dataset.act) {
            case 'choices': {
                const box = $('#cq-choices');
                if (!box.hidden) { box.hidden = true; break; }
                box.innerHTML = c.o.map((o) => `<span class="badge">${esc(o)}</span>`).join('');
                box.dataset.clue = c.id;
                box.hidden = false;
                hints++;
                save();
                break;
            }
            case 'reveal-letter':
                if (solved) break;
                if (reveal([cur])) { hints++; stepInWord(1); afterChange(); }
                break;
            case 'reveal-word':
                if (solved) break;
                if (reveal(c.cells)) { hints++; afterChange(); }
                break;
            case 'check-word': say(check(c.cells), 'in this word'); break;
            case 'check-all': say(check(allCells()), 'in the puzzle'); break;
            case 'reveal-all':
                if (solved || !confirm('Show every answer? This ends the puzzle.')) break;
                reveal(allCells());
                hints++;
                solved = true;
                grid.classList.add('done');
                save();
                break;
            case 'clear':
                if (!confirm('Clear every square and start this puzzle again?')) break;
                allCells().forEach((i) => { delete fill[i]; delete marks[i]; paint(i); });
                hints = 0; secs = 0; solved = false; ticking = false;
                grid.classList.remove('done');
                showTime();
                save();
                break;
            case 'copy': copy(location.origin + location.pathname + P.link, b); break;
            case 'print': window.print(); break;
            case 'close': b.closest('dialog').close(); break;
            default: return;
        }
        if (b.closest('.cq-now')) input.focus({ preventScroll: true });
    });

    // ---------- start ----------
    // keep the full puzzle link in the address bar so a reload or bookmark gives the same grid
    history.replaceState(null, '', location.pathname + P.link);
    allCells().forEach(paint);
    if (solved) grid.classList.add('done');
    ticking = Object.keys(fill).length > 0;
    showTime();
    $('#cq-now').hidden = false;
    $('#cq-tools').hidden = false;
    window.addEventListener('resize', UI.debounce(render, 100));
    render();
});
