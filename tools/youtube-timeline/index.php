<?php
$site_page = ['components' => true, 'css' => ['/assets/css/pages/tools-youtube-timeline.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <link rel="preconnect" href="https://i.ytimg.com">
</head>

<body>
    <?php site_header(); ?>

    <main class="page ytt" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">YouTube Playlist Timeline</h1>
        <p class="page-lede">Paste a playlist link to see when every video in it was uploaded.</p>

        <form class="form form-card" id="form">
            <label>Playlist link <input class="field" id="url" name="list" type="url" inputmode="url" placeholder="https://www.youtube.com/playlist?list=PL..." required><small id="hint"></small></label>
            <div class="actions"><button class="btn primary" id="go">Make timeline</button></div>
        </form>

        <p class="note" id="status" hidden></p>
        <div class="progress" id="bar" hidden><i></i></div>

        <section id="out" hidden>
            <h2 class="sec-h"><a id="title" target="_blank" rel="noopener"></a> <span class="n" id="by"></span></h2>
            <div class="stat-row" id="stats"></div>

            <h2 class="sec-h" id="chart-h"></h2>
            <div class="ytt-chart" id="chart"></div>

            <h2 class="sec-h">Timeline</h2>
            <div class="stabs" id="order"><button class="on" data-o="old" type="button">Oldest first</button><button data-o="new" type="button">Newest first</button></div>
            <div id="list"></div>
        </section>
    </main>

    <?php site_footer(null, false); ?>

    <script>
        (() => {
            const $ = id => document.getElementById(id);
            const esc = s => String(s ?? '').replace(/[&<>"']/g, c => '&#' + c.charCodeAt(0) + ';');
            const DAY = 864e5;
            const fmt = (d, o = { day: 'numeric', month: 'short', year: 'numeric' }) => d.toLocaleDateString('en-GB', o);
            const span = ms => {
                const d = Math.round(ms / DAY);
                if (d < 1) return 'same day';
                if (d < 60) return d + (d === 1 ? ' day' : ' days');
                if (d < 730) return Math.round(d / 30.44) + ' months';
                return (d / 365.25).toFixed(1).replace(/\.0$/, '') + ' years';
            };
            let dated = [], undated = [], order = 'old', run = 0;

            // accepts a playlist page link, a watch link with &list=, or a bare ID
            const listId = s => {
                s = s.trim();
                try { const u = new URL(s); s = u.searchParams.get('list') || ''; } catch { }
                return /^[A-Za-z0-9_-]{10,80}$/.test(s) ? s : '';
            };

            const status = (msg, cls = 'note') => { const el = $('status'); el.className = cls; el.textContent = msg; el.hidden = !msg; };

            async function load(id) {
                const me = ++run;
                $('out').hidden = true;
                $('go').disabled = true;
                $('go').textContent = 'Loading...';
                $('bar').hidden = false;
                $('bar').firstChild.style.width = '0';
                let head = null, videos = [], token = '';
                try {
                    for (let page = 0; page < 200 && token !== null; page++) { // 200 pages = YouTube's 10,000-video playlist cap
                        const r = await fetch('api.php?list=' + id + (token ? '&token=' + encodeURIComponent(token) : ''));
                        const d = await r.json().catch(() => ({ error: 'Couldn\'t reach YouTube. Try again in a minute.' }));
                        if (me !== run) return;
                        if (d.error) throw d.error;
                        head = head || d;
                        videos.push(...d.videos);
                        token = d.next || null;
                        status('Loading videos... ' + videos.length.toLocaleString() + ' of ' + head.count.toLocaleString());
                        $('bar').firstChild.style.width = Math.min(100, videos.length / Math.max(1, head.count) * 100) + '%';
                    }
                } catch (e) {
                    status(typeof e === 'string' ? e : 'Couldn\'t reach YouTube. Try again in a minute.', 'err');
                    return;
                } finally {
                    if (me === run) { $('go').disabled = false; $('go').textContent = 'Make timeline'; $('bar').hidden = true; }
                }
                status('');
                dated = videos.filter(v => v.date).map(v => ({ ...v, t: Date.parse(v.date) })).sort((a, b) => a.t - b.t);
                undated = videos.filter(v => !v.date);
                dated.forEach((v, i) => v.gap = i ? v.t - dated[i - 1].t : null);
                render(head, id);
            }

            function render(head, id) {
                $('title').textContent = head.title;
                $('title').href = 'https://www.youtube.com/playlist?list=' + id;
                $('by').textContent = head.channel ? 'by ' + head.channel : '';
                $('out').hidden = false;
                if (!dated.length) {
                    $('stats').innerHTML = $('chart').innerHTML = $('chart-h').textContent = '';
                    renderList();
                    return;
                }
                const first = dated[0], last = dated[dated.length - 1];
                const gaps = dated.slice(1).map(v => v.gap);
                const longest = gaps.length ? dated.reduce((a, v) => v.gap > (a.gap ?? -1) ? v : a) : null;
                const card = (b, label, title = '') => `<div class="stat-card" title="${esc(title)}"><b>${esc(b)}</b><span>${esc(label)}</span></div>`;
                $('stats').innerHTML = card(dated.length.toLocaleString(), dated.length === 1 ? 'video' : 'videos')
                    + card(fmt(new Date(first.t)), 'first upload', first.title)
                    + card(fmt(new Date(last.t)), 'latest upload', last.title)
                    + (gaps.length ? card(span(last.t - first.t), 'from first to latest')
                        + card(span((last.t - first.t) / gaps.length), 'average gap')
                        + card(span(longest.gap), 'longest gap', 'Before ' + longest.title) : '');

                // per month for short playlists, per year past 4 years
                const byYear = last.t - first.t > 4 * 365.25 * DAY;
                const key = t => { const d = new Date(t); return byYear ? d.getFullYear() : d.getFullYear() * 12 + d.getMonth(); };
                const counts = new Map();
                for (let k = key(first.t); k <= key(last.t); k++) counts.set(k, 0);
                dated.forEach(v => counts.set(key(v.t), counts.get(key(v.t)) + 1));
                const max = Math.max(...counts.values());
                const name = k => byYear ? String(k) : fmt(new Date(Math.floor(k / 12), k % 12), { month: 'short', year: 'numeric' });
                $('chart-h').textContent = byYear ? 'Uploads per year' : 'Uploads per month';
                $('chart').innerHTML = [...counts].map(([k, n]) => {
                    const label = `${name(k)}: ${n} ${n === 1 ? 'video' : 'videos'}`;
                    return `<div class="ytt-col" title="${label}" role="img" aria-label="${label}"><i style="height:${n / max * 100}%"></i><span>${byYear || k % 12 === 0 ? esc(byYear ? k : Math.floor(k / 12)) : ''}</span></div>`;
                }).join('');
                renderList();
            }

            function renderList() {
                const rows = order === 'old' ? dated : [...dated].reverse();
                let html = '', year = null;
                rows.forEach((v, i) => {
                    const d = new Date(v.t);
                    if (d.getFullYear() !== year) {
                        if (year !== null) html += '</ol>';
                        year = d.getFullYear();
                        const n = dated.filter(x => new Date(x.t).getFullYear() === year).length;
                        html += `<h3 class="ytt-year">${year} <span class="n">${n} ${n === 1 ? 'video' : 'videos'}</span></h3><ol class="ytt-line">`;
                    }
                    const gap = v.gap === null ? 'First video' : span(v.gap) + ' after the one before';
                    html += `<li><a class="ytt-row" href="https://www.youtube.com/watch?v=${esc(v.id)}" target="_blank" rel="noopener">`
                        + `<img src="https://i.ytimg.com/vi/${esc(v.id)}/mqdefault.jpg" alt="" width="160" height="90" ${i > 8 ? 'loading="lazy" ' : ''}decoding="async">`
                        + `<span class="grow"><b>${esc(v.title)}</b><small><time datetime="${esc(v.date)}" title="${esc(d.toLocaleString('en-GB'))}">${fmt(d, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })}</time> · ${gap}</small></span></a></li>`;
                });
                if (year !== null) html += '</ol>';
                if (undated.length) html += `<p class="muted">${undated.length} ${undated.length === 1 ? 'video has' : 'videos have'} no upload date because ${undated.length === 1 ? 'it\'s' : 'they\'re'} private or deleted.</p>`;
                $('list').innerHTML = html || '<p class="muted">This playlist has no videos.</p>';
                $('list').querySelectorAll('img').forEach(img => img.onerror = () => img.style.visibility = 'hidden');
            }

            $('order').addEventListener('click', e => {
                const b = e.target.closest('button');
                if (!b) return;
                order = b.dataset.o;
                $('order').querySelectorAll('button').forEach(x => x.classList.toggle('on', x === b));
                renderList();
            });

            $('form').addEventListener('submit', e => {
                e.preventDefault();
                const id = listId($('url').value);
                $('url').setAttribute('aria-invalid', id ? 'false' : 'true');
                $('hint').textContent = id ? '' : 'Paste a link with list= in it, like the one on a playlist page.';
                if (!id) return;
                history.replaceState(null, '', '?list=' + id);
                load(id);
            });

            const start = listId(location.href);
            if (start) { $('url').value = 'https://www.youtube.com/playlist?list=' + start; load(start); }
        })();
    </script>
</body>

</html>
