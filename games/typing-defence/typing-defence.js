// Typing Defence: words fall, type them before they land. DOM + transforms.
document.addEventListener('DOMContentLoaded', () => {
    const { $, esc, store, num } = UI;
    const LEVEL_SECS = 20; // keep in step with validate.php
    const field = $('#td-field'), layer = $('#td-words'), input = $('#td-input');
    const screens = { start: $('#td-start'), pause: $('#td-pause'), ended: $('#td-ended') };
    const go = $('#td-go'), result = $('#td-result'), form = $('#td-form');
    const hud = { score: $('#td-score'), level: $('#td-level'), lives: $('#td-lives'), wpm: $('#td-wpm') };
    let words = [], list = [];
    let live = [], target = null, buf = '';
    let score, letters, lives, t, spawnIn, last, state = 'idle', raf = 0, H = 0, finalWpm = 0, finalSecs = 0;

    const level = () => 1 + Math.floor(t / LEVEL_SECS);
    const wpm = () => (t < 3 ? 0 : Math.round(letters / 5 / (t / 60)));
    const show = (name) => Object.entries(screens).forEach(([k, el]) => { el.hidden = k !== name; });

    function paintHud() {
        hud.score.textContent = num(score);
        hud.level.textContent = level();
        hud.lives.textContent = lives;
        hud.lives.setAttribute('aria-label', lives + (lives === 1 ? ' life' : ' lives'));
        hud.wpm.textContent = wpm();
    }

    function paintWord(w) {
        const n = w === target ? buf.length : 0;
        w.el.innerHTML = '<b>' + w.text.slice(0, n) + '</b>' + w.text.slice(n);
        w.el.classList.toggle('target', w === target);
    }

    function spawn() {
        const maxLen = Math.min(10, 4 + Math.floor(t / 15));
        const pool = list.filter((x) => x.length <= maxLen);
        let text;
        for (let i = 0; i < 8; i++) { // avoid two words sharing a first letter
            text = pool[Math.floor(Math.random() * pool.length)];
            if (!live.some((w) => w.text[0] === text[0])) break;
        }
        const el = document.createElement('div');
        el.className = 'td-word';
        el.textContent = text;
        el.style.transform = 'translate(0, -40px)';
        layer.appendChild(el);
        const w = { el, text, y: -30, xr: Math.random(), w: el.offsetWidth };
        live.push(w);
    }

    function drop(w) {
        live.splice(live.indexOf(w), 1);
        if (w === target) { target = null; buf = ''; input.value = ''; }
        w.el.classList.add('hit');
        setTimeout(() => w.el.remove(), 150);
    }

    function loop(now) {
        raf = requestAnimationFrame(loop);
        const dt = Math.min(0.05, (now - last) / 1000);
        last = now;
        t += dt;
        H = field.clientHeight;
        const W = field.clientWidth;
        spawnIn -= dt;
        if (spawnIn <= 0) { spawn(); spawnIn = Math.max(0.6, 2.2 - t * 0.012); }
        const speed = H * Math.min(0.5, 0.09 + t * 0.0008);
        for (const w of live.slice()) {
            w.y += speed * dt;
            if (w.y + 28 >= H) {
                drop(w);
                lives--;
                field.classList.add('hurt');
                setTimeout(() => field.classList.remove('hurt'), 250);
                if (lives <= 0) { end(); return; }
            } else {
                w.el.style.transform = 'translate(' + Math.round(w.xr * Math.max(0, W - w.w - 16) + 8) + 'px,' + Math.round(w.y) + 'px)';
            }
        }
        paintHud();
    }

    function startLoop() { last = performance.now(); cancelAnimationFrame(raf); raf = requestAnimationFrame(loop); }

    function start() {
        live.forEach((w) => w.el.remove());
        live = []; target = null; buf = ''; input.value = '';
        score = 0; letters = 0; lives = 3; t = 0; spawnIn = 0.3;
        result.hidden = true;
        show(null);
        state = 'play';
        paintHud();
        input.focus({ preventScroll: true });
        fit();
        startLoop();
    }

    function pause() {
        if (state !== 'play') return;
        state = 'pause'; cancelAnimationFrame(raf); show('pause');
    }
    function resume() {
        if (state !== 'pause') return;
        state = 'play'; show(null); input.focus({ preventScroll: true }); startLoop();
    }

    function end() {
        cancelAnimationFrame(raf);
        state = 'over';
        input.blur();
        paintHud();
        finalWpm = wpm();
        finalSecs = Math.round(t);
        const best = Math.max(score, store.get('td-best', 0));
        store.set('td-best', best);
        show('ended');
        $('#td-r-score').textContent = num(score);
        $('#td-r-wpm').textContent = finalWpm;
        $('#td-r-level').textContent = level();
        $('#td-best').textContent = 'best ' + num(best);
        $('#td-form-err').hidden = true;
        $('#td-form-ok').hidden = true;
        $('#td-name').value = store.get('td-name', '');
        const canSave = score > 0 && finalWpm > 0 && finalSecs >= 5;
        form.querySelector('label').hidden = !canSave;
        $('#td-save').hidden = !canSave;
        $('#td-save').disabled = false;
        $('#td-save').textContent = 'Save score';
        result.hidden = false;
        result.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    function press(c) {
        if (!target) {
            const c2 = live.filter((w) => w.text[0] === c).sort((a, b) => b.y - a.y)[0];
            if (!c2) return;
            target = c2; buf = c;
        } else if (target.text[buf.length] === c) {
            buf += c;
        } else return;
        if (buf.length === target.text.length) {
            const w = target;
            score += w.text.length * level();
            letters += w.text.length;
            drop(w);
        } else paintWord(target);
    }

    // Works with desktop keys and phone keyboards: the hidden input's value is the diff source
    input.addEventListener('input', () => {
        if (state !== 'play') { input.value = ''; return; }
        const v = input.value.toLowerCase().replace(/[^a-z]/g, '');
        let p = 0;
        while (p < v.length && p < buf.length && v[p] === buf[p]) p++;
        if (p < buf.length) {
            const old = target;
            buf = buf.slice(0, p);
            if (!buf) target = null;
            if (old) paintWord(old);
        }
        for (const c of v.slice(p)) press(c);
        input.value = buf;
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { state === 'play' ? pause() : resume(); }
    });
    document.addEventListener('visibilitychange', () => { if (document.hidden) pause(); });
    field.addEventListener('click', () => {
        if (state === 'pause') resume();
        else if (state === 'play') input.focus({ preventScroll: true });
    });

    // Phones: keep the play area above the on-screen keyboard
    function fit() {
        const vv = window.visualViewport;
        if (!vv || innerWidth > 640 || state !== 'play') { field.style.removeProperty('--td-h'); return; }
        const h = Math.max(160, Math.min(360, vv.height - 130));
        field.style.setProperty('--td-h', h + 'px');
        field.scrollIntoView({ block: 'start' });
    }
    if (window.visualViewport) visualViewport.addEventListener('resize', fit);

    go.addEventListener('click', start);
    $('#td-again').addEventListener('click', start);

    // Leaderboard
    const board = $('#td-board'), status = $('#td-board-status'), wrap = $('#td-board-wrap');
    function renderBoard(entries, me) {
        status.hidden = true;
        wrap.hidden = !entries.length;
        if (!entries.length) { status.hidden = false; status.className = 'note'; status.textContent = 'No scores yet. Be the first.'; }
        board.innerHTML = entries.map((e) => {
            const d = new Date(e.date);
            const date = isNaN(d) ? '' : d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
            const mine = me && e.name.toLowerCase() === me.toLowerCase();
            return '<tr' + (mine ? ' class="td-me"' : '') + '><td>' + e.rank + '</td><td class="td-name">' + esc(e.name) +
                '</td><td class="num">' + num(e.score) + '</td><td class="num">' + e.wpm + '</td><td>' + date + '</td></tr>';
        }).join('');
    }
    function boardError(msg) { status.hidden = false; status.className = 'err'; status.textContent = msg; }
    fetch('leaderboard.php').then((r) => r.json()).then((j) => {
        if (!j.entries) throw 0;
        renderBoard(j.entries);
    }).catch(() => boardError("Couldn't load the leaderboard. Try again in a minute."));

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const name = $('#td-name').value.trim();
        const err = $('#td-form-err'), ok = $('#td-form-ok'), btn = $('#td-save');
        err.hidden = ok.hidden = true;
        if (!name) { err.textContent = 'Enter a name of 1 to 20 characters.'; err.hidden = false; return; }
        btn.disabled = true; btn.textContent = 'Saving...';
        fetch('leaderboard.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, score, wpm: finalWpm, duration: finalSecs }),
        }).then((r) => r.json()).then((j) => {
            if (j.error) throw new Error(j.error);
            store.set('td-name', name);
            renderBoard(j.entries, name);
            ok.textContent = j.improved ? 'Saved. You are ranked ' + j.rank + '.' : 'Saved. Your best is still ' + num(j.best) + '.';
            ok.hidden = false;
            btn.textContent = 'Saved';
        }).catch((x) => {
            err.textContent = x.message && x.message !== 'Failed to fetch' ? x.message : "Couldn't save your score. Try again.";
            err.hidden = false; btn.disabled = false; btn.textContent = 'Save score';
        });
    });

    fetch('words.json').then((r) => r.json()).then((w) => {
        list = w; go.disabled = false;
    }).catch(() => { go.textContent = 'Words failed to load'; });
});
