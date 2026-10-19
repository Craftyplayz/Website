# Handoff: restyle the Tools tab

**Read `_dev/handoffs/README.md` first.** It holds the rules shared by every section: one or two pages at a time, the design guide in `/plan.html`, staying inside your tab, safe git commands and testing. Your test port is **8104** and your Chrome debug port is **9404**.

## Your files
- **Folders you may edit:** `tools/` (everything below it).
- **Stylesheets that are yours:** `assets/css/pages/tools.css` and `assets/css/pages/speedtest.css`, plus any new `assets/css/pages/tools-*.css`.

## Don't touch
- `tools/index.php` and `tools/random/index.php`: already modern card grids.
- `tools/calculator.html` and `tools/clip.html`: they only launch Windows apps (`ms-calculator://`), so there's no UI.
- `tools/reading-speed/texts/`: data.

## Pages, in suggested order
| Page | File | Notes |
|---|---|---|
| QR Code Generator | `tools/qr-code/index.php` | Made in the browser with the vendored `tools/qr-code/qrcode.js` (qrcode-generator 1.4.4, MIT). No network requests. Page CSS: `assets/css/pages/tools-qr-code.css`. |
| Random Number | `tools/random/number/index.php` | Uses `tools.css`. |
| Random Image | `tools/random/image/index.php` | Has a 2000px × 500px button, now capped with `max-width: 100%`. |
| Reading Speed Test | `tools/reading-speed/index.html` | **Plain HTML**, not PHP. The header and footer come from `<div id="site-header">` / `<div id="site-footer">` placeholders filled in by `/assets/js/site.js`. It still links `/assets/css/legacy.css` and `speedtest.css`. Texts load from `texts/` through relative paths. You may convert it to `index.php` with the normal skeleton; if you do, delete `index.html` in the same commit. |
| Docker Install Script | `tools/docker-script/index.php` | A form that builds a script. |
| Install Script | `tools/install-script/index.php` | Click-to-copy command lines. |
| Secret Santa | `tools/secret-santa/index.html` | A full-screen app with its own festive styling and no site header. Pairing links already sent to people point at `pairing.html?...`, so keep that file name and its query format. **Ask the user** before adding the site header or changing the theme. |
| Secret Santa Pairing | `tools/secret-santa/pairing.html` | Same as above. Opened from shared links. |
