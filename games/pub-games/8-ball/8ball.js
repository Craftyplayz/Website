// 8-Ball Pool: UK pub frame tracker (reds and yellows). Pass-and-play on one phone by the table.
(function () {
    'use strict';

    /* engine:start (no DOM in here) */
    const COLOURS = ['red', 'yellow'];
    const NAME = { red: 'red', yellow: 'yellow' };
    // Rule sets fix the penalty, what potting an opponent's ball does and how colours are decided.
    const RULESETS = {
        pub: { label: 'English pub rules', penalty: 'pub', oppBall: 'foul', breakPick: 'first', blackOnBreak: 'rerack' },
        world: { label: 'WEPF World Rules', penalty: 'world', oppBall: 'foul', breakPick: 'open', blackOnBreak: 'rerack' },
        blackball: { label: 'WPA Blackball', penalty: 'blackball', oppBall: 'ok', breakPick: 'open', blackOnBreak: 'rerack' },
        intl: { label: 'International Rules', penalty: 'intl', oppBall: 'turn', breakPick: 'open', blackOnBreak: 'respot' },
    };
    const DEFAULT_RULES = { set: 'pub', blackOnBreak: 'rerack', twoOnBlack: true, callBlack: false, inOffBlack: false };
    const FOULS = {
        'in-off': 'in-off', wrong: 'wrong ball first', 'no-hit': 'no ball hit', cushion: 'no cushion after contact',
        off: 'ball off the table', push: 'push or double hit', touch: 'touched a ball', feet: 'both feet off the floor',
        early: 'shot before the balls stopped', jump: 'jump shot', baulk: 'played from outside baulk',
        break: 'illegal break', 'black-off': 'black off the table',
    };
    const BIH = { baulk: 'cue ball in hand behind the baulk line', anywhere: 'ball in hand anywhere on the table' };
    const plural = (n, one, many) => n + ' ' + (n === 1 ? one : many);

    const rs = (g) => RULESETS[g.rules.set];
    const freshFrame = (breaker) => ({ col: null, down: { red: [], yellow: [] }, turn: breaker, breaker, visits: 1, free: false, bih: 'baulk', onBreak: true, breakPots: 0, over: null });

    function newMatch(names, race, rules, breaker) {
        return { v: 1, names, race, rules: Object.assign({}, DEFAULT_RULES, rules), score: [0, 0], frameNo: 1, f: freshFrame(breaker), matchWinner: null, msg: { kind: 'note', text: names[breaker] + ' to break.' }, log: [], undo: [] };
    }

    const left = (g, c) => 7 - g.f.down[c].length;
    const colourOf = (g, p) => (g.f.col ? g.f.col[p] : null);
    const onBlack = (g, p) => !!g.f.col && left(g, g.f.col[p]) === 0;
    const nm = (g, p) => g.names[p];

    function snapshot(g, label) {
        const copy = Object.assign({}, g);
        delete copy.undo;
        g.undo.push({ label, state: JSON.stringify(copy) });
        if (g.undo.length > 40) g.undo.shift();
    }
    function say(g, kind, text) {
        g.msg = { kind, text };
        g.log.push({ frame: g.frameNo, text });
        if (g.log.length > 80) g.log.shift();
        return { kind, text };
    }
    const err = (text) => ({ kind: 'err', text });
    function guard(g) {
        if (g.matchWinner !== null) return err('The match is over. Start a new match.');
        if (g.f.over) return err('This frame is over. Tap Next frame to rack up again.');
        return null;
    }

    function assign(g, p, c) {
        const other = COLOURS[1 - COLOURS.indexOf(c)];
        g.f.col = p === 0 ? [c, other] : [other, c];
    }

    function winFrame(g, w, why) {
        g.f.over = { winner: w, text: why };
        g.score[w]++;
        let text = why + ' ' + nm(g, w) + ' wins frame ' + g.frameNo + '.';
        if (g.score[w] >= g.race) { g.matchWinner = w; text += ' ' + nm(g, w) + ' wins the match ' + g.score[w] + '–' + g.score[1 - w] + '.'; }
        g.f.over.text = text;
        return say(g, 'win', text);
    }
    const loseFrame = (g, p, why) => winFrame(g, 1 - p, why);

    // The incoming player's visits, free shot and ball in hand after a foul by p.
    function penalise(g, p, why, inOff) {
        const q = 1 - p, f = g.f, pen = rs(g).penalty;
        Object.assign(f, { turn: q, onBreak: false, visits: pen === 'intl' ? 1 : 2, free: pen === 'pub' || pen === 'blackball' });
        f.bih = pen === 'intl' ? 'anywhere' : pen === 'world' ? (inOff ? 'baulk' : '') : 'baulk';
        let extra = '';
        if (f.visits === 2 && !g.rules.twoOnBlack && onBlack(g, q)) { f.visits = 1; extra = ' Only one visit on the black (house rule).'; }
        return say(g, 'warn', 'Foul by ' + nm(g, p) + ' (' + why + '). ' + nm(g, q) + ' has ' + penaltyText(f) + '.' + extra);
    }
    function penaltyText(f) {
        const bits = [plural(f.visits, 'visit', 'visits')];
        if (f.free) bits.push('a free shot');
        if (f.bih) bits.push(BIH[f.bih]);
        return bits.join(', ');
    }

    function passTurn(g, text) {
        const f = g.f;
        Object.assign(f, { turn: 1 - f.turn, visits: 1, free: false, bih: '', onBreak: false });
        return say(g, 'note', text + ' ' + nm(g, f.turn) + ' to play.');
    }

    // Any legal pot uses up the free shot; under Blackball it also turns "one shot, one visit" into one visit.
    function usedShot(g) {
        const f = g.f;
        f.free = false; f.bih = '';
        if (f.visits === 2 && rs(g).penalty === 'blackball') f.visits = 1;
    }
    function blackNote(g, p) {
        if (!onBlack(g, p)) return '';
        let t = ' ' + nm(g, p) + ' is on the black.';
        if (p === g.f.turn && g.f.visits === 2 && !g.rules.twoOnBlack) { g.f.visits = 1; t += ' The second visit is lost on the black (house rule).'; }
        return t;
    }

    function potColour(g, c) {
        const no = guard(g);
        if (no) return no;
        if (left(g, c) === 0) return err('All 7 ' + NAME[c] + 's are already potted.');
        const f = g.f, p = f.turn;
        snapshot(g, 'pot');
        f.down[c].push(p);
        const n = left(g, c), cnt = ' ' + plural(n, NAME[c], NAME[c] + 's') + ' left.';
        if (f.onBreak) {
            f.breakPots++;
            if (rs(g).breakPick === 'first' && !f.col) {
                assign(g, p, c);
                return say(g, 'ok', nm(g, p) + ' takes ' + c + 's (first ball potted). ' + nm(g, 1 - p) + ' has ' + f.col[1 - p] + 's. If both colours went in, ' + nm(g, p) + ' may choose: undo and tap the other colour first.');
            }
            return say(g, 'note', 'A ' + c + ' went in on the break.' + (f.col ? '' : ' The table stays open.') + ' Tap Break done when the balls stop.');
        }
        if (!f.col) {
            assign(g, p, c);
            usedShot(g);
            return say(g, 'ok', nm(g, p) + ' takes ' + c + 's. ' + nm(g, 1 - p) + ' has ' + f.col[1 - p] + 's.' + cnt);
        }
        if (f.col[p] === c) {
            usedShot(g);
            return say(g, 'ok', 'A ' + c + ' for ' + nm(g, p) + '.' + cnt + blackNote(g, p));
        }
        // opponent's colour
        const o = 1 - p;
        if (f.free) {
            usedShot(g);
            return say(g, 'ok', 'A ' + c + ' on the free shot counts for ' + nm(g, o) + '. ' + nm(g, p) + ' carries on.' + cnt + blackNote(g, o));
        }
        const mode = rs(g).oppBall;
        if (mode === 'foul') return penalise(g, p, 'potted ' + nm(g, o) + "'s " + c);
        if (mode === 'turn') return passTurn(g, nm(g, p) + ' potted ' + nm(g, o) + "'s " + c + ': loss of turn.' + blackNote(g, o));
        return say(g, 'note', 'The ' + c + ' counts for ' + nm(g, o) + '. Blackball allows it only if ' + nm(g, p) + ' also potted a ' + f.col[p] + ' on that shot; if not, tap Miss.' + cnt + blackNote(g, o));
    }

    // Miss, or "Break done" while the break is on.
    function endVisit(g) {
        const no = guard(g);
        if (no) return no;
        const f = g.f, p = f.turn;
        snapshot(g, 'miss');
        if (f.onBreak) {
            if (f.breakPots > 0) {
                Object.assign(f, { onBreak: false, bih: '' });
                return say(g, 'ok', 'Ball potted on the break: ' + nm(g, p) + ' carries on.' + (f.col ? '' : ' The table is open.'));
            }
            if (f.visits > 1) {
                Object.assign(f, { visits: f.visits - 1, onBreak: false, bih: '' });
                return say(g, 'note', 'Nothing potted on the break. ' + nm(g, p) + ' takes the second visit.');
            }
            return passTurn(g, 'Nothing potted on the break. The table is open.');
        }
        if (f.visits > 1) {
            Object.assign(f, { visits: f.visits - 1, free: false, bih: '' });
            return say(g, 'note', nm(g, p) + ' missed and takes the second visit, from where the cue ball lies.');
        }
        return passTurn(g, nm(g, p) + ' missed.');
    }

    function foul(g, reason) {
        const no = guard(g);
        if (no) return no;
        if (!FOULS[reason]) return err('Pick a foul from the list.');
        const f = g.f, p = f.turn, q = 1 - p;
        if (reason === 'break' && !f.onBreak) return err('An illegal break can only be called on the break.');
        snapshot(g, 'foul');
        if (reason === 'black-off') return loseFrame(g, p, nm(g, p) + ' knocked the black off the table and loses the frame.');
        if (reason === 'in-off' && g.rules.inOffBlack && onBlack(g, p)) return loseFrame(g, p, nm(g, p) + ' went in-off while on the black (house rule) and loses the frame.');
        if (reason === 'break') {
            g.f = freshFrame(q);
            if (rs(g).penalty === 'intl') return say(g, 'warn', 'Illegal break: re-rack. ' + nm(g, q) + ' chooses who breaks (Re-rack with the other player swaps back).');
            g.f.visits = 2;
            return say(g, 'warn', 'Illegal break: re-rack. ' + nm(g, q) + ' breaks and has 2 visits.');
        }
        if (reason === 'in-off' && f.onBreak && (rs(g).penalty === 'world' || rs(g).penalty === 'intl')) {
            Object.assign(f, { turn: q, visits: 1, free: false, bih: 'baulk', onBreak: false });
            return say(g, 'warn', 'In-off on the break: loss of turn. ' + nm(g, q) + ' has 1 visit, ' + BIH.baulk + '.');
        }
        return penalise(g, p, FOULS[reason], reason === 'in-off' || reason === 'off');
    }

    // how: 'clean' | 'foul' (in-off or another foul on the same shot) | 'wrong' (not the called pocket)
    function potBlack(g, how) {
        const no = guard(g);
        if (no) return no;
        const f = g.f, p = f.turn, q = 1 - p;
        snapshot(g, 'black');
        if (f.onBreak) {
            const rule = g.rules.blackOnBreak;
            if (rule === 'win') return how === 'foul' ? loseFrame(g, p, 'Black and a foul on the break: ' + nm(g, p) + ' loses the frame.') : winFrame(g, p, 'Black potted on the break.');
            if (rule === 'lose') return loseFrame(g, p, 'Black potted on the break (house rule): ' + nm(g, p) + ' loses the frame.');
            if (rule === 'respot') return say(g, 'note', 'Black on the break: re-spot it. Tap any other balls that went in, then Break done.');
            g.f = freshFrame(p);
            return say(g, 'note', 'Black potted on the break: re-rack and ' + nm(g, p) + ' breaks again. No penalty.');
        }
        if (how === 'foul') return loseFrame(g, p, 'Black potted with a foul: ' + nm(g, p) + ' loses the frame.');
        if (!onBlack(g, p)) {
            const why = f.col ? plural(left(g, f.col[p]), f.col[p], f.col[p] + 's') + ' still on' : 'the table still open';
            return loseFrame(g, p, 'Black potted with ' + why + ': ' + nm(g, p) + ' loses the frame.');
        }
        if (how === 'wrong') return loseFrame(g, p, 'Black in the wrong pocket: ' + nm(g, p) + ' loses the frame.');
        return winFrame(g, p, nm(g, p) + ' potted the black.');
    }

    function nextFrame(g) {
        if (g.matchWinner !== null) return err('The match is over. Start a new match.');
        if (!g.f.over) return err('Finish this frame first, or use Re-rack.');
        snapshot(g, 'frame');
        g.frameNo++;
        g.f = freshFrame(1 - g.f.breaker);
        return say(g, 'note', 'Frame ' + g.frameNo + ': ' + nm(g, g.f.breaker) + ' to break.');
    }

    // stalemate or a re-rack: same frame number, the original breaker breaks again (or the other player with swap)
    function reRack(g, swap) {
        const no = guard(g);
        if (no) return no;
        snapshot(g, 'rerack');
        g.f = freshFrame(swap ? 1 - g.f.breaker : g.f.breaker);
        return say(g, 'note', 'Re-rack: ' + nm(g, g.f.breaker) + ' breaks.');
    }

    function undo(g) {
        const step = g.undo.pop();
        if (!step) return null;
        const back = JSON.parse(step.state);
        back.undo = g.undo;
        back.msg = { kind: 'note', text: 'Undone. ' + (back.msg ? back.msg.text : '') };
        return back;
    }

    function status(g) {
        const f = g.f, p = f.turn;
        if (g.matchWinner !== null) return nm(g, g.matchWinner) + ' won the match';
        if (f.over) return nm(g, f.over.winner) + ' won the frame';
        if (f.onBreak) return nm(g, p) + ' to break' + (f.visits > 1 ? ' · ' + penaltyText(f) : '');
        let t = nm(g, p) + ' to play';
        const bits = [];
        if (f.visits > 1 || f.free || f.bih) bits.push(penaltyText(f));
        bits.push(onBlack(g, p) ? 'on the black' : f.col ? 'on ' + f.col[p] + 's' : 'open table');
        return t + ' · ' + bits.join(' · ');
    }
    /* engine:end */

    if (typeof window === 'undefined') { module.exports = { newMatch, potColour, endVisit, foul, potBlack, nextFrame, reRack, undo, status, left, onBlack }; return; }

    document.addEventListener('DOMContentLoaded', () => {
        const { $, $$, esc, toast } = UI;
        const KEY = 'cp.8ball.v1';
        const root = $('.eb');
        const setupForm = $('.eb-setup', root);
        const board = $('.eb-board', root);

        function load() {
            const g = UI.store.get(KEY, null);
            if (!g || g.v !== 1 || !Array.isArray(g.names) || !g.f || !RULESETS[g.rules && g.rules.set]) return null;
            g.undo = Array.isArray(g.undo) ? g.undo : [];
            g.log = Array.isArray(g.log) ? g.log : [];
            return g;
        }
        let G = load();
        const save = () => UI.store.set(KEY, G);

        /* ---------- setup ---------- */
        // src: the last match, so a new match starts with the same names and rules
        function fillSetup(src) {
            const r = src ? src.rules : DEFAULT_RULES;
            if (src) { setupForm.p1.value = src.names[0]; setupForm.p2.value = src.names[1]; setupForm.race.value = String(src.race); }
            setupForm.set.value = r.set;
            setupForm.blackOnBreak.value = r.blackOnBreak;
            setupForm.twoOnBlack.checked = r.twoOnBlack;
            setupForm.callBlack.checked = r.callBlack;
            setupForm.inOffBlack.checked = r.inOffBlack;
            setHelp();
        }
        const HELP = {
            pub: 'Two visits after a foul, free shot first, cue ball in hand behind the baulk line. Potting an opponent\'s ball is a foul.',
            world: 'Two visits after a foul, no free shot, cue ball stays where it is (in baulk after an in-off). Potting an opponent\'s ball is a foul.',
            blackball: 'Free shot then one visit after a foul, cue ball in hand behind the baulk line. You may pot an opponent\'s ball along with one of your own.',
            intl: 'One visit with ball in hand anywhere after a foul. Potting an opponent\'s ball is a loss of turn.',
        };
        function setHelp() { $('.eb-set-help', setupForm).textContent = HELP[setupForm.set.value]; }
        setupForm.set.addEventListener('change', () => { setupForm.blackOnBreak.value = RULESETS[setupForm.set.value].blackOnBreak; setHelp(); });

        setupForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const names = [setupForm.p1, setupForm.p2].map((f, i) => f.value.trim().replace(/\s+/g, ' ').slice(0, 20) || 'Player ' + (i + 1));
            if (names[0].toLowerCase() === names[1].toLowerCase()) names[1] += ' 2';
            const race = Math.min(21, Math.max(1, parseInt(setupForm.race.value, 10) || 1));
            const rules = {
                set: RULESETS[setupForm.set.value] ? setupForm.set.value : 'pub',
                blackOnBreak: ['rerack', 'respot', 'win', 'lose'].includes(setupForm.blackOnBreak.value) ? setupForm.blackOnBreak.value : 'rerack',
                twoOnBlack: setupForm.twoOnBlack.checked, callBlack: setupForm.callBlack.checked, inOffBlack: setupForm.inOffBlack.checked,
            };
            G = newMatch(names, race, rules, setupForm.breaker.value === '1' ? 1 : 0);
            save();
            show();
            $('.eb-msg', root).focus();
        });

        /* ---------- board ---------- */
        const ballHtml = (c, extra) => '<span class="eb-ball ' + c + (extra ? ' ' + extra : '') + '" aria-hidden="true"></span>';

        function render() {
            const f = G.f;
            const msg = $('.eb-msg', root);
            msg.className = 'eb-msg ' + ({ ok: 'ok', warn: 'eb-warn', win: 'eb-win', err: 'err' }[G.msg.kind] || 'note');
            msg.textContent = G.msg.text;
            $('.eb-status', root).textContent = status(G);

            // scores
            $$('.eb-player', root).forEach((el, i) => {
                const c = f.col ? f.col[i] : null;
                el.classList.toggle('is-turn', !f.over && G.matchWinner === null && f.turn === i);
                $('.eb-name', el).textContent = G.names[i];
                $('.eb-name', el).title = G.names[i];
                $('.eb-frames', el).textContent = G.score[i];
                let tag = '<span class="badge">Open table</span>';
                if (c) tag = onBlack(G, i) ? '<span class="badge eb-tag">' + ballHtml('black', 'mini') + 'On the black</span>' : '<span class="badge eb-tag">' + ballHtml(c, 'mini') + plural(left(G, c), c, c + 's') + ' left</span>';
                if (f.breaker === i && !f.over) tag += '<span class="badge">Breaker</span>';
                $('.eb-tag-row', el).innerHTML = tag;
            });
            $('.eb-race', root).textContent = 'Frame ' + G.frameNo + ' · race to ' + G.race;

            // the table: tap any ball still on to pot one of that colour
            COLOURS.forEach((c) => {
                const n = left(G, c);
                $('.eb-row-' + c + ' .eb-n', root).textContent = n + ' left';
                $('.eb-row-' + c + ' .eb-balls', root).innerHTML = Array.from({ length: 7 }, (_, i) => {
                    const down = i >= n;
                    return '<button class="eb-slot" type="button" data-act="pot" data-id="' + c + '" aria-disabled="' + down + '" aria-label="' + (down ? 'Potted ' + c : 'Pot a ' + c + ', ' + n + ' left') + '">' + ballHtml(c, down ? 'potted' : '') + '</button>';
                }).join('');
            });

            const over = !!f.over;
            $('.eb-over', root).hidden = !over;
            msg.hidden = over; // the result card says it
            $('.eb-actions', root).hidden = over;
            if (over) {
                const done = G.matchWinner !== null;
                $('.eb-over-title', root).textContent = done ? G.names[G.matchWinner] + ' wins the match ' + G.score[G.matchWinner] + '–' + G.score[1 - G.matchWinner] : G.names[f.over.winner] + ' wins the frame';
                $('.eb-over-reason', root).textContent = f.over.text;
                $('[data-act="next"]', root).hidden = done;
                $('[data-act="rematch"]', root).hidden = !done;
            }
            $('[data-act="miss"]', root).textContent = f.onBreak ? 'Break done' : 'Miss / end visit';
            $$('[data-act="undo"]', root).forEach((b) => (b.disabled = !G.undo.length));

            $('.eb-log', root).innerHTML = G.log.slice().reverse().map((l) => '<li class="rowi"><small>F' + l.frame + '</small><span>' + esc(l.text) + '</span></li>').join('') || '<li class="muted">Nothing yet.</li>';
            renderTableRules();
        }

        // the "Your table" rules list follows the chosen rule set and house rules
        function renderTableRules() {
            const r = G ? G.rules : null;
            const box = $('.eb-your-rules', root);
            box.hidden = !r;
            if (!r) return;
            const BOB = { rerack: 'Black on the break: re-rack, same player breaks again.', respot: 'Black on the break: re-spot it and carry on.', win: 'Black on the break wins the frame.', lose: 'Black on the break loses the frame.' };
            const items = [RULESETS[r.set].label + ': ' + HELP[r.set], BOB[r.blackOnBreak],
                r.twoOnBlack ? 'Two visits carry on to the black.' : 'Only one visit on the black: reaching the black ends a second visit.',
                r.callBlack ? 'Call the black: name the pocket; the black in another pocket loses.' : 'The black needn\'t be called.'];
            if (r.inOffBlack) items.push('An in-off while on the black loses the frame.');
            $('ul', box).innerHTML = items.map((t) => '<li>' + esc(t) + '</li>').join('');
        }

        function show(src) {
            setupForm.hidden = !!G;
            board.hidden = !G;
            if (G) render(); else { fillSetup(src); renderTableRules(); }
        }

        function act(r) {
            if (!r) return;
            if (r.kind === 'err') { toast(r.text, 'err'); const m = $('.eb-msg', root); m.className = 'eb-msg err'; m.textContent = r.text; return; }
            save();
            render();
            if (r.kind === 'win') $('.eb-over .btn.primary:not([hidden])', root)?.focus();
        }

        /* ---------- dialogs ---------- */
        const foulDlg = $('#eb-foul'), blackDlg = $('#eb-black'), newDlg = $('#eb-new');
        function openFoul() {
            if (guard(G)) return act(guard(G));
            $('[data-id="break"]', foulDlg).hidden = !G.f.onBreak;
            foulDlg.showModal();
        }
        function openBlack() {
            if (guard(G)) return act(guard(G));
            const f = G.f, p = f.turn;
            let lead = '';
            if (f.onBreak) lead = 'On the break. Your table plays: ' + { rerack: 're-rack', respot: 're-spot', win: 'black on the break wins', lose: 'black on the break loses' }[G.rules.blackOnBreak] + '.';
            else if (!onBlack(G, p)) lead = G.names[p] + ' still has ' + (f.col ? plural(left(G, f.col[p]), f.col[p], f.col[p] + 's') + ' on' : 'an open table') + ', so potting the black now loses the frame.';
            else lead = G.names[p] + ' is on the black.';
            $('.eb-black-lead', blackDlg).textContent = lead;
            $('[data-id="wrong"]', blackDlg).hidden = !G.rules.callBlack || f.onBreak;
            blackDlg.showModal();
        }

        root.addEventListener('click', (e) => {
            const el = e.target.closest('[data-act]');
            if (!el) return;
            const id = el.dataset.id;
            switch (el.dataset.act) {
                case 'pot':
                    if (el.getAttribute('aria-disabled') === 'true') { act(err('That ' + id + ' is already potted. ' + plural(left(G, id), id, id + 's') + ' left.')); break; }
                    act(potColour(G, id)); break;
                case 'miss': act(endVisit(G)); break;
                case 'foul': openFoul(); break;
                case 'foul-pick': foulDlg.close(); act(foul(G, id)); break;
                case 'black': openBlack(); break;
                case 'black-pick': blackDlg.close(); act(potBlack(G, id)); break;
                case 'undo': { const b = undo(G); if (b) { G = b; save(); render(); } break; }
                case 'next': act(nextFrame(G)); break;
                case 'rerack': act(reRack(G, id === 'swap')); break;
                case 'rematch': G = newMatch(G.names, G.race, G.rules, 1 - G.f.breaker); save(); render(); break;
                case 'new-ask': newDlg.showModal(); break;
                case 'new': { newDlg.close(); const keep = G; G = null; try { localStorage.removeItem(KEY); } catch (x) { } show(keep); setupForm.p1.focus(); break; }
                case 'dialog-close': el.closest('dialog').close(); break;
            }
        });

        show();
    });
})();
