// Kelly Pool: pass-and-play tracker. One phone is passed round; secret numbers only show after a
// "Pass to [name]" screen and are never put back on screen by a reload, the back button or a tab switch.
(function () {
    'use strict';

    /* engine:start (no DOM in here) */
    const RACK = 15;
    const MODES = { lms: 'Last man standing' };
    const DEFAULT_SETTINGS = { pin: false, hideAfter: 15, vibrate: true, turns: false };

    const maxBalls = (players) => Math.min(5, Math.floor(RACK / players));
    const plural = (n, one, many) => n + ' ' + (n === 1 ? one : many);

    // unbiased random integer below n, from crypto so the deal is fair
    function randInt(n) {
        const a = new Uint32Array(1);
        const lim = Math.floor(0x100000000 / n) * n;
        do crypto.getRandomValues(a); while (a[0] >= lim);
        return a[0] % n;
    }
    function shuffle(list) {
        const a = list.slice();
        for (let i = a.length - 1; i > 0; i--) { const j = randInt(i + 1); [a[i], a[j]] = [a[j], a[i]]; }
        return a;
    }

    function newGame(mode, names, balls, settings) {
        const g = {
            v: 1, mode, phase: 'deal', settings: Object.assign({}, DEFAULT_SETTINGS, settings),
            setup: { players: names.length, balls },
            players: names.map((name, i) => ({ id: 'p' + (i + 1), name, balls: [], lost: [], out: false, pin: null })),
            deal: { index: 0, stage: 'pass' }, potted: {}, winner: null, endReason: null, history: [], undo: [], startedAt: Date.now(),
        };
        return deal(g);
    }

    function deal(g) {
        const rack = shuffle(Array.from({ length: RACK }, (_, i) => i + 1));
        const n = g.setup.balls;
        g.players.forEach((p, i) => {
            p.balls = rack.slice(i * n, (i + 1) * n).sort((a, b) => a - b);
            p.lost = []; p.out = false; p.pin = null;
        });
        Object.assign(g, { phase: 'deal', deal: { index: 0, stage: 'pass' }, turn: g.players[0].id, potted: {}, winner: null, endReason: null, history: [], undo: [], startedAt: Date.now() });
        return g;
    }

    const ownerOf = (g, ball) => g.players.find((p) => p.balls.includes(ball)) || null;
    const byId = (g, id) => g.players.find((p) => p.id === id) || null;
    const ballsLeft = (p) => p.balls.length - p.lost.length;
    const alive = (g) => g.players.filter((p) => !p.out);
    const pottedCount = (g) => Object.keys(g.potted).length;

    function snapshot(g, ball) {
        const copy = Object.assign({}, g);
        delete copy.undo;
        g.undo.push({ ball, state: JSON.stringify(copy) });
        if (g.undo.length > 50) g.undo.shift();
    }

    // Pot a ball: whoever potted it, its owner loses a life.
    // Returns the outcome the dialog shows. Order: snapshot for undo, then update the state.
    function pot(g, ball) {
        if (g.phase !== 'play' || g.potted[ball]) return null;
        snapshot(g, ball);
        const owner = ownerOf(g, ball);
        let o = owner ? loseBall(g, owner, ball) : { kind: 'none', ball };
        g.potted[ball] = { owner: owner ? owner.id : null, at: Date.now() };
        const text = outcomeText(g, o).body;
        g.history.push({ ball, owner: owner ? owner.id : null, text, at: Date.now() });
        if (!g.winner && pottedCount(g) === RACK) {
            g.phase = 'over'; g.endReason = 'no-winner';
            o = Object.assign({}, o, { kind: 'no-winner', before: o.kind });
        }
        fixTurn(g);
        return o;
    }

    // Whose turn it is (the "Show whose turn it is" setting). Only the Next player button moves it on,
    // apart from skipping anyone who is out.
    // step 1 for the Next player button, -1 for Previous player
    function nextTurn(g, step = 1) {
        const n = g.players.length;
        const i = g.players.findIndex((p) => p.id === g.turn);
        for (let k = 1; k <= n; k++) {
            const p = g.players[(((i + k * step) % n) + n) % n];
            if (!p.out) { g.turn = p.id; return p; }
        }
        return null;
    }
    function fixTurn(g) {
        const p = byId(g, g.turn);
        if (!p || p.out) nextTurn(g);
    }

    function loseBall(g, owner, ball) {
        owner.lost.push(ball);
        if (ballsLeft(owner) > 0) return { kind: 'lose', ball, owner: owner.id };
        owner.out = true;
        const left = alive(g);
        if (left.length === 1) {
            win(g, left[0], 'last-standing');
            return { kind: 'win-last', ball, owner: owner.id, winner: left[0].id };
        }
        return { kind: 'out', ball, owner: owner.id };
    }

    function win(g, p, reason) {
        g.winner = p.id; g.endReason = reason; g.phase = 'over';
    }

    function undo(g) {
        const step = g.undo.pop();
        if (!step) return null;
        const back = JSON.parse(step.state);
        back.undo = g.undo;
        // undo puts the ball back; whose turn it is stays where the Next player button left it
        back.turn = g.turn;
        fixTurn(back);
        return { game: back, ball: step.ball };
    }

    // Heading, body and button for each outcome (plan section 8). Names are plain text here.
    function outcomeText(g, o) {
        const owner = o.owner ? byId(g, o.owner) : null;
        const name = owner ? owner.name : '';
        const left = owner ? ballsLeft(owner) : 0;
        switch (o.kind) {
            case 'none': return { title: "No one's ball", body: 'Ball ' + o.ball + " isn't anyone's number.", button: 'Carry on' };
            case 'lose': return { title: name + ' loses a ball', body: 'Ball ' + o.ball + ' was ' + name + "'s. " + name + ' has ' + plural(left, 'ball', 'balls') + ' left.', button: 'Carry on' };
            case 'out': return { title: name + ' is out', body: 'Ball ' + o.ball + ' was ' + name + "'s last ball.", button: 'Carry on' };
            case 'win-last': {
                const w = byId(g, o.winner).name;
                return { title: w + ' wins', body: 'Ball ' + o.ball + ' knocked out ' + name + '. ' + w + ' is the last player standing.', button: 'See results' };
            }
            case 'no-winner': return { title: 'No winner', body: 'Every ball has been potted.', button: 'See results' };
        }
        return { title: '', body: '', button: 'Carry on' };
    }
    /* engine:end */

    document.addEventListener('DOMContentLoaded', () => {
        const { $, $$, esc, toast, ago } = UI;
        const KEY = 'cp.kellypool.v1', NAMES_KEY = 'cp.kellypool.names', SETTINGS_KEY = 'cp.kellypool.settings';
        const root = $('.kp');
        const hero = $('.kp-hero', root);
        const priv = $('.kp-private', root);
        const outcomeDlg = $('#kp-outcome');

        /* ---------- store: every call survives blocked storage ---------- */
        const storageOk = (() => { try { localStorage.setItem('cp.kellypool.t', '1'); localStorage.removeItem('cp.kellypool.t'); return true; } catch (e) { return false; } })();
        const save = () => { if (G) UI.store.set(KEY, G); };
        const forget = () => { try { localStorage.removeItem(KEY); } catch (e) { } };
        const loadNames = () => { const n = UI.store.get(NAMES_KEY, []); return Array.isArray(n) ? n.filter((s) => typeof s === 'string').slice(0, 12) : []; };
        const saveNames = (list) => UI.store.set(NAMES_KEY, list.slice(0, 12));
        const loadSettings = () => Object.assign({}, DEFAULT_SETTINGS, UI.store.get(SETTINGS_KEY, {}));
        function load() {
            const g = UI.store.get(KEY, null);
            if (!g || g.v !== 1 || !Array.isArray(g.players) || !MODES[g.mode]) return null;
            if (g.phase !== 'deal' && g.phase !== 'play' && g.phase !== 'over') return null;
            g.deal = { index: g.deal && g.deal.index < g.players.length ? g.deal.index : 0, stage: 'pass' };
            g.undo = Array.isArray(g.undo) ? g.undo : [];
            g.settings = Object.assign({}, DEFAULT_SETTINGS, g.settings);
            fixTurn(g);
            return g;
        }

        let G = load();               // the saved game (deal, play or over)
        let settings = loadSettings();
        let draft = null;             // setup and names, kept in memory until the deal
        let view = 'home';
        let check = null;             // { id, stage } while a player checks their numbers from the board
        let pendingOutcome = null;
        let hideTimer = 0;

        /* ---------- views ---------- */
        function show(next, how) {
            if (view === 'private' && next !== 'private') clearPrivate();
            view = next;
            root.dataset.view = next;
            $$('[data-view]', root).forEach((el) => { if (el !== root) el.hidden = el.dataset.view !== next; });
            hero.hidden = next === 'private';
            hero.classList.toggle('kp-compact', next !== 'home');
            $('.kp-lede', hero).hidden = next !== 'home';
            const line = $('.kp-mode-line', hero);
            line.hidden = next === 'home';
            $('.kp-tools', hero).hidden = !(next === 'play' || next === 'over');
            if (next === 'setup' || next === 'names') line.textContent = MODES[draft.mode];
            if (next === 'play' || next === 'over') line.textContent = MODES[G.mode];
            // the private screen covers the page; keep the keyboard and screen readers off what's under it
            const cover = next === 'private';
            [$('#header'), $('.site-footer'), $('.crumbs', root), hero].forEach((el) => el && (el.inert = cover));
            if (how === 'push') history.pushState({ kp: next }, '');
            else if (how === 'replace') history.replaceState({ kp: next }, '');
            if (next === 'home') renderHome();
            if (next === 'setup') renderSetup();
            if (next === 'names') renderNames();
            if (next === 'play') renderBoard();
            if (next === 'over') renderOver();
            if (next === 'private') renderPrivate();
        }

        function focusFirst(sel) {
            const el = $(sel, root);
            if (el) el.focus({ preventScroll: false });
        }

        /* ---------- home ---------- */
        function renderHome() {
            $('.kp-nostore', root).hidden = storageOk;
            const live = G && (G.phase === 'deal' || G.phase === 'play');
            $('.kp-resume', root).hidden = !live;
            if (live) {
                const onTable = RACK - pottedCount(G);
                $('.kp-resume-meta', root).textContent = MODES[G.mode] + ' · ' + plural(G.players.length, 'player', 'players') + ' · ' +
                    (G.phase === 'deal' ? 'dealing to ' + G.players[G.deal.index].name : plural(onTable, 'ball', 'balls') + ' on the table');
            }
            // one primary per view: with a game to resume, the mode buttons step down
            $('[data-act="kp-mode"][data-id="lms"]', root).classList.toggle('primary', !live);
            renderSettings();
        }

        /* ---------- players and balls ---------- */
        function renderSetup(note) {
            const max = maxBalls(draft.players);
            $$('[data-act="kp-pick-players"]', root).forEach((b) => {
                const on = +b.dataset.id === draft.players;
                b.classList.toggle('on', on); b.setAttribute('aria-pressed', String(on));
            });
            $$('[data-act="kp-pick-balls"]', root).forEach((b) => {
                const n = +b.dataset.id, on = n === draft.balls;
                b.classList.toggle('on', on); b.setAttribute('aria-pressed', String(on));
                b.disabled = n > max;
            });
            $('.kp-limit', root).textContent = note || (max < 5 ? draft.players + ' players can have up to ' + plural(max, 'ball', 'balls') + ' each (15 balls in the rack).' : '');
            const dealt = draft.players * draft.balls, spare = RACK - dealt;
            $('.kp-summary', root).textContent = draft.players + ' players × ' + plural(draft.balls, 'ball', 'balls') + ' = ' + dealt + ' of 15 balls dealt. ' +
                (spare === 0 ? 'Every ball belongs to someone.' : spare + (spare === 1 ? ' belongs' : ' belong') + ' to no one.');
        }

        function pickPlayers(n) {
            draft.players = n;
            const max = maxBalls(n);
            let note = '';
            if (draft.balls > max) { draft.balls = max; note = 'Balls each changed to ' + max + ' so everyone gets a full set.'; }
            draft.names.length = Math.min(draft.names.length, 15);
            renderSetup(note);
        }

        /* ---------- names ---------- */
        function renderNames() {
            const box = $('.kp-fields', root);
            let html = '';
            for (let i = 0; i < draft.players; i++) {
                html += '<label>Player ' + (i + 1) + ' <input class="field" name="p' + (i + 1) + '" value="' + esc(draft.names[i] || '') +
                    '" autocomplete="off" maxlength="16" enterkeyhint="' + (i === draft.players - 1 ? 'done' : 'next') + '" spellcheck="false"><small class="kp-err" hidden></small></label>';
            }
            box.innerHTML = html;
            renderChips();
        }
        const fields = () => $$('.kp-fields input', root);
        function renderChips() {
            const used = new Set(fields().map((f) => f.value.trim().toLowerCase()).filter(Boolean));
            const list = loadNames().filter((n) => !used.has(n.toLowerCase()));
            $('.kp-recent', root).hidden = !list.length;
            $('.kp-chips', root).innerHTML = list.map((n) => '<button class="btn small" type="button" data-act="kp-chip" data-id="' + esc(n) + '">' + esc(n) + '</button>').join('');
        }
        function setError(input, msg) {
            const small = input.parentElement.querySelector('.kp-err');
            if (msg) { input.setAttribute('aria-invalid', 'true'); small.textContent = msg; small.hidden = false; }
            else { input.removeAttribute('aria-invalid'); small.textContent = ''; small.hidden = true; }
        }
        function submitNames() {
            const inputs = fields();
            const seen = new Map();
            let bad = null;
            inputs.forEach((f) => {
                const v = f.value.trim().replace(/\s+/g, ' ');
                f.value = v;
                let msg = '';
                if (!v) msg = 'Enter a name.';
                else if (seen.has(v.toLowerCase())) msg = 'Each player needs a different name.';
                else seen.set(v.toLowerCase(), true);
                setError(f, msg);
                if (msg && !bad) bad = f;
            });
            if (bad) { bad.focus(); return; }
            const names = inputs.map((f) => f.value);
            const recent = names.concat(loadNames().filter((n) => !names.some((m) => m.toLowerCase() === n.toLowerCase())));
            saveNames(recent);
            draft.names = names;
            G = newGame(draft.mode, names, draft.balls, settings);
            save();
            show('private', 'push');
        }

        /* ---------- pass and reveal ---------- */
        // Which player and step the private screen is on. Deal: G.deal. Checking from the board: check.
        const privPlayer = () => (check ? byId(G, check.id) : G.players[G.deal.index]);
        const privStage = () => (check ? check.stage : G.deal.stage);
        function setStage(stage) {
            if (check) check.stage = stage; else G.deal.stage = stage;
            renderPrivate();
        }

        function renderPrivate() {
            clearTimeout(hideTimer);
            const p = privPlayer();
            const stage = privStage();
            const name = esc(p.name);
            const top = $('.kp-private-top', priv), mid = $('.kp-private-mid', priv), act = $('.kp-private-actions', priv);
            top.innerHTML = check ? '<span class="badge">Check numbers</span>' : '<span class="badge">Dealing · ' + (G.deal.index + 1) + ' of ' + G.players.length + '</span>';
            const cancel = check ? '<button class="btn" type="button" data-act="kp-hide">Cancel</button>' : '';
            if (stage === 'pass') {
                mid.innerHTML = '<p class="kp-pass-name">Pass to <b>' + name + '</b></p><p class="kp-pass-sub">Everyone else, look away.</p>';
                act.innerHTML = '<button class="btn primary" type="button" data-act="kp-iam">I am ' + name + '</button>' + cancel;
            } else if (stage === 'pin') {
                mid.innerHTML = '<h2>' + name + '</h2><form class="form kp-pin-form" novalidate><label>Enter your PIN<input class="field" name="pin" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="4" autocomplete="off"><small class="kp-err" hidden></small></label></form>';
                act.innerHTML = '<button class="btn primary" type="button" data-act="kp-pin">Show my numbers</button>' + cancel;
            } else {
                const balls = p.balls;
                const spoken = balls.length === 1 ? String(balls[0]) : balls.slice(0, -1).join(', ') + ' and ' + balls[balls.length - 1];
                const needPin = G.settings.pin && !p.pin;
                const label = check ? 'Hide my numbers' : G.deal.index === G.players.length - 1 ? 'Hide and start the game' : 'Hide and pass on';
                mid.innerHTML = '<h2>' + name + '</h2><p>Your numbers</p>' +
                    '<div class="kp-reveal-balls" role="img" aria-label="Your numbers: ' + spoken + '">' + balls.map((n) => ballHtml(n, G.potted[n] ? 'potted' : '')).join('') + '</div>' +
                    '<p class="muted">' + 'You lose a ball each time one of these goes down. Keep them secret.' + '</p>' +
                    (needPin ? '<form class="form kp-pin-form" novalidate><label>Set a 4-digit PIN to check your numbers later<input class="field" name="pin" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="4" autocomplete="off"><small class="kp-err" hidden></small></label></form>' : '') +
                    '<p class="muted">Your numbers hide after ' + G.settings.hideAfter + ' seconds.</p>';
                act.innerHTML = '<button class="btn primary" type="button" data-act="kp-hide">' + label + '</button>';
                hideTimer = setTimeout(autoHide, G.settings.hideAfter * 1000);
            }
            const first = $('input', mid) || $('.btn.primary', act);
            if (first) first.focus();
        }

        // wipe the numbers out of the page, not just out of view
        function clearPrivate() {
            clearTimeout(hideTimer);
            $$('.kp-private-top, .kp-private-mid, .kp-private-actions', priv).forEach((el) => (el.innerHTML = ''));
        }

        // timer ran out, tab hidden or page left: back to a screen with nothing private on it
        function autoHide() {
            if (view !== 'private' || privStage() === 'pass') return;
            if (check) { check = null; show('play'); return; }
            G.deal.stage = 'pass';
            save();
            renderPrivate();
        }

        function pinValue() {
            const f = $('.kp-pin-form input', priv);
            if (!f) return { ok: true };
            const v = f.value.trim();
            if (!/^\d{4}$/.test(v)) { setError(f, 'Enter 4 numbers.'); f.focus(); return { ok: false }; }
            return { ok: true, value: v, field: f };
        }

        function iAm() {
            const p = privPlayer();
            setStage(G.settings.pin && p.pin ? 'pin' : 'reveal');
        }

        function enterPin() {
            const r = pinValue();
            if (!r.ok) return;
            if (r.value !== privPlayer().pin) { r.field.value = ''; setError(r.field, "That PIN doesn't match. Try again."); r.field.focus(); return; }
            setStage('reveal');
        }

        function hideNumbers() {
            if (privStage() === 'reveal') {
                const r = pinValue();
                if (!r.ok) return;
                if (r.value) privPlayer().pin = r.value;
            }
            if (check) {
                const id = check.id;
                check = null; save(); show('play');
                const tile = $('.kp-player[data-id="' + id + '"]', root);
                if (tile) tile.focus();
                return;
            }
            if (G.deal.index < G.players.length - 1) {
                G.deal = { index: G.deal.index + 1, stage: 'pass' };
                save(); renderPrivate();
            } else {
                G.phase = 'play'; G.deal = { index: 0, stage: 'pass' };
                save(); show('play', 'replace');
                focusFirst('.kp-slot');
            }
        }

        /* ---------- board ---------- */
        const ballHtml = (n, extra) => '<span class="kp-ball b' + n + (n > 8 ? ' stripe' : '') + (extra ? ' ' + extra : '') + '" aria-hidden="true"><b>' + n + '</b></span>';
        const possessive = (id) => (id ? byId(G, id).name + "'s" : "No one's");

        function badgeFor(p) {
            if (G.winner === p.id) return '<span class="badge gold">Winner</span>';
            if (p.out) return '<span class="badge r">Out</span>';
            return '<span class="badge">' + ballsLeft(p) + ' left</span>';
        }

        function renderBoard() {
            const potted = pottedCount(G), onTable = RACK - potted, inCount = alive(G).length;
            $('.kp-st-in', root).textContent = inCount;
            $('.kp-st-of', root).textContent = 'of ' + G.players.length;
            $('.kp-st-table', root).textContent = onTable;
            $('.kp-st-potted', root).textContent = potted + ' potted';
            const last = G.history[G.history.length - 1];
            $('.kp-st-last-card', root).hidden = !last;
            if (last) { $('.kp-st-last', root).textContent = last.ball; $('.kp-st-owner', root).textContent = possessive(last.owner); }
            $('.kp-players-n', root).textContent = inCount === G.players.length ? G.players.length : inCount + ' in · ' + G.players.length;
            $('.kp-balls-n', root).textContent = onTable + ' left';

            const playing = G.phase === 'play';
            const turns = playing && G.settings.turns;
            $('.kp-turn', root).hidden = !turns;
            if (turns) $('.kp-turn-name', root).textContent = byId(G, G.turn).name;
            $('.kp-players', root).innerHTML = G.players.map((p) => {
                const pips = '<span class="kp-pips" aria-hidden="true">' + p.balls.map((_, i) => '<i' + (i < ballsLeft(p) ? ' class="on"' : '') + '></i>').join('') + '</span>';
                const inner = '<b title="' + esc(p.name) + '">' + esc(p.name) + '</b>' + badgeFor(p) + pips;
                const status = (G.winner === p.id ? 'winner' : p.out ? 'out' : plural(ballsLeft(p), 'ball', 'balls') + ' left') + (turns && G.turn === p.id ? ', to shoot' : '');
                if (p.out || !playing) return '<div class="kp-player' + (p.out ? ' is-out' : '') + '" aria-label="' + esc(p.name) + ', ' + status + '">' + inner + '</div>';
                return '<button class="kp-player' + (turns && G.turn === p.id ? ' is-turn' : '') + '" type="button" data-act="kp-player" data-id="' + p.id + '" aria-label="' + esc(p.name) + ', ' + status + '. Check numbers">' + inner + '</button>';
            }).join('');

            let rack = '';
            for (let n = 1; n <= RACK; n++) {
                const pt = G.potted[n];
                const label = pt ? 'Ball ' + n + ', potted, ' + (pt.owner ? esc(byId(G, pt.owner).name) + "'s ball" : "no one's ball") : 'Ball ' + n + ', on the table';
                rack += '<button class="kp-slot" type="button" data-act="kp-ball" data-id="' + n + '" aria-label="' + label + '"' + (pt || !playing ? ' disabled' : '') + '>' +
                    ballHtml(n, pt ? 'potted' : '') + '<span class="kp-cap" aria-hidden="true">' + (pt ? esc(possessive(pt.owner)) : '') + '</span></button>';
            }
            $('.kp-rack', root).innerHTML = rack;
        }

        function tapBall(n) {
            const o = pot(G, n);
            if (!o) return;
            if (G.settings.vibrate && navigator.vibrate) navigator.vibrate(30);
            save();
            renderBoard();                 // the board is up to date before the dialog opens
            const t = outcomeText(G, o);
            pendingOutcome = n;
            openDialog(t.title, t.body, [{ label: t.button, act: 'kp-outcome-ok', primary: true }]);
        }

        function openDialog(title, body, buttons) {
            $('#kp-outcome-title').textContent = title;
            $('.kp-outcome-body', outcomeDlg).textContent = body;
            $('.actions', outcomeDlg).innerHTML = buttons.map((b) => '<button class="btn' + (b.primary ? ' primary' : '') + '" type="button" data-act="' + b.act + '"' + (b.id ? ' data-id="' + b.id + '"' : '') + '>' + esc(b.label) + '</button>').join('');
            outcomeDlg.showModal();
            $('.btn.primary', outcomeDlg).focus();
        }

        // closing the outcome (button or Esc) carries on, or shows the results
        outcomeDlg.addEventListener('close', () => {
            if (pendingOutcome === null) return;
            const n = pendingOutcome;
            pendingOutcome = null;
            if (G.phase === 'over') { show('over', 'replace'); focusFirst('.kp-over .btn.primary'); }
            else focusSlot(n);
        });

        // the potted ball is disabled now, so focus moves to the next ball still on the table
        function focusSlot(n) {
            const slots = $$('.kp-slot', root);
            const open = slots.filter((s) => !s.disabled);
            const next = open.find((s) => +s.dataset.id >= n) || open[open.length - 1];
            if (next) next.focus();
        }

        /* ---------- history ---------- */
        function renderHistory() {
            const list = G ? G.history.slice().reverse() : [];
            $('.kp-history-empty', root).hidden = list.length > 0;
            $('[data-act="kp-undo"]', root).disabled = !(G && G.undo.length && (G.phase === 'play' || G.phase === 'over'));
            $('.kp-history-list', root).innerHTML = list.map((h) => {
                const d = new Date(h.at);
                return '<li class="rowi">' + ballHtml(h.ball, 'small') + '<span>' + esc(h.text) + '</span><small title="' + esc(d.toLocaleString('en-GB')) + '">' + esc(ago(h.at)) + '</small></li>';
            }).join('');
        }

        function undoLast() {
            const r = G && undo(G);
            if (!r) return;
            G = r.game;
            save();
            if (view === 'over') show('play', 'replace'); else renderBoard();
            renderHistory();
            toast('Ball ' + r.ball + ' is back on the table.', 'ok');
        }

        /* ---------- game over ---------- */
        function renderOver() {
            const w = G.winner ? byId(G, G.winner) : null;
            const title = $('.kp-over-title', root);
            title.innerHTML = w ? esc(w.name) + ' wins <span class="badge gold">Winner</span>' : 'No winner';
            $('.kp-over-reason', root).textContent = G.endReason === 'last-standing' ? 'Last player standing.' : 'Every ball has been potted.';
            $('.kp-over-rows', root).innerHTML = G.players.map((p) => {
                const res = G.winner === p.id ? '<span class="badge gold">Winner</span>' : p.out ? '<span class="badge r">Out</span>' : '<span class="badge">In</span>';
                const nums = p.balls.map((n) => ballHtml(n, 'small' + (G.potted[n] ? ' potted' : ''))).join('');
                const spoken = p.balls.map((n) => n + (G.potted[n] ? ' (potted)' : '')).join(', ');
                return '<tr><td title="' + esc(p.name) + '">' + esc(p.name) + '</td><td><span class="kp-nums" role="img" aria-label="' + spoken + '">' + nums + '</span></td><td>' + res + '</td></tr>';
            }).join('');
        }

        /* ---------- settings ---------- */
        const form = $('.kp-settings-form', root);
        function renderSettings() {
            const locked = !!(G && (G.phase === 'deal' || G.phase === 'play'));
            const s = locked ? Object.assign({}, settings, { pin: G.settings.pin }) : settings;
            form.pin.checked = !!s.pin;
            form.hideAfter.value = String(s.hideAfter);
            form.vibrate.checked = !!s.vibrate;
            form.turns.checked = !!s.turns;
            form.pin.disabled = locked;
            $('.kp-locked', root).hidden = !locked;
        }
        form.addEventListener('change', () => {
            const locked = !!(G && (G.phase === 'deal' || G.phase === 'play'));
            if (!locked) settings.pin = form.pin.checked;
            settings.hideAfter = [10, 15, 30].includes(+form.hideAfter.value) ? +form.hideAfter.value : 15;
            settings.vibrate = form.vibrate.checked;
            settings.turns = form.turns.checked;
            UI.store.set(SETTINGS_KEY, settings);
            if (G) { Object.assign(G.settings, { hideAfter: settings.hideAfter, vibrate: settings.vibrate, turns: settings.turns }); save(); }
            // one toast per burst of changes, not a stack of them
            if (!$$('.toasts > div').some((t) => t.textContent === 'Settings saved.')) toast('Settings saved.', 'ok');
        });
        form.addEventListener('submit', (e) => e.preventDefault());

        /* ---------- drawers and dialogs (ui.js has no hooks for these, so the page wires them) ---------- */
        let drawerOpener = null;
        function openDrawer(id, opener) {
            $$('.kp-drawer.open', root).forEach((d) => closeDrawer(d, false));
            const d = $('#' + id);
            if (id === 'kp-history') renderHistory();
            if (id === 'kp-settings') renderSettings();
            drawerOpener = opener || null;
            d.inert = false;
            d.classList.add('open');
            d.setAttribute('aria-hidden', 'false');
            if (opener) opener.setAttribute('aria-expanded', 'true');
            d.focus();
        }
        function closeDrawer(d, refocus = true) {
            d.classList.remove('open');
            d.setAttribute('aria-hidden', 'true');
            d.inert = true;
            $$('[aria-controls="' + d.id + '"]', root).forEach((b) => b.setAttribute('aria-expanded', 'false'));
            if (refocus && drawerOpener && document.contains(drawerOpener) && !drawerOpener.closest('[hidden]')) drawerOpener.focus();
            drawerOpener = null;
        }
        const openDrawerEl = () => $('.kp-drawer.open', root);

        /* ---------- leaving and starting ---------- */
        function startMode(mode) {
            draft = { mode, players: 3, balls: 3, names: [] };
            show('setup', 'push');
            focusFirst('.kp-pick.on');
        }
        function goHome(clear) {
            if (clear) { G = null; forget(); }
            check = null;
            show('home', 'replace');
            focusFirst('.kp-resume:not([hidden]) .btn.primary, [data-act="kp-mode"]');
        }
        function resume() {
            if (!G) return;
            if (G.phase === 'deal') show('private', 'push');
            else { show(G.phase === 'over' ? 'over' : 'play', 'push'); focusFirst('.kp-slot:not(:disabled)'); }
        }

        /* ---------- events ---------- */
        root.addEventListener('click', (e) => {
            const el = e.target.closest('[data-act]');
            if (!el) {
                // a click outside an open drawer closes it
                const d = openDrawerEl();
                if (d && !d.contains(e.target)) closeDrawer(d);
                return;
            }
            const id = el.dataset.id;
            switch (el.dataset.act) {
                case 'kp-mode': startMode(id); break;
                case 'kp-pick-players': pickPlayers(+id); break;
                case 'kp-pick-balls': if (+id <= maxBalls(draft.players)) { draft.balls = +id; renderSetup(); } break;
                case 'kp-back': history.back(); break;
                case 'kp-chip': {
                    const empty = fields().find((f) => !f.value.trim());
                    if (empty) { empty.value = id; setError(empty, ''); renderChips(); const next = fields().find((f) => !f.value.trim()); (next || empty).focus(); }
                    break;
                }
                case 'kp-shuffle': {
                    const inputs = fields();
                    const vals = shuffle(inputs.map((f) => f.value));
                    inputs.forEach((f, i) => { f.value = vals[i]; setError(f, ''); });
                    break;
                }
                case 'kp-iam': iAm(); break;
                case 'kp-pin': enterPin(); break;
                case 'kp-hide': if (check && privStage() !== 'reveal') { check = null; show('play'); } else hideNumbers(); break;
                case 'kp-player': check = { id, stage: 'pass' }; show('private'); break;
                case 'kp-ball': tapBall(+id); break;
                case 'kp-next': if (G.phase === 'play') { nextTurn(G, id === 'back' ? -1 : 1); save(); renderBoard(); } break;
                case 'kp-outcome-ok': outcomeDlg.close(); break;
                case 'kp-undo': undoLast(); break;
                case 'kp-resume': resume(); break;
                case 'kp-abandon': $('#kp-end').showModal(); break;
                case 'kp-again': G = deal(G); save(); show('private', 'replace'); break;
                case 'kp-change': draft = { mode: G.mode, players: G.players.length, balls: G.setup.balls, names: G.players.map((p) => p.name) }; show('setup', 'push'); focusFirst('.kp-pick.on'); break;
                case 'kp-home': goHome(true); break;
                case 'kp-clear-names': saveNames([]); if (view === 'names') renderChips(); toast('Recent names cleared.', 'ok'); break;
                case 'kp-leave': $('#kp-exit').close(); goHome(false); break;
                case 'kp-end-ask': $('#kp-exit').close(); $('#kp-end').showModal(); break;
                case 'kp-end': $('#kp-end').close(); goHome(true); toast('Game ended.', 'note'); break;
                case 'drawer': {
                    const d = $('#' + el.getAttribute('aria-controls'));
                    if (d.classList.contains('open')) closeDrawer(d); else openDrawer(d.id, el);
                    break;
                }
                case 'drawer-close': closeDrawer(el.closest('.drawer')); break;
                case 'dialog': $('#' + el.getAttribute('aria-controls')).showModal(); break;
                case 'dialog-close': el.closest('dialog').close(); break;
            }
        });

        root.addEventListener('submit', (e) => {
            e.preventDefault();
            const f = e.target;
            if (f.matches('.kp-setup')) { draft.names.length = Math.min(draft.names.length, draft.players); show('names', 'push'); focusFirst('.kp-fields input'); }
            else if (f.matches('.kp-names')) submitNames();
            else if (f.matches('.kp-pin-form')) { if (privStage() === 'pin') enterPin(); else hideNumbers(); }
        });

        root.addEventListener('input', (e) => {
            const f = e.target;
            if (f.matches('.kp-fields input')) { setError(f, ''); draft.names[fields().indexOf(f)] = f.value; renderChips(); }
            if (f.matches('.kp-pin-form input')) { f.value = f.value.replace(/\D/g, '').slice(0, 4); setError(f, ''); }
        });

        // Enter in a name moves to the next one; on the last it deals
        root.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter' || !e.target.matches('.kp-fields input')) return;
            const list = fields(), i = list.indexOf(e.target);
            if (i < list.length - 1) { e.preventDefault(); list[i + 1].focus(); }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape' || document.querySelector('dialog[open]')) return;
            const d = openDrawerEl();
            if (d) { e.preventDefault(); closeDrawer(d); }
        });

        // Back button: steps back through setup, asks before leaving a game, never shows anyone's numbers
        window.addEventListener('popstate', () => {
            $$('dialog[open]').forEach((d) => d.close());
            const d = openDrawerEl();
            if (d) closeDrawer(d, false);
            if (view === 'names') show('setup');
            else if (view === 'setup') show('home');
            else if (view === 'private') {
                if (check) { check = null; show('play'); history.pushState({ kp: 'play' }, ''); }
                else { G.deal.stage = 'pass'; save(); renderPrivate(); history.pushState({ kp: 'private' }, ''); }
            } else if (view === 'play') { history.pushState({ kp: 'play' }, ''); $('#kp-exit').showModal(); }
            else if (view === 'over') show('home');
        });

        document.addEventListener('visibilitychange', () => { if (document.hidden) autoHide(); });
        window.addEventListener('pagehide', () => { if (view === 'private') { autoHide(); clearPrivate(); } });
        // a page restored from the back/forward cache never comes back on someone's numbers
        window.addEventListener('pageshow', (e) => { if (e.persisted && view === 'private') renderPrivate(); });

        /* ---------- start ---------- */
        show('home', 'replace');
    });
})();
