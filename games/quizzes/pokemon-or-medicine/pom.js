// Pokémon or Medicine (/games/quizzes/pokemon-or-medicine/)
document.addEventListener('DOMContentLoaded', () => {
    const { $, esc, store } = UI;
    const ROUND = 20;
    const pool = window.POM_POOL;
    const poke = pool.filter((x) => typeof x[1] === 'number');
    const meds = pool.filter((x) => typeof x[1] !== 'number');
    const rand = (a) => a[Math.floor(Math.random() * a.length)];
    const isPoke = (x) => typeof x[1] === 'number';
    const what = (x) => (isPoke(x) ? 'a Pokémon (#' + x[1] + ')' : x[1] === 'b' ? 'a medicine (brand name)' : 'a medicine (generic name)');

    let round, i, score, missed, answered;

    function start() {
        // Each name is a coin flip between the two lists, with no repeats in a round
        const seen = new Set();
        round = [];
        while (round.length < ROUND) {
            const x = rand(Math.random() < 0.5 ? poke : meds);
            if (!seen.has(x[0])) { seen.add(x[0]); round.push(x); }
        }
        i = 0; score = 0; missed = [];
        $('#pom-end').hidden = true;
        $('#pom-play').hidden = false;
        show();
    }

    function show() {
        const x = round[i];
        answered = false;
        $('#pom-name').textContent = x[0];
        $('#pom-round').textContent = 'Name ' + (i + 1) + ' of ' + ROUND;
        $('#pom-score').textContent = score + ' correct';
        $('#pom-reveal').hidden = true;
        document.querySelectorAll('[data-pick]').forEach((b) => { b.disabled = false; b.classList.remove('pom-right', 'pom-wrong'); });
    }

    function pick(p) {
        if (answered) return;
        answered = true;
        const x = round[i];
        const right = (p === 'p') === isPoke(x);
        if (right) score++; else missed.push(x);
        document.querySelectorAll('[data-pick]').forEach((b) => {
            b.disabled = true;
            if (b.dataset.pick === p) b.classList.add(right ? 'pom-right' : 'pom-wrong');
        });
        const spr = $('#pom-spr');
        spr.hidden = !isPoke(x);
        if (isPoke(x)) {
            const n = x[1] - 1;
            spr.style.backgroundPosition = '-' + (n % 32) * 64 + 'px -' + Math.floor(n / 32) * 64 + 'px';
        }
        $('#pom-verdict').innerHTML = '<b>' + (right ? 'Right.' : 'Wrong.') + '</b> ' + esc(x[0]) + ' is ' + esc(what(x)) + '.';
        $('#pom-score').textContent = score + ' correct';
        $('#pom-next').textContent = i + 1 < ROUND ? 'Next name' : 'See results';
        $('#pom-reveal').hidden = false;
        $('#pom-next').focus();
    }

    function next() {
        if (!answered) return;
        if (++i < ROUND) return show();
        const best = Math.max(store.get('pom-best', 0), score);
        store.set('pom-best', best);
        $('#pom-play').hidden = true;
        $('#pom-end').hidden = false;
        $('#pom-final').textContent = score + ' / ' + ROUND;
        $('#pom-best').textContent = best + ' / ' + ROUND;
        $('#pom-round').textContent = 'Round over';
        $('#pom-msg').textContent = score === ROUND ? 'Every one right.' : score >= 15 ? 'Good round.' : score >= 10 ? 'About as good as guessing, but not bad.' : 'Worse than guessing.';
        $('#pom-score').textContent = '';
        $('#pom-missed').innerHTML = (missed.length ? '<p class="muted">The ones you got wrong</p>' : '') + missed.map((x) => '<div class="rowi"><span class="grow">' + esc(x[0]) + '</span><small>' + esc(what(x)) + '</small></div>').join('');
        $('#pom-again').focus();
    }

    $('.pom-pick').addEventListener('click', (e) => { const b = e.target.closest('[data-pick]'); if (b) pick(b.dataset.pick); });
    $('#pom-next').addEventListener('click', next);
    $('#pom-again').addEventListener('click', start);
    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey || e.metaKey || e.altKey || e.target.closest('input, textarea, select')) return;
        const k = e.key.toLowerCase();
        if (k === 'p' || k === 'm') pick(k);
        else if (k === 'enter' && answered && !$('#pom-play').hidden && e.target.id !== 'pom-next') { e.preventDefault(); next(); }
    });

    start();
});
