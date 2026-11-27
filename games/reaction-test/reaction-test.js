// Reaction Test: five tries, average, global leaderboard.
document.addEventListener('DOMContentLoaded', () => {
    const { esc, store } = UI;
    const $ = (id) => document.getElementById(id);
    const panel = $('rt-panel'), big = $('rt-big'), small = $('rt-small'), triesEl = $('rt-tries');
    const TRIES = 5, LOCK_MS = 250;

    let state = 'idle'; // idle | wait | go | soon | between | done
    let times = [], timer = 0, goAt = 0, lockUntil = 0, run = null, me = '';

    const bandOf = (ms) => (ms < 200 ? ['Fast', 'under 200 ms'] : ms <= 300 ? ['Typical', '200 to 300 ms'] : ['Slow', 'over 300 ms']);

    function show(next, headline, line) {
        state = next;
        panel.dataset.state = next === 'between' || next === 'done' ? 'idle' : next;
        big.textContent = headline;
        small.textContent = line;
    }

    function drawTries() {
        triesEl.innerHTML = Array.from({ length: TRIES }, (_, i) => {
            const cls = i < times.length ? 'done' : i === times.length && state !== 'done' && state !== 'idle' ? 'now' : '';
            return `<li class="${cls}">${i < times.length ? times[i] + ' ms' : i + 1}</li>`;
        }).join('');
    }

    function startTry() {
        clearTimeout(timer);
        if (state === 'idle' || state === 'done') { times = []; $('rt-result').hidden = true; }
        show('wait', 'Wait for it…', 'Click only when the panel turns green.');
        drawTries();
        timer = setTimeout(() => {
            requestAnimationFrame(() => {
                if (state !== 'wait') return;
                show('go', 'Click!', '');
                goAt = 0;
                // The next frame starts after the change was painted, so time from there.
                requestAnimationFrame((ts) => { goAt = ts; });
            });
        }, 1500 + Math.random() * 3500);
    }

    function tooSoon() {
        clearTimeout(timer);
        lockUntil = performance.now() + LOCK_MS;
        show('soon', 'Too soon', 'That try does not count. Click or press Space to try again.');
    }

    function finishTry(ms) {
        times.push(ms);
        lockUntil = performance.now() + LOCK_MS;
        if (times.length < TRIES) {
            show('between', ms + ' ms', 'Click or press Space for try ' + (times.length + 1) + ' of ' + TRIES + '.');
            drawTries();
            return;
        }
        const avg = Math.round(times.reduce((a, b) => a + b, 0) / TRIES), best = Math.min(...times);
        const [word, note] = bandOf(avg);
        run = { avg, tries: times.slice() };
        show('done', avg + ' ms average', word + '. Click or press Space to go again.');
        drawTries();
        $('rt-avg').textContent = avg;
        $('rt-best').textContent = best;
        $('rt-verdict').textContent = word;
        $('rt-verdict-note').textContent = note;
        const pb = store.get('rt-best', null);
        const isPb = pb === null || avg < pb;
        if (isPb) store.set('rt-best', avg);
        $('rt-pb').textContent = isPb ? (pb === null ? 'Your first result is saved as your personal best.' : 'New personal best, beating ' + pb + ' ms.') : 'Your personal best is ' + pb + ' ms.';
        $('rt-form').hidden = avg < 100; // the server rejects anything under 100 ms
        $('rt-submit').disabled = false;
        $('rt-submit').textContent = 'Add to leaderboard';
        $('rt-msg').hidden = true;
        $('rt-result').hidden = false;
    }

    // Space, Enter, mouse and touch all land here
    function press(e) {
        const now = performance.now();
        if (state === 'wait') return tooSoon();
        if (state === 'go') {
            if (!goAt) return tooSoon(); // not painted yet: nobody can have reacted
            return finishTry(Math.max(1, Math.round(e.timeStamp - goAt)));
        }
        if (now < lockUntil) return;
        startTry();
    }

    panel.addEventListener('pointerdown', (e) => {
        if (e.button !== 0) return;
        e.preventDefault();
        panel.focus({ preventScroll: true });
        press(e);
    });
    document.addEventListener('keydown', (e) => {
        if ((e.key !== ' ' && e.key !== 'Enter') || e.repeat || e.ctrlKey || e.metaKey || e.altKey) return;
        const t = e.target;
        if (t !== panel && t !== document.body && t !== document.documentElement) return; // leave fields, links and other buttons alone
        e.preventDefault();
        press(e);
    });

    // Leaderboard
    const status = $('rt-board-status'), board = $('rt-board');
    const fmtDate = (iso) => new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });

    function drawBoard(entries) {
        status.hidden = true;
        board.hidden = !entries.length;
        if (!entries.length) { status.hidden = false; status.className = 'note'; status.textContent = 'No scores yet. Be the first.'; return; }
        const key = me.toLowerCase();
        board.innerHTML = '<table class="table"><thead><tr><th>#</th><th>Name</th><th class="num">Average</th><th>Date</th></tr></thead><tbody>' +
            entries.map((e) => `<tr${key && e.name.toLowerCase() === key ? ' class="rt-me"' : ''}><td>${e.rank}</td><td>${esc(e.name)}</td><td class="num">${e.score} ms</td><td>${esc(fmtDate(e.date))}</td></tr>`).join('') +
            '</tbody></table>';
    }

    function boardError() {
        status.hidden = false;
        status.className = 'err';
        status.textContent = "Couldn't load the leaderboard. Try again in a minute.";
    }

    fetch('leaderboard.php').then((r) => r.ok ? r.json() : Promise.reject()).then((d) => drawBoard(d.entries)).catch(boardError);

    $('rt-name').value = store.get('rt-name', '');
    $('rt-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!run) return;
        const name = $('rt-name').value.trim().replace(/\s+/g, ' ');
        const msg = $('rt-msg'), btn = $('rt-submit');
        if (!name) { $('rt-name').setAttribute('aria-invalid', 'true'); return; }
        $('rt-name').removeAttribute('aria-invalid');
        btn.disabled = true;
        btn.textContent = 'Saving...';
        msg.hidden = true;
        try {
            const r = await fetch('leaderboard.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ name, score: run.avg, tries: run.tries }) });
            const d = await r.json().catch(() => ({}));
            if (!r.ok) throw new Error(d.error || 'Could not save your score.');
            store.set('rt-name', name);
            me = name;
            drawBoard(d.entries);
            msg.className = 'ok';
            msg.textContent = d.improved ? `Saved. You are number ${d.rank} on the leaderboard.` : `Your best on the leaderboard is still ${d.best} ms, number ${d.rank}.`;
            btn.textContent = 'Added';
        } catch (err) {
            msg.className = 'err';
            msg.textContent = err.message || 'Could not save your score.';
            btn.disabled = false;
            btn.textContent = 'Add to leaderboard';
        }
        msg.hidden = false;
    });

    drawTries();
});
