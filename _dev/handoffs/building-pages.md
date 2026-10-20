# Building pages: the complete guide

This is the single source of truth for building or restyling any page on craftyplayz.com.
The same design rules, with live component samples, are at `/design/` (`design/index.php`), the page to point other models at. When a rule changes, change it in both.
Session rules (ports, git, staying inside your tab) are in `README.md` next to this file.

**Reference pages** — when in doubt, open these and copy what they do:

| Kind of page | Reference | What to copy |
|---|---|---|
| Plain text / info | `/about.php` | template + `.grid` of `.card`s, `.kv` facts |
| Long text / transcript | `/minecraft/projects/life-series/season-1/session-1/` | one `<pre>` in a page stylesheet, link back in the lede |
| Hub (section landing) | `/minecraft/index.php` | hero + `site_hub()` cards straight from `site.json` |
| Server-fetched API, one value | `/extras/facts/` | PHP fetches, renders, shows `.err` on failure |
| Server-fetched API, cached | `/games/steam/` + `steam.php`, `/extras/dnd/` | disk cache, stale fallback, parallel `curl_multi`, keys server-side |
| Browser-fetched API | `/games/free/`, `/minecraft/servers/` | loading note → grid, sticky `.bar` filters, drawer details, polling |
| Heavy tool | `/minecraft/tools/nbt-viewer/` | the gold standard for everything below |

---

## 1. Design philosophy

Five rules, in priority order. When two clash, the higher one wins.

1. **Fast.** Content is visible and usable in under 1 second on a phone. The first view is HTML from PHP; JS only improves it. Nothing heavy loads until it's needed.
2. **Fits every screen.** One layout that flows from 320px to 2560px. Grids pick their own column count, sizes use `clamp()`, and breakpoints exist only where content actually breaks.
3. **Uncluttered.** One job per page. One green button per view. Empty things are hidden, details live behind a click (drawer, `<details>`, dialog), raw data comes last.
4. **One dark theme.** Every page uses the same tokens from `global.css`. No page invents a colour, a font, or a light mode.
5. **Calm motion.** Animation only explains a change (opened, pressed, state moved), lasts under 200ms, and uses `transform`/`opacity` only.

A good page reads like this: header → breadcrumbs → title → one muted line → the content, in cards, in a grid that fills the width → footer. Nothing else.

---

## 2. Making a new page, step by step

1. **Pick the folder.** URLs are folders: `/<section>/<group?>/<page>/index.php`. Sections are `minecraft`, `games`, `books`, `tools`, `extras`. Lowercase, hyphens, no `.php` in links.
2. **Copy `_templates/page.php`** to `<folder>/index.php`.
3. **Set `$site_page`** at the top (see §3). Usually `['components' => true]`, plus `'ui' => true` if you use `UI.*`, `'css' => [...]` for a page stylesheet, `'config' => true` if you need an API key.
4. **Add the page to `data/site.json`** (see §4). Without this it has no menu entry, no search result, no title and no breadcrumb. This is a shared file: if you're in a parallel session, request it in `requests.md` instead.
5. **Build the content** with components (§8). Page-only CSS goes in `assets/css/pages/<section>-<name>.css`.
6. **Run the checklist** (§16) and the tests (§15).
7. **Commit only your paths** (README §3).

Minimal page:

```php
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
```

Never write your own `<title>`, `<meta charset>`, viewport, favicon, or `<link>` to `global.css` — `page_head()` does all of it, with cache-busting `?v=` URLs.

---

## 3. `$site_page` options

