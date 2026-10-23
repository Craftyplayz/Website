// Snooker scoreboard: pass-and-play on one phone. WPBSA rules with a few house-rule switches.
// Rules are in docs/snooker.md; the engine below has no DOM so it can be checked on its own.
(function () {
    'use strict';

    /* engine:start (no DOM in here) */
    const VALUE = { red: 1, yellow: 2, green: 3, brown: 4, blue: 5, pink: 6, black: 7 };
    const COLOURS = ['yellow', 'green', 'brown', 'blue', 'pink', 'black'];
    const PRESETS = {
        wpbsa: { freeBall: true, putBack: true, miss: true, flat4: false },
        league: { freeBall: true, putBack: true, miss: false, flat4: false },
        pub: { freeBall: true, putBack: false, miss: false, flat4: true },
    };
    const cap = (s) => s.charAt(0).toUpperCase() + s.slice(1);

    function newMatch(o) {
        const m = {
            v: 1, names: o.names, reds: o.reds, bestOf: o.bestOf, firstBreak: o.firstBreak || 0,
            rules: Object.assign({}, PRESETS.wpbsa, o.rules), handicap: o.handicap || [0, 0],
            frames: [0, 0], high: [0, 0], frameNo: 1, winner: null, f: null,
        };
        m.f = newFrame(m);
        return m;
    }

    // on: 'red' | 'colour' (any colour, after a red) | 'clear' (COLOURS[f.clear] in order)
    function newFrame(m) {
        const turn = (m.firstBreak + m.frameNo - 1) % 2;
        return {
            reds: m.reds, on: m.reds ? 'red' : 'clear', clear: 0, scores: m.handicap.slice(), turn, brk: 0,
            extraRed: false, lastColour: false, freeBall: false, misses: [0, 0], respot: false, choose: false, winner: null,
        };
    }

    const ballOn = (f) => (f.on === 'clear' ? COLOURS[f.clear] : f.on);
    // the least a foul can be worth right now
    const minFoul = (f) => Math.max(4, f.on === 'clear' ? VALUE[COLOURS[f.clear]] : 4);
    function remaining(f) {
        if (f.on === 'red') return f.reds * 8 + 27;
        if (f.on === 'colour') return f.reds * 8 + 7 + 27;
        return COLOURS.slice(f.clear).reduce((s, c) => s + VALUE[c], 0);
    }
    // snookers player i needs (0 = none), and whether clearing the table only ties
    function snookers(f, i) {
        const behind = f.scores[1 - i] - f.scores[i], rem = remaining(f);
        return { need: behind > rem ? Math.ceil((behind - rem) / minFoul(f)) : 0, tie: behind > 0 && behind === rem };
    }

    // '' when the ball can be potted now, else the reason it can't
    function check(m, ball, free) {
        const f = m.f;
        if (m.winner !== null || f.winner !== null) return 'The frame is over. Start the next frame.';
        if (f.choose) return 'Pick who plays the re-spotted black first.';
        if (free) {
            if (!f.freeBall) return 'A free ball is only allowed after a foul, when you are snookered.';
            if (ball === 'red') return 'Nominate a colour as the free ball, not a red.';
            if (f.on === 'clear' && COLOURS.indexOf(ball) <= f.clear) return ball === ballOn(f) ? cap(ball) + ' is already the ball on. Nominate another colour.' : 'The ' + ball + ' is off the table.';
            return '';
        }
        if (f.on === 'red') {
            if (ball === 'red') return '';
            return f.lastColour ? "A red is on. You can't pot two colours in a row." : 'A red is on. Pot a red before a colour.';
        }
        if (f.on === 'colour') {
            if (ball !== 'red') return '';
            if (f.extraRed && f.reds > 0) return '';
            return f.reds ? 'A colour is on. Playing at reds in two shots in a row is a 7-point foul.' : 'There are no reds left. Take a colour.';
        }
        if (ball === 'red') return 'All the reds are gone. ' + cap(ballOn(f)) + ' is on.';
        const i = COLOURS.indexOf(ball);
        if (i < f.clear) return 'The ' + ball + ' is already potted.';
        if (i > f.clear) return cap(ballOn(f)) + ' is on, not ' + ball + '. Clear the colours in order: yellow, green, brown, blue, pink, black.';
        return '';
    }

    function endBreak(m) {
        const f = m.f;
        m.high[f.turn] = Math.max(m.high[f.turn], f.brk);
        f.brk = 0; f.extraRed = false; f.lastColour = false;
        if (f.on === 'colour') toNextAfterColour(f);
    }
    function toNextAfterColour(f) {
        if (f.reds) f.on = 'red'; else { f.on = 'clear'; f.clear = 0; }
    }

    function winFrame(m, w, how) {
        const f = m.f;
        endBreak(m);
        f.winner = w; f.how = how;
        m.frames[w]++;
        if (m.frames[w] > m.bestOf / 2) m.winner = w;
        return { ok: true, msg: m.names[w] + (m.winner === w ? ' wins the match ' : ' wins the frame ') + (m.winner === w ? m.frames[w] + '–' + m.frames[1 - w] : f.scores[w] + '–' + f.scores[1 - w]) + '.' };
    }

    // the black was potted or fouled with only the black left
    function lastBlack(m) {
        const f = m.f;
        if (f.scores[0] === f.scores[1]) {
            endBreak(m);
            Object.assign(f, { on: 'clear', clear: 5, respot: true, choose: true, freeBall: false });
            return { ok: true, msg: 'Scores level, so the black is re-spotted. Toss a coin: the winner picks who plays first, with the cue ball in hand in the D.' };
        }
        return winFrame(m, f.scores[0] > f.scores[1] ? 0 : 1, 'black');
    }

    function pot(m, ball, free) {
        const err = check(m, ball, free);
        if (err) return { ok: false, msg: err };
        const f = m.f;
        let pts, msg = '';
        if (free) {
            pts = f.on === 'red' ? 1 : VALUE[ballOn(f)];
            if (f.on === 'red') { f.on = 'colour'; f.extraRed = false; }
            msg = 'Free ball: the ' + ball + ' counts as the ' + (pts === 1 ? 'red' : ballOn(f)) + ' for ' + pts + '.';
        } else if (ball === 'red') {
            pts = 1; f.reds--;
            if (f.on === 'colour') msg = 'Two reds in the same shot: both count.';
            f.on = 'colour'; f.extraRed = true;
        } else if (f.on === 'colour') {
            pts = VALUE[ball]; f.extraRed = false; f.lastColour = true;
            toNextAfterColour(f);
        } else {
            pts = VALUE[ball]; f.clear++;
        }
        f.scores[f.turn] += pts; f.brk += pts; f.freeBall = false; f.misses[f.turn] = 0;
        if (f.clear === 6) return lastBlack(m);
        return { ok: true, msg };
    }

    // end of a visit without a foul (missed, safety)
    function endVisit(m) {
        const f = m.f;
        if (m.winner !== null || f.winner !== null) return { ok: false, msg: 'The frame is over. Start the next frame.' };
        if (f.choose) return { ok: false, msg: 'Pick who plays the re-spotted black first.' };
        endBreak(m);
        f.turn = 1 - f.turn; f.freeBall = false;
        return { ok: true, msg: m.names[f.turn] + ' to play.' };
    }

    // o: { pen, reds (reds potted on the foul), miss (foul and a miss with a full ball view), putBack }
    function foul(m, o) {
        const f = m.f;
        if (m.winner !== null || f.winner !== null) return { ok: false, msg: 'The frame is over. Start the next frame.' };
        if (f.choose) return { ok: false, msg: 'Pick who plays the re-spotted black first.' };
        const pen = m.rules.flat4 ? 4 : Number(o.pen);
        if (!Number.isInteger(pen) || pen < 4 || pen > 7) return { ok: false, msg: 'A foul is worth 4 to 7 points.' };
        if (pen < minFoul(f)) return { ok: false, msg: 'With the ' + ballOn(f) + ' on, a foul is worth at least ' + minFoul(f) + '.' };
        const k = Number(o.reds) || 0;
        if (!Number.isInteger(k) || k < 0 || k > f.reds) return { ok: false, msg: 'Only ' + f.reds + ' reds are left on the table.' };
        const off = f.turn, opp = 1 - off;
        f.scores[opp] += pen;
        f.reds -= k;
        endBreak(m);
        if (f.on === 'red' && !f.reds) { f.on = 'clear'; f.clear = 0; } // last red down on a foul: yellow is on
        f.misses[off] = o.miss && m.rules.miss ? f.misses[off] + 1 : 0;
        let msg = pen + ' points to ' + m.names[opp] + '.';
        if (f.on === 'clear' && f.clear === 5) return lastBlack(m);
        if (f.misses[off] >= 3) return winFrame(m, opp, 'misses');
        if (f.misses[off] === 2) msg += ' Warning to ' + m.names[off] + ': a third miss with a full ball view loses the frame.';
        const back = o.putBack && m.rules.putBack;
        f.turn = back ? off : opp;
        // the free ball call is void if the offender is put back in; nothing to nominate with only the black left
        f.freeBall = !back && m.rules.freeBall;
        return { ok: true, msg: msg + ' ' + m.names[f.turn] + ' to play.' };
    }

    function chooseRespot(m, who) {
        const f = m.f;
        if (!f.choose) return { ok: false, msg: '' };
        f.choose = false; f.turn = who;
        return { ok: true, msg: m.names[who] + ' plays the black, cue ball in hand.' };
    }

    function concede(m, who) {
        if (m.winner !== null || m.f.winner !== null) return { ok: false, msg: 'The frame is over.' };
        return winFrame(m, 1 - who, 'concede');
    }

    function nextFrame(m) {
        if (m.winner !== null || m.f.winner === null) return { ok: false, msg: '' };
        m.frameNo++;
        m.f = newFrame(m);
        return { ok: true, msg: 'Frame ' + m.frameNo + ': ' + m.names[m.f.turn] + ' breaks.' };
    }

    // stalemate: scores in this frame are cancelled and the same player breaks again
    function rerack(m) {
        if (m.winner !== null || m.f.winner !== null) return { ok: false, msg: 'The frame is over.' };
        m.f = newFrame(m);
        return { ok: true, msg: 'Re-racked. ' + m.names[m.f.turn] + ' breaks again.' };
    }
    /* engine:end */

    // for the self-check (node): engine only
    if (typeof module !== 'undefined') module.exports = { newMatch, pot, foul, endVisit, remaining, snookers, check, chooseRespot, concede, nextFrame, rerack, minFoul };
    if (typeof document === 'undefined') return;

    document.addEventListener('DOMContentLoaded', () => {
        const { $, $$, esc, toast } = UI;
        const KEY = 'cp.snooker.v1';
        const root = $('.snk');
        const setup = $('.snk-setup', root), play = $('.snk-play', root);
        const foulDlg = $('#snk-foul'), concedeDlg = $('#snk-concede'), newDlg = $('#snk-new');
        let M = null, undo = [], freeMode = false, draftReds = 15;

        const storageOk = (() => { try { localStorage.setItem('cp.snooker.t', '1'); localStorage.removeItem('cp.snooker.t'); return true; } catch (e) { return false; } })();
        const save = () => { if (M) UI.store.set(KEY, { m: M, undo: undo.slice(-30) }); };
        const forget = () => { try { localStorage.removeItem(KEY); } catch (e) { } };
        function load() {
            const s = UI.store.get(KEY, null);
            if (!s || !s.m || s.m.v !== 1 || !s.m.f) return;
            M = s.m; undo = Array.isArray(s.undo) ? s.undo : [];
        }

        // every change goes through here: snapshot for undo, run, keep it only if the rules allowed it
        function act(fn, label) {
            const before = JSON.stringify(M);
            const r = fn();
            if (!r.ok) { if (r.msg) toast(r.msg, 'err'); return r; }
            undo.push({ label, state: before });
            if (undo.length > 100) undo.shift();
            freeMode = false;
            save(); render();
            if (r.msg) toast(r.msg, M.f.winner !== null || M.f.choose ? 'ok' : 'note');
            return r;
        }

        /* ---------- setup ---------- */
        function syncRuleChecks() {
            const p = PRESETS[setup.preset.value];
            if (p) Object.keys(p).forEach((k) => (setup[k].checked = p[k]));
        }
        function renderSetup() {
            $$('[data-act="snk-reds"]', setup).forEach((b) => { const on = +b.dataset.id === draftReds; b.classList.toggle('on', on); b.setAttribute('aria-pressed', String(on)); });
        }
        function startMatch() {
            const names = [setup.p1.value.trim() || 'Player 1', setup.p2.value.trim() || 'Player 2'];
            const hc = [setup.h1, setup.h2].map((el) => {
                const n = Number(el.value || 0);
                const bad = !Number.isInteger(n) || n < 0 || n > 200;
                el.setAttribute('aria-invalid', String(bad));
                return bad ? null : n;
            });
            if (hc.includes(null)) { toast('A handicap start is a whole number from 0 to 200.', 'err'); return; }
            const rules = {};
            Object.keys(PRESETS.wpbsa).forEach((k) => (rules[k] = setup[k].checked));
            M = newMatch({ names, reds: draftReds, bestOf: +setup.bestOf.value, firstBreak: +setup.breaker.value, rules, handicap: hc });
            undo = [];
            save(); show('play');
            toast(names[M.f.turn] + ' breaks.', 'note');
        }

        /* ---------- board ---------- */
        function show(v) {
            setup.hidden = v !== 'setup'; play.hidden = v !== 'play';
            if (v === 'setup') {
                const r = $('.snk-resume', root);
                r.hidden = !M;
                if (M) $('.snk-resume-meta', r).textContent = M.names[0] + ' ' + M.frames[0] + '–' + M.frames[1] + ' ' + M.names[1] + ', frame ' + M.frameNo + ' (best of ' + M.bestOf + ')';
                renderSetup();
            } else render();
            renderRulesInPlay();
        }

        function render() {
            if (!M) return;
            const f = M.f, over = f.winner !== null;
            $$('.snk-player', play).forEach((el, i) => {
                el.classList.toggle('on', !over && !f.choose && f.turn === i);
                $('.snk-name', el).textContent = M.names[i];
                $('.snk-score', el).textContent = f.scores[i];
                $('.snk-frames', el).textContent = M.frames[i] + (M.frames[i] === 1 ? ' frame' : ' frames');
                $('.snk-high', el).textContent = Math.max(M.high[i], f.turn === i ? f.brk : 0);
                $('.snk-at', el).hidden = over || f.choose || f.turn !== i;
            });
            $('.snk-match', play).textContent = 'Frame ' + M.frameNo + ' · best of ' + M.bestOf + ' · ' + M.reds + ' reds';
            const lead = Math.abs(f.scores[0] - f.scores[1]);
            $('.snk-st-break', play).textContent = f.brk;
            $('.snk-st-rem', play).textContent = remaining(f);
            $('.snk-st-lead', play).textContent = lead;
            $('.snk-st-leader', play).textContent = lead ? M.names[f.scores[0] > f.scores[1] ? 0 : 1] : 'Level';
            $('.snk-st-reds', play).textContent = f.reds;

            // status line: what's on, or what the table must do next
            const on = f.choose ? 'Re-spotted black: pick who plays first'
                : over ? 'Frame over'
                    : freeMode ? 'Free ball: tap the colour ' + M.names[f.turn] + ' nominates'
                        : M.names[f.turn] + ' is on ' + (f.on === 'red' ? 'a red' : f.on === 'colour' ? 'a colour' : 'the ' + ballOn(f)) + (f.respot ? ' (re-spotted)' : '');
            $('.snk-on', play).textContent = on;

            // banners: result, re-spotted black, snookers
            const res = $('.snk-result', play);
            res.hidden = !over;
            if (over) {
                const w = f.winner, matchOver = M.winner !== null;
                $('.snk-result-title', res).textContent = M.names[w] + (matchOver ? ' wins the match' : ' wins the frame');
                const why = f.how === 'concede' ? M.names[1 - w] + ' conceded. ' : f.how === 'misses' ? M.names[1 - w] + ' missed three times with a full ball view. ' : '';
                $('.snk-result-body', res).textContent = why + 'Frame ' + f.scores[w] + '–' + f.scores[1 - w] + '. Frames ' + M.frames[w] + '–' + M.frames[1 - w] + (matchOver ? '.' : ' (best of ' + M.bestOf + ').');
                $('[data-act="snk-next"]', res).hidden = matchOver;
                $('[data-act="snk-new-ask"]', res).classList.toggle('primary', matchOver);
            }
            $('.snk-choose', play).hidden = !f.choose;
            $$('.snk-choose [data-act="snk-respot"]', play).forEach((b, i) => (b.textContent = M.names[i] + ' plays first'));

            const notes = [];
            if (!over && !f.choose) [0, 1].forEach((i) => {
                const s = snookers(f, i);
                if (s.need) notes.push(M.names[i] + ' needs snookers: ' + s.need + ' (behind by ' + (f.scores[1 - i] - f.scores[i]) + ' with ' + remaining(f) + ' left).');
                else if (s.tie) notes.push(M.names[i] + ' can only tie by clearing the table, which means a re-spotted black.');
            });
            if (!over && !f.choose && M.rules.miss && f.misses.some((n) => n === 2)) notes.push(M.names[f.misses[0] === 2 ? 0 : 1] + ' has missed twice: a third miss with a full ball view loses the frame.');
            const nb = $('.snk-notes', play);
            nb.innerHTML = notes.map((t) => '<p class="note">' + esc(t) + '</p>').join('');
            nb.hidden = !notes.length;

            // balls: ones that can't be potted stay tappable so the board can say why
            $$('.snk-ball', play).forEach((b) => {
                const why = check(M, b.dataset.id, freeMode);
                b.setAttribute('aria-disabled', String(!!why));
                b.title = why || (freeMode ? 'Nominate the ' + b.dataset.id : 'Pot the ' + b.dataset.id + ' (' + VALUE[b.dataset.id] + ')');
                const left = b.dataset.id === 'red' ? f.reds : f.on === 'clear' && COLOURS.indexOf(b.dataset.id) < f.clear ? 0 : 1;
                b.classList.toggle('gone', !left);
            });
            const redBall = $('.snk-ball[data-id="red"] .snk-ball-n', play);
            redBall.textContent = f.on === 'colour' && f.extraRed && f.reds ? '+1' : f.reds;

            $('.snk-actions', play).hidden = over || f.choose;
            $('.snk-stats', play).hidden = over;
            const fb = $('[data-act="snk-free"]', play);
            fb.hidden = !f.freeBall;
            fb.setAttribute('aria-pressed', String(freeMode));
            fb.textContent = freeMode ? 'Cancel free ball' : 'Free ball';
            $$('[data-act="snk-undo"]', play).forEach((b) => (b.disabled = !undo.length));
            $('.snk-more', play).hidden = over;
        }

        function renderRulesInPlay() {
            const r = M ? M.rules : (() => { const o = {}; Object.keys(PRESETS.wpbsa).forEach((k) => (o[k] = setup[k].checked)); return o; })();
            const parts = [
                r.freeBall ? 'free ball on' : 'no free ball',
                r.putBack ? 'put-back on' : 'no put-back',
                r.miss ? 'miss rule on' : 'no miss rule',
                r.flat4 ? 'all fouls 4 points' : 'fouls 4 to 7',
            ];
            $('.snk-house', root).textContent = (M ? 'This match: ' : 'Selected: ') + parts.join(', ') + '.';
        }

        /* ---------- foul dialog ---------- */
        function openFoul() {
            const f = M.f, off = f.turn, opp = 1 - off;
            const form = $('form', foulDlg);
            form.pen.value = minFoul(f);
            form.pen.setAttribute('aria-invalid', 'false');
            $('.snk-pen-help', foulDlg).textContent = 'With ' + (f.on === 'red' ? 'a red' : f.on === 'colour' ? 'a colour' : 'the ' + ballOn(f)) + ' on, at least ' + minFoul(f) + '. Use the value of the highest ball involved if that is more.';
            $('.snk-pen', foulDlg).hidden = M.rules.flat4;
            $('.snk-flat', foulDlg).hidden = !M.rules.flat4;
            form.reds.value = 0; form.reds.max = f.reds;
            $('.snk-foul-reds', foulDlg).hidden = !f.reds;
            form.miss.checked = false;
            $('.snk-foul-miss', foulDlg).hidden = !M.rules.miss;
            $('#snk-foul-title').textContent = 'Foul by ' + M.names[off];
            const go = $('[data-act="snk-foul-go"]', foulDlg), back = $('[data-act="snk-foul-back"]', foulDlg);
            go.textContent = M.names[opp] + ' plays';
            back.textContent = 'Put ' + M.names[off] + ' back in';
            back.hidden = !M.rules.putBack;
            markPen();
            foulDlg.showModal();
        }
        function markPen() {
            const form = $('form', foulDlg);
            $$('[data-act="snk-pen"]', foulDlg).forEach((b) => { const on = b.dataset.id === String(form.pen.value); b.classList.toggle('on', on); b.setAttribute('aria-pressed', String(on)); });
        }
        function submitFoul(putBack) {
            const form = $('form', foulDlg);
            const r = act(() => foul(M, { pen: form.pen.value, reds: form.reds.value, miss: form.miss.checked, putBack }), 'foul');
            if (r.ok) foulDlg.close();
            else form.pen.setAttribute('aria-invalid', String(/foul is worth/.test(r.msg)));
        }

        /* ---------- events ---------- */
        root.addEventListener('click', (e) => {
            const el = e.target.closest('[data-act]');
            if (!el) return;
            const id = el.dataset.id;
            switch (el.dataset.act) {
                case 'snk-reds': draftReds = +id; renderSetup(); break;
                case 'snk-resume': show('play'); break;
                case 'snk-pot': {
                    if (el.getAttribute('aria-disabled') === 'true') { toast(check(M, id, freeMode), 'err'); break; }
                    act(() => pot(M, id, freeMode), (freeMode ? 'free ball ' : 'pot ') + id);
                    break;
                }
                case 'snk-miss': act(() => endVisit(M), 'end of visit'); break;
                case 'snk-foul': openFoul(); break;
                case 'snk-pen': $('form', foulDlg).pen.value = id; markPen(); break;
                case 'snk-foul-go': submitFoul(false); break;
                case 'snk-foul-back': submitFoul(true); break;
                case 'snk-free': freeMode = !freeMode; render(); break;
                case 'snk-respot': act(() => chooseRespot(M, +id), 'pick re-spotted black'); break;
                case 'snk-undo': {
                    const step = undo.pop();
                    if (!step) break;
                    M = JSON.parse(step.state); freeMode = false;
                    save(); render();
                    toast('Undone: ' + step.label + '.', 'ok');
                    break;
                }
                case 'snk-rerack': act(() => rerack(M), 're-rack'); break;
                case 'snk-concede-ask':
                    $$('[data-act="snk-concede"]', concedeDlg).forEach((b, i) => (b.textContent = M.names[i] + ' concedes'));
                    concedeDlg.showModal();
                    break;
                case 'snk-concede': concedeDlg.close(); act(() => concede(M, +id), 'concede'); break;
                case 'snk-next': act(() => nextFrame(M), 'next frame'); break;
                case 'snk-new-ask': newDlg.showModal(); break;
                case 'snk-new':
                    newDlg.close();
                    if (id === 'same' && M) {
                        M = newMatch({ names: M.names, reds: M.reds, bestOf: M.bestOf, firstBreak: M.firstBreak, rules: M.rules, handicap: M.handicap });
                        undo = []; save(); render(); toast('New match. ' + M.names[M.f.turn] + ' breaks.', 'note');
                    } else { M = null; undo = []; forget(); show('setup'); }
                    break;
                case 'dialog-close': el.closest('dialog').close(); break;
            }
        });

        root.addEventListener('submit', (e) => {
            e.preventDefault();
            if (e.target === setup) startMatch();
            else if (e.target.closest('#snk-foul')) submitFoul(false);
        });
        root.addEventListener('input', (e) => {
            const t = e.target;
            if (t.name === 'pen') { t.setAttribute('aria-invalid', 'false'); markPen(); }
        });
        root.addEventListener('change', (e) => {
            const t = e.target;
            if (t.name === 'preset') { syncRuleChecks(); renderRulesInPlay(); }
            else if (t.form === setup && t.type === 'checkbox') { setup.preset.value = 'custom'; renderRulesInPlay(); }
        });

        /* ---------- start ---------- */
        $('.snk-nostore', root).hidden = storageOk;
        load();
        show(M ? 'play' : 'setup');
    });

})();
