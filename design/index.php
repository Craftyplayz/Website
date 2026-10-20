<?php
// Design guide: how every page on the site looks and is built, with live samples.
// The samples use the real global.css + components.css and the swatches are read from global.css,
// so this page shows exactly what a page gets. The full build guide (data/APIs, testing, parallel
// sessions) is _dev/handoffs/building-pages.md: when a rule changes, change it in both.
$site_page = [
    'title' => 'Design Guide',
    'description' => 'How every page on craftyplayz.com looks and is built: tokens, components, layout and rules',
    'components' => true,
    'ui' => true,
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';

// colour tokens straight from the :root block in global.css
$css = (string) @file_get_contents(SITE_ROOT . '/assets/css/global.css');
preg_match('/:root\s*\{(.*?)\}/s', $css, $root);
preg_match_all('/--([\w-]+):\s*(#[0-9a-f]{3,8})\s*;/i', isset($root[1]) ? $root[1] : '', $tokens, PREG_SET_ORDER);
$uses = [
    'bg' => 'Page background', 'card' => 'Cards', 'card2' => 'Rows, tiles and nested cards inside a card',
    'sunken' => 'Drawer, footer, code and raw blocks', 'line' => 'Borders', 'line-strong' => 'Button borders, drop-zone dashes',
    'text' => 'Body text', 'muted' => 'Secondary text, meta, counts. Nothing darker is ever text',
    'accent' => 'Focus ring, active tab, progress. "Act" or "done" only', 'accent-soft' => 'Selected background (tab on, picked row)',
    'btn' => 'Primary button', 'btn-line' => 'Primary button border', 'btn2' => 'Secondary button', 'btn2-hover' => 'Secondary button hover',
    'link' => 'Links in content', 'highlight' => 'Search hits, "new"', 'danger-bg' => 'Error background', 'danger-line' => 'Error border',
    'info-bg' => 'Note background', 'info-line' => 'Note border',
];

// [title, when to use it, markup]. The markup is rendered live and shown as code, so the two always match.
$samples = [
    'buttons' => ['Buttons', 'One <code>.primary</code> per view. Everything else is a plain <code>.btn</code> or a link.', <<<'HTML'
<button class="btn primary">Save</button>
<button class="btn">Cancel</button>
<a class="btn small" href="#components">Small link</a>
<button class="btn" disabled>Working...</button>
HTML],
    'cards' => ['Cards in a grid', 'Every block of content is a <code>.card</code>. <code>.grid</code> picks its own column count; use <code>.masonry</code> for mixed heights. The count sits in the heading.', <<<'HTML'
<div class="grid">
  <section class="card">
    <h2>Inventory <span class="n">36 stacks · 1,204 items</span></h2>
    <p class="muted">Card content.</p>
  </section>
  <section class="card">
    <h2>Stats</h2>
    <p class="muted">Card content.</p>
  </section>
</div>
HTML],
    'tiles' => ['Tiles', 'Headline numbers.', <<<'HTML'
<div class="tiles">
  <div class="tile"><b>1,204</b><span>items</span></div>
  <div class="tile"><b>47h</b><span>played</span></div>
  <div class="tile"><b>12</b><span>deaths</span></div>
</div>
HTML],
    'badges' => ['Badges and pills', 'Status labels: <code>g</code> green, <code>r</code> red, <code>p</code> purple, <code>b</code> blue. Tinted, never bright fills.', <<<'HTML'
<div class="badges">
  <span class="badge">Survival</span>
  <span class="badge g">Online</span>
  <span class="badge r">Hardcore</span>
  <span class="badge p">Op</span>
  <span class="badge b">Flying</span>
  <span class="pill">1.21</span>
</div>
HTML],
    'rows' => ['Row list', 'A list with an icon, a name and a value.', <<<'HTML'
<div class="rows">
  <div class="rowi">
    <img src="/minecraft/tools/item-list/minecraft_diamond_sword.png" alt="" width="24" height="24">
    <span class="grow">Diamond Sword</span><small>×1</small>
  </div>
  <div class="rowi">
    <img src="/minecraft/tools/item-list/minecraft_oak_planks.png" alt="" width="24" height="24">
    <span class="grow">Oak Planks</span><small>×64</small>
  </div>
</div>
HTML],
    'kv' => ['Key / value', 'Detail facts.', <<<'HTML'
<div class="kv">
  <span>Version</span><span>1.21.4</span>
  <span>Players</span><span>3 / 20</span>
  <span>Seed</span><span class="mono">hidden</span>
</div>
HTML],
    'table' => ['Table', 'Always inside <code>.table-wrap</code> so only the table scrolls on a phone. Numbers get <code>.num</code> (right-aligned). Add <code>.tall</code> to the wrap for long tables: it caps the height and keeps the header in view.', <<<'HTML'
<div class="table-wrap">
  <table class="table">
    <thead><tr><th>Item</th><th class="num">Count</th><th class="num">Shulker boxes</th></tr></thead>
    <tbody>
      <tr><td>Oak Planks</td><td class="num">1,728</td><td class="num">1</td></tr>
      <tr><td>Glass</td><td class="num">3,456</td><td class="num">2</td></tr>
      <tr><td>Redstone Dust</td><td class="num">96</td><td class="num">1</td></tr>
    </tbody>
  </table>
</div>
HTML],
    'form' => ['Form', 'Each <code>&lt;label&gt;</code> wraps its text and its field. <code>.cols</code> puts fields side by side and stacks them on phones. The rules are under Forms below.', <<<'HTML'
<form class="form form-card">
  <label>Player name
    <input class="field" name="player" autocomplete="off" placeholder="Steve">
  </label>
  <div class="cols">
    <label>Dimension
      <select class="field" name="dimension">
        <option>Overworld</option>
        <option>Nether</option>
      </select>
    </label>
    <label>Amount
      <input class="field" name="amount" type="number" inputmode="numeric" min="1" max="64" value="65" aria-invalid="true">
      <small>Enter a number from 1 to 64.</small>
    </label>
  </div>
  <label>Notes (optional)
    <textarea class="field" name="notes" rows="3"></textarea>
  </label>
  <fieldset>
    <legend>Show</legend>
    <label class="check"><input type="radio" name="show" checked> All items</label>
    <label class="check"><input type="radio" name="show"> Only missing items</label>
  </fieldset>
  <label class="check"><input type="checkbox" name="remember" checked> Remember these settings</label>
  <div class="actions">
    <button class="btn primary">Save settings</button>
    <button class="btn" type="reset">Reset</button>
  </div>
</form>
HTML],
    'progress' => ['Progress', 'Completion bars. The width is the one inline style that is fine.', <<<'HTML'
<div class="progress"><i style="width:64%"></i></div>
HTML],
    'jump' => ['Jump links', 'Long pages only, and only for sections that exist.', <<<'HTML'
<nav class="jump">
  <a href="#colour">Colour</a>
  <a href="#components">Components</a>
  <a href="#checklist">Checklist</a>
</nav>
HTML],
    'stabs' => ['Pill tabs', 'Switch panels inside a card. The active one gets <code>.on</code>.', <<<'HTML'
<div class="stabs">
  <button class="on">Recent</button>
  <button>Rarest</button>
  <button>All</button>
</div>
HTML],
    'bar' => ['Toolbar and field', 'Tool and list pages get one sticky <code>.bar</code>. Content pages don\'t need one.', <<<'HTML'
<div class="bar">
  <input class="field" type="search" placeholder="Search..." aria-label="Search">
  <button class="btn small">Expand all</button>
  <span class="muted">36 results</span>
</div>
HTML],
    'messages' => ['Messages', 'A plain sentence saying what happened and what to do. Toasts: <code>UI.toast(\'Saved\', \'ok\')</code>; they go away on their own.', <<<'HTML'
<p class="note">Loading games...</p>
<p class="err">Couldn't reach FreeToGame. Try again in a minute.</p>
<p class="ok">Saved.</p>
<button class="btn small" data-act="toast">Show a toast</button>
HTML],
    'drop' => ['Drop zone', 'File tools. Add <code>.over</code> while a file is dragged over it. Files are read in the browser.', <<<'HTML'
<div class="drop">Drop a file here or <button class="btn small">choose one</button></div>
HTML],
    'drawer' => ['Drawer', 'Details for a clicked item. Full width on phones; <code>Esc</code> closes it.', <<<'HTML'
<button class="btn" data-act="drawer">Open the drawer</button>
<aside class="drawer" id="dg-drawer" aria-label="Details" aria-hidden="true" tabindex="-1">
  <button class="close" data-act="drawer-close" aria-label="Close">&times;</button>
  <h2>Diamond Sword</h2>
  <p class="muted">Slides in with transform only, in 180ms.</p>
</aside>
HTML],
    'dialog' => ['Dialog', 'The native <code>&lt;dialog&gt;</code>: <code>showModal()</code> gives focus trapping, <code>Esc</code> and a backdrop for free.', <<<'HTML'
<button class="btn" data-act="dialog">Open a dialog</button>
<dialog class="dlg" id="dg-dialog">
  <h2>Share</h2>
  <p class="muted">One short line about what this does.</p>
  <div class="actions"><button class="btn" data-act="dialog-close">Close</button></div>
</dialog>
HTML],
    'tree' => ['Raw data tree', 'Raw JSON or NBT, last on the page and collapsed. Build children only when a node opens.', <<<'HTML'
<div class="tree">
  <details open>
    <summary><span class="k">Inventory</span> <span class="ty">list · 2</span></summary>
    <div class="tl"><span class="k">id</span>: <span class="v string">"minecraft:diamond_sword"</span></div>
    <div class="tl"><span class="k">count</span>: <span class="v number">1</span></div>
  </details>
</div>
HTML],
    'hub' => ['Hub cards', 'Section landing pages. Made by <code>site_hub(\'minecraft\')</code> from <code>data/site.json</code>; never write them by hand.', <<<'HTML'
<div class="hub-grid">
  <a class="hub-card" href="/minecraft/tools/nbt-viewer/"><b>NBT Viewer</b><span>Explore player .dat files</span></a>
  <a class="hub-card" href="/minecraft/servers/"><b>Server Status</b><span>Live status of my Minecraft servers</span></a>
</div>
HTML],
    'icons' => ['Icons', 'Only where they help people recognise something faster. The rules are under Icons below.', <<<'HTML'
<button class="btn">
  <svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true"><rect x="5.5" y="5.5" width="8" height="8" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M10.5 5.5v-2a1 1 0 0 0-1-1h-6a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h2" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>
  Copy link
</button>
<button class="btn small" aria-label="Close" title="Close">&times;</button>
<a class="btn" href="https://github.com/Craftyplayz/Website" target="_blank" rel="noopener">
  <img src="/assets/img/icons/github.svg" alt="" width="16" height="16">
  GitHub
</a>
HTML],
];

$skeleton = <<<'PHP'
<?php
$site_page = ['components' => true, 'css' => ['/assets/css/pages/tools-example.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>

    <main class="page" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Example Tool</h1>
        <p class="page-lede">One line saying what this page does.</p>

        <div class="grid">
            <section class="card"><h2>Section</h2><p>Content.</p></section>
        </div>
    </main>

    <?php site_footer(null, false); ?>
</body>

</html>
PHP;

$siteJson = <<<'JSON'
{ "title": "Server Status", "url": "/minecraft/servers/", "description": "Live status of my Minecraft servers", "keywords": ["server", "online"] }
JSON;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
    <meta name="robots" content="noindex">
    <style>
        /* design guide page */
        .dg-stack { display: grid; gap: 16px; }
        .dg-stack .card > :last-child { margin-bottom: 0; }
        .dg h3 { margin: 16px 0 8px; font-size: 15px; }
        .dg ul, .dg ol { margin: 0 0 12px; padding-left: 20px; }
        .dg li { margin: 4px 0; }
        .dg p { margin: 0 0 12px; }
        .dg code { padding: 1px 5px; background: var(--card2); border-radius: 4px; font: 13px var(--mono); }
        .dg .table-wrap { margin-bottom: 12px; }
        .dg-code { margin: 0 0 12px; padding: 10px 12px; overflow-x: auto; background: var(--sunken); border: 1px solid var(--line); border-radius: var(--radius); font: 13px/1.5 var(--mono); }
        .dg-code code { padding: 0; background: none; }
        .dg-swatches { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px; margin-bottom: 12px; }
        .dg-sw { overflow: hidden; background: var(--card2); border: 1px solid var(--line); border-radius: var(--radius); font-size: 13px; }
        .dg-sw i { display: block; height: 40px; border-bottom: 1px solid var(--line); }
        .dg-sw div { padding: 6px 8px; }
        .dg-sw small { display: block; color: var(--muted); font-size: 12px; line-height: 1.35; }
        .dg-comps { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 440px), 1fr)); gap: 16px; }
        .dg-comp { display: flex; flex-direction: column; gap: 8px; min-width: 0; }
        .dg-comp h3 { margin: 0; }
        .dg-comp p { margin: 0; font-size: 14px; }
        .dg-demo { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 12px; background: var(--bg); border: 1px dashed var(--line-strong); border-radius: var(--radius); }
        .dg .dg-demo > :not(dialog), .dg-comp .dg-code { margin: 0; }
        .dg-demo > .grid, .dg-demo > .tiles, .dg-demo > .rows, .dg-demo > .kv, .dg-demo > .progress, .dg-demo > .bar, .dg-demo > .drop, .dg-demo > .tree, .dg-demo > .hub-grid, .dg-demo > .note, .dg-demo > .err, .dg-demo > .ok, .dg-demo > .form, .dg-demo > .table-wrap { flex: 1 1 100%; }
        .dg-demo > .bar { position: static; padding-top: 0; }
    </style>
