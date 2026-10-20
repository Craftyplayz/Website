<?php
$site_page = ['config' => true, 'components' => true, 'css' => ['/assets/css/pages/games-steam.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
require dirname(__DIR__) . '/steam.php';
// appid goes into API URLs, so digits only
$game = substr(preg_replace('/\D/', '', isset($_GET['game']) ? $_GET['game'] : ''), 0, 10);
$id = (int)$game;

$items = $id ? steam_items([$id]) : [];
$info = isset($items[$id]) ? $items[$id] : null;
$games = $id ? steam_games() : [];
$mine = isset($games[$id]) ? $games[$id] : null;
$player = $id ? steam_json(steam_url('ISteamUserStats/GetPlayerAchievements/v1', ['steamid' => $steamid, 'appid' => $id, 'l' => 'english']), $mine && empty($mine['playtime_2weeks']) ? 86400 : 600) : null;
$achievements = isset($player['playerstats']['achievements']) ? $player['playerstats']['achievements'] : [];
$schema = $achievements ? steam_schema([$id])[$id] : [];
$rarity = $achievements ? steam_rarity([$id])[$id] : [];
$now = $id ? steam_api('ISteamUserStats/GetNumberOfCurrentPlayers/v1', ['appid' => $id], 300) : null;
$news = $id ? steam_api('ISteamNews/GetNewsForApp/v2', ['appid' => $id, 'count' => 3, 'maxlength' => 400], 3600) : null;
$news = isset($news['appnews']['newsitems']) ? $news['appnews']['newsitems'] : [];

$gametitle = $info ? $info['name'] : ($mine ? $mine['name'] : (isset($player['playerstats']['gameName']) ? $player['playerstats']['gameName'] : 'Steam Game'));
$site_page['title'] = $gametitle . ' Stats';

$done = $first = $latest = 0;
foreach ($achievements as $a) {
    if (!$a['achieved']) continue;
    $done++;
    $first = $first ? min($first, $a['unlocktime']) : $a['unlocktime'];
    $latest = max($latest, $a['unlocktime']);
}
$price = isset($info['best_purchase_option']) ? $info['best_purchase_option'] : null;
$reviews = isset($info['reviews']['summary_filtered']) ? $info['reviews']['summary_filtered'] : null;
$deck = isset($info['platforms']['steam_deck_compat_category']) ? $info['platforms']['steam_deck_compat_category'] : 0;

// News bodies are BBCode/HTML with Steam image placeholders; keep the plain text
function steam_news_text($s)
{
    $s = preg_replace(['~\{STEAM_CLAN_IMAGE\}\S*~', '~\[/?[a-z0-9*]+(=[^\]]*)?\]~i'], ' ', $s);
    return trim(preg_replace('~\s+~', ' ', html_entity_decode(strip_tags($s), ENT_QUOTES)));
}
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
        <?php steam_hero([
            'title' => $gametitle,
            'lede' => !empty($info['basic_info']['short_description']) ? h(html_entity_decode($info['basic_info']['short_description'], ENT_QUOTES)) : '',
            'bg' => $id && ($info || $mine) ? steam_art($id, $items, 'library_hero') : '',
        ]); ?>

        <?php if ($game === ''): ?>
            <p class="err">No game picked. Choose one from the <a href="/games/steam/library/">Steam Library</a>.</p>
        <?php elseif (!$info && !$achievements && !$mine): ?>
            <p class="err">Couldn't load this game from Steam. Try again in a minute.</p>
        <?php endif; ?>

        <?php if ($info || $mine): ?>
            <div class="steam-game-top section">
                <div class="cover-card steam-cap-static">
                    <img src="<?= h(steam_art($id, $items, 'library_capsule')) ?>" data-alt="<?= h(steam_art($id, $items)) ?>" alt="" width="300" height="450" decoding="async">
                    <?php if ($achievements): ?><?= steam_ring($done / count($achievements) * 100, $done . ' of ' . count($achievements) . ' achievements') ?><?php endif; ?>
                </div>

                <div class="steam-game-info">
                    <?php if ($mine): ?>
                        <div class="stat-row">
                            <div class="stat-card"><b><?= $mine['playtime_forever'] ? steam_hours($mine['playtime_forever']) : '0 h' ?></b><span>Played</span><?php if (!empty($mine['playtime_2weeks'])): ?><small><?= steam_hours($mine['playtime_2weeks']) ?> in 2 weeks</small><?php endif; ?></div>
                            <?php if (!empty($mine['rtime_last_played'])): ?>
                                <div class="stat-card"><b><?= steam_time($mine['rtime_last_played']) ?></b><span>Last played</span></div>
                            <?php endif; ?>
                            <?php if ($achievements): ?>
                                <div class="stat-card"><b><?= $done ?> / <?= count($achievements) ?></b><span>Achievements</span><?php if ($latest): ?><small>latest <?= mb_strtolower(steam_ago($latest)) ?></small><?php endif; ?></div>
                            <?php endif; ?>
                            <?php if ($first): ?>
                                <div class="stat-card"><b><?= date('M Y', $first) ?></b><span>First achievement</span></div>
                            <?php endif; ?>
                            <?php foreach (['windows' => 'Windows', 'linux' => 'Linux', 'deck' => 'Steam Deck', 'mac' => 'macOS'] as $k => $label):
                                if (!empty($mine['playtime_' . $k . '_forever']) && $mine['playtime_' . $k . '_forever'] != $mine['playtime_forever']): ?>
                                <div class="stat-card"><b><?= steam_hours($mine['playtime_' . $k . '_forever']) ?></b><span>on <?= $label ?></span></div>
                            <?php endif; endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <section class="card">
                        <div class="kv">
                            <?php if (!empty($info['basic_info']['developers'])): ?>
                                <span>Developer</span><span><?= h(implode(', ', array_column($info['basic_info']['developers'], 'name'))) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($info['basic_info']['publishers'])): ?>
                                <span>Publisher</span><span><?= h(implode(', ', array_column($info['basic_info']['publishers'], 'name'))) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($info['release']['steam_release_date'])): ?>
                                <span>Released</span><span><?= date('j M Y', $info['release']['steam_release_date']) ?></span>
                            <?php endif; ?>
                            <?php if ($price): ?>
                                <span>Price</span>
                                <span class="steam-price">
                                    <?php if (!empty($price['discount_pct'])): ?><span class="badge g">-<?= (int)$price['discount_pct'] ?>%</span><span class="steam-was"><?= h($price['formatted_original_price']) ?></span><?php endif; ?>
                                    <?= h($price['formatted_final_price']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($reviews && !empty($reviews['review_count'])): ?>
                                <span>Reviews</span><span><?= h($reviews['review_score_label']) ?> (<?= (int)$reviews['percent_positive'] ?>% of <?= number_format($reviews['review_count']) ?>)</span>
                            <?php endif; ?>
                            <?php if (isset(STEAM_DECK[$deck])): ?>
                                <span>Steam Deck</span><span><?= STEAM_DECK[$deck] ?></span>
                            <?php endif; ?>
                            <?php if (!empty($now['response']['player_count'])): ?>
                                <span>Playing now</span><span><span class="status-dot online"></span><?= number_format($now['response']['player_count']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($reviews && !empty($reviews['review_count'])): ?>
                            <div class="steam-reviews" title="<?= (int)$reviews['percent_positive'] ?>% positive"><i style="width: <?= (int)$reviews['percent_positive'] ?>%"></i></div>
                        <?php endif; ?>
                        <p class="steam-actions"><a class="btn primary" target="_blank" rel="noopener" href="https://store.steampowered.com/app/<?= $id ?>">Store page</a></p>
                    </section>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($achievements): ?>
            <h2 class="sec-h">Achievements <span class="n"><?= $done ?> of <?= count($achievements) ?> unlocked</span></h2>
            <div class="bar steam-bar">
                <input class="field" id="q" type="search" placeholder="Search <?= count($achievements) ?> achievements" aria-label="Search achievements">
                <div class="stabs" id="show" role="group" aria-label="Show">
                    <button type="button" data-f="all" class="on" aria-pressed="true">All</button>
                    <button type="button" data-f="1" aria-pressed="false">Unlocked</button>
                    <button type="button" data-f="0" aria-pressed="false">Locked</button>
                </div>
                <select class="field" id="sort" aria-label="Sort by">
                    <option value="i">Game order</option>
                    <option value="rare">Rarest first</option>
                    <option value="common">Most common first</option>
                    <option value="t">Recently unlocked</option>
                </select>
            </div>
            <div class="ach-grid section" id="ach">
                <?php foreach ($achievements as $i => $a):
                    $pct = isset($rarity[$a['apiname']]) ? $rarity[$a['apiname']] : null;
                    $meta = isset($schema[$a['apiname']]) ? $schema[$a['apiname']] : [];
                    $search = $a['achieved'] || empty($meta['hidden']) ? $a['name'] . ' ' . $a['description'] : $a['name'];
                    steam_ach_card($a, $meta, $pct, null, ' data-i="' . $i . '" data-got="' . ($a['achieved'] ? 1 : 0) . '" data-t="' . (int)$a['unlocktime'] . '" data-p="' . ($pct === null ? -1 : $pct) . '" data-q="' . h(mb_strtolower($search)) . '"');
                endforeach; ?>
            </div>
            <p class="steam-count" id="none" hidden>No achievements match.</p>
        <?php endif; ?>

        <?php if ($news): ?>
            <section class="card section steam-news">
                <h2>News</h2>
                <?php foreach ($news as $n): ?>
                    <details>
                        <summary><?= h($n['title']) ?><small><?= date('j M Y', $n['date']) ?></small></summary>
                        <p><?= h(steam_news_text($n['contents'])) ?> <a target="_blank" rel="noopener" href="<?= h($n['url']) ?>">Read more</a></p>
                    </details>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>

    <?php site_footer(null, false); ?>
    <script src="<?= h(asset('/games/steam/steam.js')) ?>" defer></script>
    <script>
        (function () {
            var list = document.getElementById('ach');
            if (!list) return;
            var rows = [].slice.call(list.children), sort = document.getElementById('sort'), q = document.getElementById('q'),
                none = document.getElementById('none'), show = 'all';
            var by = {
                i: function (a, b) { return a.dataset.i - b.dataset.i; },
                rare: function (a, b) { return (+a.dataset.p < 0) - (+b.dataset.p < 0) || a.dataset.p - b.dataset.p; },
                common: function (a, b) { return b.dataset.p - a.dataset.p; },
                t: function (a, b) { return b.dataset.t - a.dataset.t || a.dataset.i - b.dataset.i; }
            };
            function run() {
                var term = q.value.trim().toLowerCase(), n = 0;
                rows.sort(by[sort.value]).forEach(function (r) {
                    r.hidden = (show != 'all' && r.dataset.got != show) || (term && r.dataset.q.indexOf(term) < 0);
                    if (!r.hidden) n++;
                    list.appendChild(r);
                });
                none.hidden = n > 0;
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
        })();
    </script>
</body>

</html>
