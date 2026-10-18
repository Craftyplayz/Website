# Handoff: restyle the Games tab

**Read `_dev/handoffs/README.md` first.** It holds the rules shared by every section: one or two pages at a time, the design guide in `/plan.html`, staying inside your tab, safe git commands and testing. Your test port is **8102** and your Chrome debug port is **9402**.

## Your files
- **Folders you may edit:** `games/` (everything below it).
- **Stylesheets that are yours:** `assets/css/pages/steam.css`, `steamnew.css`, `factorio.css` and `hypixel-collection.css` (only Games pages use them), plus any new `assets/css/pages/games-*.css`.

## Don't touch
- `games/chapterguesser/`: it has its own finished UI, a live leaderboard (`data/leaderboards.json`, server only) and tests.
- `games/adarkroom/`: a third-party game.
- `games/index.php`, `games/hypixel/index.php`, `games/quizzes/index.php`: already modern card grids.
- `games/factorio/map/`: a full-screen iframe.
- **Live data:** `games/the-game/thegame.json` (gitignored, server only). Testing The Game locally creates it, so delete it before committing.

## Pages, in suggested order
| Page | File | Notes |
|---|---|---|
| Steam Stats | `games/steam/index.php` | Big (about 200 lines). `.gamename { left: 352px }` in `steamnew.css` scrolls the page sideways. Reads `games/steam/data/`. Needs `'config' => true`. |
| Steam Game | `games/steam/game/index.php` | The achievements table and info box. `?game=` is digits only, so keep that check. |
| Retro Achievements | `games/retro/index.php` | Uses `steamnew.css`. It links `/retro/game?game=`, a page that has never existed: drop the link or ask. |
| Factorio Saves | `games/factorio/index.php` | A thumbnail gallery from `assets.craftyplayz.com`. |
| Factorio Command Generator | `games/factorio/commands/index.php` | A plain text tool. |
| HP Quiz index | `games/quizzes/harry-potter/index.php` | Links the 4 levels. |
| HP Quiz Easy, Medium, Hard, Extra Hard | `games/quizzes/harry-potter/<level>/index.php` | The 4 pages share the same markup. Each loads jQuery 3.3.1 plus its own `assets/js/scripts.js` and `assets/css/styles.css` through relative paths, so the URL must end in `/`. Extra Hard has tests in `tests/`. Rework one level, then copy it to the rest. |
| Collection Tracker | `games/hypixel/collections/index.php` | Hypixel API. Needs `'config' => true`. |
| Collection (track) | `games/hypixel/collections/track/index.php` | Needs `?item=&name=&profile=`. Fatal error when the API returns nothing (this happened before the reorg too). |
| Melon War | `games/hypixel/collections/melon-war/index.php` | Hypixel API. |
| Minions | `games/hypixel/minions/index.php` | Hypixel API. |
| Ghast Tear Calculator | `games/hypixel/ghast-calculator/index.php` | Built with PHP `echo`. Calls `die()` when the API fails. |
| Regex Builder | `games/hypixel/regex-builder/index.php` | A small JS tool. |
| Yu-Gi-Oh Guide, Next Packs, Sets, Extra Rules | `games/yugioh/{,next-packs/,sets/,extra-rules/}index.php` | Text guides; one shared style fits all 4. |
| Skyrim Mods | `games/skyrim/index.php` | A link list. |
| The Game | `games/the-game/index.php` | Writes `thegame.json`, see above. |
| Guess Who, Guess Who: Avatar | `games/guesswho/{og,atla}/` | Full-screen games with no site header. Ask the user before adding one. |
| Geriant Bingo | `games/bingo/geriant/index.html` | Full-screen, with no site header. Ask the user first. |
