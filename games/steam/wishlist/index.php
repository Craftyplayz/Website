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
usort($sale, function ($a, $b) use ($items) {
    return $items[$b['appid']]['best_purchase_option']['discount_pct'] <=> $items[$a['appid']]['best_purchase_option']['discount_pct'];
});

function steam_wish_card($w, $items, $lazy)
{
    $id = (int)$w['appid'];
    $it = $items[$id];
    $p = isset($it['best_purchase_option']) ? $it['best_purchase_option'] : null;
    $rv = isset($it['reviews']['summary_filtered']) ? $it['reviews']['summary_filtered'] : null;
    $rel = isset($it['release']) ? $it['release'] : [];
    $ends = !empty($p['active_discounts'][0]['discount_end_date']) ? $p['active_discounts'][0]['discount_end_date'] : 0;
    ?>
    <article class="card steam-game">
        <a class="steam-art" target="_blank" rel="noopener" href="https://store.steampowered.com/app/<?= $id ?>" tabindex="-1" aria-hidden="true">
            <img src="<?= h(steam_art($id, $items)) ?>" alt="" width="460" height="215" <?= $lazy ? 'loading="lazy" ' : '' ?>decoding="async">
        </a>
        <h3><a target="_blank" rel="noopener" href="https://store.steampowered.com/app/<?= $id ?>"><?= h($it['name']) ?></a></h3>
        <div class="kv">
            <span>Price</span>
            <span class="steam-price">
                <?php if ($p): ?>
                    <?php if (!empty($p['discount_pct'])): ?><span class="badge g">-<?= (int)$p['discount_pct'] ?>%</span><span class="steam-was"><?= h($p['formatted_original_price']) ?></span><?php endif; ?>
                    <?= h(isset($p['formatted_final_price']) ? $p['formatted_final_price'] : '') ?>
                <?php elseif (!empty($rel['is_coming_soon'])): ?>
                    Not out yet
                <?php else: ?>
                    Not for sale
                <?php endif; ?>
            </span>
            <?php if ($ends): ?>
                <span>Sale ends</span><span><?= date('D j M', $ends) ?></span>
            <?php endif; ?>
            <?php if (!empty($rel['is_coming_soon'])): ?>
                <span>Release</span><span><?= !empty($rel['steam_release_date']) && isset($rel['coming_soon_display']) && $rel['coming_soon_display'] == 'date_full' ? date('j M Y', $rel['steam_release_date']) : h(isset($rel['custom_release_date_message']) ? $rel['custom_release_date_message'] : 'Coming soon') ?></span>
            <?php elseif (!empty($rel['steam_release_date'])): ?>
                <span>Released</span><span><?= date('Y', $rel['steam_release_date']) ?><?= !empty($rel['is_early_access']) ? ' (early access)' : '' ?></span>
            <?php endif; ?>
            <?php if ($rv && !empty($rv['review_count'])): ?>
                <span>Reviews</span><span><?= h($rv['review_score_label']) ?> (<?= (int)$rv['percent_positive'] ?>%)</span>
            <?php endif; ?>
            <span>Added</span><span><?= date('M Y', $w['date_added']) ?></span>
        </div>
    </article>
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
        <h1 class="page-title">Steam Wishlist</h1>
        <?php if ($wl): ?>
            <p class="page-lede"><?= count($wl) ?> games I'd like to play<?= $sale ? ', ' . count($sale) . ' on sale right now' : '' ?>. Prices in GBP.</p>
        <?php endif; ?>
        <?php steam_nav('/games/steam/wishlist/'); ?>

        <?php if (!$wl): ?>
            <p class="err">Couldn't load the wishlist from Steam. Try again in a minute.</p>
        <?php endif; ?>

        <?php if ($sale): ?>
            <h2 class="steam-h">On sale</h2>
            <div class="steam-grid section">
                <?php foreach ($sale as $i => $w) steam_wish_card($w, $items, $i > 5); ?>
            </div>
        <?php endif; ?>

        <?php if ($wl): ?>
            <h2 class="steam-h">Everything, in my order</h2>
            <div class="steam-grid section">
                <?php foreach ($wl as $w) steam_wish_card($w, $items, true); ?>
            </div>
        <?php endif; ?>
    </main>

    <?php site_footer(null, false); ?>
    <script>
        document.addEventListener('error', function (e) {
            if (e.target.closest && e.target.closest('.steam-art')) e.target.style.visibility = 'hidden';
        }, true);
    </script>
</body>

</html>
