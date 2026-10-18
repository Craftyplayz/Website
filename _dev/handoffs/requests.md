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
**Change:** `books/covers/bulk/index.php` calls Google Books from the browser with a key written into the page (`AIzaSyBOk3…`), and the repo is public. `php/config.php` already defines a separate `$googlebookskey`, so the fix fits inside `books/`: add `books/covers/bulk/api.php` that forwards `q` and `maxResults` to Google Books with `$googlebookskey`, caches results on disk for a day, and sends real `Cache-Control` headers. Then remove the key from the page. Owner action: restrict or delete the exposed key in Google Cloud Console, because it stays in git history.
**Why:** the design guide says keys never reach the browser.

**File:** `.htaccess`
**Change:** the Harry Potter Characters page (`/books/harry-potter/`) and its Character stub (`/books/harry-potter/character/`) were moved to `_dev/archive/books/harry-potter/` at the owner's request. Point their old URLs at the Books page:
```
RedirectMatch 301 ^/books/harry-potter/?$ /books/
RedirectMatch 301 ^/books/harry-potter/character/?$ /books/
```
and change lines 146–147 (`/extras/harrypotter/` and `/extras/harrypotter/characters`) to redirect to `/books/` instead of `/books/harry-potter/`. Line 148 must keep `info` and `quiz` but drop `character`; line 149 (`/json/`) can go, since the JSON is archived.
**Why:** those URLs now 404.
