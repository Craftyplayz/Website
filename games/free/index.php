<?php
$site_page = ['components' => true, 'css' => ['/assets/css/pages/games-free.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <link rel="preconnect" href="https://www.freetogame.com">
</head>

<body>
    <?php site_header(); ?>

    <main class="page" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Free Games</h1>
        <p class="page-lede">Free-to-play PC and browser games. Data and images from <a href="https://www.freetogame.com" target="_blank" rel="noopener">FreeToGame</a>.</p>

        <div class="bar">
            <input class="field grow" id="q" type="search" placeholder="Search titles, publishers..." aria-label="Search">
            <select class="field" id="platform" aria-label="Platform">
                <option value="">All platforms</option>
                <option value="PC (Windows)">PC</option>
                <option value="Web Browser">Browser</option>
            </select>
            <select class="field" id="genre" aria-label="Genre"><option value="">All genres</option></select>
            <select class="field" id="sort" aria-label="Sort">
                <option value="popularity">Most popular</option>
                <option value="release">Newest</option>
                <option value="title">A to Z</option>
            </select>
            <span class="muted" id="count"></span>
        </div>

        <p class="note" id="status">Loading games...</p>
        <div class="ftg-grid" id="grid"></div>
    </main>

    <aside class="drawer" id="drawer" aria-label="Game details" tabindex="-1">
        <button class="close" id="close" aria-label="Close">&times;</button>
        <div id="detail"></div>
    </aside>

    <?php site_footer(null, false); ?>

    <script>
        (() => {
            const API = 'https://www.freetogame.com/api/';
            const $ = id => document.getElementById(id);
            const esc = s => String(s ?? '').replace(/[&<>"']/g, c => '&#' + c.charCodeAt(0) + ';');
            const date = s => /^\d{4}-\d\d-\d\d$/.test(s) ? new Date(s + 'T00:00').toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : (s || 'Unknown');
            let games = [];

            // popularity order comes from the API; the rank is kept so the other sorts can fall back to it
            fetch(API + 'games?sort-by=popularity').then(r => r.ok ? r.json() : Promise.reject(r.status)).then(list => {
                games = list.map((g, i) => ({ ...g, rank: i, hay: (g.title + ' ' + g.publisher + ' ' + g.developer + ' ' + g.short_description).toLowerCase() }));
                [...new Set(games.map(g => g.genre.trim()))].sort().forEach(g => $('genre').add(new Option(g, g)));
                $('status').remove();
                render();
            }).catch(() => {
                $('status').className = 'err';
                $('status').textContent = "Couldn't reach FreeToGame. Try again in a minute.";
            });

            function render() {
                const q = $('q').value.trim().toLowerCase(), p = $('platform').value, ge = $('genre').value, s = $('sort').value;
                const list = games.filter(g => (!q || g.hay.includes(q)) && (!p || g.platform.includes(p)) && (!ge || g.genre.trim() === ge));
                if (s === 'title') list.sort((a, b) => a.title.localeCompare(b.title));
                else if (s === 'release') list.sort((a, b) => b.release_date.localeCompare(a.release_date) || a.rank - b.rank);
                else list.sort((a, b) => a.rank - b.rank);
                $('count').textContent = list.length + ' of ' + games.length;
                $('grid').innerHTML = list.length ? list.map((g, i) => `
                    <button class="card ftg-game" data-id="${+g.id}">
                        <img src="${esc(g.thumbnail)}" alt="" width="365" height="206" ${i > 8 ? 'loading="lazy" ' : ''}decoding="async">
                        <b>${esc(g.title)}</b>
                        <span class="muted">${esc(g.short_description)}</span>
                        <span class="badges"><span class="badge">${esc(g.genre.trim())}</span><span class="badge b">${g.platform.includes('Browser') ? 'Browser' : 'PC'}</span></span>
                    </button>`).join('') : '<p class="muted">No games match.</p>';
            }

            function open(id) {
                const g = games.find(x => x.id === id);
                $('detail').innerHTML = `<h2>${esc(g.title)}</h2><p class="muted">Loading details...</p>`;
                $('drawer').classList.add('open');
                $('drawer').focus();
                fetch(API + 'game?id=' + id).then(r => r.ok ? r.json() : Promise.reject(r.status)).then(d => {
                    if (d.id !== id) return; // another game was opened meanwhile
                    const req = d.minimum_system_requirements;
                    const reqRows = req ? [['OS', req.os], ['CPU', req.processor], ['Memory', req.memory], ['Graphics', req.graphics], ['Storage', req.storage]]
                        .filter(r => r[1]).map(r => `<span>${r[0]}</span><span>${esc(r[1])}</span>`).join('') : '';
                    $('detail').innerHTML = `
                        <h2>${esc(d.title)}</h2>
                        <img class="ftg-hero" src="${esc(d.thumbnail)}" alt="" width="365" height="206">
                        <p><a class="btn primary" href="${esc(d.game_url)}" target="_blank" rel="noopener">Play for free</a>
                           <a class="btn" href="${esc(d.freetogame_profile_url)}" target="_blank" rel="noopener">FreeToGame page</a></p>
                        <div class="kv">
                            <span>Genre</span><span>${esc(d.genre)}</span>
                            <span>Platform</span><span>${esc(d.platform)}</span>
                            <span>Status</span><span>${esc(d.status)}</span>
                            <span>Developer</span><span>${esc(d.developer)}</span>
                            <span>Publisher</span><span>${esc(d.publisher)}</span>
                            <span>Released</span><span>${esc(date(d.release_date))}</span>
                        </div>
                        ${(d.description || '').split(/\r?\n\s*\n/).map(p => `<p>${esc(p)}</p>`).join('')}
                        ${reqRows ? `<h3>Minimum requirements</h3><div class="kv">${reqRows}</div>` : ''}
                        ${(d.screenshots || []).length ? `<h3>Screenshots</h3><div class="ftg-shots">${d.screenshots.map(x => `<a href="${esc(x.image)}" target="_blank" rel="noopener"><img src="${esc(x.image)}" alt="Screenshot" loading="lazy"></a>`).join('')}</div>` : ''}`;
                }).catch(() => {
                    $('detail').innerHTML = `<h2>${esc(g.title)}</h2><p class="err">Couldn't load the details.</p>`;
                });
            }

            const close = () => $('drawer').classList.remove('open');
            ['q', 'platform', 'genre', 'sort'].forEach(id => $(id).addEventListener('input', render));
            $('grid').addEventListener('click', e => { const b = e.target.closest('[data-id]'); if (b) open(+b.dataset.id); });
            $('close').addEventListener('click', close);
            document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
        })();
    </script>
</body>

</html>