| Key | Effect |
|---|---|
| `'title'` | Tab title. Leave it out: the title comes from `site.json`. Set it only on pages that aren't in `site.json` (hubs, viewers). |
| `'description'` | Meta description. Same rule: comes from `site.json`. |
| `'components' => true` | Loads `assets/css/components.css` (`.btn`, `.card`, `.grid`, `.drawer`…). Almost every page wants this. |
| `'ui' => true` | Loads `assets/js/ui.js` (`window.UI`). It's `defer`, so use it inside `DOMContentLoaded`. |
| `'css' => ['/assets/css/pages/x.css']` | Extra stylesheets, versioned automatically. |
| `'config' => true` | Loads `php/config.php` silently, giving `$steamkey`, `$hypixelkey`, `$mcitemskey`… **Never echo a key into the page.** |
| `'legacy' => true` | Old pages only. Loads `legacy.css`; pair with `<main class="page page-legacy">`. Never use on a new page. |

`site_footer(null, false)` = footer without breadcrumbs (new pages print breadcrumbs at the top). **Full-screen apps** (100vh: OBS overlays, BRB screen, flashing colours) skip the footer. Pages without the site header (overlays, fake-update screens) never get one added without the owner agreeing.

---

## 4. Keeping the header up to date (`data/site.json`)

`data/site.json` is the **only** place navigation lives. The PHP header, mega-menus, mobile menu, site search (`/` key), breadcrumbs, tab titles, meta descriptions, hub cards and the sitemap all read it. Never hard-code a nav link anywhere else.

Structure: `sections[] → groups[] → pages[]`, plus top-level `links[]` (About).

```json
{ "title": "Server Status", "url": "/minecraft/servers/", "description": "Live status of my Minecraft servers", "keywords": ["server", "online"] }
```

| Page field | Meaning |
|---|---|
| `title` | Menu text, tab title (`Title · Crafty Playz`), breadcrumb, search result. Short, Title Case. |
| `url` | Folder URL with a trailing `/` (`/games/free/`). Folder URLs also match everything inside them for breadcrumbs. |
| `description` | One short sentence, no full stop. Becomes the meta description, hub card text and search text. |
| `keywords` | Extra search words people might type (`"f2p"`, `"litematica"`). |
| `"nav": false` | Hidden from the mega-menu, still searchable and still on hub pages. Use for sub-pages and minor pages. |
| `"search": false` | Hidden from search **and** hubs. Use for viewers that need a `?id=` (Steam Game, Pet). |
| `"hub_skip": true` | Hidden from hub cards only (e.g. individual Life Series sessions). |

| Group field | Meaning |
|---|---|
| `url` | Makes the group heading a link (to a group hub page). |
| `"subnav": true` | Every page in the group gets a second nav bar under the header (the Captainalism shop). |
| `logo` | Swaps the header logo on that group's pages. |

When you **add** a page: add it to the right group with title, url, description, keywords. When you **move** a page: change `url` here and add a `RedirectMatch 301` in `.htaccess` for the old URL. When you **retire** a page: remove it here, move the files to `_dev/archive/`, and redirect the old URL. Keep each group's visible (`nav` ≠ false) list short — about 4 to 8 items; the rest go `"nav": false`.

Check after editing: the JSON parses (`php -r "json_decode(file_get_contents('data/site.json'), false, 512, JSON_THROW_ON_ERROR);"`), the page shows in its menu at 1366px **and** in the burger menu at 390px, `/` search finds it, and the breadcrumb under the header shows the right section.

---

## 5. Colours (dark theme)

All colours are CSS variables in `assets/css/global.css`. **Pages use variables only — never a hex value** (the only exceptions are content colours with real meaning, like Minecraft `§` formatting codes or syntax highlighting).

