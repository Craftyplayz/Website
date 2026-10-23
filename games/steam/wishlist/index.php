<?php
$site_page = ['config' => true, 'components' => true, 'css' => ['/assets/css/pages/games-steam.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
require dirname(__DIR__) . '/steam.php';

$wl = steam_api('IWishlistService/GetWishlist/v1', ['steamid' => $steamid], 3600);
$wl = isset($wl['response']['items']) ? $wl['response']['items'] : [];
// my ranking first; priority 0 means "not ranked", which Steam lists last
usort($wl, function ($a, $b) {
    return ($a['priority'] == 0) - ($b['priority'] == 0) ?: $a['priority'] - $b['priority'];
});
$items = steam_items(array_column($wl, 'appid'), 3 * 3600); // prices change, so a few hours at most
$wl = array_values(array_filter($wl, function ($w) use ($items) {
    return isset($items[$w['appid']]);
}));
$sale = array_values(array_filter($wl, function ($w) use ($items) {
    return !empty($items[$w['appid']]['best_purchase_option']['discount_pct']);
}));

function steam_wish_card($w, $items, $rank, $lazy)
{
    $id = (int)$w['appid'];
    $it = $items[$id];
    $p = isset($it['best_purchase_option']) ? $it['best_purchase_option'] : null;
    $rv = isset($it['reviews']['summary_filtered']) ? $it['reviews']['summary_filtered'] : null;
    $rel = isset($it['release']) ? $it['release'] : [];
    $ends = !empty($p['active_discounts'][0]['discount_end_date']) ? $p['active_discounts'][0]['discount_end_date'] : 0;
    $disc = !empty($p['discount_pct']) ? (int)$p['discount_pct'] : 0;
    $cents = $p && isset($p['final_price_in_cents']) ? (int)$p['final_price_in_cents'] : -1;
    $attrs = ' data-rank="' . $rank . '" data-name="' . h(mb_strtolower($it['name'])) . '" data-disc="' . $disc . '" data-price="' . $cents . '" data-rev="' . ($rv && !empty($rv['review_count']) ? (int)$rv['percent_positive'] : -1) . '" data-added="' . (int)$w['date_added'] . '" data-rel="' . (!empty($rel['steam_release_date']) ? (int)$rel['steam_release_date'] : 0) . '"';
    ?>
    <a class="steam-wish" target="_blank" rel="noopener" href="https://store.steampowered.com/app/<?= $id ?>"<?= $attrs ?>>
        <span class="steam-wish-art">
            <img src="<?= h(steam_art($id, $items)) ?>" alt="" width="460" height="215" <?= $lazy ? 'loading="lazy" ' : '' ?>decoding="async">
            <span class="steam-wish-rank" title="My ranking">#<?= $rank ?></span>
            <?php if ($disc): ?><span class="steam-wish-off">-<?= $disc ?>%</span><?php endif; ?>
        </span>
        <span class="steam-wish-body">
            <b><?= h($it['name']) ?></b>
            <span class="steam-wish-meta">
                <?php if (!empty($rel['is_coming_soon'])): ?>
                    <span class="badge b"><?= !empty($rel['steam_release_date']) && isset($rel['coming_soon_display']) && $rel['coming_soon_display'] == 'date_full' ? date('j M Y', $rel['steam_release_date']) : h(isset($rel['custom_release_date_message']) ? $rel['custom_release_date_message'] : 'Coming soon') ?></span>
                <?php elseif (!empty($rel['steam_release_date'])): ?>
                    <span class="badge"><?= date('Y', $rel['steam_release_date']) ?><?= !empty($rel['is_early_access']) ? ' · early access' : '' ?></span>
                <?php endif; ?>
                <?php if ($rv && !empty($rv['review_count'])): ?>
                    <span class="badge<?= $rv['percent_positive'] >= 80 ? ' g' : ($rv['percent_positive'] < 50 ? ' r' : '') ?>" title="<?= h($rv['review_score_label']) ?>, <?= number_format($rv['review_count']) ?> reviews"><?= (int)$rv['percent_positive'] ?>% positive</span>
                <?php endif; ?>
            </span>
            <span class="steam-wish-foot">
                <small class="muted">Added <?= date('M Y', $w['date_added']) ?><?= $ends ? ' · sale ends ' . date('D j M', $ends) : '' ?></small>
                <span class="steam-wish-price">
                    <?php if ($p): ?>
                        <?php if ($disc): ?><s class="steam-was"><?= h($p['formatted_original_price']) ?></s><?php endif; ?>
                        <b><?= h(isset($p['formatted_final_price']) ? $p['formatted_final_price'] : '') ?></b>
                    <?php else: ?>
                        <small class="muted"><?= !empty($rel['is_coming_soon']) ? 'Not out yet' : 'Not for sale' ?></small>
                    <?php endif; ?>
                </span>
            </span>
        </span>
    </a>
    <?php
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
        <?php steam_hero(['title' => 'Steam Wishlist', 'lede' => $wl ? count($wl) . ' games I\'d like to play' . ($sale ? ', ' . count($sale) . ' on sale right now' : '') . '. Prices in GBP.' : '', 'current' => '/games/steam/wishlist/']); ?>

        <?php if (!$wl): ?>
            <p class="err">Couldn't load the wishlist from Steam. Try again in a minute.</p>
        <?php else: ?>
            <div class="bar steam-bar">
                <input class="field" id="q" type="search" placeholder="Search <?= count($wl) ?> games" aria-label="Search the wishlist">
                <div class="stabs" id="show" role="group" aria-label="Show">
                    <button type="button" data-f="all" class="on" aria-pressed="true">All</button>
                    <?php if ($sale): ?><button type="button" data-f="sale" aria-pressed="false">On sale <span class="n"><?= count($sale) ?></span></button><?php endif; ?>
                </div>
                <select class="field" id="sort" aria-label="Sort by">
                    <option value="rank">My order</option>
                    <option value="disc">Biggest discount</option>
                    <option value="price">Cheapest</option>
                    <option value="rev">Best reviewed</option>
                    <option value="rel">Newest release</option>
                    <option value="added">Recently added</option>
                    <option value="name">Name</option>
                </select>
            </div>
            <p class="steam-count" id="count" aria-live="polite"><?= count($wl) ?> games</p>
            <div class="steam-wishes" id="grid">
                <?php foreach ($wl as $i => $w) steam_wish_card($w, $items, $i + 1, $i > 5); ?>
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
                count = document.getElementById('count'), show = 'all';
            // games without a price (unreleased, delisted) go last when sorting by price
            var by = {
                rank: function (a, b) { return a.dataset.rank - b.dataset.rank; },
                disc: function (a, b) { return b.dataset.disc - a.dataset.disc || a.dataset.rank - b.dataset.rank; },
                price: function (a, b) { return (a.dataset.price < 0) - (b.dataset.price < 0) || a.dataset.price - b.dataset.price; },
                rev: function (a, b) { return b.dataset.rev - a.dataset.rev; },
                rel: function (a, b) { return b.dataset.rel - a.dataset.rel; },
                added: function (a, b) { return b.dataset.added - a.dataset.added; },
                name: function (a, b) { return a.dataset.name.localeCompare(b.dataset.name); }
            };
            function run() {
                var term = q.value.trim().toLowerCase(), n = 0;
                cards.sort(by[sort.value]).forEach(function (c) {
                    c.hidden = (show == 'sale' && c.dataset.disc == 0) || (term && c.dataset.name.indexOf(term) < 0);
                    if (!c.hidden) n++;
                    grid.appendChild(c);
                });
                count.textContent = n + (n == 1 ? ' game' : ' games');
            }
            var tabs = document.getElementById('show');
            tabs.addEventListener('click', function (e) {
                var b = e.target.closest('button');
                if (!b) return;
                show = b.dataset.f;
                [].forEach.call(tabs.children, function (x) { x.classList.toggle('on', x == b); x.setAttribute('aria-pressed', x == b); });
                run();
            });
            var t;
            q.addEventListener('input', function () { clearTimeout(t); t = setTimeout(run, 80); });
            sort.addEventListener('change', run);
        })();
    </script>
</body>

</html>
