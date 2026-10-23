/* 9-ball rack tracker: pure rack logic (also run by node for the self-check) + the pass-and-play UI */
(function (root) {
    'use strict';
    const DEFAULTS = { target: 7, breakFmt: 'alternate', threeFoul: true, pushOut: true, nineBreak: 'win', callNine: false, bih: 'anywhere', foulsMode: 'all', threePoint: false };
    const BALLS = [1, 2, 3, 4, 5, 6, 7, 8, 9];
    const FOULS_ALL = ['Scratch (cue ball potted)', 'Wrong ball hit first', 'No rail after contact', 'Ball jumped off the table', 'Push or double hit', 'Touched a ball', 'Feet off the floor', 'Illegal break', 'Other foul'];
    const FOULS_CUE = ['Scratch (cue ball potted)', 'Cue ball jumped off the table'];

    /* ---------- rack logic ---------- */
    const lowest = (g) => BALLS.find((b) => !g.down[b]);
    const other = (i) => 1 - i;

    function resetRack(g, breaker) {
        Object.assign(g, { down: {}, shot: 0, bih: false, fouls: [0, 0], push: null, turn: breaker, breaker, rackWin: null });
    }

    function newMatch(names, opts, first) {
        const g = { names, opts: Object.assign({}, DEFAULTS, opts), score: [0, 0], rackNo: 1, matchWin: null, undo: [], last: [] };
        resetRack(g, first === 1 ? 1 : 0);
        return g;
    }

    const strip = (g) => JSON.stringify(Object.assign({}, g, { undo: undefined }));
    function snap(g) { g.undo.push(strip(g)); if (g.undo.length > 100) g.undo.shift(); }

    function win(g, w, reason, text) {
        g.rackWin = { winner: w, reason, text };
        g.score[w]++;
        if (g.score[w] >= g.opts.target) g.matchWin = w;
    }

    // type: legal | miss | foul | push | pass | next | rerack. d: { potted: [], reason, called }.
    // Returns { msgs } or { error }. Validation happens before any change, so an error leaves the game untouched.
    function act(g, type, d) {
        d = d || {};
        const o = g.opts, msgs = [], say = (t, s) => msgs.push({ t, s });
        const err = (s) => ({ error: s });
        const finish = () => { g.last = msgs; return { msgs }; };
        const me = g.turn, opp = other(me), who = g.names[me], whoOpp = g.names[opp];

        if (type === 'next') {
            if (!g.rackWin || g.matchWin !== null) return err('There is no next rack to start.');
            snap(g);
            g.rackNo++;
            resetRack(g, o.breakFmt === 'winner' ? g.rackWin.winner : other(g.breaker));
            say('ok', 'Rack ' + g.rackNo + '. ' + g.names[g.turn] + ' breaks.');
            return finish();
        }
        if (g.rackWin) return err('This rack is over. Start the next rack or undo.');
        if (type === 'rerack') {
            snap(g);
            resetRack(g, g.breaker);
            say('note', 'Balls re-racked. ' + g.names[g.breaker] + ' breaks again.');
            return finish();
        }
        if (type === 'pass') {
            if (!g.push) return err('There is no push-out to pass back.');
            snap(g);
            g.turn = g.push.by; g.push = null;
            say('note', who + ' passes the shot back. ' + g.names[g.turn] + ' shoots.');
            return finish();
        }

        const pots = Array.from(new Set(d.potted || [])).sort((a, b) => a - b);
        for (const b of pots) if (g.down[b]) return err('Ball ' + b + ' is already down.');
        const brk = g.shot === 0, on = lowest(g);
        const list = (a) => a.join(', ');

        if (type === 'legal') {
            if (!pots.length) return err('Tap the balls that went down first, or use Miss / end turn.');
            let winKind = null, spot = false, ends = false;
            if (pots.includes(9)) {
                if (brk) { if (o.nineBreak === 'win') winKind = 'break'; else { spot = true; ends = pots.length === 1; } }
                else if (o.callNine && on !== 9 && !d.called) { spot = true; ends = true; }
                else winKind = on === 9 ? 'nine' : 'combo';
            }
            snap(g);
            if (winKind) {
                pots.forEach((b) => { g.down[b] = true; });
                const text = winKind === 'break' ? who + ' pots the 9 on the break.' : winKind === 'combo' ? who + ' wins with a combination on the 9.' : who + ' pots the 9.';
                win(g, me, winKind, text);
                say('ok', text + ' ' + who + ' wins the rack.');
                if (g.matchWin !== null) say('ok', who + ' wins the match ' + g.score[0] + ' to ' + g.score[1] + '.');
                return finish();
            }
            pots.filter((b) => b !== 9 || !spot).forEach((b) => { g.down[b] = true; });
            if (spot) say('note', '9 is re-spotted: ' + (brk ? 'it was not a winning 9 under this rule set.' : 'it was not called.'));
            const down = pots.filter((b) => b !== 9 || !spot);
            g.fouls[me] = 0; g.shot++; g.push = null; g.bih = false;
            if (ends) { g.turn = opp; say('note', who + ' stays off. ' + whoOpp + ' shoots.'); }
            else say('ok', (brk ? 'Legal break. ' : 'Legal shot. ') + (down.length ? 'Down: ' + list(down) + '. ' : '') + who + ' shoots again.');
            return finish();
        }

        if (type === 'miss') {
            if (pots.length) return err('Balls are marked as potted. Use Legal shot or Foul, or tap them again to clear.');
            snap(g);
            g.fouls[me] = 0; g.turn = opp; g.shot++; g.bih = false; g.push = null;
            say('note', (brk ? 'Legal break, nothing down (four balls to a rail). ' : 'Turn over. ') + whoOpp + ' shoots.');
            return finish();
        }

        if (type === 'foul') {
            if (!d.reason) return err('Choose the reason for the foul.');
            snap(g);
            if (pots.includes(9)) say('note', '9 is re-spotted.');
            pots.filter((b) => b !== 9).forEach((b) => { g.down[b] = true; });
            g.fouls[me]++; g.shot++; g.push = null;
            say('err', 'Foul by ' + who + ': ' + d.reason.toLowerCase() + '.');
            if (o.threeFoul && g.fouls[me] >= 3) {
                win(g, opp, 'three-foul', who + ' commits a third foul in a row.');
                say('err', who + ' commits a third foul in a row and loses the rack. ' + whoOpp + ' wins the rack.');
                if (g.matchWin !== null) say('ok', whoOpp + ' wins the match ' + g.score[0] + ' to ' + g.score[1] + '.');
                return finish();
            }
            if (o.threeFoul && g.fouls[me] === 2) say('err', 'Warning: ' + who + ' is on two fouls in a row. A third loses the rack.');
            g.turn = opp; g.bih = true;
            say('note', whoOpp + ' has ball in hand' + (o.bih === 'line' ? ' behind the head string.' : ' anywhere.'));
            return finish();
        }

        if (type === 'push') {
            if (!o.pushOut) return err('Push-outs are switched off for this match.');
            if (g.shot !== 1) return err('A push-out is only allowed on the shot straight after the break.');
            snap(g);
            if (pots.includes(9)) say('note', '9 is re-spotted.');
            pots.filter((b) => b !== 9).forEach((b) => { g.down[b] = true; });
            g.push = { by: me }; g.turn = opp; g.shot++; g.bih = false;
            say('note', who + ' pushes out. ' + whoOpp + ' can shoot from here or pass the shot back.');
            return finish();
        }
        return err('Unknown action.');
    }

    function undo(g) {
        if (!g.undo.length) return { error: 'Nothing to undo.' };
        const s = JSON.parse(g.undo.pop()), rest = g.undo;
        Object.keys(g).forEach((k) => { delete g[k]; });
        Object.assign(g, s, { undo: rest, last: [{ t: 'note', s: 'Undid the last action.' }] });
        return { msgs: g.last };
    }

    /* ---------- self-check: node games/pub-games/9-ball/9ball.js ---------- */
    function selfTest() {
        const assert = require('assert');
        const mk = (o) => newMatch(['A', 'B'], o || { target: 2 }, 0);
        let g = mk();
        assert.strictEqual(act(g, 'legal', { potted: [9] }).msgs[0].t, 'ok');           // 9 on the break
        assert.strictEqual(g.rackWin.reason, 'break'); assert.deepStrictEqual(g.score, [1, 0]);
        act(g, 'next'); assert.strictEqual(g.turn, 1); assert.strictEqual(g.rackNo, 2);   // alternate breaks
        g = mk(); act(g, 'foul', { potted: [9, 3], reason: 'Scratch (cue ball potted)' }); // 9 on a foul break
        assert.ok(!g.down[9] && g.down[3] && g.bih && g.turn === 1 && !g.rackWin);
        assert.ok(act(g, 'push', {}).error === undefined);                                  // shot 1 push-out
        assert.strictEqual(g.turn, 0); assert.ok(g.push); act(g, 'pass'); assert.strictEqual(g.turn, 1);
        assert.ok(act(g, 'push', {}).error);                                                // no second push-out
        g = mk(); act(g, 'miss'); assert.ok(act(g, 'push', { potted: [9] }).msgs.some((m) => /re-spotted/.test(m.s)));
        assert.ok(act(g, 'legal', { potted: [3] }).msgs.length);                            // opponent shoots
        assert.ok(act(g, 'legal', { potted: [3] }).error);                                  // already down
        g = mk(); act(g, 'miss'); act(g, 'foul', { reason: 'x' });                          // B fouls (1)
        act(g, 'foul', { reason: 'x' }); act(g, 'foul', { reason: 'x' });                   // A (1), B (2)
        assert.ok(act(g, 'foul', { reason: 'x' }).msgs.some((m) => /third foul/.test(m.s)) === false); // A (2), warning only
        act(g, 'foul', { reason: 'x' });                                          // B (3)
        assert.ok(g.rackWin && g.rackWin.reason === 'three-foul' && g.rackWin.winner === 0);
        assert.ok(undo(g).msgs && !g.rackWin && g.fouls[1] === 2);                          // undo restores
        g = mk(); act(g, 'miss'); assert.strictEqual(act(g, 'legal', { potted: [2, 9] }).msgs[0].s.indexOf('combination') > -1, true);
        g = mk({ target: 2, callNine: true }); act(g, 'miss'); act(g, 'legal', { potted: [1] }); // call the 9
        act(g, 'legal', { potted: [9] }); assert.ok(!g.rackWin && !g.down[9] && g.turn === 0);
        act(g, 'legal', { potted: [2, 3, 4, 5, 6, 7, 8] }); act(g, 'legal', { potted: [9] }); assert.ok(g.rackWin);
        g = mk({ target: 1 }); act(g, 'legal', { potted: [9] }); assert.strictEqual(g.matchWin, 0); assert.ok(act(g, 'next').error);
        g = mk({ target: 2, nineBreak: 'spot' }); act(g, 'legal', { potted: [9] }); assert.ok(!g.rackWin && g.turn === 1);
        assert.strictEqual(act(mk(), 'miss').error, undefined);
        console.log('9ball self-check passed');
    }

    const NB = { DEFAULTS, newMatch, act, undo, lowest, resetRack, selfTest, FOULS_ALL, FOULS_CUE };
    if (typeof module !== 'undefined' && module.exports) { module.exports = NB; if (require.main === module) selfTest(); return; }
    root.NineBall = NB;

    /* ---------- UI ---------- */
    document.addEventListener('DOMContentLoaded', () => {
        const page = document.querySelector('.nb');
        if (!page) return;
        const { esc, store } = UI;
        const KEY = 'cp.9ball.game';
        const setup = page.querySelector('.nb-setup'), play = page.querySelector('.nb-play');
        let G = null, sel = [], reason = '', called = false, tmsg = [];

        const load = () => {
            const g = store.get(KEY, null);
            return g && Array.isArray(g.names) && g.opts && Array.isArray(g.undo) && g.down ? g : null;
        };
        const save = () => { try { store.set(KEY, G); } catch (e) { /* storage blocked: the game still works */ } };
        const ballHtml = (n) => '<span class="nb-ball b' + n + (n > 8 ? ' stripe' : '') + '" aria-hidden="true"><b>' + n + '</b></span>';
        const bihText = () => G.shot === 0 ? 'Cue ball in hand behind the head string' : G.bih ? (G.opts.bih === 'line' ? 'Ball in hand behind the head string' : 'Ball in hand anywhere') : '';

        function rulesList(o) {
            const li = [
                'Race to ' + o.target + ' racks. ' + (o.breakFmt === 'winner' ? 'The winner of a rack breaks.' : 'Players alternate the break.'),
                'The 9 on the break ' + (o.nineBreak === 'win' ? 'wins the rack.' : 'does not win: it is spotted and must be called.'),
                o.callNine ? 'Call the 9 unless it is the last ball. An uncalled 9 is spotted and the turn ends.' : 'Any legal pot of the 9 wins, combinations included.',
                o.pushOut ? 'A push-out is allowed on the shot after the break only.' : 'No push-outs.',
                o.foulsMode === 'cue' ? 'Only cue-ball fouls count: a scratch or the cue ball off the table.' : 'Every foul gives the opponent ball in hand.',
                'Ball in hand ' + (o.bih === 'line' ? 'behind the head string.' : 'anywhere on the table.'),
                o.threeFoul ? 'Three fouls in a row by one player lose the rack, with a warning at two.' : 'No three-foul rule.',
                o.threePoint ? 'Break: at least three balls potted or past the head string, otherwise it is a foul.' : 'Break: pot a ball or drive four balls to a rail.'
            ];
            return li.map((s) => '<li>' + esc(s) + '</li>').join('');
        }

        function show(view) { setup.hidden = view !== 'setup'; play.hidden = view !== 'play'; }

        function render() {
            if (!G) { show('setup'); return; }
            show('play');
            const o = G.opts, me = G.turn, on = G.rackWin ? 0 : NineBall.lowest(G);
            const names = G.names.map(esc);
            const over = G.rackWin;
            let h = '<div class="stat-row">' +
                '<div class="stat-card"><b>' + G.score[0] + ' : ' + G.score[1] + '</b><span>Rack ' + G.rackNo + ' score</span><small>Race to ' + o.target + '</small></div>' +
                '<div class="stat-card"><b>' + (over ? '-' : G.shot === 0 ? 'Break' : 'Shot ' + (G.shot + 1)) + '</b><span>' + (over ? 'Rack over' : 'This rack') + '</span><small>' + (over ? '' : (9 - Object.keys(G.down).length) + ' balls on the table') + '</small></div></div>';

            if (over) {
                const matchOver = G.matchWin !== null;
                h += '<section class="card nb-banner ' + (matchOver ? 'gold' : 'ok') + '" role="status"><h2>' + (matchOver ? esc(G.names[G.matchWin]) + ' wins the match' : esc(G.names[over.winner]) + ' wins the rack') + '</h2>' +
                    '<p>' + esc(over.text) + ' Score ' + G.score[0] + ' to ' + G.score[1] + '.</p>' +
                    '<div class="actions">' + (matchOver ? '<button class="btn primary" type="button" data-act="new">New match</button>' : '<button class="btn primary" type="button" data-act="next">Start rack ' + (G.rackNo + 1) + '</button>') +
                    '<button class="btn" type="button" data-act="undo">Undo</button></div></section>';
            }

            h += '<div class="nb-players">' + G.names.map((n, i) => {
                const f = G.fouls[i], turn = !over && i === me;
                return '<div class="nb-player' + (turn ? ' turn' : '') + '"><div class="nb-pname"><b>' + names[i] + '</b><span class="nb-score">' + G.score[i] + '</span></div>' +
                    '<div class="badges">' + (turn ? '<span class="badge b">' + (G.shot === 0 ? 'Breaking' : 'Shooting') + '</span>' : '') + '</div>' +
                    (o.threeFoul ? '<div class="nb-fouls' + (f >= 2 ? ' warn' : '') + '" role="img" aria-label="' + f + ' of 3 fouls in a row">' +
                        [0, 1, 2].map((k) => '<i class="' + (k < f ? 'on' : '') + '"></i>').join('') + '<small>' + (f >= 2 ? 'Next foul loses the rack' : f + ' fouls in a row') + '</small></div>' : '') + '</div>';
            }).join('') + '</div>';

            if (!over) {
                const bih = bihText();
                h += '<p class="nb-turn" aria-live="polite"><b>' + names[me] + '</b> ' + (G.shot === 0 ? 'to break' : 'to shoot') + (bih ? ' <span class="badge r">' + esc(bih) + '</span>' : '') + '</p>';
                if (G.push) h += '<section class="card nb-push"><h2>Push-out</h2><p>' + esc(G.names[G.push.by]) + ' pushed out. ' + names[me] + ' can shoot from here, or pass the shot back.</p>' +
                    '<div class="actions"><button class="btn primary" type="button" data-act="shoot">Shoot from here</button><button class="btn" type="button" data-act="pass">Pass back</button></div></section>';
            }

            if (tmsg.length || (G.last && G.last.length && !over)) {
                const m = tmsg.length ? tmsg : G.last;
                h += '<div class="nb-msgs" aria-live="polite">' + m.map((x) => '<p class="' + (x.t === 'err' ? 'err' : x.t === 'ok' ? 'ok' : 'note') + '">' + esc(x.s) + '</p>').join('') + '</div>';
            }

            h += '<section class="card nb-table"><h2>Balls <span class="n">' + (over ? '' : 'Tap the balls that go down on this shot') + '</span></h2><div class="nb-rack">' +
                BALLS.map((n) => {
                    const down = !!G.down[n], s = sel.includes(n), isOn = n === on && !down;
                    const cap = down ? 'Down' : s ? 'Marked' : isOn ? 'Ball on' : '';
                    return '<button type="button" class="nb-bb' + (down ? ' down' : '') + (s ? ' sel' : '') + (isOn ? ' on' : '') + '" data-act="ball" data-n="' + n + '" aria-pressed="' + s + '" aria-label="Ball ' + n + (down ? ', down' : isOn ? ', ball on' : '') + (s ? ', marked' : '') + '"' + (over ? ' disabled' : '') + '>' +
                        ballHtml(n) + '<span class="nb-cap" aria-hidden="true">' + cap + '</span></button>';
                }).join('') + '</div>' +
                (!over ? '<p class="muted nb-marked">' + (sel.length ? 'Marked on this shot: ' + sel.join(', ') : 'No balls marked yet.') + '</p>' : '') + '</section>';

            if (!over) {
                const reasons = o.foulsMode === 'cue' ? FOULS_CUE : FOULS_ALL.filter((r) => r !== 'Illegal break' || G.shot === 0);
                if (!reasons.includes(reason)) reason = '';
                h += '<section class="card nb-actions"><h2>Result of the shot</h2>' +
                    (o.callNine ? '<label class="check"><input type="checkbox" data-act="called"' + (called ? ' checked' : '') + '> The 9 was called</label>' : '') +
                    '<div class="actions"><button class="btn primary" type="button" data-act="legal">Legal shot</button><button class="btn" type="button" data-act="miss">Miss / end turn</button>' +
                    '<button class="btn' + (G.shot === 1 && o.pushOut ? '' : ' nb-off') + '" type="button" data-act="push"' + (G.shot === 1 && o.pushOut ? '' : ' aria-disabled="true"') + '>Push-out</button></div>' +
                    '<div class="nb-foulrow"><label>Foul reason <select class="field" data-act="reason"><option value="">Choose a reason</option>' +
                    reasons.map((r) => '<option' + (r === reason ? ' selected' : '') + '>' + esc(r) + '</option>').join('') + '</select></label>' +
                    '<button class="btn" type="button" data-act="foul">Foul</button></div>' +
                    '<p class="muted">Marked balls count as potted on this shot. For a foul, mark any balls that went down too.</p></section>';
            }

            h += '<div class="actions nb-foot"><button class="btn small" type="button" data-act="undo"' + (G.undo.length ? '' : ' disabled') + '>Undo (' + G.undo.length + ')</button>' +
                (!over ? '<button class="btn small" type="button" data-act="rerack">Re-rack (stalemate)</button>' : '') +
                '<button class="btn small" type="button" data-act="new">New match</button></div>' +
                '<details class="card nb-live"><summary>Rules in this match</summary><ul>' + rulesList(o) + '</ul></details>';
            play.innerHTML = h;
        }

        function run(type, d) {
            const r = NineBall.act(G, type, d);
            if (r.error) { tmsg = [{ t: 'err', s: r.error }]; render(); return; }
            tmsg = []; sel = []; called = false; reason = '';
            save(); render();
        }

        play.addEventListener('click', (e) => {
            const b = e.target.closest('[data-act]');
            if (!b || !G || b.disabled) return;
            const a = b.dataset.act;
            if (a === 'ball') {
                const n = +b.dataset.n;
                if (G.down[n]) { tmsg = [{ t: 'err', s: 'Ball ' + n + ' is already down.' }]; render(); return; }
                sel = sel.includes(n) ? sel.filter((x) => x !== n) : sel.concat(n);
                tmsg = []; render();
            } else if (a === 'legal') run('legal', { potted: sel, called });
            else if (a === 'miss') run('miss', { potted: sel });
            else if (a === 'foul') run('foul', { potted: sel, reason });
            else if (a === 'push') run('push', { potted: sel });
            else if (a === 'pass') run('pass');
            else if (a === 'shoot') { G.last = []; G.push = null; save(); tmsg = []; render(); }
            else if (a === 'next') run('next');
            else if (a === 'rerack') run('rerack');
            else if (a === 'undo') { const r = NineBall.undo(G); sel = []; tmsg = r.error ? [{ t: 'err', s: r.error }] : []; if (!r.error) save(); render(); }
            else if (a === 'new') { show('setup'); }
        });
        play.addEventListener('change', (e) => {
            const a = e.target.dataset.act;
            if (a === 'reason') reason = e.target.value;
            if (a === 'called') called = e.target.checked;
        });

        /* setup form */
        const form = setup;
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const f = new FormData(form), v = (k) => f.get(k);
            const names = [(v('p1') || '').trim() || 'Player 1', (v('p2') || '').trim() || 'Player 2'];
            const target = Math.max(1, Math.min(99, parseInt(v('target'), 10) || 7));
            const opts = {
                target, breakFmt: v('breakFmt') === 'winner' ? 'winner' : 'alternate', threeFoul: !!v('threeFoul'), pushOut: !!v('pushOut'),
                nineBreak: v('nineBreak') === 'spot' ? 'spot' : 'win', callNine: !!v('callNine'), bih: v('bih') === 'line' ? 'line' : 'anywhere',
                foulsMode: v('foulsMode') === 'cue' ? 'cue' : 'all', threePoint: !!v('threePoint')
            };
            G = NineBall.newMatch(names, opts, v('first') === '2' ? 1 : 0);
            sel = []; tmsg = []; reason = ''; called = false;
            save(); render();
        });
        setup.querySelector('[data-act="cancel"]').addEventListener('click', () => { if (G) show('play'); });

        G = load();
        const cancel = setup.querySelector('[data-act="cancel"]');
        cancel.hidden = !G;
        new MutationObserver(() => { cancel.hidden = !G; }).observe(setup, { attributes: true, attributeFilter: ['hidden'] });
        render();
    });
})(typeof window !== 'undefined' ? window : globalThis);