| Token | Value | Use |
|---|---|---|
| `--bg` | `#202020` | Page background |
| `--card` | `#2a2a2a` | Cards |
| `--card2` | `#323232` | Rows, tiles and nested cards inside a card |
| `--sunken` | `#1b1b1b` | Drawer, footer, code/raw blocks |
| `--line` | `#3d3d3d` | Borders |
| `--line-strong` | `#555` | Button borders, drop-zone dashes |
| `--text` | `#ececec` | Body text |
| `--muted` | `#9b9b9b` | Secondary text, meta, counts. Nothing darker than this is ever text. |
| `--accent` | `#7ed957` | Focus ring, active tab, progress, hover border. "Act" or "done" only. |
| `--accent-soft` | `#2d4224` | Selected/active background (pill tab on, picked row) |
| `--btn` / `--btn-line` | `#3d7a2a` / `#5aa83f` | Primary button |
| `--btn2` / `--btn2-hover` | `#3a3a3a` / `#474747` | Secondary button |
| `--link` | `#8cc8ff` | Links in content |
| `--highlight` | `#ffd84a` | Search hits, "new" |
| `--danger-bg` / `--danger-line` | `#4a2424` / `#8a3a3a` | Errors |
| `--info-bg` / `--info-line` | `#24384a` / `#3a628a` | Notes |

Rules:
- **Three surface levels only:** `--bg` → `--card` → `--card2`. Depth comes from these steps, never from shadows. Shadows are only for things that float (drawer, menus, dialogs).
- **Green means act or done.** Never decoration. Status uses tinted backgrounds with a matching border (`.badge.g`, `.ok`, `.err`, `.note`), never bright fills.
- **Green is the accent on every page.** Pages and sections never re-tint it or any other token.
- `color-scheme: dark` is already set, so scrollbars, inputs and date pickers are dark. Don't fight it.
- No gradients, glows or borders-inside-borders. (Exceptions already in components: the progress bar fill, the drop-zone stripes.)

---

## 6. Font, sizes and spacing

**Font:** `var(--font)` = `system-ui, -apple-system, "Segoe UI", Roboto, sans-serif`. **Code/IDs:** `var(--mono)` = `ui-monospace, Consolas, monospace`. No web fonts, no icon fonts, no Font Awesome — they delay text and cost bytes. Icons: see §8 Icons.

| Text | Size / weight | Where it's set |
|---|---|---|
| Page title `h1.page-title` | `clamp(24px, 4vw, 34px)`, bold, line-height 1.2 | `global.css` |
| Hero / landing title | `clamp(26px, 5vw, 42px)` | `hero.css` |
| Card heading `.card h2` | 17px, 600, icon 22px | `components.css` |
| Sub-heading inside a card `h3` | 15px | page CSS |
| Body text in `.page` | browser default (16px), line-height 1.5 | inherited — don't set it |
| Lede `.page-lede` | body size, `--muted` | `global.css` |
| Controls, rows, buttons, fields | 14px | `.btn`, `.rowi`, `.field` |
| Small button, meta, breadcrumbs, counts | 13px, `--muted` | `.btn.small`, `.crumbs` |
| Badges, pills, tile labels | 12px | `.badge`, `.tile span` |
| Code, IDs, raw data | `var(--mono)` 12–13px | `.mono`, `.tree` |

Never set `font-family` or a body `font-size` in a page stylesheet. Use these sizes; don't invent 15.5px or 18px.

**Spacing scale:** 4 · 6 · 8 · 10 · 12 · 16 · 20 · 24px. Nothing else.

| Thing | Value |
|---|---|
| Card padding | 16px |
| Gap between cards | 16px (`.grid`, `.masonry`) |
| Gap inside lists | 6px (`.rows`); 8px (`.tiles`, `.badges` 6px) |
| Title → lede → content | 4px → 20px (already set) |
| Radius | `var(--radius)` 8px controls · `var(--radius-lg)` 12px cards · 999px pills |
| Content width | max 1280px, 16px side padding (`.page` does this) |

**Numbers and dates:** `UI.num(n)` / `toLocaleString()` for numbers, `UI.ago(ms)` for "3 days ago" with the exact date in `title=""`, IDs in `.mono`. Counts go in the card heading: `<h2>Inventory <span class="n">36 stacks · 1,204 items</span></h2>`.

---

## 7. Layout that fits every screen

