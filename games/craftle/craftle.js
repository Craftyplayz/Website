// Craftle: guess the item from its crafting recipe. 6 guesses; every wrong guess reveals more of the grid.
document.addEventListener('DOMContentLoaded', () => {
    'use strict';
    const { $, esc, store, toast, copy } = UI;
    const MAX = 6, KEY = 'craftle:v1', ICON = '/minecraft/tools/item-list/minecraft_';
    const name = (id) => id.split('_').map((w) => w[0].toUpperCase() + w.slice(1)).join(' ');
    const img = (id, cls = '') => `<img class="${cls}" src="${ICON}${id}.png" alt="${esc(name(id))}" title="${esc(name(id))}" width="32" height="32">`;
    const hash = (s) => { let h = 2166136261; for (const c of s) h = Math.imul(h ^ c.charCodeAt(0), 16777619); return h >>> 0; };
    const rng = (seed) => () => { seed = (seed + 0x6D2B79F5) | 0; let t = Math.imul(seed ^ (seed >>> 15), 1 | seed); t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t; return ((t ^ (t >>> 14)) >>> 0) / 4294967296; };
    const today = () => new Date().toISOString().slice(0, 10);
    const dayBefore = (d) => new Date(Date.parse(d) - 864e5).toISOString().slice(0, 10);

    let data, state, stats = store.get(KEY, { days: {}, streak: 0, best: 0, last: '', played: 0 });
    let matches = [], sel = -1;
    const el = {};
    ['mode', 'left', 'status', 'craft', 'grid', 'result', 'form', 'input', 'list', 'end', 'verdict', 'guesses', 'stats', 'foot', 'daily', 'again', 'practice', 'copy', 'go'].forEach((k) => { el[k] = $('#cr-' + k); });

    function start(daily) {
        const date = today(), rand = daily ? rng(hash(date)) : Math.random;
        const rec = daily ? data.recipes[hash(date) % data.recipes.length] : data.recipes[Math.floor(Math.random() * data.recipes.length)];
        const idx = rec.g.map((g, i) => (g ? i : -1)).filter((i) => i >= 0);
        for (let i = idx.length - 1; i > 0; i--) { const j = Math.floor(rand() * (i + 1)); [idx[i], idx[j]] = [idx[j], idx[i]]; }
        state = { daily, date, rec, order: idx, guesses: daily ? (stats.days[date] || []) : [] };
        el.input.value = '';
        render();
    }

    const won = () => state.guesses.includes(state.rec.r);
    const over = () => won() || state.guesses.length >= MAX;
    // 5 wrong guesses reveal the whole recipe; the 6th guess is the last chance
    const shown = () => { const w = state.guesses.filter((g) => g !== state.rec.r).length, n = state.order.length; return over() ? n : Math.min(n, Math.ceil(n * w / (MAX - 1))); };

    function render() {
        const rev = new Set(state.order.slice(0, shown())), fin = over();
        el.mode.textContent = state.daily ? 'Daily puzzle' : 'Practice puzzle';
        el.left.textContent = fin ? '' : `${MAX - state.guesses.length} of ${MAX} guesses left`;
        el.status.hidden = true;
        el.craft.hidden = false;
        el.grid.innerHTML = state.rec.g.map((id, i) => `<div class="cr-slot">${id && rev.has(i) ? img(id) : ''}</div>`).join('');
        el.result.innerHTML = fin ? img(state.rec.r) : '<span aria-hidden="true">?</span>';
        el.form.hidden = fin;
        el.end.hidden = !fin;
        el.daily.hidden = state.daily;
        el.stats.hidden = false;
        el.guesses.innerHTML = state.guesses.map((g) => `<li class="cr-guess ${g === state.rec.r ? 'ok' : 'bad'}">${img(g)}<span class="grow">${esc(name(g))}</span><small>${g === state.rec.r ? 'Correct' : 'Not it'}</small></li>`).join('');
        if (fin) {
            const w = won();
            el.verdict.className = 'cr-verdict ' + (w ? 'ok' : 'err');
            el.verdict.innerHTML = (w ? `You got it in ${state.guesses.length} of ${MAX}. ` : 'Out of guesses. ') + `It was <b>${esc(name(state.rec.r))}</b>.`;
        } else el.input.focus({ preventScroll: true });
        $('#cr-s-streak').textContent = stats.streak;
        $('#cr-s-best').textContent = stats.best;
        $('#cr-s-played').textContent = stats.played;
        el.foot.textContent = state.daily ? `Puzzle for ${state.date} (UTC). A new one starts at midnight UTC. Recipes from Minecraft Java ${data.version}.` : 'Practice puzzles don’t change your streak.';
        closeList();
    }

    function guess(id) {
        if (over() || !data.names.includes(id)) return;
        if (state.guesses.includes(id)) return toast('You already guessed that.', 'note');
        state.guesses.push(id);
        el.input.value = '';
        if (state.daily) {
            stats.days[state.date] = state.guesses;
            if (over()) {
                stats.played++;
                if (won()) { stats.streak = stats.last === dayBefore(state.date) ? stats.streak + 1 : 1; stats.best = Math.max(stats.best, stats.streak); } else stats.streak = 0;
                stats.last = state.date;
                Object.keys(stats.days).sort().slice(0, -30).forEach((d) => delete stats.days[d]);
            }
            store.set(KEY, stats);
        }
        render();
    }

    function openList() {
        const q = el.input.value.trim().toLowerCase().replace(/\s+/g, ' ');
        matches = q ? data.names.filter((id) => !state.guesses.includes(id) && name(id).toLowerCase().includes(q))
            .sort((a, b) => (name(b).toLowerCase().startsWith(q) - name(a).toLowerCase().startsWith(q)) || a.localeCompare(b)).slice(0, 8) : [];
        sel = -1;
        el.list.innerHTML = matches.map((id, i) => `<li role="option" id="cr-o${i}" data-id="${id}" aria-selected="false">${img(id)}<span>${esc(name(id))}</span></li>`).join('');
        el.list.hidden = !matches.length;
        el.input.setAttribute('aria-expanded', String(matches.length > 0));
    }
    function closeList() { el.list.hidden = true; el.input.setAttribute('aria-expanded', 'false'); el.input.removeAttribute('aria-activedescendant'); matches = []; }
    function mark(i) {
        sel = (i + matches.length) % matches.length;
        [...el.list.children].forEach((li, n) => li.setAttribute('aria-selected', String(n === sel)));
        el.input.setAttribute('aria-activedescendant', 'cr-o' + sel);
        el.list.children[sel].scrollIntoView({ block: 'nearest' });
    }

    el.input.addEventListener('input', openList);
    el.input.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown' && matches.length) { e.preventDefault(); mark(sel + 1); }
        else if (e.key === 'ArrowUp' && matches.length) { e.preventDefault(); mark(sel - 1); }
        else if (e.key === 'Escape' && matches.length) { e.stopPropagation(); closeList(); }
    });
    el.form.addEventListener('submit', (e) => {
        e.preventDefault();
        const typed = el.input.value.trim().toLowerCase();
        const id = matches[sel] || data.names.find((n) => name(n).toLowerCase() === typed) || matches[0];
        if (id) guess(id); else toast('Pick an item from the list.', 'note');
    });
    el.list.addEventListener('mousedown', (e) => { // mousedown keeps the input focused
        const li = e.target.closest('li'); if (li) { e.preventDefault(); guess(li.dataset.id); }
    });
    el.input.addEventListener('blur', () => setTimeout(closeList, 100));
    el.practice.onclick = el.again.onclick = () => start(false);
    el.daily.onclick = () => start(true);
    el.copy.onclick = () => {
        const sq = state.guesses.map((g) => (g === state.rec.r ? '🟩' : '🟥')).join('');
        copy(`Craftle ${state.daily ? state.date : 'practice'} ${won() ? state.guesses.length : 'X'}/${MAX}\n${sq}\nhttps://craftyplayz.com/games/craftle/`, el.copy);
    };

    fetch('/games/craftle/recipes.json').then((r) => { if (!r.ok) throw 0; return r.json(); }).then((d) => { data = d; start(true); })
        .catch(() => { el.status.className = 'err'; el.status.textContent = 'Couldn’t load the recipes. Try again in a minute.'; });
});
