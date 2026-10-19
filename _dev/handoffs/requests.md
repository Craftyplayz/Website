# Requests for changes to shared files

Sessions append here instead of editing shared files (see README.md, section 2). Add a heading with your section name, the file, the exact change and why.

## Done (2026-10-19, applied by the Games session at the owner's request)
- **Tools:** `.htaccess` redirects `/tools/reading-speed/index.html` to `/tools/reading-speed/`.
- **Books:** `.htaccess` sends `/books/harry-potter/...` and `/extras/harrypotter/...` to `/books/`. The Google Books key note needed no change (the covers tool now uses Open Library).
- **Games:** `.htaccess` sends `/hypixel/...`, `/games/hypixel/...` and `/games/bingo/...` to `/games/`; the `/Code/jelly` and `/css/hypixel/collection.css` redirects are gone; the `/css/*.css` redirect no longer lists `factorio`, `minecraft`, `steam` or `steamnew`. `data/links.json` no longer has the `melon` short link.
- **Extras:** `data/site.json` lists the D&D page as "D&D Characters" / "D&D Beyond character sheets".
- **Minecraft:** `assets/css/components.css` gives `<a class="btn">` the button colour instead of the link colour (`.page a.btn`, `.page a.btn.primary`).

## Open
