// Frame Guesser: screens, pickers and timer. The server picks frames, keeps the
// answers and scores every guess (api.php); this file only shows what it sends.
document.addEventListener('DOMContentLoaded', () => {
    const { $, $$, esc, store, toast } = UI;
    const root = $('.fg');
    if (!root || !$('#titleGrid')) return; // nothing to play: PHP already shows why

    const TITLES = window.FG.titles;
    const MODES = window.FG.modes;
    const NAME_KEY = 'frameGuesser_name', PICK_KEY = 'frameGuesser_titles', BEST_KEY = 'frameGuesser_best';
    const POINTS = (n) => `${n} ${n === 1 ? 'point' : 'points'}`;

    let picked = new Set(store.get(PICK_KEY, []).filter((n) => TITLES.some((t) => t.name === n)));
    if (TITLES.length === 1 || !picked.size) picked = new Set(TITLES.map((t) => t.name));
    let mode = 'normal', state = null, sel = {}, busy = false, timer = 0, deadline = 0, timeUp = false;
    let boardMode = 'normal', boardRows = [];

    // ── helpers ──
    const pickedNames = () => TITLES.filter((t) => picked.has(t.name)).map((t) => t.name); // same order as the server's sort
    const setKey = () => pickedNames().join('|');
    const setLabel = (names) => (names.length <= 2 ? names.join(' and ') : `${names.length} titles`);
    const answerText = (r, many) => [many ? r.title : '', r.season || '', r.episode].filter(Boolean).join(' · ');
    const bests = () => store.get(BEST_KEY, {});
    const bestFor = (m) => bests()[`${setKey()}#${m}`] || 0;

    async function api(url, body) {
        let res;
        try {
            res = await fetch(url, body ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) } : {});
        } catch (e) {
            throw new Error("Couldn't reach the game server. Check your connection and try again.");
        }
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.error || 'Something went wrong. Try again in a minute.');
        return data;
    }

    function show(id) {
        $$('.fg-screen').forEach((s) => (s.hidden = s.id !== id));
        $$('[data-set-name]').forEach((el) => (el.textContent = setLabel(pickedNames())));
        if (id !== 'quizScreen') stopTimer();
        const first = $(`#${id} button:not([disabled])`);
        if (first) first.focus({ preventScroll: true });
        root.scrollIntoView({ block: 'start' });
    }

    // ── title picker ──
    function renderPicks() {
        $$('.fg-title').forEach((b) => b.setAttribute('aria-pressed', picked.has(b.dataset.name)));
        $('#toModes').disabled = !picked.size;
        $('#pickCount').textContent = TITLES.length === 1 ? '1 title' : `${picked.size} of ${TITLES.length} picked`;
        const all = $('#pickAll');
        if (all) all.textContent = picked.size === TITLES.length ? 'Clear all' : 'Select all';
        store.set(PICK_KEY, [...picked]);
    }
    $('#titleGrid').addEventListener('click', (e) => {
        const b = e.target.closest('.fg-title');
        if (!b || TITLES.length === 1) return;
        picked.has(b.dataset.name) ? picked.delete(b.dataset.name) : picked.add(b.dataset.name);
        renderPicks();
    });
    const allBtn = $('#pickAll');
    if (allBtn) allBtn.addEventListener('click', () => {
        picked = picked.size === TITLES.length ? new Set() : new Set(TITLES.map((t) => t.name));
        renderPicks();
    });
    $('#toModes').addEventListener('click', openMenu);
    renderPicks();

    // ── mode menu ──
    function openMenu() {
        for (const m of Object.keys(MODES)) $(`[data-best="${m}"]`).textContent = bestFor(m);
        show('menuScreen');
    }
    const nameInput = $('#playerName');
    nameInput.value = store.get(NAME_KEY, '');
    nameInput.addEventListener('input', () => store.set(NAME_KEY, nameInput.value.trim()));
    $$('.fg-mode').forEach((b) => b.addEventListener('click', () => start(b.dataset.mode, b)));

    // ── playing ──
    async function start(m, btn) {
        if (busy) return;
        busy = true;
        if (btn) btn.disabled = true;
        try {
            const st = await api('api.php', { action: 'start', mode: m, titles: pickedNames() });
            mode = m;
            timeUp = false;
            $('#modeTag').textContent = `${setLabel(st.titles)} · ${MODES[m].title}`;
            $('#bestNum').textContent = bestFor(m);
            show('quizScreen');
            if (MODES[m].seconds) startTimer(st.timeLeft);
            showRound(st);
        } catch (e) {
            toast(e.message, 'err');
        } finally {
            busy = false;
            if (btn) btn.disabled = false;
        }
    }

    const img = $('#frameImg');
    img.addEventListener('error', () => { if (img.getAttribute('src')) { $('#frameErr').hidden = false; img.style.visibility = 'hidden'; } });
    img.addEventListener('load', () => { $('#frameErr').hidden = true; img.style.visibility = ''; });
    $$('.fg-title img').forEach((i) => (i.complete && !i.naturalWidth ? (i.style.visibility = 'hidden') : i.addEventListener('error', () => (i.style.visibility = 'hidden'))));
    $('#frameRetry').addEventListener('click', () => (img.src = `${state.frame}&r=${Date.now()}`));

    function showRound(st) {
        state = st;
        sel = {};
        $('#frameErr').hidden = true;
        img.src = st.frame;
        new Image().src = st.next; // preload while the player guesses
        $('#result').innerHTML = '';
        updateStats();
        renderStep();
    }

    function updateStats() {
        $('#scoreNum').textContent = state.score;
        const m = MODES[mode];
        if (m.rounds) {
            $('#progressNum').textContent = `${Math.min(state.round + 1, m.rounds)} / ${m.rounds}`;
            $('#progressLbl').textContent = 'Frame';
        } else if (m.lives) {
            $('#progressNum').textContent = state.lives;
            $('#progressLbl').textContent = state.lives === 1 ? 'Life left' : 'Lives left';
        }
    }

    const inPlay = () => TITLES.filter((t) => state.titles.includes(t.name));

    // Title → season → episode; a step with only one option is picked for you
    function renderStep() {
        const titles = inPlay();
        const many = titles.length > 1;
        let options, text, kind, back = false;
        if (sel.title === undefined && !many) sel.title = titles[0].name;
        if (sel.title === undefined) {
            [options, text, kind] = [titles.map((t) => t.name), 'Which show or series is this from?', 'title'];
        } else {
            const t = titles.find((x) => x.name === sel.title);
            back = many;
            if (sel.season === undefined && t.groups.length === 1) sel.season = t.groups[0].name;
            if (sel.season === undefined) {
                [options, text, kind] = [t.groups.map((g) => g.name), 'Which season?', 'season'];
            } else {
                const g = t.groups.find((x) => x.name === sel.season);
                back = back || t.groups.length > 1;
                options = g.episodes;
                text = g.name === null ? 'Which one is it?' : 'Which episode?';
                kind = 'episode';
            }
        }
        const where = [many ? sel.title : '', sel.season || ''].filter(Boolean).join(' · ');
        $('#stepText').innerHTML = (where ? `<b>${esc(where)}</b> · ` : '') + esc(text);
        $('#choices').className = `fg-choices fg-choices-${kind}`;
        $('#choices').innerHTML = options.map((o, i) => `<button class="btn" type="button" data-i="${i}" title="${esc(o)}">${esc(o)}</button>`).join('')
            + (back ? '<button class="btn fg-back" type="button" data-back>Back</button>' : '');
        $('#choices').dataset.kind = kind;
        $('#choices').options = options;
        const first = $('#choices button');
        if (first && document.activeElement && root.contains(document.activeElement)) first.focus({ preventScroll: true });
    }

    function stepBack() {
        if (busy || $('#choices').dataset.kind === 'done') return;
        const t = inPlay().find((x) => x.name === sel.title);
        if (sel.season !== undefined && t && t.groups.length > 1) delete sel.season;
        else if (inPlay().length > 1) sel = {};
        else return;
        renderStep();
    }

    $('#choices').addEventListener('click', (e) => {
        const b = e.target.closest('button');
        if (!b || busy) return;
        if (b.hasAttribute('data-back')) return stepBack();
        if (b.hasAttribute('data-next')) return next();
        if (timeUp) return; // finish() is already on its way
        const box = $('#choices'), value = box.options[+b.dataset.i];
        if (box.dataset.kind === 'title') { sel = { title: value }; renderStep(); }
        else if (box.dataset.kind === 'season') { sel.season = value; renderStep(); }
        else if (box.dataset.kind === 'episode') guess(value, b);
    });

    let after = null; // the state to move on to once the player has seen the answer
    async function guess(episode, btn) {
        busy = true;
        $$('#choices button').forEach((b) => (b.disabled = true));
        let data;
        try {
            data = await api('api.php', { action: 'guess', title: sel.title, season: sel.season, episode });
        } catch (e) {
            toast(e.message, 'err');
            $$('#choices button').forEach((b) => (b.disabled = false));
            busy = false;
            return;
        }
        busy = false;
        const r = data.result;
        if (!r) return gameOver(data.state); // the clock ran out before the guess arrived
        btn.classList.add(r.kind === 'exact' ? 'fg-ok' : 'fg-bad');
        const answer = esc(answerText(r, state.titles.length > 1));
        const msg = { exact: ['ok', `Right: ${answer}. ${POINTS(r.points)}.`], season: ['note', `Right season. It was ${answer}. ${POINTS(r.points)}.`], miss: ['err', `It was ${answer}. No points.`] }[r.kind];
        state.score = data.state.score;
        if (data.state.lives !== undefined) state.lives = data.state.lives;
        updateStats();
        $('#choices').dataset.kind = 'done';
        after = data.state;
        const last = data.state.over || timeUp;
        $('#result').innerHTML = `<p class="${msg[0]}">${msg[1]}</p>`;
        if (MODES[mode].seconds && !last) return setTimeout(next, 900); // the clock keeps running, so keep moving
        $('#choices').insertAdjacentHTML('beforeend', `<button class="btn primary fg-next" type="button" data-next>${last ? 'See your score' : 'Next frame'}</button>`);
        $('.fg-next').focus({ preventScroll: true });
    }

    function next() {
        if (!after || $('#quizScreen').hidden) return;
        const st = after;
        after = null;
        if (st.over) return gameOver(st);
        if (timeUp) return finish();
        showRound(st);
    }

    // ── timed mode clock ──
    function startTimer(ms) {
        deadline = performance.now() + ms;
        $('#progressLbl').textContent = 'Time left';
        const tick = () => {
            const left = Math.max(0, deadline - performance.now());
            const s = Math.ceil(left / 1000);
            $('#progressNum').textContent = `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
            if (left > 0) return;
            stopTimer();
            timeUp = true;
            if (!busy && $('#choices').dataset.kind !== 'done') finish();
            else if (!busy) next();
        };
        tick();
        timer = setInterval(tick, 250);
    }
    function stopTimer() { clearInterval(timer); timer = 0; }

    async function finish() {
        try {
            gameOver(await api('api.php', { action: 'finish' }));
        } catch (e) {
            toast(e.message, 'err');
            show('menuScreen');
        }
    }

    // ── game over ──
    function gameOver(st) {
        state = st;
        stopTimer();
        const m = MODES[mode];
        $('#overTitle').textContent = { complete: 'All frames done', lives: 'Out of lives', time: "Time's up" }[st.end] || 'Game over';
        $('#overScore').textContent = st.score;
        $('#overLbl').textContent = m.rounds ? `points from ${m.rounds} frames` : m.lives ? `points before your ${m.lives === 3 ? 'third' : 'last'} miss` : `points in ${m.seconds} seconds`;
        const key = `${st.titles.join('|')}#${mode}`, all = bests();
        $('#overRecord').hidden = !(st.score > (all[key] || 0));
        if (st.score > (all[key] || 0)) { all[key] = st.score; store.set(BEST_KEY, all); }
        renderRounds($('#overRounds'), st.rounds, st.titles.length > 1);
        $('#overRounds').previousElementSibling.hidden = !st.rounds.length;
        const box = $('#overBoard');
        box.innerHTML = '';
        show('gameoverScreen');
        if (st.score < 1) return;
        const name = store.get(NAME_KEY, '');
        if (name) return save(name);
        box.innerHTML = `<form class="form fg-save"><label>Name for the leaderboard <input class="field" maxlength="20" autocomplete="nickname" required></label><div class="actions"><button class="btn">Save score</button></div></form>`;
        box.querySelector('form').addEventListener('submit', (e) => {
            e.preventDefault();
            const v = box.querySelector('input').value.trim();
            if (!v) return;
            store.set(NAME_KEY, v);
            nameInput.value = v;
            save(v);
        });
    }

    async function save(name) {
        const box = $('#overBoard');
        box.innerHTML = '<p class="note">Saving your score...</p>';
        try {
            const r = await api('leaderboard.php', { name });
            box.innerHTML = r.improved
                ? `<p class="ok">Saved as ${esc(name)}: number ${r.rank} on this leaderboard.</p>`
                : `<p class="note">Your best of ${esc(r.best)} on this leaderboard still stands (number ${r.rank}).</p>`;
        } catch (e) {
            box.innerHTML = `<p class="err">${esc(e.message)}</p>`;
        }
    }

    function renderRounds(el, rounds, many) {
        el.innerHTML = rounds.map((r) => {
            const g = r.guess;
            const guessed = answerText({ title: g.title, season: g.season, episode: g.episode }, many);
            return `<div class="rowi"><img src="${esc(r.image)}" alt="" width="96" height="54" loading="lazy" decoding="async">`
                + `<span class="grow"><b>${esc(answerText(r, many))}</b><small>${r.points === 3 ? 'Guessed exactly' : 'You said ' + esc(guessed)}</small></span>`
                + `<span class="badge ${r.points === 3 ? 'g' : r.points ? 'b' : 'r'}">${POINTS(r.points)}</span></div>`;
        }).join('');
        el.querySelectorAll('img').forEach((i) => i.addEventListener('error', () => (i.style.visibility = 'hidden')));
    }

    // ── leaderboards ──
    function openBoard(m) {
        boardMode = m;
        $('#boardTabs').innerHTML = Object.entries(MODES).map(([id, info]) => `<button type="button" data-m="${id}" aria-pressed="${id === m}" class="${id === m ? 'on' : ''}">${esc(info.title)}</button>`).join('');
        show('boardScreen');
        loadBoard();
    }
    async function loadBoard() {
        const body = $('#boardBody'), m = boardMode;
        body.innerHTML = '<p class="note">Loading the leaderboard...</p>';
        try {
            const data = await api(`leaderboard.php?titles=${encodeURIComponent(setKey())}&mode=${m}`);
            if (m !== boardMode) return;
            boardRows = data.entries;
            const me = store.get(NAME_KEY, '').toLowerCase();
            body.innerHTML = !boardRows.length ? `<p class="muted">No scores yet for ${esc(MODES[m].title)} with these titles. Play a game to be first.</p>`
                : `<div class="table-wrap"><table class="table"><thead><tr><th class="num">#</th><th>Name</th><th class="num">Score</th><th>Date</th><th><span class="fg-sr">Replay</span></th></tr></thead><tbody>`
                + boardRows.map((e, i) => `<tr${e.name.toLowerCase() === me ? ' class="fg-me"' : ''}><td class="num">${e.rank}</td><td>${esc(e.name)}</td><td class="num">${e.score}</td>`
                    + `<td title="${esc(new Date(e.date).toLocaleString())}">${esc(new Date(e.date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }))}</td>`
                    + `<td>${e.replay ? `<button class="btn small" type="button" data-replay="${i}">View frames</button>` : ''}</td></tr>`).join('')
                + '</tbody></table></div>';
        } catch (e) {
            if (m === boardMode) body.innerHTML = `<p class="err">${esc(e.message)}</p>`;
        }
    }
    $('#boardTabs').addEventListener('click', (e) => { const b = e.target.closest('[data-m]'); if (b) openBoard(b.dataset.m); });
    $('#boardBody').addEventListener('click', async (e) => {
        const b = e.target.closest('[data-replay]');
        if (!b) return;
        const entry = boardRows[+b.dataset.replay];
        b.disabled = true;
        try {
            const { replay } = await api(`leaderboard.php?replay=${entry.replay}`);
            $('#replayTitle').innerHTML = `${esc(entry.name)} <span class="n">${esc(MODES[replay.mode].title)} · ${POINTS(entry.score)}</span>`;
            renderRounds($('#replayRounds'), replay.rounds, replay.titles.length > 1);
            show('replayScreen');
        } catch (err) {
            toast(err.message, 'err');
            b.disabled = false;
        }
    });

    // ── navigation and keys ──
    root.addEventListener('click', (e) => {
        const b = e.target.closest('[data-go]');
        if (!b) return;
        const go = b.dataset.go;
        if (go === 'series') show('seriesScreen');
        else if (go === 'menu') openMenu();
        else if (go === 'board') openBoard($('#gameoverScreen').hidden ? boardMode : mode);
        else if (go === 'again') start(mode, b);
    });
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape' || e.defaultPrevented || document.querySelector('.drawer.open, dialog[open]')) return;
        if (!$('#quizScreen').hidden) stepBack();
        else if (!$('#replayScreen').hidden) show('boardScreen');
        else if (!$('#boardScreen').hidden || !$('#menuScreen').hidden) { $('#boardScreen').hidden ? show('seriesScreen') : openMenu(); }
    });
});
