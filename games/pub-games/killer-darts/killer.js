// Killer darts tracker: setup, per-dart input, undo and a saved game. Rules are in docs/killer-darts.md.
(function () {
    'use strict';

    /* engine:start (no DOM in here) */
    const DEFAULTS = { lives: 3, need: 1, count: 'doubles', self: 'life', straight: false, loseStatus: false, bull: 'dead' };
    const RING = { 1: 'single', 2: 'double', 3: 'treble' };
    const MAX_PLAYERS = 12;

    // how much one dart is worth for a given "which darts count" setting (0 = no effect)
    function weight(count, mult) {
        if (count === 'any') return mult;
        if (count === 'triples') return mult === 3 ? 1 : 0;
        if (count === 'dt') return mult === 2 ? 1 : mult === 3 ? 2 : 0;
        return mult === 2 ? 1 : 0;
    }
    const countText = (c) => ({ doubles: 'doubles', dt: 'doubles and trebles', triples: 'trebles', any: 'any part of a number' })[c];
    const plural = (n, one, many) => n + ' ' + (n === 1 ? one : many);
    const alive = (g) => g.players.filter((p) => !p.out);

    function newGame(list, opts) {
        const o = Object.assign({}, DEFAULTS, opts);
        return {
            v: 1, opts: o, cur: 0, thrown: 0, darts: [], over: false, winner: null, msg: null,
            players: list.map((p) => ({ name: p.name, num: p.num, lives: o.lives, killer: o.straight, hits: 0, out: false })),
        };
    }

    // Validates setup rows [{name, num|null}]; fills blank numbers with unused random ones. Returns {errors:[{i,field,text}]} or {list}.
    function checkSetup(rows, rnd) {
        const errors = [];
        const names = rows.map((r) => String(r.name || '').trim());
        names.forEach((n, i) => {
            if (!n) errors.push({ i, field: 'name', text: 'Enter a name.' });
            else if (names.indexOf(n) !== i) errors.push({ i, field: 'name', text: 'Another player already has this name.' });
        });
        const used = new Set();
        rows.forEach((r, i) => {
            if (r.num === null || r.num === '' || r.num === undefined) return;
            const n = Number(r.num);
            if (!Number.isInteger(n) || n < 1 || n > 20) errors.push({ i, field: 'num', text: 'Use a whole number from 1 to 20.' });
            else if (used.has(n)) errors.push({ i, field: 'num', text: n + ' is already taken. Each player needs a different number.' });
            else used.add(n);
        });
        if (rows.length < 2) errors.push({ i: 0, field: 'name', text: 'Add at least two players.' });
        if (errors.length) return { errors };
        const free = [];
        for (let n = 1; n <= 20; n++) if (!used.has(n)) free.push(n);
        const list = rows.map((r, i) => {
            if (r.num !== null && r.num !== '' && r.num !== undefined) return { name: names[i], num: Number(r.num) };
            const k = Math.floor(rnd() * free.length);
            return { name: names[i], num: free.splice(k, 1)[0] };
        });
        return { list };
    }

    function nextPlayer(g) {
        const n = g.players.length;
        for (let k = 1; k <= n; k++) {
            const i = (g.cur + k) % n;
            if (!g.players[i].out) { g.cur = i; break; }
        }
        g.thrown = 0; g.darts = [];
    }

    // One dart. target: 0 miss, 1-20, 25 bull. mult 1-3. Returns {kind: ok|note|err|win, msg, used}.
    // "err" means the dart was not accepted and nothing changed.
    function dart(g, target, mult) {
        const done = (kind, msg, label, used = true) => {
            const r = { kind, msg, used };
            if (used) { g.thrown++; g.darts.push(label); }
            g.msg = r;
            return r;
        };
        if (g.over) return { kind: 'err', msg: 'The game is over. Start a new game to play again.', used: false };
        if (g.thrown >= 3) return { kind: 'err', msg: g.players[g.cur].name + ' has thrown 3 darts. Press Next player (a 4th dart is not allowed).', used: false };
        const o = g.opts, p = g.players[g.cur];
        if (target === 25 && mult === 3) return { kind: 'err', msg: 'There is no treble bull. Choose single or double for the bull.', used: false };
        if (target === 0) return done('note', p.name + ' missed.', 'Miss');
        const ring = RING[mult];
        if (target === 25) {
            const label = mult === 2 ? 'Double bull' : 'Bull';
            if (o.bull === 'life') {
                if (p.lives >= o.lives) return done('note', label + ': ' + p.name + ' is already on the full ' + plural(o.lives, 'life', 'lives') + ', so it has no effect.', label);
                p.lives++;
                return done('ok', label + ': ' + p.name + ' gains a life (' + plural(p.lives, 'life', 'lives') + ').', label);
            }
            return done('note', label + ' does nothing. The bull is nobody\'s number in this game.', label);
        }
        const label = ring[0].toUpperCase() + ring.slice(1) + ' ' + target;
        const owner = g.players.find((q) => q.num === target);
        if (!owner) return done('note', label + ': nobody has ' + target + ', so it has no effect.', label);
        if (owner.out) return done('note', label + ': ' + target + ' was ' + owner.name + '\'s number and they are out, so it is dead and has no effect.', label);
        const w = weight(o.count, mult);
        if (!w) return done('note', label + ' has no effect. Only ' + countText(o.count) + ' count in this game.', label);
        if (owner === p) {
            if (!p.killer) {
                p.hits += w;
                if (p.hits >= o.need) {
                    p.killer = true;
                    return done('ok', label + ': ' + p.name + ' is now a killer. Hit other players\' numbers to take their lives.', label);
                }
                return done('ok', label + ': ' + p.name + ' is ' + (o.need - p.hits) + ' hit' + (o.need - p.hits === 1 ? '' : 's') + ' away from becoming a killer.', label);
            }
            // own number as a killer
            if (o.self === 'none') return done('note', label + ' is ' + p.name + '\'s own number. Nothing happens.', label);
            if (o.self === 'heal') {
                const gain = Math.min(w, o.lives - p.lives);
                p.lives += gain;
                return done('ok', label + ': ' + p.name + (gain ? ' gains ' + plural(gain, 'life', 'lives') + ' (' + plural(p.lives, 'life', 'lives') + ').' : ' is on the full ' + plural(o.lives, 'life', 'lives') + ', so it has no effect.'), label);
            }
            let text = label + ' is ' + p.name + '\'s own number.';
            if (o.self === 'status' || o.self === 'both') { p.killer = false; p.hits = 0; text += ' ' + p.name + ' is no longer a killer.'; }
            if (o.self !== 'status') {
                const lose = Math.min(p.lives, o.self === 'scaled' ? w : 1);
                p.lives -= lose;
                text += ' ' + p.name + ' loses ' + plural(lose, 'life', 'lives') + ' (' + plural(p.lives, 'life', 'lives') + ' left).';
                if (!p.lives) return finish(g, p, text, label, done);
            }
            return done('bad', text, label);
        }
        if (!p.killer) return done('note', label + ' has no effect: ' + p.name + ' is not a killer yet. Hit your own number (' + p.num + ') first.', label);
        const lose = Math.min(owner.lives, w);
        owner.lives -= lose;
        let text = label + ': ' + owner.name + ' loses ' + plural(lose, 'life', 'lives') + ' (' + plural(owner.lives, 'life', 'lives') + ' left).';
        if (o.loseStatus && owner.killer && owner.lives > 0) { owner.killer = false; owner.hits = 0; text += ' ' + owner.name + ' is no longer a killer.'; }
        if (!owner.lives) return finish(g, owner, text, label, done);
        return done('ok', text, label);
    }

    // a player just reached 0 lives
    function finish(g, who, text, label, done) {
        who.out = true; who.killer = false;
        text += ' ' + who.name + ' is eliminated.';
        const left = alive(g);
        if (left.length === 1) {
            g.over = true; g.winner = g.players.indexOf(left[0]);
            return done('win', text + ' ' + left[0].name + ' wins!', label);
        }
        // the thrower knocked themselves out: their turn is over
        if (who === g.players[g.cur]) g.thrown = 2;
        return done('ok', text, label);
    }

    // g.undo is a list of JSON snapshots, newest last
    function snap(stack, g) {
        stack.push(JSON.stringify(g));
        if (stack.length > 200) stack.shift();
    }
    /* engine:end */

    // ---------------------------------------------------------------- page
    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
    const KEY = 'cp.killerdarts.v1';
    const root = $('.kd');
    if (!root) return;

    let G = null, undoStack = [], mult = 2;
    const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const rnd = () => { const a = new Uint32Array(1); crypto.getRandomValues(a); return a[0] / 4294967296; };

    function save() { try { localStorage.setItem(KEY, JSON.stringify({ g: G, undo: undoStack, mult })); } catch (e) { } }
    function load() {
        try {
            const s = JSON.parse(localStorage.getItem(KEY));
            if (s && s.g && s.g.v === 1 && Array.isArray(s.g.players) && s.g.players.length > 1) { G = s.g; undoStack = Array.isArray(s.undo) ? s.undo : []; mult = [1, 2, 3].includes(s.mult) ? s.mult : 2; }
        } catch (e) { }
    }
    function forget() { try { localStorage.removeItem(KEY); } catch (e) { } }

    function show(view) {
        $$('[data-view]', root).forEach((el) => { el.hidden = el.dataset.view !== view; });
    }

    // ---------- setup
    const rowsBox = $('.kd-rows');
    function addRow(name = '', num = '') {
        if ($$('.kd-row', rowsBox).length >= MAX_PLAYERS) return;
        const d = document.createElement('div');
        d.className = 'kd-row';
        d.innerHTML = '<label>Player name <input class="field" name="name" maxlength="20" autocomplete="off" value="' + esc(name) + '"><small></small></label>' +
            '<label>Number (optional) <input class="field" name="num" type="number" inputmode="numeric" min="1" max="20" placeholder="Random" value="' + esc(num) + '"><small></small></label>' +
            '<button class="btn small" type="button" data-act="remove" aria-label="Remove player">Remove</button>';
        rowsBox.appendChild(d);
    }
    function readRows() {
        return $$('.kd-row', rowsBox).map((r) => ({ name: $('[name=name]', r).value, num: $('[name=num]', r).value === '' ? null : Number($('[name=num]', r).value) }));
    }
    function opts() {
        const f = $('.kd-form');
        const v = (n) => f.elements[n].value;
        return {
            lives: Math.min(9, Math.max(1, parseInt(v('lives'), 10) || 3)), need: Number(v('need')), count: v('count'), self: v('self'),
            straight: f.elements.straight.checked, loseStatus: f.elements.loseStatus.checked, bull: v('bull'),
        };
    }
    function clearErrors() {
        $$('.kd-row [aria-invalid]', root).forEach((i) => i.removeAttribute('aria-invalid'));
        $$('.kd-row small', root).forEach((s) => { s.textContent = ''; });
        $('.kd-setup-err').hidden = true;
    }
    function startGame(e) {
        e.preventDefault();
        clearErrors();
        const res = checkSetup(readRows(), rnd);
        if (res.errors) {
            res.errors.forEach((er) => {
                const row = $$('.kd-row', rowsBox)[er.i];
                const inp = $('[name=' + er.field + ']', row);
                inp.setAttribute('aria-invalid', 'true');
                inp.parentNode.querySelector('small').textContent = er.text;
            });
            const box = $('.kd-setup-err');
            box.textContent = 'Fix the ' + plural(res.errors.length, 'problem', 'problems') + ' marked above to start. ' + res.errors[0].text;
            box.hidden = false;
            const first = $('[aria-invalid]', rowsBox);
            if (first) first.focus();
            return;
        }
        G = newGame(res.list, opts());
        undoStack = [];
        G.msg = { kind: 'note', msg: 'Game on. ' + G.players[0].name + ' throws first. Hit your own double to become a killer.' };
        save(); render();
    }
    function randomise() {
        const nums = Array.from({ length: 20 }, (_, i) => i + 1);
        $$('.kd-row', rowsBox).forEach((r) => { $('[name=num]', r).value = nums.splice(Math.floor(rnd() * nums.length), 1)[0]; });
        clearErrors();
    }

    // ---------- play
    function lifePips(p, max) {
        let s = '';
        for (let i = 0; i < max; i++) s += '<i class="' + (i < p.lives ? 'on' : '') + '"></i>';
        return '<span class="kd-pips" role="img" aria-label="' + plural(p.lives, 'life', 'lives') + '">' + s + '</span>';
    }
    function render() {
        if (!G) { show('setup'); return; }
        show('play');
        const g = G, p = g.players[g.cur];
        const m = g.msg;
        const msg = $('.kd-msg');
        msg.className = 'kd-msg ' + (m ? ({ ok: 'ok', win: 'ok', err: 'err', bad: 'err', note: 'note' })[m.kind] : 'note');
        msg.textContent = m ? m.msg : '';
        const win = $('.kd-win');
        win.hidden = !g.over;
        if (g.over) $('.kd-win-name').textContent = g.players[g.winner].name + ' wins';

        const o = g.opts;
        $('.kd-active').innerHTML = [
            plural(o.lives, 'life', 'lives') + ' each; ' + plural(o.need, 'hit', 'hits') + ' on your own number to become a killer.',
            'Counting darts: ' + countText(o.count) + (o.count === 'dt' ? ' (treble = 2)' : o.count === 'any' ? ' (single 1, double 2, treble 3)' : '') + '.',
            'Killer hits own number: ' + ({ life: 'loses a life', scaled: 'loses lives equal to the dart', status: 'loses killer status', both: 'loses a life and killer status', heal: 'gains lives up to the start', none: 'nothing happens' })[o.self] + '.',
            o.bull === 'life' ? 'Bull gives the thrower a life (up to the start).' : 'Bull does nothing.',
            o.straight ? 'Everyone starts as a killer.' : '',
            o.loseStatus ? 'A killer who loses a life loses killer status.' : '',
        ].filter(Boolean).map((t) => '<li>' + esc(t) + '</li>').join('');
        $('.kd-turn-name').textContent = g.over ? 'Game over' : p.name;
        $('.kd-turn-sub').textContent = g.over ? '' : (g.thrown >= 3 ? 'Three darts thrown' : 'Dart ' + (g.thrown + 1) + ' of 3') + ' · ' + (p.killer ? 'killer' : 'aim at your double ' + p.num);
        $('.kd-darts').innerHTML = [0, 1, 2].map((i) => '<span class="badge' + (g.darts[i] ? ' b' : '') + '">' + esc(g.darts[i] || 'Dart ' + (i + 1)) + '</span>').join('');
        const next = $('[data-act=next]');
        next.disabled = g.over;
        next.classList.toggle('primary', !g.over && g.thrown >= 3);
        $('[data-act=undo]').disabled = !undoStack.length;
        $$('[data-mult]').forEach((b) => { b.classList.toggle('on', Number(b.dataset.mult) === mult); b.setAttribute('aria-pressed', Number(b.dataset.mult) === mult); });
        $('.kd-ctl').classList.toggle('is-done', g.over);

        $('.kd-grid').innerHTML = Array.from({ length: 20 }, (_, i) => i + 1).concat(25).map((n) => {
            const o = g.players.find((q) => q.num === n);
            const cls = o ? (o.out ? ' dead' : o === p ? ' mine' : ' owned') : '';
            const cap = n === 25 ? 'Bull' : o ? o.name : 'Free';
            return '<button type="button" class="kd-n' + cls + '" data-act="hit" data-n="' + n + '"' + (g.over ? ' disabled' : '') + '><b>' + (n === 25 ? '25' : n) + '</b><small>' + esc(cap) + '</small></button>';
        }).join('');

        const alive = g.players.filter((q) => !q.out).length;
        $('.kd-players-n').textContent = alive + ' of ' + g.players.length + ' left';
        $('.kd-players').innerHTML = g.players.map((q, i) =>
            '<div class="kd-player' + (i === g.cur && !g.over ? ' is-turn' : '') + (q.out ? ' is-out' : '') + '">' +
            '<span class="kd-num">' + q.num + '</span><div class="kd-pname"><b>' + esc(q.name) + '</b>' + lifePips(q, g.opts.lives) + '</div>' +
            (q.out ? '<span class="badge r">Out</span>' : q.killer ? '<span class="badge g">Killer</span>' : g.opts.need > 1 ? '<span class="badge">' + q.hits + '/' + g.opts.need + ' hits</span>' : '<span class="badge">Not a killer</span>') + '</div>').join('');
    }
    function hit(n) {
        const before = JSON.stringify(G);
        const r = dart(G, n === 0 ? 0 : n, mult);
        if (r.used) { undoStack.push(before); if (undoStack.length > 200) undoStack.shift(); } else G.msg = r;
        save(); render();
    }
    function go(e) {
        const b = e.target.closest('[data-act],[data-mult]');
        if (!b) return;
        if (b.dataset.mult) { mult = Number(b.dataset.mult); save(); render(); return; }
        const act = b.dataset.act;
        if (act === 'hit') hit(Number(b.dataset.n));
        else if (act === 'miss') hit(0);
        else if (act === 'next' && G && !G.over) {
            undoStack.push(JSON.stringify(G));
            nextPlayer(G);
            G.msg = { kind: 'note', msg: G.players[G.cur].name + ' to throw.' };
            save(); render();
        } else if (act === 'undo' && undoStack.length) {
            G = JSON.parse(undoStack.pop());
            G.msg = { kind: 'note', msg: 'Last action undone. ' + G.players[G.cur].name + ' to throw.' };
            save(); render();
        } else if (act === 'new') {
            if (G && !G.over && !confirm('Leave this game? It will be lost.')) return;
            G = null; undoStack = []; forget(); show('setup');
        } else if (act === 'add') addRow();
        else if (act === 'remove') { if ($$('.kd-row', rowsBox).length > 2) { b.closest('.kd-row').remove(); clearErrors(); } }
        else if (act === 'random') randomise();
    }

    load();
    if (!G) { addRow('Player 1'); addRow('Player 2'); addRow('Player 3'); }
    root.addEventListener('click', go);
    $('.kd-form').addEventListener('submit', startGame);
    render();

    window.KillerEngine = { newGame, dart, nextPlayer, checkSetup, weight };
})();
