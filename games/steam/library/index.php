<?php
$site_page = ['config' => true, 'components' => true, 'css' => ['/assets/css/pages/games-steam.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
require dirname(__DIR__) . '/steam.php';

$games = steam_games();
$progress = steam_progress(steam_achievements($games));
$items = steam_items(array_keys($games));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <link rel="preconnect" href="https://shared.akamai.steamstatic.com">
</head>

<body>
    <?php site_header(); ?>

    <main class="page steam" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <?php steam_hero(['title' => 'Steam Library', 'lede' => 'Every game I own on Steam, with playtime and achievement progress.', 'current' => '/games/steam/library/']); ?>

        <?php if (!$games): ?>
            <p class="err">Couldn't load the game list from Steam. Try again in a minute.</p>
        <?php else: ?>
            <div class="bar steam-bar">
                <input class="field" id="q" type="search" placeholder="Search <?= count($games) ?> games" aria-label="Search games">
                <div class="stabs" id="show" role="group" aria-label="Show">
                    <button type="button" data-f="played" class="on" aria-pressed="true">Played</button>
                    <button type="button" data-f="all" aria-pressed="false">All</button>
                    <button type="button" data-f="unplayed" aria-pressed="false">Never played</button>
                    <button type="button" data-f="perfect" aria-pressed="false">100%</button>
                </div>
                <select class="field" id="sort" aria-label="Sort by">
                    <option value="min">Most played</option>
                    <option value="last">Recently played</option>
                    <option value="pct">Achievement %</option>
                    <option value="name">Name</option>
                </select>
            </div>
            <p class="steam-count" id="count" aria-live="polite"><?= count($games) ?> games</p>
            <div class="steam-caps" id="grid">
                <?php $i = 0; foreach ($games as $id => $g) steam_card($g, $items, isset($progress[$id]) ? $progress[$id] : null, $i++ > 5); ?>
            </div>
        <?php endif; ?>
    </main>

    <?php site_footer(null, false); ?>
    <script src="<?= h(asset('/games/steam/steam.js')) ?>" defer></script>
    <script>
        (function () {
            var grid = document.getElementById('grid');
            if (!grid) return;
            var cards = [].slice.call(grid.children), q = document.getElementById('q'), sort = document.getElementById('sort'),
                count = document.getElementById('count'), show = 'played';
            var tests = {
                played: function (d) { return d.min >= 60; },
                all: function () { return true; },
                unplayed: function (d) { return d.min == 0; },
                perfect: function (d) { return d.pct == 100; }
            };
            function run() {
                var term = q.value.trim().toLowerCase(), key = sort.value, n = 0;
                cards.sort(function (a, b) {
                    if (key == 'name') return a.dataset.name.localeCompare(b.dataset.name);
                    return b.dataset[key] - a.dataset[key] || b.dataset.min - a.dataset.min;
                });
                cards.forEach(function (c) {
                    var ok = tests[show](c.dataset) && (!term || c.dataset.name.indexOf(term) > -1);
                    c.hidden = !ok;
                    if (ok) n++;
                    grid.appendChild(c);
                });
                count.textContent = n + (n == 1 ? ' game' : ' games');
            }
            document.getElementById('show').addEventListener('click', function (e) {
                var b = e.target.closest('button');
                if (!b) return;
                show = b.dataset.f;
                [].forEach.call(this.children, function (x) { x.classList.toggle('on', x == b); x.setAttribute('aria-pressed', x == b); });
                run();
            });
            var t;
            q.addEventListener('input', function () { clearTimeout(t); t = setTimeout(run, 80); });
            sort.addEventListener('change', run);
            run();
        })();
    </script>
</body>

</html>
