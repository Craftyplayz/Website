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