**Do**
- Build mobile-first at 320px, then let it grow.
- Grids that choose their own columns: `grid-template-columns: repeat(auto-fill, minmax(260px, 1fr))` (`.grid`), `minmax(120px, 1fr)` for tiles, `columns: 3 340px` (`.masonry`) for mixed-height cards.
- `auto-fit` instead of `auto-fill` when few items should stretch to fill the row (server detail columns).
- `flex-wrap: wrap` on every toolbar and button row.
- `clamp()` for headings and repeated UI sizes (the NBT viewer's `--s: clamp(30px, 8.6vw, 46px)` for slots).
- `min-width: 0` on grid/flex children that hold text, plus `overflow: hidden; text-overflow: ellipsis; white-space: nowrap` on long names.
- Panels sized `width: min(460px, 100vw)` — full-screen on phones for free.
- Images: `width: 100%; height: auto; aspect-ratio: W / H` plus `width`/`height` attributes, so nothing jumps while loading.
- Wide data (tables, code, transcripts): wrap only that block in `overflow-x: auto`.
- Tap targets at least 40px tall on touch.

**Don't**
- Scroll the page sideways at any width. Ever.
- Put fixed pixel widths on containers.
- Make separate mobile and desktop markup, or sniff the user agent.
- Hide anything essential behind hover.
- Use `body { overflow: hidden }` or `100vh` on content pages.
- Add a breakpoint "just in case". The header has one (900px); most pages need zero.

Test widths: **320, 390, 768, 1366, 2560**.

---

## 8. Components (`components.css`, opt-in with `'components' => true`)

Build with these before writing any CSS. If a page needs something new that other pages could use, request it for `components.css` in `requests.md` and keep a local copy in the page stylesheet meanwhile.

```html
<!-- buttons: one .primary per view -->
<button class="btn primary">Save</button> <button class="btn">Cancel</button> <a class="btn small" href="...">Open</a>

<!-- cards in a self-filling grid (or .masonry for mixed heights) -->
<div class="grid">
  <section class="card"><h2>Stats <span class="n">12</span></h2> ... </section>
</div>

<!-- headline numbers -->
<div class="tiles"><div class="tile"><b>1,204</b><span>items</span></div></div>

<!-- status labels: g=green r=red p=purple b=blue -->
<div class="badges"><span class="badge g">Online</span><span class="badge">Survival</span></div>

<!-- row list with icon, text, value -->
<div class="rows"><div class="rowi"><img src="" alt="" width="24" height="24"><span class="grow">Name</span><small>value</small></div></div>

<!-- key/value facts -->
<div class="kv"><span>Version</span><span>1.21</span></div>

<!-- progress -->
<div class="progress"><i style="width:40%"></i></div>

<!-- jump links on long pages (only for sections that exist) -->
<nav class="jump"><a href="#stats">Stats</a><a href="#raw">Raw</a></nav>

<!-- pill tabs inside a card; active gets .on -->
<div class="stabs"><button class="on">Recent</button><button>Rarest</button></div>

<!-- sticky toolbar for tool/list pages -->
<div class="bar"><input class="field" type="search" placeholder="Search..." aria-label="Search"> <span class="muted" id="count"></span></div>

<!-- messages -->
<p class="err">Couldn't load X. Try again in a minute.</p> <p class="note">Loading...</p> <p class="ok">Saved.</p>

<!-- file drop zone (add .over while dragging) -->
<div class="drop">Drop a file here or <button class="btn small">choose one</button></div>

<!-- details drawer (toggle .open) -->
<aside class="drawer" id="drawer" aria-label="Details" tabindex="-1"><button class="close" aria-label="Close">&times;</button><div id="detail"></div></aside>

<!-- native dialog: dlg.showModal() / dlg.close() -->
<dialog class="dlg"><h2>Share</h2> ... <div class="actions"><button class="btn">Close</button></div></dialog>

<!-- raw JSON/NBT -->
<div class="tree">...</div>

<!-- table: always in .table-wrap; .num right-aligns numbers; .table-wrap.tall caps the height with a sticky header -->
<div class="table-wrap"><table class="table">
  <thead><tr><th>Item</th><th class="num">Count</th></tr></thead>
  <tbody><tr><td>Glass</td><td class="num">3,456</td></tr></tbody>
</table></div>

<!-- form: each label wraps its text and field; .cols = side by side, stacks on phones -->
<form class="form form-card">
  <label>Player name <input class="field" name="player"></label>
  <div class="cols">
    <label>Dimension <select class="field"><option>Overworld</option></select></label>
    <label>Amount <input class="field" type="number" inputmode="numeric" min="1" max="64" aria-invalid="true"><small>Enter a number from 1 to 64.</small></label>
  </div>
  <fieldset><legend>Show</legend><label class="check"><input type="radio" name="show" checked> All items</label></fieldset>
  <label class="check"><input type="checkbox"> Remember these settings</label>
  <div class="actions"><button class="btn primary">Save settings</button><button class="btn" type="reset">Reset</button></div>
</form>
```

Helpers: `.muted`, `.mono`, `.hide`. `global.css` also gives `.hub`/`.hub-card` (via `site_hub()`), `.crumbs`, `.page-title`, `.page-lede`.

**Forms**
- Every field sits inside its `<label>`, label text first (no `id` needed). A placeholder is only an example value, never the label.
- One field per row by default; `.cols` only for short fields that belong together. Forms sit in `.form-card` (max 560px).
- Mark optional fields "(optional)"; never asterisks on required ones.
- `type="number"` + `inputmode`/`min`/`max` for numbers, `<select>` for more than 4 fixed choices, radios in a `<fieldset>` for 2 to 4, a `.check` checkbox for yes/no.
- Errors: `aria-invalid="true"` on the field (red border) and what to fix in the label's `<small>`. Check on blur or send, not every key press. A failed send shows one `.err` above the buttons.
- Tools that calculate update as you type (debounced) with no button. A button is for actions that send, save or take a while: the view's one `.primary`, a verb ("Save settings", not "Submit"), disabled with "Saving..." while working.
- Buttons go last, in an `.actions` row, primary first.

**Icons**
- Only where they help people recognise something faster: outside services (GitHub, Discord, Steam), game items, and well-known controls (close, search, copy, menu). Never decoration on headings or buttons that already have clear text.
- UI icons: inline SVG in `currentColor`; add new ones to `site_icon()` in `includes/functions.php` rather than pasting the same SVG into several pages.
- Service logos: `/assets/img/icons/` (white 24×24 SVGs) as an `<img>` beside the name. Minecraft items: `/minecraft/tools/item-list/`, `image-rendering: pixelated` above 16px.
- Sizes: 16px beside text and in buttons, 22px in card headings (automatic), 24px in row lists.
- Icon-only buttons need `aria-label` and `title`; decorative icons get `alt=""` or `aria-hidden="true"`.
- No icon fonts, no Font Awesome, no emoji as icons or in titles, headings, menus or buttons.

**Page stylesheets** (`assets/css/pages/<section>-<name>.css`):
- Start with a one-line comment naming the page.
- Prefix every class with a short page tag (`.ftg-`, `.srv-`, `.dnd`) so nothing leaks.
- Tokens only. No `transition: all`. No `!important` except to beat legacy styles.
- A stylesheet another tab uses is shared — copy, don't edit (README §2).
- A few lines of page CSS may sit in a `<style>` in `<head>` (servers page); anything over ~40 lines goes in a file so it caches.

---

## 9. Data and APIs

Pick the pattern by where the data can come from:

| Situation | Pattern | Example |
|---|---|---|
| Static content | Write it in the PHP/HTML | About |
| API needs a key, or no CORS, or is rate-limited | **Server-side fetch + disk cache** | Steam, D&D, NBT `api.php` |
| Public, CORS-enabled, keyless API | **Browser fetch** | Free Games, Server Status |
| Lots of data the page owns | JSON file in the page folder, loaded by PHP | `characters.json` |

### 9a. Server-side fetch with a disk cache

Every PHP call to an external API is cached. Copy `dnd_get()` (`extras/dnd/index.php`) or `steam_fetch()` (`games/steam/steam.php`); they already do all of this:

- **Cache file** keyed by `md5($url)` so keys never appear in file names. Store it in `sys_get_temp_dir() . '/craftyplayz-<name>'` (like Steam) so nothing lands in the repo. If it must live in the page folder, add the folder to `.gitignore`.
- **Lifetime per data type:** live status 5 min · profiles/characters 10 min · libraries 1 h · rules/catalogue data 1 day.
- **Stale fallback:** a failed refresh returns the last good copy and says so (`'stale' => true` → show "Showing data from 2 hours ago"). An outage never blanks the page.
- **Remember misses:** a 4xx writes a `.miss` file that stops retries for the cache lifetime (or an hour), so bad IDs can't hammer the API.
- **Timeouts:** `CURLOPT_CONNECTTIMEOUT => 5`, `CURLOPT_TIMEOUT => 10–12`, `CURLOPT_ENCODING => ''` (gzip). Set a `CURLOPT_USERAGENT` that names the site.
- **Many URLs:** fetch in parallel with `curl_multi` and cap how many stale entries refresh per view (`$refresh`), so a 100-game page never waits on 100 requests.
- **Trim before caching:** keep only the fields the page uses (`dnd_trim_rules` cuts 540 KB to a few KB).
- **Parallel endpoints** (an `api.php` hit by many `<img>` at once): call `session_write_close()` first, and send real `Cache-Control` headers (`public, max-age=2592000, immutable` for things that never change).
- **Keys:** `'config' => true`, use `$steamkey` etc. only in PHP. A browser request that needs a keyed API goes through a small PHP proxy in the page folder (`books/covers/bulk/api.php`, NBT `api.php`) that whitelists what it forwards.
- **Pass data to JS** with `<?= json_encode($data) ?>` inside a `<script>` — not a second fetch.

### 9b. Browser fetch

- Server-render the page shell (title, lede, toolbar, empty grid) so the layout is visible instantly.
- Show `<p class="note" id="status">Loading games...</p>`; on success remove it and render; on failure turn it into `.err` with a plain sentence ("Couldn't reach FreeToGame. Try again in a minute."). Never leave a spinner forever, never show a raw error object.
- Each item shows its own state when items load independently ("Checking…", "Check failed" — Server Status).
- `<link rel="preconnect" href="https://api.example.com">` in `<head>` for hosts needed straight away.
- Poll only as often as the API's own cache updates (servers: 5 min), and stop when the tab is hidden if it's frequent.
- Guard against races: when a detail request returns, check it's still the one on screen (`if (d.id !== id) return;`).
- Credit the source in the lede or a muted line at the bottom ("Data from mcsrvstat.us, cached for 5 minutes").

### 9c. Rendering dynamic data safely and fast

- **Escape everything** from an API: `h()` in PHP, `UI.esc()` in JS, before it touches `innerHTML`. Never use an API's own `html` field (MOTD example in `minecraft/servers`).
- **Hide what's empty:** build helpers that return `''` for missing values (`tile()`/`kv()` in Server Status filter out empty rows), so cards only appear when there's data.
- **Large lists:** render in chunks of ~250 with "Show more"; build tree/hidden content only when opened; cap search results (~300).
- **Filtering:** debounce input ~80ms (`UI.debounce`), then filter an in-memory array or toggle classes — don't re-fetch.
- **State in the URL:** selected item in `location.hash` or a query param via `history.replaceState`, so links are shareable and reload keeps state. Private data (IPs, chat, passwords, seeds) never goes in a share link and stays click-to-reveal.
- **Event delegation:** one listener on the container routing on `data-id` / `data-act`. No inline `onclick`.
- **Images from APIs:** `loading="lazy" decoding="async"` past the first screenful (first ~9 eager), set `width`/`height`, and give a fallback (`onerror` → local placeholder) so there's never a broken-image icon.
- **Below-the-fold heavy blocks:** `content-visibility: auto; contain-intrinsic-size: auto 200px;`.

---

## 10. JavaScript conventions

- Plain JS, no framework, no build step, no jQuery. A library only for one job and loaded on first use (skinview3d in the NBT viewer), with the page still working if it fails.
- Wrap page code in an IIFE or `document.addEventListener('DOMContentLoaded', () => { ... })` (needed when using `UI`, which is `defer`).
- Put page scripts at the end of `<body>` or as `defer` files. Nothing render-blocking.
- `UI` helpers (`'ui' => true`): `$`, `$$`, `esc`, `store.get/set` (safe localStorage), `debounce`, `toast(msg, 'ok'|'err'|'note')`, `copy(text, button)`, `ago(ms)`, `num(n)`.
- **Keyboard:** `/` focuses the site search (or your page search — call `e.preventDefault()` on `/` in your handler and the site one stays out of the way). `Esc` closes the innermost open thing (drawer, dialog, menu). Enter activates.
- Every action answers within 100ms: disable the button and change its text while working, "Copied!" for 1s, toasts that go away on their own (errors ~9s, others ~4s).
- Remove `console.log` debugging before committing.
- Analytics (Umami + GA) are added by `page_head()`/`site.js`. Don't add more trackers.

---

## 11. Speed budget

| Measure | Target |
|---|---|
| Content visible on 4G | < 1 s |
| HTML + CSS + JS over the network (gzip) | < 60 KB content page · < 150 KB tool |
| Render-blocking third-party requests | 0 |
| Layout shift after load | none (every image/embed has dimensions) |
| Interaction response | < 100 ms |

- PHP renders the first view, including the header (never fetched by JS, except plain `.html` pages).
- Static files are versioned by `asset()` (`?v=<mtime>`), so they can be cached for a year.
- Images: WebP/AVIF; galleries show ~400px thumbnails and open the full file on click.
- Animations, canvases and polling stop when offscreen (`IntersectionObserver`) or the tab is hidden.
- Footer extras (the cat fact) load in `requestIdleCallback` — follow that for any nice-to-have.

---

## 12. Motion

| Use | Duration | Property |
|---|---|---|
| Hover/focus colour | `var(--fast)` 120ms | background, border-color, color |
| Press | 60ms | `translateY(1px)` |
| Caret flip | 120ms | rotate |
| Drawer/panel | `var(--base)` 180ms ease-out | transform |
| Menu/dropdown | 120–150ms ease-out | opacity + 4px translate |
| Scroll to section | browser default | `scrollIntoView({behavior: 'smooth'})` |

Never: entrance/scroll-reveal animations, staggered cards, animating width/height/top/margin, `transition: all`, transitions over 200ms, colour flashes on hover. `global.css` already turns motion off for `prefers-reduced-motion`; loops you add in JS must check `matchMedia('(prefers-reduced-motion: reduce)')` too.

---

## 13. Writing and clutter

**Writing: British English, plain words**
- British spelling: colour, centre, organise, favourite, grey, licence (noun), metres.
- Plain words, short sentences. No hype, jokes or exclamation marks in interface text (titles, ledes, labels, buttons, messages). A joke page's content can be silly; the interface around it stays plain.
- Title Case for page titles and menu items (from `site.json`); sentence case for everything else (card headings, buttons, labels, tabs).
- Buttons start with a verb and say what happens ("Copy link"). Never "OK", "Submit" or "Click here". Link text says where it goes.
- No full stop on titles, headings, labels, buttons or `site.json` descriptions. Ledes, help text and messages are sentences and get one.
- Digits with thousands commas (1,204), a space before units (64 MB, 5 min), dates as day month year (20 Oct 2026).
- Talk to the reader as "you"; contractions are fine. Messages say what happened and what to do, without blame or error codes.

**Clutter**

- One job per page. One `.btn.primary` per view; everything else is a secondary button or a link.
- One muted line under the title, not a paragraph. Help text is one muted line where it's needed.
- Overview first → details on click (drawer, `<details>`, "Show all 312") → raw data last, collapsed.
- No empty cards, no "0 results" headings, no "coming soon" sections (one compact prompt at most).
- No autoplay, no popups, no flashing on load (the flashing-colours page keeps its warning gate).
- Muted text instead of extra headings or boxes.

---

## 14. Accessibility and safety

- One `<h1>`, headings in order. `<button>` for actions, `<a>` for navigation, never clickable `<div>`s.
- Every input has a `<label>` or `aria-label`; every image has `alt` (`alt=""` if decorative).
- Visible focus: `:focus-visible` ring in `--accent` is global for `.page` — never remove an outline without a replacement.
- `aria-expanded` on toggles, `aria-hidden`/`tabindex="-1"` + focus on drawers, native `<dialog>` for modals (free focus trap and Esc).
- External links: `target="_blank" rel="noopener"`.
- Escape all output (`h()`, `UI.esc()`), keys stay server-side, user files are parsed in the browser and only uploaded on an explicit action.

---

## 15. Testing

Full procedure: README §5 (portable PHP, your own port and Chrome debug port). Minimum for every page:

1. `php -l` every PHP file you changed.
2. Serve with `php -S 127.0.0.1:<port> -t .` and load the page.
3. Headless Chrome screenshots at **1366px and 390px** (also try 320px for anything with a grid or toolbar). Look at them.
4. No PHP warnings, no JS console errors, no 404s for same-site files, `document.documentElement.scrollWidth <= innerWidth` at every width.
5. API pages: test the failure path too (block the host or use a bad ID) — the page must show `.err`, not a blank or a stack trace.
6. Delete runtime files your test created (cache files, `thegame.json`) before committing.

---

## 16. Checklist (every page, before commit)

**Structure**
- [ ] Built from `_templates/page.php`; no hand-written `<title>`/meta/global CSS links
- [ ] In `data/site.json` with title, url (trailing `/`), description, keywords and the right `nav`/`search` flags
- [ ] Shows in its menu (desktop + burger), in `/` search, with correct breadcrumbs and tab title
- [ ] Old URL redirected in `.htaccess` if it moved

**Look**
- [ ] `<main class="page">`, `h1.page-title`, one-line `p.page-lede`
- [ ] Only components + a prefixed page stylesheet; only `var(--…)` colours; no `font-family`/body size
- [ ] Sizes and spacing from §6; radius 8/12/999
- [ ] One primary button; no empty sections; details behind a click
- [ ] Tables in `.table-wrap`; every form field inside its label; icons only where they help
- [ ] British spelling, plain words, sentence-case buttons and headings

**Screens**
- [ ] No sideways scroll at 320 / 390 / 1366 / 2560
- [ ] Grids auto-fill; long names ellipsis; images have dimensions

**Speed**
- [ ] Scripts deferred or at end of body; libraries loaded on use
- [ ] External API calls cached server-side (or a public CORS API called from the browser)
- [ ] Lazy images below the fold; big lists chunked

**Behaviour**
- [ ] Loading, empty and error states all render sensibly
- [ ] Esc closes, `/` doesn't clash, keyboard can reach everything
- [ ] All API/user text escaped; no keys in HTML; no `console.log`
- [ ] Motion ≤ 200ms, transform/opacity only

---

## 17. Known gaps (fix when touching these files)

- `.htaccess` has no long-cache rule for `/assets` yet; it caches CSS/JS for a day. `asset()` already versions URLs, so `Cache-Control: public, max-age=31536000, immutable` on `/assets/` is safe to add.
- `extras/dnd/cache/` (and `books/covers/bulk/cache/`) are not ignored as folders; `.gitignore` only lists three `char-*.json` names, so a new character's cache file can be committed by accident. Either ignore the folder or move the cache to `sys_get_temp_dir()` like Steam.
