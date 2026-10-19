# Requests for changes to shared files

Sessions append here instead of editing shared files (see README.md, section 2). Add a heading with your section name, the file, the exact change and why.

## Tools

**File:** `.htaccess`
**Change:** add, next to the other Tools redirects:
```
RedirectMatch 301 ^/tools/reading-speed/index\.html$ /tools/reading-speed/
```
Line 155 (`/tests/speedtest/(.*)`) can stay; `/tests/speedtest/index.html` redirects to `/tools/reading-speed/index.html`, which this rule then catches.
**Why:** commit 3898abc converted the Reading Speed Test from `index.html` to `index.php` and deleted `index.html`, so old links to `/tools/reading-speed/index.html` (and `/tests/speedtest/index.html`, which redirects there) now 404.

## Books

**File:** none (no shared-file change needed); this is a note for the owner.
**Change:** done inside `books/`. The Bulk Cover Downloader now calls `books/covers/bulk/api.php`, which uses `$googlebookskey` from `php/config.php` server-side and caches for a day. The old key (`AIzaSyBOk3…`) is no longer in the page. Done 2026-10-19: the old key is deleted, and `$googlebookskey` is restricted to the IONOS server IP `82.165.88.82` and to the Books API only. If IONOS changes that IP, searches fail with 403 `API_KEY_IP_ADDRESS_BLOCKED`: update the IP on the key.
**Why:** the design guide says keys never reach the browser.

**File:** `.htaccess`
**Change:** everything under `books/harry-potter/` (the Characters page, the Character stub, `json/`, Info and the old Quiz) was moved to `_dev/archive/books/harry-potter/` at the owner's request, and the Harry Potter group was removed from `data/site.json`. Point the old URLs at the Books page:
```
RedirectMatch 301 ^/books/harry-potter(/.*)?$ /books/
```
and replace lines 146–149 (all the `^/extras/harrypotter/...` rules) with:
```
RedirectMatch 301 ^/extras/harrypotter(/.*)?$ /books/
```
**Why:** those URLs now 404.

## Games

**File:** `.htaccess`
**Change:** every Hypixel Skyblock page (`/games/hypixel/` and everything under it) and `assets/css/pages/hypixel-collection.css` were moved to `_dev/archive/hypixel/` at the owner's request, and the Hypixel group was removed from `data/site.json`. Point the old URLs at the Games page. Replace lines 57–59 and 122–131 (all the `^/hypixel/...` rules) with:
```
RedirectMatch 301 ^/(games/)?hypixel(/.*)?$ /games/
```
and delete line 52 (`/css/hypixel/collection.css`), whose target no longer exists.
**Why:** those URLs now 404.

**File:** `data/links.json`
**Change:** remove the `"melon"` short link (line 2). It points at `/games/hypixel/collections/track/?item=MELON&name=Melon`, which is archived; with the rule above it would land on `/games/`.
**Why:** a short link to an archived page.

**File:** `.htaccess` (Games, 2026-10-19)
**Change:** Geriant Bingo was moved to `_dev/archive/bingo/` at the owner's request. Add:
```
RedirectMatch 301 ^/games/bingo(/.*)?$ /games/
```
and on line 53 (`/css/(books|dnd|factorio|gallery|...|steam|steamnew|tools)\.css`) drop `factorio`, `steam` and `steamnew`: those stylesheets are archived or deleted, so the redirect targets 404.
**Why:** the old Bingo URL now 404s. (Guess Who needs nothing: `games/guesswho/og/` and `atla/` redirect to the new page themselves.)

## Extras
- **`data/site.json`**, line 232: change the D&D page's title from `"D&D Character"` to `"D&D Characters"` and its description from `"D&D character sheet"` to `"D&D Beyond character sheets"`.
**Why:** `/extras/dnd/` is now a chooser listing every character in `extras/dnd/characters.json` (each opens as `?id=`), so the plural matches the page heading and the breadcrumbs on the sheet pages.

## Minecraft
**File:** `assets/css/components.css`
**Change:** after the `.btn.primary:hover` rule (line 21), add:
```
.page a.btn { color: var(--text); text-decoration: none; }
.page a.btn.primary { color: #fff; }
```
**Why:** `global.css` line 213 (`.page a { color: var(--link); }`) is more specific than `.btn`, so every `<a class="btn">` on a restyled page gets blue link text instead of the button colour. Minecraft works around it in its own page stylesheets (`minecraft-gallery.css`); this fixes it for every section.

**File:** `.htaccess`
**Change:** on line 53 (`/css/(books|dnd|factorio|gallery|homepage|minecraft|...)\.css`) drop `minecraft`.
**Why:** `assets/css/pages/minecraft.css` was deleted (no live page used it after the Minecraft restyle), so that redirect target now 404s.