</head>

<body>
    <?php site_header(); ?>

    <main class="page dg" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Design Guide</h1>
        <p class="page-lede">How every page on this site looks and is built. The samples use the site's real CSS, so copy them as they are.</p>

        <nav class="jump">
            <a href="#start">Start here</a><a href="#principles">Principles</a><a href="#build">Build a page</a>
            <a href="#colour">Colour</a><a href="#type">Type and spacing</a><a href="#layout">Layout</a>
            <a href="#components">Components</a><a href="#forms">Forms</a><a href="#icons">Icons</a>
            <a href="#motion">Motion</a><a href="#writing">Writing</a><a href="#content">Content</a>
            <a href="#data">Data and speed</a><a href="#a11y">Accessibility</a><a href="#checklist">Checklist</a>
        </nav>

        <div class="dg-stack">
            <section class="card" id="start">
                <h2>Start here</h2>
                <ul>
                    <li><b>Read this page top to bottom before building anything.</b> Rules are in priority order: when two clash, the earlier one wins.</li>
                    <li><b>Build with the components below before writing any CSS.</b> Copy their markup from the code blocks.</li>
                    <li><b>Never invent a value.</b> Colours are tokens, sizes and spacing come from the scales here, the font is the system font.</li>
                    <li><b>Change the look, not the behaviour,</b> when restyling an existing page. Keep its features, URLs, query parameters and data files.</li>
                    <li>In the repo, the full build guide (data/API patterns, testing, parallel sessions) is <code>_dev/handoffs/building-pages.md</code>.</li>
                </ul>
                <h3>Reference pages: when in doubt, copy what these do</h3>
                <div class="table-wrap">
                    <table class="table">
                        <tr><th>Kind of page</th><th>Reference</th><th>What to copy</th></tr>
                        <tr><td>Heavy tool</td><td><a href="/minecraft/tools/nbt-viewer/">NBT Viewer</a></td><td>The gold standard for everything on this page</td></tr>
                        <tr><td>Plain text / info</td><td><a href="/about.php">About</a></td><td>Template, <code>.grid</code> of <code>.card</code>s, <code>.kv</code> facts</td></tr>
                        <tr><td>Section landing</td><td><a href="/minecraft/">Minecraft</a></td><td>Hero plus <code>site_hub()</code> cards from <code>site.json</code></td></tr>
                        <tr><td>Long text</td><td><a href="/minecraft/projects/life-series/season-1/session-1/">Life Series session</a></td><td>One <code>&lt;pre&gt;</code> in a page stylesheet, a link back in the lede</td></tr>
                        <tr><td>Server-fetched API</td><td><a href="/games/steam/">Steam</a>, <a href="/extras/dnd/">D&amp;D</a></td><td>Disk cache, stale fallback, parallel <code>curl_multi</code>, keys server-side</td></tr>
                        <tr><td>Browser-fetched API</td><td><a href="/games/free/">Free Games</a>, <a href="/minecraft/servers/">Server Status</a></td><td>Loading note, then grid, sticky <code>.bar</code> filters, drawer details</td></tr>
                    </table>
                </div>
            </section>

            <section class="card" id="principles">
                <h2>Principles <span class="n">in priority order</span></h2>
                <ol>
                    <li><b>Fast.</b> Content is visible and usable in under 1 second on a phone. The first view is HTML from PHP; JS only improves it. Nothing heavy loads until it's needed.</li>
                    <li><b>Fits every screen.</b> One layout that flows from 320px to 2560px. Grids pick their own column count, sizes use <code>clamp()</code>, and breakpoints exist only where content actually breaks.</li>
                    <li><b>Uncluttered.</b> One job per page. One green button per view. Empty things are hidden, details live behind a click, raw data comes last.</li>
                    <li><b>One dark theme.</b> Every page uses the same tokens. No page invents a colour, a font, or a light mode.</li>
                    <li><b>Calm motion.</b> Animation only explains a change (opened, pressed, state moved), lasts under 200ms, and uses <code>transform</code> and <code>opacity</code> only.</li>
                </ol>
                <p class="muted">A good page reads: header → breadcrumbs → title → one muted line → the content, in cards, in a grid that fills the width → footer. Nothing else.</p>
            </section>

            <section class="card" id="build">
                <h2>Build a page</h2>
                <ol>
                    <li><b>Pick the folder.</b> URLs are folders: <code>/&lt;section&gt;/&lt;group&gt;/&lt;page&gt;/index.php</code>. Sections are <code>minecraft</code>, <code>games</code>, <code>books</code>, <code>tools</code>, <code>extras</code>. Lowercase, hyphens, links never end in <code>.php</code>.</li>
                    <li><b>Copy <code>_templates/page.php</code></b> to that folder as <code>index.php</code> and set <code>$site_page</code>.</li>
                    <li><b>Add the page to <code>data/site.json</code>.</b> It is the only source of the header menus, search, breadcrumbs, tab titles and hub cards. Never hard-code a nav link anywhere else.</li>
                    <li><b>Build the content</b> with the components below. Page-only CSS goes in <code>assets/css/pages/&lt;section&gt;-&lt;name&gt;.css</code>.</li>
                    <li><b>Run the checklist</b> at the bottom of this page.</li>
                </ol>
                <pre class="dg-code"><code><?= h($skeleton) ?></code></pre>
                <p class="muted">Never write your own <code>&lt;title&gt;</code>, charset, viewport, favicon or link to <code>global.css</code>: <code>page_head()</code> does all of it, with cache-busting URLs.</p>

                <h3><code>$site_page</code> options</h3>
                <div class="table-wrap">
                    <table class="table">
                        <tr><th>Key</th><th>Effect</th></tr>
                        <tr><td><code>'components' => true</code></td><td>Loads <code>components.css</code>. Almost every page wants it.</td></tr>
                        <tr><td><code>'ui' => true</code></td><td>Loads <code>ui.js</code> (<code>window.UI</code>). It's deferred, so use it inside <code>DOMContentLoaded</code>.</td></tr>
                        <tr><td><code>'css' => [...]</code></td><td>Page stylesheets, versioned automatically.</td></tr>
                        <tr><td><code>'config' => true</code></td><td>Loads the API keys (<code>$steamkey</code>, <code>$hypixelkey</code>…) for PHP. Never print a key.</td></tr>
                        <tr><td><code>'title'</code>, <code>'description'</code></td><td>Only for pages that aren't in <code>site.json</code>. Otherwise both come from there.</td></tr>
                        <tr><td><code>'legacy' => true</code></td><td>Old, unrestyled pages only. Never on a new page.</td></tr>
                    </table>
                </div>
                <p class="muted">Full-screen apps (OBS overlays, BRB screen) skip the footer. Pages that have no site header never get one added without the owner agreeing.</p>

                <h3>A <code>data/site.json</code> entry</h3>
                <pre class="dg-code"><code><?= h($siteJson) ?></code></pre>
                <div class="table-wrap">
                    <table class="table">
                        <tr><th>Field</th><th>Meaning</th></tr>
                        <tr><td><code>title</code></td><td>Menu text, tab title, breadcrumb, search result. Short, Title Case.</td></tr>
                        <tr><td><code>url</code></td><td>Folder URL with a trailing <code>/</code>.</td></tr>
                        <tr><td><code>description</code></td><td>One short sentence, no full stop. Becomes the meta description, hub card text and search text.</td></tr>
                        <tr><td><code>keywords</code></td><td>Extra words people might search for.</td></tr>
                        <tr><td><code>"nav": false</code></td><td>Not in the menu, still in search and hubs. Each menu group shows about 4 to 8 pages; the rest get this.</td></tr>
                        <tr><td><code>"search": false</code></td><td>Not in search or hubs. For viewers that need an <code>?id=</code>.</td></tr>
                        <tr><td><code>"hub_skip": true</code></td><td>Not on hub cards only.</td></tr>
                    </table>
                </div>
                <p class="muted">Moving a page: change its <code>url</code> and add a <code>RedirectMatch 301</code> for the old URL in <code>.htaccess</code>. Retiring one: remove it, move the files to <code>_dev/archive/</code>, redirect the old URL.</p>
            </section>

            <section class="card" id="colour">
                <h2>Colour <span class="n">dark only · live from global.css</span></h2>
                <div class="dg-swatches">
                    <?php foreach ($tokens as $t): ?>
                        <div class="dg-sw"><i style="background:var(--<?= h($t[1]) ?>)"></i>
                            <div><b class="mono">--<?= h($t[1]) ?></b> <span class="muted mono"><?= h($t[2]) ?></span><?php if (isset($uses[$t[1]])): ?><small><?= h($uses[$t[1]]) ?></small><?php endif; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <ul>
                    <li><b>Pages use <code>var(--…)</code> only, never a hex value.</b> The only exceptions are content colours with real meaning, like Minecraft <code>§</code> codes or syntax highlighting.</li>
                    <li><b>Three surface levels:</b> <code>--bg</code> → <code>--card</code> → <code>--card2</code>. Depth comes from these steps, never from shadows. Only floating things (drawer, menus, dialogs) have a shadow.</li>
                    <li><b>Green means act or done.</b> Never decoration. Status uses tinted backgrounds with a matching border (<code>.badge.g</code>, <code>.ok</code>, <code>.err</code>, <code>.note</code>).</li>
                    <li><b>Green is the accent on every page.</b> Pages and sections never re-tint it or any other token.</li>
                    <li>No gradients, glows or borders inside borders. <code>color-scheme: dark</code> is already set, so scrollbars and form controls are dark.</li>
                </ul>
            </section>

            <section class="card" id="type">
                <h2>Type and spacing</h2>
                <p><b>Font:</b> <code>var(--font)</code>, the system font. <b>Code and IDs:</b> <code>var(--mono)</code>. No web fonts, no icon fonts. Icons are covered under <a href="#icons">Icons</a>.</p>
                <div class="grid">
                    <div class="table-wrap">
                        <table class="table">
                            <tr><th>Text</th><th>Size</th></tr>
                            <tr><td>Page title <code>h1.page-title</code></td><td><code>clamp(24px, 4vw, 34px)</code>, bold</td></tr>
                            <tr><td>Hero / landing title</td><td><code>clamp(26px, 5vw, 42px)</code></td></tr>
                            <tr><td>Card heading <code>.card h2</code></td><td>17px, 600, icon 22px</td></tr>
                            <tr><td>Sub-heading <code>h3</code></td><td>15px</td></tr>
                            <tr><td>Body</td><td>browser default (16px), line-height 1.5</td></tr>
                            <tr><td>Controls, rows, buttons</td><td>14px</td></tr>
                            <tr><td>Meta, breadcrumbs, counts, small buttons</td><td>13px, <code>--muted</code></td></tr>
                            <tr><td>Badges, pills, tile labels</td><td>12px</td></tr>
                            <tr><td>Code, IDs, raw data</td><td><code>var(--mono)</code> 12–13px</td></tr>
                        </table>
                    </div>
                    <div class="table-wrap">
                        <table class="table">
                            <tr><th>Thing</th><th>Value</th></tr>
                            <tr><td>Spacing scale</td><td>4 · 6 · 8 · 10 · 12 · 16 · 20 · 24px, nothing else</td></tr>
                            <tr><td>Card padding</td><td>16px</td></tr>
                            <tr><td>Gap between cards</td><td>16px</td></tr>
                            <tr><td>Gap inside lists</td><td>6px rows · 8px tiles</td></tr>
                            <tr><td>Radius</td><td>8px controls · 12px cards · 999px pills</td></tr>
                            <tr><td>Content width</td><td>max 1280px, 16px side padding (<code>.page</code>)</td></tr>
                        </table>
                    </div>
                </div>
                <p>Never set <code>font-family</code> or a body font size in a page stylesheet, and don't invent sizes like 15.5px or 18px.</p>
                <p><b>Numbers and dates:</b> <code>UI.num(n)</code> for numbers, <code>UI.ago(ms)</code> for "3 days ago" with the exact date in <code>title=""</code>, IDs in <code>.mono</code>.</p>
            </section>

            <section class="card" id="layout">
                <h2>Layout that fits every screen <span class="n">test at 320 · 390 · 768 · 1366 · 2560</span></h2>
                <div class="grid">
                    <div>
                        <div class="badges"><span class="badge g">Do</span></div>
                        <ul>
                            <li>Build mobile-first at 320px, then let it grow.</li>
                            <li>Grids that choose their own columns: <code>repeat(auto-fill, minmax(260px, 1fr))</code>. Use <code>auto-fit</code> when a few items should stretch to fill the row.</li>
                            <li><code>flex-wrap: wrap</code> on every toolbar and button row.</li>
                            <li><code>clamp()</code> for headings and repeated UI sizes.</li>
                            <li><code>min-width: 0</code> on grid and flex children that hold text, plus ellipsis on long names.</li>
                            <li>Panels sized <code>width: min(460px, 100vw)</code>, so they're full-screen on phones.</li>
                            <li>Images get <code>width</code>/<code>height</code> attributes and <code>height: auto</code>, so nothing jumps while loading.</li>
                            <li>Wide data (tables, code): wrap only that block in <code>overflow-x: auto</code>.</li>
                            <li>Tap targets at least 40px tall on touch.</li>
                        </ul>
                    </div>
                    <div>
                        <div class="badges"><span class="badge r">Don't</span></div>
                        <ul>
                            <li>Scroll the page sideways at any width.</li>
                            <li>Put fixed pixel widths on containers.</li>
                            <li>Make separate mobile and desktop markup, or sniff the user agent.</li>
                            <li>Hide anything essential behind hover.</li>
                            <li>Use <code>body { overflow: hidden }</code> or <code>100vh</code> on content pages.</li>
                            <li>Add a breakpoint "just in case". The header has one (900px); most pages need none.</li>
                        </ul>
                    </div>
                </div>
            </section>

            <section class="card" id="components">
                <h2>Components <span class="n">components.css, loaded with 'components' => true</span></h2>
                <p class="muted">If a page needs something that isn't here and other pages could use it too, ask for it to be added to <code>components.css</code>, and keep a local copy in the page stylesheet until then. Helpers: <code>.muted</code>, <code>.mono</code>, <code>.hide</code>.</p>
                <div class="dg-comps">
                    <?php foreach ($samples as $id => $s): ?>
                        <section class="dg-comp" id="c-<?= h($id) ?>">
                            <h3><?= h($s[0]) ?></h3>
                            <p class="muted"><?= $s[1] ?></p>
                            <div class="dg-demo"><?= $s[2] ?></div>
                            <pre class="dg-code"><code><?= h($s[2]) ?></code></pre>
                        </section>
                    <?php endforeach; ?>
                </div>
                <h3 id="forms">Forms</h3>
                <ul>
                    <li><b>Every field sits inside its <code>&lt;label&gt;</code></b>, label text first, so clicking the text focuses the field and no <code>id</code> is needed. Never use a placeholder instead of a label; a placeholder is only an example value.</li>
                    <li><b>One field per row by default.</b> Use <code>.cols</code> only for short fields that belong together (dimension and amount, from and to). Forms sit in <code>.form-card</code> (max 560px wide) so fields don't stretch across the screen.</li>
                    <li><b>Mark the optional fields</b> with "(optional)" in the label. Never mark required ones with an asterisk.</li>
                    <li><b>Use the right input:</b> <code>type="number"</code> with <code>inputmode</code>, <code>min</code> and <code>max</code> for numbers, <code>&lt;select&gt;</code> for more than 4 fixed choices, radios in a <code>&lt;fieldset&gt;</code> for 2 to 4, a <code>.check</code> checkbox for yes/no.</li>
                    <li><b>Errors:</b> set <code>aria-invalid="true"</code> on the field (red border) and put what to fix in the label's <code>&lt;small&gt;</code> ("Enter a number from 1 to 64."). Check a field when it loses focus or the form is sent, not on every key press. A failed send shows one <code>.err</code> above the buttons.</li>
                    <li><b>Tools that calculate update as you type</b> (debounced) and need no button. A button is for actions that send, save or take a while; it is the view's one <code>.primary</code>, says what it does ("Save settings", not "Submit"), and is disabled with "Saving..." while it works.</li>
                    <li><b>Buttons go last</b>, in an <code>.actions</code> row, primary first.</li>
                </ul>
                <h3 id="icons">Icons</h3>
                <ul>
                    <li><b>Use an icon only when it helps people recognise something faster:</b> links to outside services (GitHub, Discord, Steam), game items, and the few controls everyone knows (close, search, copy, menu). Never decorate headings or buttons that already have clear text.</li>
                    <li><b>UI icons are inline SVG</b> in <code>currentColor</code>, so they follow the text colour. Add new ones to <code>site_icon()</code> in <code>includes/functions.php</code> instead of pasting the same SVG into several pages.</li>
                    <li><b>Service logos</b> come from <code>/assets/img/icons/</code> (white, 24×24 SVGs) as an <code>&lt;img&gt;</code> beside the name. <b>Minecraft items</b> come from <code>/minecraft/tools/item-list/</code>, with <code>image-rendering: pixelated</code> when shown larger than 16px.</li>
                    <li><b>Sizes:</b> 16px beside text and in buttons, 22px in card headings (set automatically by <code>.card h2 img</code>), 24px in row lists.</li>
                    <li><b>An icon-only button</b> needs <code>aria-label</code> and <code>title</code>. A decorative icon gets <code>alt=""</code> or <code>aria-hidden="true"</code>.</li>
                    <li>No icon fonts, no Font Awesome, and no emoji as icons or in titles, headings, menus or buttons.</li>
                </ul>
                <h3>Page stylesheets</h3>
                <ul>
                    <li>Named <code>assets/css/pages/&lt;section&gt;-&lt;name&gt;.css</code>, starting with a one-line comment naming the page.</li>
                    <li>Every class gets a short page prefix (<code>.srv-</code>, <code>.ftg-</code>) so nothing leaks.</li>
                    <li>Tokens only, no <code>transition: all</code>, no <code>!important</code> except to beat legacy styles.</li>
                    <li>Under about 40 lines may sit in a <code>&lt;style&gt;</code> in <code>&lt;head&gt;</code>; anything bigger goes in a file so it caches.</li>
                </ul>
            </section>

            <section class="card" id="motion">
                <h2>Motion <span class="n">short, smooth, only when it explains a change</span></h2>
                <div class="table-wrap">
                    <table class="table">
                        <tr><th>Use</th><th>Duration</th><th>Property</th></tr>
                        <tr><td>Hover / focus colour</td><td><code>var(--fast)</code> 120ms</td><td>background, border-color, color</td></tr>
                        <tr><td>Press</td><td>60ms</td><td><code>translateY(1px)</code></td></tr>
                        <tr><td>Caret flip</td><td>120ms</td><td>rotate</td></tr>
                        <tr><td>Drawer / panel</td><td><code>var(--base)</code> 180ms ease-out</td><td>transform</td></tr>
                        <tr><td>Menu / dropdown</td><td>120–150ms ease-out</td><td>opacity + 4px translate</td></tr>
                        <tr><td>Scroll to a section</td><td>browser default</td><td><code>scrollIntoView({ behavior: 'smooth' })</code></td></tr>
                    </table>
                </div>
                <p><b>Never:</b> entrance or scroll-reveal animations, staggered cards, animating width/height/top/margin, <code>transition: all</code>, anything over 200ms, colour flashes on hover. <code>global.css</code> turns motion off for <code>prefers-reduced-motion</code>; loops you start in JS must check it too, and stop when offscreen or the tab is hidden.</p>
            </section>

            <section class="card" id="writing">
                <h2>Writing <span class="n">British English, plain words</span></h2>
                <ul>
                    <li><b>British spelling:</b> colour, centre, organise, favourite, grey, licence (noun), metres.</li>
                    <li><b>Plain words and short sentences.</b> Say what something is or does. No hype, no jokes and no exclamation marks in interface text (titles, ledes, labels, buttons, messages). The content of a joke page can be as silly as it likes; the interface around it stays plain.</li>
                    <li><b>Capitals:</b> page titles and menu items use Title Case (they come from <code>site.json</code>). Everything else uses sentence case: card headings, buttons, labels, tabs.</li>
                    <li><b>Buttons start with a verb</b> and say what happens: "Copy link", "Download PNG". Never "OK", "Submit" or "Click here". Link text says where it goes.</li>
                    <li><b>Full stops:</b> none on titles, headings, labels, buttons or <code>site.json</code> descriptions. Ledes, help text and messages are sentences and get one.</li>
                    <li><b>Numbers and dates:</b> digits with thousands commas (1,204), a space before units (64 MB, 5 min), dates as day month year (20 Oct 2026).</li>
                    <li><b>Talk to the reader as "you".</b> Contractions are fine (can't, don't). Messages say what happened and what to do, without blame or error codes.</li>
                </ul>
            </section>

            <section class="card" id="content">
                <h2>Content and clutter</h2>
                <ul>
                    <li><b>One job per page.</b> One <code>.btn.primary</code> per view; everything else is a secondary button or a link.</li>
                    <li><b>One muted line under the title,</b> not a paragraph. Help text is one muted line where it's needed.</li>
                    <li><b>Overview first,</b> details on click (drawer, <code>&lt;details&gt;</code>, "Show all 312"), raw data last and collapsed.</li>
                    <li><b>Hide what's empty.</b> No empty cards, no "0 results" headings, no "coming soon" sections. One compact prompt at most.</li>
                    <li><b>Every state has a sentence:</b> loading says what's loading, errors say what failed and what to do. Never a spinner forever or a raw error object.</li>
                    <li><b>Nothing plays or flashes on load.</b> No autoplay, no popups. The flashing-colours page keeps its warning gate.</li>
                    <li><b>Muted text instead of extra headings or boxes.</b></li>
                    <li><b>Credit data sources</b> in the lede or a muted line at the bottom ("Data from mcsrvstat.us, cached for 5 minutes").</li>
                </ul>
            </section>

            <section class="card" id="data">
                <h2>Data, speed and safety</h2>
                <div class="table-wrap">
                    <table class="table">
                        <tr><th>Budget</th><th>Target</th></tr>
                        <tr><td>Content visible on 4G</td><td>under 1s</td></tr>
                        <tr><td>HTML + CSS + JS over the network (gzip)</td><td>under 60 KB for content pages, under 150 KB for tools</td></tr>
                        <tr><td>Render-blocking third-party requests</td><td>0</td></tr>
                        <tr><td>Layout shift after load</td><td>none</td></tr>
                        <tr><td>Interaction response</td><td>under 100ms</td></tr>
                    </table>
                </div>
                <ul>
                    <li><b>Plain HTML, CSS and JS.</b> No framework, no build step, no jQuery. A library only for one job, loaded on first use, and the page still works if it fails.</li>
                    <li><b>Scripts are deferred</b> or at the end of <code>&lt;body&gt;</code>. Event delegation on <code>data-act</code> / <code>data-id</code>, no inline <code>onclick</code>.</li>
                    <li><b>External APIs:</b> keyed, rate-limited or no-CORS APIs are fetched in PHP with a disk cache and a stale fallback (copy <code>steam_fetch()</code> or <code>dnd_get()</code>). Public CORS APIs can be fetched from the browser. Pass PHP data to JS with <code>json_encode</code>, not a second fetch.</li>
                    <li><b>Big lists</b> render in chunks of about 250 with "Show more"; hidden content is built when it opens; filters debounce about 80ms and filter in memory.</li>
                    <li><b>Images:</b> WebP or AVIF, <code>loading="lazy" decoding="async"</code> past the first screen, a fallback so there's never a broken-image icon. Galleries show about 400px thumbnails.</li>
                    <li><b>Escape everything</b> from an API or user: <code>h()</code> in PHP, <code>UI.esc()</code> in JS. Keys stay server-side. User files are read in the browser and only uploaded on an explicit action.</li>
                    <li><b>Private data</b> (IPs, chat, passwords, seeds) is click-to-reveal and never goes in a share link.</li>
                </ul>
            </section>

            <section class="card" id="a11y">
                <h2>Accessibility and keyboard</h2>
                <ul>
                    <li>One <code>&lt;h1&gt;</code>, headings in order. <code>&lt;button&gt;</code> for actions, <code>&lt;a&gt;</code> for navigation, never a clickable <code>&lt;div&gt;</code>.</li>
                    <li>Every input has a <code>&lt;label&gt;</code> or <code>aria-label</code>; every image has <code>alt</code> (<code>alt=""</code> if decorative).</li>
                    <li>The green <code>:focus-visible</code> ring is global. Never remove an outline without a replacement.</li>
                    <li><code>aria-expanded</code> on toggles, <code>aria-hidden</code> and focus on drawers, native <code>&lt;dialog&gt;</code> for modals.</li>
                    <li><code>/</code> focuses the site search (a page with its own search calls <code>preventDefault()</code> on <code>/</code>). <code>Esc</code> closes the innermost open thing. Enter activates.</li>
                    <li>Every action answers within 100ms: disable the button and change its text while working, "Copied!" for 1s.</li>
                    <li>External links: <code>target="_blank" rel="noopener"</code>.</li>
                </ul>
            </section>

            <section class="card" id="checklist">
                <h2>Checklist <span class="n">every page, before it ships</span></h2>
                <h3>Structure</h3>
                <ul>
                    <li>Built from <code>_templates/page.php</code>; no hand-written <code>&lt;title&gt;</code>, meta or global CSS links</li>
                    <li>In <code>data/site.json</code> with title, url (trailing <code>/</code>), description, keywords and the right flags</li>
                    <li>Shows in its menu (desktop and burger), in <code>/</code> search, with the right breadcrumbs and tab title</li>
                    <li>Old URL redirected in <code>.htaccess</code> if it moved</li>
                </ul>
                <h3>Look</h3>
                <ul>
                    <li><code>&lt;main class="page"&gt;</code>, <code>h1.page-title</code>, one-line <code>p.page-lede</code></li>
                    <li>Only components and a prefixed page stylesheet; only <code>var(--…)</code> colours; no font-family or body size</li>
                    <li>Sizes and spacing from the scales; radius 8 / 12 / 999</li>
                    <li>One primary button; no empty sections; details behind a click</li>
                    <li>Tables in <code>.table-wrap</code>; every form field inside its label; icons only where they help</li>
                    <li>British spelling, plain words, sentence-case buttons and headings</li>
                </ul>
                <h3>Screens</h3>
                <ul>
                    <li>No sideways scroll at 320 / 390 / 1366 / 2560</li>
                    <li>Grids auto-fill; long names ellipsis; images have dimensions</li>
                </ul>
                <h3>Speed</h3>
                <ul>
                    <li>Scripts deferred or at the end of body; libraries loaded on use</li>
                    <li>External API calls cached server-side, or a public CORS API called from the browser</li>
                    <li>Lazy images below the fold; big lists chunked</li>
                </ul>
                <h3>Behaviour</h3>
                <ul>
                    <li>Loading, empty and error states all render sensibly</li>
                    <li><code>Esc</code> closes, <code>/</code> doesn't clash, the keyboard reaches everything</li>
                    <li>All API and user text escaped; no keys in HTML; no <code>console.log</code></li>
                    <li>Motion 200ms or less, transform and opacity only</li>
                </ul>
            </section>
        </div>
    </main>

    <?php site_footer(null, false); ?>

    <script>
        // live samples: drawer, dialog, pill tabs, toast
        document.addEventListener('DOMContentLoaded', () => {
            const drawer = document.getElementById('dg-drawer');
            const dialog = document.getElementById('dg-dialog');
            const setDrawer = (open) => {
                drawer.classList.toggle('open', open);
                drawer.setAttribute('aria-hidden', String(!open));
                if (open) drawer.focus();
            };
            document.addEventListener('click', (e) => {
                const tab = e.target.closest('.stabs button');
                if (tab) tab.parentElement.querySelectorAll('button').forEach((b) => b.classList.toggle('on', b === tab));
                const act = e.target.closest('[data-act]');
                if (!act) return;
                const a = act.dataset.act;
                if (a === 'drawer') setDrawer(true);
                else if (a === 'drawer-close') setDrawer(false);
                else if (a === 'dialog') dialog.showModal();
                else if (a === 'dialog-close') dialog.close();
                else if (a === 'toast') UI.toast('Saved. Toasts go away on their own.', 'ok');
            });
            document.addEventListener('submit', (e) => {
                if (!e.target.closest('.dg-demo')) return;
                e.preventDefault();
                UI.toast('Saved. This sample form doesn\'t send anything.', 'ok');
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && drawer.classList.contains('open')) setDrawer(false);
            });
        });
    </script>
</body>

</html>
