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
**Change:** done inside `books/`. The Bulk Cover Downloader now calls `books/covers/bulk/api.php`, which uses `$googlebookskey` from `php/config.php` server-side and caches for a day. The old key (`AIzaSyBOk3…`) is no longer in the page. Owner actions in Google Cloud Console: (1) delete the old key, because it stays in git history; (2) change `$googlebookskey` from a `www.craftyplayz.com` referrer restriction to an IP restriction for the IONOS server, then remove the `CURLOPT_REFERER` line (marked `shortcut:`) from `api.php`.
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
