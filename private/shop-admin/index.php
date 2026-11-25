<?php
// Castle Crafty shop admin (/shop/admin/ → here via the root .htaccess, so /private/'s password applies).
// Standalone like /shop/ (same stone-and-gold look), laid out as an admin tool. admin.js renders everything
// from /shop/admin/data (data.php).
header('X-Robots-Tag: noindex, nofollow');
$v = function ($f) { return $f . '?v=' . @filemtime(__DIR__ . '/' . $f); };
?>
<!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Admin · Castle Crafty</title>
<link rel="icon" href="/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/private/shop-admin/<?= $v('admin.css') ?>">
</head>
<body>
<a class="skip" href="#main">Skip to the content</a>
<div class="shell">
  <aside class="side">
    <a class="brand" href="/shop/" title="Open the shop">Castle Crafty<small>Admin</small></a>
    <nav class="nav" id="nav" aria-label="Views">
      <a href="#overview" data-view="overview">Overview</a>
      <a href="#log" data-view="log">Log</a>
      <a href="#alerts" data-view="alerts">Alerts <span class="count" id="n-alerts"></span></a>
      <a href="#stock" data-view="stock">Stock <span class="count" id="n-low"></span></a>
      <a href="#chests" data-view="chests">Chests <span class="count" id="n-wrong"></span></a>
      <a href="#customers" data-view="customers">Customers</a>
      <a href="#prices" data-view="prices">Prices <span class="count" id="n-prices"></span></a>
    </nav>
    <p class="sidefoot" id="updated"></p>
  </aside>

  <main class="main" id="main" tabindex="-1">
    <header class="top">
      <h1 id="title">Overview</h1>
      <div class="kpis" id="kpis"></div>
    </header>
    <p class="status" id="status" role="status">Loading the shop data…</p>
    <div class="stale" id="stale" role="alert" hidden></div>

    <section class="view" data-view="overview" hidden>
      <div class="cards">
        <article class="card wide"><h2>Takings <small>last 90 days</small></h2><div id="chart"></div></article>
        <article class="card"><h2>Needs attention</h2><div id="attention"></div></article>
        <article class="card"><h2>Latest activity <a class="more" href="#log">Full log</a></h2><div id="latest"></div></article>
        <article class="card"><h2>Best sellers <small>14 days</small></h2><div id="best"></div></article>
        <article class="card"><h2>Price suggestions <a class="more" href="#prices">All</a></h2><div id="ov-prices"></div></article>
      </div>
    </section>

    <section class="view" data-view="log" hidden>
      <div class="bar">
        <select id="month" aria-label="Month"></select>
        <div class="chips" id="kinds" role="group" aria-label="Show"></div>
        <input type="search" id="lq" placeholder="Player or item" aria-label="Filter by player or item">
        <span class="dim" id="log-n"></span>
      </div>
      <div class="table-wrap"><table class="grid-t" id="log-t"></table></div>
      <p><button class="btn" id="more" hidden>Show more</button></p>
    </section>

    <section class="view" data-view="alerts" hidden>
      <p class="hint">The last 100 alerts. Every alert is also kept for good in the log.</p>
      <div class="table-wrap"><table class="grid-t" id="alerts-t"></table></div>
    </section>

    <section class="view" data-view="stock" hidden>
      <div class="bar">
        <input type="search" id="sq" placeholder="Search items" aria-label="Search items">
        <div class="chips" id="sfilter" role="group" aria-label="Show"></div>
        <span class="dim" id="stock-n"></span>
      </div>
      <div class="table-wrap"><table class="grid-t sortable" id="stock-t"></table></div>
    </section>

    <section class="view" data-view="chests" hidden>
      <div class="bar">
        <div class="chips" id="cfilter" role="group" aria-label="Show"></div>
        <span class="dim" id="chests-n"></span>
      </div>
      <p class="hint">One row per height, highest first. Red: holds another item's stock. Click a chest for what's in it.</p>
      <div id="map"></div>
    </section>

    <section class="view" data-view="customers" hidden>
      <div class="table-wrap"><table class="grid-t sortable" id="cust-t"></table></div>
    </section>

    <section class="view" data-view="prices" hidden>
      <p class="hint">From the last 14 days of sales: raise prices on items that sell out fast, lower them on items that don't move.</p>
      <div class="table-wrap"><table class="grid-t" id="prices-t"></table></div>
    </section>
  </main>

  <aside class="panel" id="panel" aria-label="Details" hidden tabindex="-1">
    <button class="x" id="panel-x" aria-label="Close details" title="Close (Esc)">&times;</button>
    <div id="panel-body"></div>
  </aside>
</div>
<script src="/assets/js/ui.js?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/js/ui.js') ?>"></script>
<script src="/private/shop-admin/<?= $v('admin.js') ?>"></script>
</body>
</html>
