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
</head>

<body>
    <?php site_header(); ?>

    <main class="page steam" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title"><?= h($gametitle) ?></h1>
        <?php if (!empty($info['basic_info']['short_description'])): ?>
            <p class="page-lede"><?= h(html_entity_decode($info['basic_info']['short_description'], ENT_QUOTES)) ?></p>
        <?php endif; ?>
        <?php steam_nav(''); ?>

        <?php if ($game === ''): ?>
            <p class="err">No game picked. Choose one from the <a href="/games/steam/library/">Steam Library</a>.</p>
        <?php elseif (!$info && !$achievements && !$mine): ?>
            <p class="err">Couldn't load this game from Steam. Try again in a minute.</p>
        <?php endif; ?>

        <?php if ($info || $mine): ?>
            <section class="steam-head">
                <div class="steam-art"><img src="<?= h(steam_art($id, $items)) ?>" alt="" width="460" height="215" decoding="async"></div>
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
                        <span>Playing now</span><span><?= number_format($now['response']['player_count']) ?></span>
                    <?php endif; ?>
                    <span>Steam</span><span><a href="https://store.steampowered.com/app/<?= $id ?>">Store page</a></span>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($mine): ?>
            <section class="card section">
                <h2>My stats</h2>
                <div class="tiles">
                    <div class="tile"><b><?= $mine['playtime_forever'] ? steam_hours($mine['playtime_forever']) : '0 h' ?></b><span>Played</span></div>
                    <?php if (!empty($mine['playtime_2weeks'])): ?>
                        <div class="tile"><b><?= steam_hours($mine['playtime_2weeks']) ?></b><span>Past 2 weeks</span></div>
                    <?php endif; ?>
                    <?php if (!empty($mine['rtime_last_played'])): ?>
                        <div class="tile"><b><?= steam_time($mine['rtime_last_played']) ?></b><span>Last played</span></div>
                    <?php endif; ?>
                    <?php if ($first): ?>
                        <div class="tile"><b><?= date('M Y', $first) ?></b><span>First achievement</span></div>
                        <div class="tile"><b><?= steam_time($latest) ?></b><span>Latest achievement</span></div>
                    <?php endif; ?>
                    <?php foreach (['windows' => 'Windows', 'linux' => 'Linux', 'deck' => 'Steam Deck', 'mac' => 'macOS'] as $k => $label):
                        if (!empty($mine['playtime_' . $k . '_forever']) && $mine['playtime_' . $k . '_forever'] != $mine['playtime_forever']): ?>
                        <div class="tile"><b><?= steam_hours($mine['playtime_' . $k . '_forever']) ?></b><span>on <?= $label ?></span></div>
                    <?php endif; endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($achievements): ?>
            <section class="card section">
                <h2>Achievements <span class="n"><?= $done ?> / <?= count($achievements) ?></span></h2>
                <div class="progress section"><i style="width: <?= round($done / count($achievements) * 100) ?>%"></i></div>
                <div class="steam-tools">
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
                <div class="rows ach" id="ach">
                    <?php foreach ($achievements as $i => $a):
                        $got = $a['achieved'] == 1;
                        $meta = isset($schema[$a['apiname']]) ? $schema[$a['apiname']] : [];
                        $icon = $got ? (isset($meta['icon']) ? $meta['icon'] : '') : (isset($meta['icongray']) ? $meta['icongray'] : '');
                        $pct = isset($rarity[$a['apiname']]) ? $rarity[$a['apiname']] : null;
                        $hidden = !$got && !empty($meta['hidden']);
                    ?>
                        <div class="rowi<?= $got ? '' : ' locked' ?>" data-i="<?= $i ?>" data-got="<?= $got ? 1 : 0 ?>" data-t="<?= (int)$a['unlocktime'] ?>" data-p="<?= $pct === null ? -1 : $pct ?>">
                            <?php if ($icon): ?><img src="<?= h($icon) ?>" alt="" width="40" height="40" loading="lazy" decoding="async"><?php endif; ?>
                            <div class="grow">
                                <b><?= h($a['name']) ?></b>
                                <?php if ($hidden && $a['description'] !== ''): ?>
                                    <details><summary>Hidden achievement</summary><?= h($a['description']) ?></details>
                                <?php else: ?>
                                    <small><?= h($a['description']) ?></small>
                                <?php endif; ?>
                            </div>
                            <?= steam_rarity_badge($pct) ?>
                            <small><?= $got ? steam_time($a['unlocktime']) : 'Locked' ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($news): ?>
            <section class="card section steam-news">
                <h2>News</h2>
                <?php foreach ($news as $n): ?>
                    <details>
                        <summary><?= h($n['title']) ?><small><?= date('j M Y', $n['date']) ?></small></summary>
                        <p><?= h(steam_news_text($n['contents'])) ?> <a href="<?= h($n['url']) ?>">Read more</a></p>
                    </details>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>

    <?php site_footer(); ?>
    <script>
        (function () {
            var list = document.getElementById('ach');
            if (!list) return;
            var rows = [].slice.call(list.children), sort = document.getElementById('sort'), show = 'all';
            var by = {
                i: function (a, b) { return a.dataset.i - b.dataset.i; },
                rare: function (a, b) { return (+a.dataset.p < 0) - (+b.dataset.p < 0) || a.dataset.p - b.dataset.p; },
                common: function (a, b) { return b.dataset.p - a.dataset.p; },
                t: function (a, b) { return b.dataset.t - a.dataset.t || a.dataset.i - b.dataset.i; }
            };
            function run() {
                rows.sort(by[sort.value]).forEach(function (r) {
                    r.hidden = show != 'all' && r.dataset.got != show;
                    list.appendChild(r);
                });
            }
            document.getElementById('show').addEventListener('click', function (e) {
                var b = e.target.closest('button');
                if (!b) return;
                show = b.dataset.f;
                [].forEach.call(this.children, function (x) { x.classList.toggle('on', x == b); x.setAttribute('aria-pressed', x == b); });
                run();
            });
            sort.addEventListener('change', run);
        })();
    </script>
</body>

</html>
