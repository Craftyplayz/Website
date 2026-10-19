// Books I've Read: series filter on the Read shelf, collapsible series on the Planned shelf,
// and a text tile for any cover that fails to load.
(() => {
    const read = document.getElementById('read');
    const tiles = [...read.querySelectorAll('.gallery-item')];
    const count = document.getElementById('read-n');

    function filter(f) {
        let n = 0;
        tiles.forEach(t => { t.hidden = !!f && !t.classList.contains(f); n += !t.hidden; });
        count.textContent = n.toLocaleString() + (n === 1 ? ' book' : ' books');
    }
    document.getElementById('read-f').addEventListener('click', e => {
        const b = e.target.closest('button');
        if (!b) return;
        for (const x of b.parentNode.children) x.classList.toggle('on', x === b);
        filter(b.dataset.f);
    });
    filter('');

    // Planned: each series shows its first book until that book is clicked.
    const planned = document.getElementById('planned');
    const groups = {};
    planned.querySelectorAll('[series]').forEach(t => (groups[t.getAttribute('series')] ??= []).push(t));
    for (const g of Object.values(groups)) {
        if (g.length < 2) continue;
        const more = document.createElement('span');
        more.className = 'more';
        g[0].append(more);
        g[0].tabIndex = 0;
        g[0].setAttribute('role', 'button');
        setOpen(g, false);
    }
    function setOpen(g, open) {
        g.slice(1).forEach(t => t.hidden = !open);
        g[0].setAttribute('aria-expanded', open);
        g[0].querySelector('.more').textContent = open ? '−' : '+' + (g.length - 1);
    }
    function toggle(t) {
        const g = groups[t.getAttribute('series')];
        if (g && g.length > 1) setOpen(g, g[1].hidden);
    }
    planned.addEventListener('click', e => {
        const t = e.target.closest('[series]');
        if (t) toggle(t);
    });
    planned.addEventListener('keydown', e => {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('[role=button]')) {
            e.preventDefault();
            toggle(e.target);
        }
    });

    function missing(img) {
        const s = document.createElement('span');
        s.className = img.className + ' missing';
        s.textContent = img.alt;
        img.replaceWith(s);
    }
    document.addEventListener('error', e => { if (e.target.matches?.('img.gallery-image')) missing(e.target); }, true);
    document.querySelectorAll('img.gallery-image').forEach(i => { if (i.complete && !i.naturalWidth) missing(i); });
})();
