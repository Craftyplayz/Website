// 501 / 301 darts scorer (x01): per-dart keypad or turn total, bust and checkout handling, undo, saved in localStorage.
(function () {
    'use strict';

    /* engine:start (no DOM in here) */
    const MULT = ['', 'S', 'D', 'T'];
    // the 3-dart totals that can never be scored (everything else from 0 to 180 can)
    const IMPOSSIBLE = new Set([163, 166, 169, 172, 173, 175, 176, 178, 179]);
    const FINAL_PREF = ['D20', 'D16', 'D18', 'D10', 'D8', 'D12', 'D14', 'D6', 'D4', 'D2', 'D1', 'D3', 'D5', 'D7', 'D9', 'D11', 'D13', 'D15', 'D17', 'D19', 'Bull'];

    const val = (d, o) => (d.n === 25 ? (o.flat ? 50 : 25 * d.m) : d.n * d.m);
    const dname = (d, o) => (d.m === 0 ? 'Miss' : d.n === 25 ? (d.m === 2 ? 'Bull' : o && o.flat ? 'Bull 50' : '25') : MULT[d.m] + d.n);
    // does this dart open (kind = o.in) or close (kind = o.out) the leg?
    const counts = (d, kind) => kind === 'straight' || (kind === 'double' ? d.m === 2 : d.m >= 2);

    const ALL = [];
    for (let n = 1; n <= 20; n++) for (let m = 1; m <= 3; m++) ALL.push({ m, n });
    ALL.push({ m: 1, n: 25 }, { m: 2, n: 25 });

    // shortest finishing route for `rem` with at most k darts, or null. Prefers common doubles.
    function route(rem, k, o) {
        const byVal = ALL.slice().sort((a, b) => val(b, o) - val(a, o));
        const finals = byVal.filter((d) => counts(d, o.out)).sort((a, b) => {
            const ia = FINAL_PREF.indexOf(dname(a)), ib = FINAL_PREF.indexOf(dname(b));
            return (ia < 0 ? 99 : ia) - (ib < 0 ? 99 : ib);
        });
        const find = (r, n) => {
            if (n === 1) { const f = finals.find((d) => val(d, o) === r); return f ? [f] : null; }
            for (const d of byVal) {
                const left = r - val(d, o);
                if (left < 1) continue;
                const rest = find(left, n - 1);
                if (rest) return [d].concat(rest);
            }
            return null;
        };
        if (rem < 1) return null;
        for (let n = 1; n <= k; n++) { const r = find(rem, n); if (r) return r; }
        return null;
    }

    function newGame(names, o) {
        const g = {
            v: 2, o, cur: 0, starter: 0, leg: 1, over: null, legWinner: null, msg: null, undo: [], t: null,
            players: names.map((name) => ({ name, rem: o.start, in: o.in === 'straight', legs: 0, darts: 0, pts: 0 })),
        };
        g.t = freshTurn(g);
        return g;
    }
    function freshTurn(g) { const p = g.players[g.cur]; return { start: p.rem, in0: p.in, darts: [], end: null }; }
    function snap(g) {
        const c = Object.assign({}, g); delete c.undo;
        g.undo.push(JSON.stringify(c));
        if (g.undo.length > 200) g.undo.shift();
    }
    function undo(g) {
        const s = g.undo.pop();
        if (!s) return false;
        const keep = g.undo;
        Object.assign(g, JSON.parse(s));
        g.undo = keep;
        g.msg = { k: 'note', text: 'Last action undone.' };
        return true;
    }
    const fail = (g, text) => { g.msg = { k: 'err', text }; return false; };
    const outName = (o) => (o.out === 'double' ? 'a double' : o.out === 'master' ? 'a double or triple' : 'any dart');

    function bustWhy(after, o) {
        return after < 0 ? 'that is more than is left' : after === 1 ? "that leaves 1, which can't be finished on " + outName(o) : 'the last dart must be ' + outName(o);
    }
    function bust(g, p, why, darts) {
        const t = g.t;
        p.rem = t.start; p.in = t.in0; t.end = 'bust'; p.darts += darts;
        g.msg = { k: 'bust', text: 'BUST! ' + p.name + ' stays on ' + t.start + ' (' + why + '). The whole turn is void.' };
    }
    function checkedOut(g, p, darts) {
        const t = g.t;
        t.end = 'out'; p.darts += darts; p.pts += t.start; p.legs++; g.legWinner = g.cur;
        if (p.legs >= g.o.legs) { g.over = g.cur; g.msg = { k: 'win', text: p.name + ' wins the match!' }; } else g.msg = { k: 'win', text: p.name + ' wins leg ' + g.leg + '!' };
    }

    function throwDart(g, d) {
        const o = g.o, p = g.players[g.cur], t = g.t;
        if (g.legWinner !== null) return fail(g, 'The leg is over. ' + (g.over !== null ? 'Start a new game or a rematch.' : 'Press Next leg.'));
        if (t.end) return fail(g, 'No more darts this turn. Press Next player.');
        if (d.n === 25 && d.m === 3) return fail(g, "There is no treble bull. Pick single or double.");
        snap(g);
        let v = val(d, o), note = '';
        if (!p.in) {
            if (counts(d, o.in)) p.in = true; else { v = 0; if (d.m) note = ' Not in yet: it needs ' + (o.in === 'double' ? 'a double' : 'a double or triple') + ' to start scoring.'; }
        }
        t.darts.push({ m: d.m, n: d.n, v });
        const after = p.rem - v;
        if (after < 0 || (after === 1 && o.out !== 'straight') || (after === 0 && !counts(d, o.out))) {
            bust(g, p, bustWhy(after, o), 3);
        } else {
            p.rem = after;
            if (after === 0) checkedOut(g, p, t.darts.length);
            else if (t.darts.length === 3) {
                t.end = 'done'; p.darts += 3; p.pts += t.start - p.rem;
                g.msg = { k: 'note', text: p.name + ' scored ' + (t.start - p.rem) + '. Press Next player.' };
            } else g.msg = { k: 'note', text: dname(d, o) + (v ? ' = ' + v + '.' : '.') + note };
        }
        return true;
    }

    // whole-turn entry. darts = how many darts the turn used (only matters for a checkout and the average)
    function enterTotal(g, raw, darts) {
        const o = g.o, p = g.players[g.cur], t = g.t;
        const total = Number(raw);
        if (g.legWinner !== null) return fail(g, 'The leg is over. ' + (g.over !== null ? 'Start a new game or a rematch.' : 'Press Next leg.'));
        if (t.end || t.darts.length) return fail(g, t.end ? 'This turn is finished. Press Next player.' : 'This turn already has darts. Finish it with the keypad or undo them.');
        if (raw === '' || raw === null || !Number.isInteger(total) || total < 0) return fail(g, 'Enter a whole number from 0 to 180.');
        if (total > 180) return fail(g, total + " can't be scored: three darts make 180 at most.");
        if (IMPOSSIBLE.has(total)) return fail(g, total + " can't be scored with three darts.");
        if (!p.in && total > 0) return fail(g, "Use the dart keypad until " + p.name + ' is in: a total cannot show which dart opened the leg.');
        const after = p.rem - total;
        if (after === 0) {
            const r = route(p.rem, 3, o);
            if (!r) return fail(g, "Can't finish " + p.rem + ' on ' + outName(o) + ' with three darts.');
            if (r.length > darts) return fail(g, "Can't finish " + p.rem + ' with ' + darts + (darts === 1 ? ' dart' : ' darts') + '. It needs at least ' + r.length + '.');
        }
        snap(g);
        if (after < 0 || (after === 1 && o.out !== 'straight')) bust(g, p, bustWhy(after, o), 3);
        else if (after === 0) { p.rem = 0; checkedOut(g, p, darts); } else {
            p.rem = after; p.darts += 3; p.pts += total; t.end = 'done';
            g.msg = { k: 'ok', text: p.name + ' scored ' + total + '.' };
            advance(g);
        }
        if (t.end === 'bust') advance(g);
        return true;
    }

    function advance(g) {
        g.cur = (g.cur + 1) % g.players.length;
        g.t = freshTurn(g);
    }
    function nextPlayer(g) {
        if (g.legWinner !== null) return fail(g, 'The leg is over. ' + (g.over !== null ? 'Start a new game or a rematch.' : 'Press Next leg.'));
        if (!g.t.end) return fail(g, 'Throw all three darts first (use Miss for a dart that missed the board).');
        snap(g); advance(g);
        g.msg = null;
        return true;
    }
    function nextLeg(g) {
        if (g.legWinner === null || g.over !== null) return fail(g, 'No leg to move on from.');
        snap(g);
        g.players.forEach((p) => { p.rem = g.o.start; p.in = g.o.in === 'straight'; });
        g.starter = (g.starter + 1) % g.players.length; g.cur = g.starter; g.legWinner = null; g.leg++;
        g.t = freshTurn(g); g.msg = null;
        return true;
    }
    const avg = (p) => (p.darts ? (p.pts / p.darts) * 3 : 0);
    /* engine:end */

    // ---------------------------------------------------------------- page
    const KEY = 'x01-game', PREF = 'x01-setup';
    const $ = (s, r) => (r || document).querySelector(s);
    const root = $('.x01');
    if (!root) return;
    const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const store = {
        get(k) { try { return JSON.parse(localStorage.getItem(k)); } catch (e) { return null; } },
        set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* private mode: game just isn't kept */ } },
        del(k) { try { localStorage.removeItem(k); } catch (e) { /* nothing to remove */ } },
    };

    let g = store.get(KEY);
    if (!g || g.v !== 2) g = null;
    const ui = { mod: 1, mode: 'darts', darts: 3 };

    const setupEl = $('.x01-setup'), playEl = $('.x01-play'), form = setupEl.querySelector('form');

    function show(view) { setupEl.hidden = view !== 'setup'; playEl.hidden = view !== 'play'; }
    function save() { if (g) store.set(KEY, Object.assign({}, g)); }

    // ---- setup form
    function syncSetup() {
        const f = form.elements, n = +f.players.value;
        form.querySelectorAll('[data-name]').forEach((el) => { el.hidden = +el.dataset.name > n; });
        $('.x01-custom', form).hidden = f.start.value !== 'custom';
    }
    function loadPrefs() {
        const p = store.get(PREF); if (!p) return;
        Object.keys(p).forEach((k) => { const el = form.elements[k]; if (el && typeof p[k] === 'string') el.value = p[k]; });
        form.querySelectorAll('[data-name] input').forEach((el, i) => { if (p['name' + i]) el.value = p['name' + i]; });
    }
    form.addEventListener('input', syncSetup);
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const f = form.elements;
        const start = f.start.value === 'custom' ? Number(f.custom.value) : Number(f.start.value);
        const bad = !Number.isInteger(start) || start < 2 || start > 1001;
        f.custom.setAttribute('aria-invalid', bad && f.start.value === 'custom' ? 'true' : 'false');
        $('.x01-custom-err', form).hidden = !(bad && f.start.value === 'custom');
        if (bad) return;
        const names = []; const prefs = {};
        for (let i = 0; i < +f.players.value; i++) names.push(form.querySelectorAll('[data-name] input')[i].value.trim() || 'Player ' + (i + 1));
        ['start', 'custom', 'players', 'in', 'out', 'bull', 'legs'].forEach((k) => { prefs[k] = f[k].value; });
        form.querySelectorAll('[data-name] input').forEach((el, i) => { prefs['name' + i] = el.value; });
        store.set(PREF, prefs);
        g = newGame(names, { start, in: f.in.value, out: f.out.value, flat: f.bull.value === 'flat', legs: +f.legs.value });
        ui.mode = 'darts'; ui.mod = 1; ui.darts = 3;
        save(); show('play'); render();
    });

    // ---- play view
    const routeText = (r, o) => r.map((d) => dname(d, o)).join(' ');
    function modeLine(o) {
        return o.start + ' · ' + (o.in === 'straight' ? 'straight in' : o.in === 'double' ? 'double in' : 'double/triple in') + ' · ' + (o.out === 'double' ? 'double out' : o.out === 'master' ? 'double/triple out' : 'straight out') + (o.flat ? ' · 50/50 bull' : '') + ' · first to ' + o.legs + (o.legs === 1 ? ' leg' : ' legs');
    }
    function playerCard(p, i) {
        const o = g.o, cur = i === g.cur && g.legWinner === null;
        const left = cur ? 3 - g.t.darts.length : 3;
        const r = p.in && p.rem <= 170 && !(cur && g.t.end) ? route(p.rem, Math.max(1, left), o) : null;
        const hint = cur && g.t.end ? '' : !p.in ? 'Needs ' + (o.in === 'double' ? 'a double' : 'a double or triple') + ' to start'
            : r ? 'Finish: ' + routeText(r, o) : p.rem <= 170 && p.rem > 0 ? (cur && left < 3 ? 'No finish with ' + left + (left === 1 ? ' dart' : ' darts') : 'No 3-dart finish') : '';
        return '<div class="x01-player' + (cur ? ' is-turn' : '') + (g.legWinner === i ? ' is-win' : '') + '"' + (cur ? ' aria-current="true"' : '') + '>' +
            '<div class="x01-pname"><b>' + esc(p.name) + '</b>' + (g.o.legs > 1 ? '<span class="badge">' + p.legs + '/' + o.legs + ' legs</span>' : '') + '</div>' +
            '<div class="x01-rem" aria-label="' + p.rem + ' left">' + p.rem + '</div>' +
            '<div class="x01-meta muted"><span>Avg ' + avg(p).toFixed(1) + '</span><span>' + p.darts + ' darts</span></div>' +
            (hint ? '<div class="x01-hint">' + esc(hint) + '</div>' : '') + '</div>';
    }
    function keypad() {
        const o = g.o, nums = [];
        for (let n = 1; n <= 20; n++) nums.push('<button class="x01-key" type="button" data-act="dart" data-n="' + n + '">' + n + '</button>');
        const mods = ['Single', 'Double', 'Treble'].map((l, i) => '<button type="button" data-act="mod" data-m="' + (i + 1) + '" class="' + (ui.mod === i + 1 ? 'on' : '') + '" aria-pressed="' + (ui.mod === i + 1) + '">' + l + '</button>').join('');
        return '<div class="stabs x01-mods" role="group" aria-label="Ring">' + mods + '</div><div class="x01-keys">' + nums.join('') +
            '<button class="x01-key" type="button" data-act="dart" data-n="25">' + (o.flat ? 'Outer 50' : '25') + '</button>' +
            '<button class="x01-key" type="button" data-act="dart" data-n="50">Bull</button>' +
            '<button class="x01-key x01-miss" type="button" data-act="dart" data-n="0">Miss</button></div>';
    }
    function totalPad() {
        const quick = [26, 41, 45, 60, 81, 85, 100, 140, 180].map((n) => '<button class="x01-key" type="button" data-act="quick" data-n="' + n + '">' + n + '</button>').join('');
        return '<form class="x01-total" novalidate><label>Turn total<input class="field" name="total" type="number" inputmode="numeric" min="0" max="180" autocomplete="off"></label>' +
            '<label>Darts used<select class="field" name="darts">' + [1, 2, 3].map((n) => '<option' + (ui.darts === n ? ' selected' : '') + '>' + n + '</option>').join('') + '</select></label>' +
            '<button class="btn primary" type="submit">Add turn</button></form><div class="x01-keys x01-quick">' + quick + '</div>' +
            '<small class="muted">Darts used only matters for a checkout and your average.</small>';
    }
    function render() {
        if (!g) return;
        const o = g.o, p = g.players[g.cur], t = g.t, over = g.legWinner !== null;
        const chips = [0, 1, 2].map((i) => { const d = t.darts[i]; return '<span class="x01-dart' + (d ? ' on' : '') + '">' + (d ? esc(dname(d, o)) + (d.v ? ' <small>' + d.v + '</small>' : '') : '') + '</span>'; }).join('');
        let acts = '';
        if (over) acts = g.over !== null ? '<button class="btn primary" type="button" data-act="rematch">Rematch</button><button class="btn" type="button" data-act="new">New game</button>' : '<button class="btn primary" type="button" data-act="nextleg">Next leg</button>';
        else acts = '<button class="btn ' + (t.end ? 'primary' : '') + '" type="button" data-act="next">Next player</button>';
        const msg = g.msg ? '<p class="x01-msg ' + g.msg.k + '" role="' + (g.msg.k === 'err' || g.msg.k === 'bust' ? 'alert' : 'status') + '">' + esc(g.msg.text) + '</p>' : '';
        playEl.innerHTML =
            '<p class="muted x01-mode">' + esc(modeLine(o)) + (g.players.length > 1 ? ' · leg ' + g.leg : '') + '</p>' + msg +
            '<div class="x01-players">' + g.players.map(playerCard).join('') + '</div>' +
            '<section class="card x01-turn"><h2>' + (over ? esc(g.players[g.legWinner].name) + ' checked out' : esc(p.name) + ' to throw') + '</h2>' +
            '<div class="x01-darts" aria-label="Darts this turn">' + chips + '</div>' +
            '<div class="stabs x01-modes" role="group" aria-label="Entry mode"><button type="button" data-act="mode" data-id="darts" class="' + (ui.mode === 'darts' ? 'on' : '') + '" aria-pressed="' + (ui.mode === 'darts') + '">Dart by dart</button><button type="button" data-act="mode" data-id="total" class="' + (ui.mode === 'total' ? 'on' : '') + '" aria-pressed="' + (ui.mode === 'total') + '">Turn total</button></div>' +
            (ui.mode === 'darts' ? keypad() : totalPad()) +
            '<div class="actions x01-actions">' + acts + '<button class="btn" type="button" data-act="undo"' + (g.undo.length ? '' : ' disabled') + '>Undo</button><button class="btn" type="button" data-act="new">New game</button></div></section>';
        save();
        if (ui.mode === 'total') { const f = playEl.querySelector('input[name=total]'); if (f && matchMedia('(hover: hover)').matches) f.focus(); }
    }
    function dartFrom(n) {
        if (n === 0) return { m: 0, n: 0 };
        if (n === 50) return { m: ui.mod === 3 ? 3 : 2, n: 25 };
        if (n === 25) return { m: ui.mod, n: 25 };
        return { m: ui.mod, n };
    }
    playEl.addEventListener('click', (e) => {
        const b = e.target.closest('[data-act]'); if (!b) return;
        const a = b.dataset.act;
        if (a === 'mod') { ui.mod = +b.dataset.m; return render(); }
        if (a === 'mode') { ui.mode = b.dataset.id; return render(); }
        if (a === 'dart') { throwDart(g, dartFrom(+b.dataset.n)); ui.mod = 1; return render(); }
        if (a === 'quick') { enterTotal(g, b.dataset.n, ui.darts); return render(); }
        if (a === 'next') { nextPlayer(g); return render(); }
        if (a === 'nextleg') { nextLeg(g); return render(); }
        if (a === 'undo') { undo(g); return render(); }
        if (a === 'rematch') { const o = g.o; g = newGame(g.players.map((x) => x.name), o); return render(); }
        if (a === 'new') { show('setup'); $('.x01-cancel').hidden = !g; }
    });
    playEl.addEventListener('submit', (e) => {
        e.preventDefault();
        const f = e.target.elements; ui.darts = +f.darts.value;
        const ok = enterTotal(g, f.total.value, ui.darts);
        if (ok) ui.darts = 3;
        render();
        if (!ok) { const el = playEl.querySelector('input[name=total]'); if (el) { el.value = f.total.value; el.setAttribute('aria-invalid', 'true'); } }
    });
    $('.x01-cancel').addEventListener('click', () => { if (g) { show('play'); render(); } });

    loadPrefs(); syncSetup();
    if (g) { show('play'); render(); } else show('setup');
    $('.x01-cancel').hidden = !g;
})();
