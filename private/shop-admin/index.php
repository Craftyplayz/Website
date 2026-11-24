<?php
// Castle Crafty shop admin (/shop/admin/ → here via the root .htaccess, so /private/'s password applies).
// Everything is rendered by admin.js from /shop/admin/data (data.php).
$site_page = ['title' => 'Shop Admin', 'components' => true, 'ui' => true, 'css' => ['/assets/css/pages/shop-admin.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
header('X-Robots-Tag: noindex, nofollow');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>

    <main class="page sa" id="main">
        <h1 class="page-title">Castle Crafty admin</h1>
        <p class="page-lede" id="sa-lede">Takings, stock, chests and alerts from the shop's chest log.</p>
        <p class="note" id="sa-status">Loading the shop data...</p>

        <div id="sa-body" hidden>
            <div class="stat-row" id="sa-stats"></div>
            <nav class="jump"><a href="#sa-takings">Takings</a><a href="#sa-alerts">Alerts</a><a href="#sa-prices">Prices</a><a href="#sa-stock">Stock</a><a href="#sa-customers">Customers</a><a href="#sa-chests">Chests</a></nav>

            <h2 class="sec-h" id="sa-takings">Takings <span class="n">last 90 days</span></h2>
            <section class="card" id="sa-chart"></section>

            <h2 class="sec-h" id="sa-alerts">Alerts <span class="n" id="sa-alerts-n"></span></h2>
            <section class="card"><div class="rows" id="sa-events"></div></section>

            <h2 class="sec-h" id="sa-prices">Price suggestions <span class="n" id="sa-prices-n"></span></h2>
            <section class="card" id="sa-suggest"></section>

            <h2 class="sec-h" id="sa-stock">Stock</h2>
            <div class="bar"><input class="field" type="search" id="sa-q" placeholder="Search items" aria-label="Search items">
                <label class="check"><input type="checkbox" id="sa-listed" checked> Shop items only</label></div>
            <div class="table-wrap tall"><table class="table" id="sa-stock-table"></table></div>

            <h2 class="sec-h" id="sa-customers">Customers <span class="n" id="sa-customers-n"></span></h2>
            <section class="card" id="sa-cust"></section>

            <h2 class="sec-h" id="sa-chests">Chests <span class="n" id="sa-chests-n"></span></h2>
            <div class="bar"><label class="check"><input type="checkbox" id="sa-empty"> Show empty chests</label>
                <span class="muted">Red border: holds another item's stock.</span></div>
            <div id="sa-map"></div>
        </div>

        <aside class="drawer" id="sa-drawer" aria-label="Item details" aria-hidden="true" tabindex="-1">
            <button class="close" aria-label="Close" title="Close">&times;</button>
            <div id="sa-detail"></div>
        </aside>
    </main>

    <?php site_footer(null, false); ?>
    <script src="<?= asset('/private/shop-admin/admin.js') ?>" defer></script>
</body>

</html>
