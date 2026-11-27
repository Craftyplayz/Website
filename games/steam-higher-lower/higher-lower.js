// Higher or Lower: Steam Hours. Data is window.SHL = [{n name, id, m minutes, a art, b fallback art}].
document.addEventListener('DOMContentLoaded', function () {
    var $ = UI.$, all = window.SHL, deck = [], left, right, streak = 0, state = 'guess';
    var best = +UI.store.get('shl-best', 0) || 0;

    function hours(m) {
        var h = m / 60;
        return h < 100 ? h.toFixed(1).replace(/\.0$/, '') + ' h' : UI.num(Math.round(h)) + ' h';
    }
    function draw() {
        if (!deck.length) {
            deck = all.slice();
            for (var i = deck.length - 1; i > 0; i--) { var j = Math.floor(Math.random() * (i + 1)); var t = deck[i]; deck[i] = deck[j]; deck[j] = t; }
        }
        return deck.pop();
    }
    function card(el, g, show) {
        el.innerHTML = '<img src="' + UI.esc(g.a) + '" data-alt="' + UI.esc(g.b) + '" alt="" width="460" height="215" decoding="async">' +
            '<div class="shl-text"><b>' + UI.esc(g.n) + '</b><span' + (show ? ' title="' + UI.esc(Math.floor(g.m / 60) + ' h ' + g.m % 60 + ' min') + '"' : '') + '>' +
            (show ? hours(g.m) + ' played' : 'How many hours?') + '</span></div>';
    }
    function fresh(el) {
        el.classList.remove('shl-in');
        void el.offsetWidth;
        el.classList.add('shl-in');
    }
    function score() { $('#streak').textContent = streak; $('#best').textContent = best; }
    function next() {
        left = right || draw();
        do { right = draw(); } while (right.id === left.id);
        card($('#left'), left, true);
        card($('#right'), right, false);
        fresh($('#right'));
        $('#guess').hidden = false;
        $('#over').hidden = true;
        state = 'guess';
    }
    function guess(higher) {
        if (state !== 'guess') return;
        state = 'wait';
        var ok = higher ? right.m >= left.m : right.m <= left.m;
        card($('#right'), right, true);
        $('#guess').hidden = true;
        if (ok) {
            streak++;
            if (streak > best) { best = streak; UI.store.set('shl-best', best); }
            score();
            setTimeout(next, 1100);
        } else {
            state = 'over';
            $('#msg').textContent = 'Wrong. ' + right.n + ' has ' + (right.m < left.m ? 'fewer' : 'more') + ' hours. Streak ' + streak + ', best ' + best + '.';
            $('#over').hidden = false;
            $('#again').focus();
        }
    }
    function restart() { streak = 0; right = null; score(); next(); }

    // Missing art: try the store header once, then hide the image so the name shows
    document.addEventListener('error', function (e) {
        var img = e.target;
        if (!img.closest || !img.closest('.shl-card')) return;
        if (img.dataset.alt && img.src !== img.dataset.alt) img.src = img.dataset.alt;
        else img.style.visibility = 'hidden';
    }, true);

    $('#guess').addEventListener('click', function (e) { var b = e.target.closest('button'); if (b) guess(b.dataset.g === 'h'); });
    $('#again').addEventListener('click', restart);
    document.addEventListener('keydown', function (e) {
        if (e.ctrlKey || e.metaKey || e.altKey || /input|select|textarea/i.test(e.target.tagName)) return;
        var k = e.key.toLowerCase();
        if (k === 'h' || k === 'arrowup') { e.preventDefault(); guess(true); }
        else if (k === 'l' || k === 'arrowdown') { e.preventDefault(); guess(false); }
        else if (k === 'enter' && state === 'over' && e.target.tagName !== 'BUTTON') { e.preventDefault(); restart(); }
    });
    restart();
});
