# Handoff: restyle the Games tab

**Read `_dev/handoffs/README.md` first.** It holds the rules shared by every section: one or two pages at a time, the design guide (`building-pages.md`, and `/design/` for live samples), staying inside your tab, safe git commands and testing. Your test port is **8102** and your Chrome debug port is **9402**.

## Your files
- **Folders you may edit:** `games/` (everything below it).
- **Stylesheets that are yours:** any `assets/css/pages/games-*.css`. (`steam.css`, `steamnew.css`, `factorio.css` and `hypixel-collection.css` are archived or deleted.)

## Don't touch
- `games/chapterguesser/`: it has its own finished UI, a live leaderboard (`data/leaderboards.json`, server only) and tests.
- `games/adarkroom/`: a third-party game.
- `games/index.php`, `games/quizzes/index.php`: already modern card grids.
- `_dev/archive/hypixel/`: every Hypixel Skyblock page was archived at the owner's request on 2026-10-19. Don't restyle them.
- `_dev/archive/bingo/` (Geriant Bingo) and `_dev/archive/guesswho/template/` were archived at the owner's request on 2026-10-19.
- **Guess Who** (`games/guesswho/index.php`) finds its boards by scanning its own folder: each subfolder of images is a board. Don't add per-board code; `og/` and `atla/` are old-URL redirects.
- `games/factorio/map/`: a full-screen iframe.
- **Live data:** `games/the-game/thegame.json` (gitignored, server only). Testing The Game locally creates it, so delete it before committing.

## Pages, in suggested order
| Page | File | Notes |
|---|---|---|
| Steam Stats | `games/steam/index.php` | Reworked 2026-10-19: profile overview. Library, Achievements and Wishlist are sibling folders. All Steam calls go through `games/steam/steam.php` (parallel curl, temp-dir cache, `STEAM_HIDE`). `games/steam/data/` is deleted. Never show `GetPlayerBans` data. `games/steam/cron.php` runs hourly, from the server crontab or an online cron service over HTTP with the `$steamcronkey` from `php/config.php` (404 without it): it refreshes the cache and records today's row of the profile History charts in `profile/cache/history.json` via `steam_history()`. |
| Steam Game | `games/steam/game/index.php` | Store info from `IStoreBrowseService/GetItems`, rarity, news. `?game=` is digits only, so keep that check. |
| Retro Achievements | `games/retro/index.php` | Uses `steamnew.css`. It links `/retro/game?game=`, a page that has never existed: drop the link or ask. |
| Factorio Saves | `games/factorio/index.php` | A thumbnail gallery from `assets.craftyplayz.com`. |
| Factorio Command Generator | `games/factorio/commands/index.php` | A plain text tool. |
| HP Quiz index | `games/quizzes/harry-potter/index.php` | Links the 4 levels. |
| HP Quiz Easy, Medium, Hard, Extra Hard | `games/quizzes/harry-potter/<level>/index.php` | The 4 pages share the same markup. Each loads jQuery 3.3.1 plus its own `assets/js/scripts.js` and `assets/css/styles.css` through relative paths, so the URL must end in `/`. Extra Hard has tests in `tests/`. Rework one level, then copy it to the rest. |
| Yu-Gi-Oh Guide, Next Packs, Sets, Extra Rules | `games/yugioh/{,next-packs/,sets/,extra-rules/}index.php` | Text guides; one shared style fits all 4. |
| Skyrim Mods | `games/skyrim/index.php` | A link list. |
| The Game | `games/the-game/index.php` | Writes `thegame.json`, see above. |
