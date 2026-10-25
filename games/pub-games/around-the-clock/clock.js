// Around the Clock: darts tracker. Engine first (no DOM, runs under node for the self-check), page code below.
(function () {
    'use strict';

    /* engine:start */
    const DEFAULTS = { skip: false, hitMode: 'any', finish: 'either', order: 'up', req: 1, equal: true };
    const KEY = 'cp.aroundclock.v1';
    const nm = (n) => (n === 25 ? 'the bull' : String(n));
    const MNAME = ['miss', 'single', 'double', 'treble'];

    function buildSeq(order) {
        const nums = Array.from({ length: 20 }, (_, i) => i + 1);
        if (order === 'down') return nums.reverse().concat(25);
        if (order === 'bullfirst') return [25].concat(nums);
        if (order === 'random') {
            const a = nums.concat(25);
            for (let i = a.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1)); [a[i], a[j]] = [a[j], a[i]]; }
            return a;
        }
        return nums.concat(25);
    }

    function newGame(names, opts, seq) {
        const o = Object.assign({}, DEFAULTS, opts);
        return {
            v: 1, o, seq: seq || buildSeq(o.order),
            players: names.map((name) => ({ name, pos: 0, hits: 0, darts: 0, fin: null })),
            cur: 0, round: 1, turn: [], last: null, finishRound: null, over: false, winners: [], msg: null, undo: [],
        };
    }

    const target = (g) => g.seq[g.players[g.cur].pos];
    const reject = (g, text) => { g.msg = { text, kind: 'err' }; return false; };

    function snapshot(g) {
        const c = Object.assign({}, g); delete c.undo;
        g.undo.push(JSON.stringify(c));
        if (g.undo.length > 300) g.undo.shift();
    }

    // m: 0 miss, 1 single, 2 double, 3 treble. n: the number the dart landed on (default: the current target).
    // Returns false (nothing thrown, g.msg says why) or true (a dart was thrown, g.msg says what happened).
    function dart(g, m, n) {
        if (g.over) return reject(g, 'The game is over. Start a new game to throw again.');
        const p = g.players[g.cur], t = target(g), o = g.o;
        if (g.turn.length >= 3) return reject(g, 'That is already three darts this turn. Pass to the next player.');
        if (n === undefined) n = t;
        if (m !== 0 && n === 25 && m === 3) return reject(g, 'There is no treble on the bull. The dart was not counted, throw again.');
        snapshot(g);
        p.darts++;
        let ok = false, text, kind = 'note';
        if (m === 0) text = p.name + ' missed. ' + nm(t) + ' is still the target.';
        else if (n !== t) text = 'That was ' + nm(n) + ', but ' + p.name + ' needs ' + nm(t) + '. Hitting a number out of order does not count.';
        else if (t === 25) {
            ok = m === 1 ? o.finish !== 'bull' : o.finish !== 'outer';
            if (!ok) text = (m === 1 ? 'The outer bull' : 'The bullseye') + ' does not count here: ' + (o.finish === 'bull' ? 'you need the bullseye (inner bull).' : 'you need the outer bull.');
        } else {
            ok = o.hitMode === 'any' || (o.hitMode === 'doubles' && m === 2) || (o.hitMode === 'trebles' && m === 3);
            if (!ok) text = 'A ' + MNAME[m] + ' ' + nm(t) + ' does not count: only ' + o.hitMode + ' score.';
        }
        g.turn.push(m === 0 ? 'Miss' : n === 25 ? (m === 1 ? 'Outer bull' : 'Bull') : (n !== t ? MNAME[m][0].toUpperCase() + ' ' + n + ' (off target)' : MNAME[m][0].toUpperCase() + ' ' + n));
        let finished = false;
        if (ok) {
            p.hits++;
            if (p.hits < o.req) { text = 'Hit ' + p.hits + ' of ' + o.req + ' on ' + nm(t) + '. Hit it ' + (o.req - p.hits) + ' more time' + (o.req - p.hits === 1 ? '' : 's') + '.'; kind = 'ok'; }
            else {
                p.hits = 0;
                const last = g.seq.length - 1;
                if (p.pos === last) { finished = true; p.fin = p.darts; p.pos = g.seq.length; }
                else {
                    const step = o.skip && o.hitMode === 'any' && o.req === 1 ? m : 1;
                    const capped = p.pos + step > last;
                    p.pos = Math.min(p.pos + step, last);
                    text = 'Hit ' + nm(t) + '. Next target: ' + nm(g.seq[p.pos]) + '.';
                    if (step > 1) text = 'A ' + MNAME[m] + ' ' + nm(t) + ' skips ahead. Next target: ' + nm(g.seq[p.pos]) + '.' + (capped ? ' The skip stops there: you still have to hit it.' : '');
                    else if (m > 1 && o.hitMode === 'any') text += ' A ' + MNAME[m] + ' only counts as one step' + (o.req > 1 ? ' with more than one hit required.' : ' because skip ahead is off.');
                    kind = 'ok';
                }
            }
        }
        if (finished) {
            text = p.name + ' hit ' + nm(t) + ' and finished in ' + p.darts + ' dart' + (p.darts === 1 ? '' : 's') + '.';
            kind = 'ok';
            if (g.finishRound === null) g.finishRound = g.round;
        }
        g.msg = { text, kind };
        if (finished || g.turn.length >= 3) endTurn(g);
        return true;
    }

    function endTurn(g) {
        const n = g.players.length;
        g.last = { name: g.players[g.cur].name, darts: g.turn.slice() };
        g.turn = [];
        const anyFin = g.players.some((p) => p.fin !== null);
        let i = g.cur, found = false;
        for (let s = 0; s < n && !found; s++) {
            i++;
            if (i >= n) { i = 0; g.round++; }
            found = g.players[i].fin === null;
        }
        if (anyFin && (!g.o.equal || !found || g.round > g.finishRound)) {
            const fins = g.players.filter((p) => p.fin !== null);
            const best = Math.min(...fins.map((p) => p.fin));
            g.winners = g.players.map((p, k) => (p.fin === best ? k : -1)).filter((k) => k >= 0);
            g.over = true;
            return;
        }
        g.cur = i;
    }

    function undo(g) {
        if (!g.undo.length) return false;
        const stack = g.undo, back = JSON.parse(stack.pop());
        Object.keys(g).forEach((k) => delete g[k]);
        Object.assign(g, back, { undo: stack, msg: { text: 'Undid the last dart.', kind: 'note' } });
        return true;
    }
    /* engine:end */

    const engine = { DEFAULTS, buildSeq, newGame, dart, undo, target };
    if (typeof module !== 'undefined' && module.exports) { module.exports = engine; return; }

    document.addEventListener('DOMContentLoaded', () => {
        const $ = (s, r) => (r || document).querySelector(s);
        const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));
        const esc = (s) => UI.esc(String(s));
        let G = null;
        let names = ['Player 1', 'Player 2'];
        const MAXP = 8;

        const save = () => { if (G) UI.store.set(KEY, G); };
        const forget = () => { try { localStorage.removeItem(KEY); } catch (e) { } };
        const load = () => {
            const g = UI.store.get(KEY, null);
            return g && g.v === 1 && Array.isArray(g.players) && g.players.length && Array.isArray(g.seq) && g.o ? g : null;
        };

        const show = (view) => { $$('[data-view]').forEach((el) => { el.hidden = el.dataset.view !== view; }); };

        function renderNames() {
            $('.atc-names').innerHTML = names.map((n, i) =>
                '<div class="atc-name-row"><label>Player ' + (i + 1) + ' <input class="field" data-i="' + i + '" value="' + esc(n) + '" maxlength="20" autocomplete="off"></label>' +
                (names.length > 1 ? '<button class="btn small" type="button" data-act="rm" data-i="' + i + '" aria-label="Remove player ' + (i + 1) + '">Remove</button>' : '') + '</div>').join('');
            $('[data-act=add]').hidden = names.length >= MAXP;
        }

        function readOpts() {
            const f = $('.atc-form');
            return {
                skip: f.skip.checked, hitMode: f.hitMode.value, finish: f.finish.value,
                order: f.order.value, req: +f.req.value, equal: f.equal.checked,
            };
        }

        function describe(o) {
            const l = [];
            l.push(o.order === 'down' ? 'Order: 20 down to 1, then the bull.' : o.order === 'bullfirst' ? 'Order: the bull first, then 1 to 20.' : o.order === 'random' ? 'Order: all 21 targets in a shuffled order.' : 'Order: 1 to 20, then the bull.');
            l.push(o.hitMode === 'any' ? 'Any single, double or treble of the target counts.' : o.hitMode === 'doubles' ? 'Only doubles of the target count.' : 'Only trebles of the target count.');
            if (o.skip && o.hitMode === 'any' && o.req === 1) l.push('Skip ahead: a double jumps 2 targets, a treble 3. A jump never finishes the game: the last target must be hit itself.');
            else if (o.skip) l.push('Skip ahead is on but does not apply with this hit rule or with more than one hit required.');
            l.push(o.finish === 'either' ? 'Either the outer bull (25) or the bullseye (50) counts.' : o.finish === 'bull' ? 'Only the bullseye (inner bull) counts.' : 'Only the outer bull (25) counts.');
            if (o.req > 1) l.push('You need ' + o.req + ' hits on each target.');
            l.push(o.equal ? 'Equal turns: if someone finishes, later players in that round still throw. Fewest darts wins; level is a tie.' : 'First to finish wins straight away.');
            return l;
        }

        const label = (n) => (n === 25 ? 'Bull' : n);

        function render() {
            if (!G) { show('setup'); renderNames(); $('.atc-rules-now').innerHTML = ''; return; }
            show('play');
            const g = G, p = g.players[g.cur], t = g.seq[Math.min(p.pos, g.seq.length - 1)];
            const done = g.over;
            $('.atc-banner').hidden = !done;
            if (done) {
                const w = g.winners.map((k) => g.players[k]);
                $('.atc-win-title').textContent = w.length > 1 ? 'Tie: ' + w.map((x) => x.name).join(' and ') : w[0].name + ' wins';
                $('.atc-win-sub').textContent = w.length > 1
                    ? 'Level on ' + w[0].fin + ' darts. Settle it with a shoot-off at the bull.'
                    : w[0].name + ' finished in ' + w[0].fin + ' dart' + (w[0].fin === 1 ? '' : 's') + ' (round ' + Math.ceil(w[0].fin / 3) + ').';
            }
            $('.atc-turn').hidden = done;
            if (!done) {
                $('.atc-who').textContent = p.name;
                $('.atc-dartno').textContent = 'Dart ' + (g.turn.length + 1) + ' of 3 · round ' + g.round;
                $('.atc-target').textContent = label(t);
                $('.atc-sub').textContent = g.o.req > 1 ? 'Hits on this target: ' + p.hits + ' of ' + g.o.req : (t === 25 ? (g.o.finish === 'either' ? 'Outer bull or bullseye' : g.o.finish === 'bull' ? 'Bullseye only' : 'Outer bull only') : 'Needs a ' + (g.o.hitMode === 'any' ? 'hit' : g.o.hitMode === 'doubles' ? 'double' : 'treble') + ' on ' + t);
                const bull = t === 25;
                $$('.atc-btn:not(.atc-miss)').forEach((b) => {
                    const m = +b.dataset.m;
                    b.textContent = bull ? ['', 'Outer bull', 'Bullseye', 'Treble'][m] : ['', 'Single', 'Double', 'Treble'][m];
                    b.hidden = bull && m === 3; // no treble ring on the bull
                });
                $('.atc-thisturn').textContent = g.turn.length ? 'This turn: ' + g.turn.join(' · ') : (g.last ? 'Last turn (' + g.last.name + '): ' + g.last.darts.join(' · ') : '');
            }
            const msg = $('.atc-msg');
            msg.hidden = !g.msg;
            if (g.msg) { msg.textContent = g.msg.text; msg.className = 'atc-msg ' + (g.msg.kind === 'err' ? 'err' : g.msg.kind === 'ok' ? 'ok' : 'note'); }
            $$('.atc-btn, .atc-seg button').forEach((b) => { b.disabled = done; });
            $('[data-act=undo]').disabled = !g.undo.length;
            $('.atc-players-n').textContent = g.players.length === 1 ? 'practice' : g.players.length;
            $('.atc-players').innerHTML = g.players.map((pl, i) => {
                const cells = g.seq.map((n, k) => '<i class="' + (k < pl.pos ? 'done' : k === pl.pos && pl.fin === null ? 'now' : '') + '">' + (n === 25 ? 'B' : n) + '</i>').join('');
                const state = pl.fin !== null ? 'Finished' : (i === g.cur && !done ? 'Throwing' : '');
                return '<div class="atc-player' + (i === g.cur && !done ? ' on' : '') + '"><div class="atc-prow"><b>' + esc(pl.name) + '</b>' +
                    (state ? ' <span class="badge ' + (pl.fin !== null ? 'g' : 'b') + '">' + state + '</span>' : '') +
                    '<span class="grow"></span><small class="muted">' + Math.min(pl.pos, g.seq.length) + ' of ' + g.seq.length + ' · ' + pl.darts + ' dart' + (pl.darts === 1 ? '' : 's') + '</small></div>' +
                    '<div class="atc-track" role="img" aria-label="' + esc(pl.name) + ': ' + Math.min(pl.pos, g.seq.length) + ' of ' + g.seq.length + ' targets">' + cells + '</div></div>';
            }).join('');
            $('.atc-rules-now').innerHTML = describe(g.o).map((s) => '<li>' + esc(s) + '</li>').join('');
        }

        function start() {
            const cleaned = names.map((n, i) => n.trim() || 'Player ' + (i + 1));
            G = newGame(cleaned, readOpts());
            G.msg = { text: 'Game on. ' + cleaned[0] + ' throws first at ' + nm(G.seq[0]) + '.', kind: 'note' };
            save(); render();
        }

        document.addEventListener('click', (e) => {
            const b = e.target.closest('[data-act], [data-m], [data-n]');
            if (!b) return;
            if (b.dataset.m !== undefined && G) { dart(G, +b.dataset.m); save(); render(); return; }
            if (b.dataset.n !== undefined && G) {
                const n = +b.dataset.n;
                if (!G.over && n === target(G) && G.turn.length < 3) { G.msg = { text: 'That is the target. Use the Single, Double or Treble buttons so the dart is scored properly.', kind: 'err' }; }
                else dart(G, 1, n);
                save(); render(); return;
            }
            const a = b.dataset.act;
            if (a === 'add' && names.length < MAXP) { names.push('Player ' + (names.length + 1)); renderNames(); }
            else if (a === 'rm') { names.splice(+b.dataset.i, 1); renderNames(); }
            else if (a === 'undo' && G) { undo(G); save(); render(); }
            else if (a === 'new') $('#atc-new').showModal();
            else if (a === 'confirm-new') { $('#atc-new').close(); names = G ? G.players.map((p) => p.name) : names; G = null; forget(); render(); }
            else if (a === 'again') { const nm2 = G.players.map((p) => p.name), o = G.o; G = newGame(nm2, o); G.msg = { text: 'New game. ' + nm2[0] + ' throws first.', kind: 'note' }; save(); render(); }
            else if (a === 'close') $('#atc-new').close();
        });
        $('.atc-names').addEventListener('input', (e) => { if (e.target.dataset.i !== undefined) names[+e.target.dataset.i] = e.target.value; });
        $('.atc-form').addEventListener('submit', (e) => { e.preventDefault(); start(); });
        $('.atc-seg').innerHTML = Array.from({ length: 21 }, (_, i) => { const n = i === 20 ? 25 : i + 1; return '<button class="btn small" type="button" data-n="' + n + '">' + (n === 25 ? 'Bull' : n) + '</button>'; }).join('');

        G = load();
        if (G && !G.over && !G.players.length) G = null;
        render();
    });
})();
