# Handoff: restyle the Games section

## The job
Restyle the pages in the **Games** menu so they match the site's new design. The pages were already moved into `/games/` and use the new header and footer (branch `reorg`, phase 6, commit `45789ff`). What's left is how they **look**: most still use the old site styles.

## Rules
1. **Rework one or two pages per session, no more.** Finish them, test them, commit them, then stop and report back. Don't start the next page without the user saying so.
2. **Follow the design guide in `/plan.html`** (open the file and use the **Design guide** tab). It covers the colour tokens, type sizes, spacing, layout, motion limits, components, page anatomy and a per-page checklist. Every page must pass that checklist.
3. **Reference design:** `/minecraft/tools/nbt-viewer/` is the page every other page should look and feel like.
4. **Change the look, not the behaviour.** Keep each page's features, URLs, query parameters (`?game=`, `?item=`, `?profile=`) and data files exactly as they are.
5. Don't touch `games/chapterguesser/` or `games/adarkroom/`. The first has its own finished UI and the second is a third-party game.

## Pages to rework (pick from the top)
| Page | File | Notes |
|---|---|---|
| Steam Stats | `games/steam/index.php` | Big (about 200 lines). `.gamename { left: 352px }` in `assets/css/pages/steamnew.css` makes the page scroll sideways. Reads `games/steam/data/`. Needs `'config' => true`. |
| Steam Game | `games/steam/game/index.php` | The achievements table and info box. `?game=` is digits only, so keep that check. |
| Retro Achievements | `games/retro/index.php` | Uses `steamnew.css` like Steam. It links to `/retro/game?game=`, a page that has never existed: remove the link or ask. |
| Factorio Saves | `games/factorio/index.php` | A thumbnail gallery from `assets.craftyplayz.com`. |
| Factorio Command Generator | `games/factorio/commands/index.php` | A plain text tool. |
| HP Quiz index | `games/quizzes/harry-potter/index.php` | Links the 4 levels. |
| HP Quiz Easy, Medium, Hard, Extra Hard | `games/quizzes/harry-potter/<level>/index.php` | The 4 pages share the same markup. Each loads jQuery 3.3.1 plus its own `assets/js/scripts.js` and `assets/css/styles.css` through relative paths, so the folder URL must end in `/`. Extra Hard has tests in `tests/`. Rework one level, then copy it to the rest. |
| Collection Tracker | `games/hypixel/collections/index.php` | Calls the Hypixel API with the keys from config. |
| Collection (track) | `games/hypixel/collections/track/index.php` | Needs `?item=&name=&profile=`. Crashes with a PHP fatal error when the API returns nothing, which happened before the move too. |
| Melon War | `games/hypixel/collections/melon-war/index.php` | Calls the Hypixel API. |
| Minions | `games/hypixel/minions/index.php` | Calls the Hypixel API. |
| Ghast Tear Calculator | `games/hypixel/ghast-calculator/index.php` | Built with PHP `echo`. Calls `die()` when the API fails. |
| Regex Builder | `games/hypixel/regex-builder/index.php` | A small JS tool. |
| Yu-Gi-Oh Guide, Next Packs, Sets, Extra Rules | `games/yugioh/{,next-packs/,sets/,extra-rules/}index.php` | Text guides; one shared style fits all 4. |
| Skyrim Mods | `games/skyrim/index.php` | A link list. |
| The Game | `games/the-game/index.php` | Writes live data to `thegame.json` (gitignored, and lives on the server only). Testing locally creates that file, so delete it before committing. |
| Guess Who, Guess Who: Avatar | `games/guesswho/{og,atla}/` | Full-screen games with no site header. Ask the user before adding one. |
| Geriant Bingo | `games/bingo/geriant/index.html` | Full-screen, with no site header. Ask the user first. |

## How a page is built now
- **Top of the file:** `$site_page = [...]` and then `require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';`. In `<head>` call `page_head()`, and in `<body>` call `site_header()` … `site_footer()`. See `_templates/page.php` for every option.
- **Legacy pages:** pages not restyled yet have `'legacy' => true` and `<main class="page page-legacy">`. That loads `assets/css/legacy.css`, the old styles.
- **Restyling a page means:**
  - Remove `'legacy' => true` and use `<main class="page">`.
  - Add `'components' => true` and build the page from the classes in `assets/css/components.css` (`.card`, `.btn`, `.tiles`, `.badge`, `.rows`/`.rowi`, `.kv`, `.stabs`, `.progress` and so on).
  - Use only the colour variables from `assets/css/global.css` (`--bg`, `--card`, `--card2`, `--line`, `--text`, `--muted`, `--accent` and the rest). Never hard-code colours.
  - Page-specific CSS goes in `assets/css/pages/<name>.css`, loaded with `'css' => [...]`.
- **API keys:** pages that call APIs need `'config' => true`, which loads the keys. Never print a key into the page.
- **Titles and descriptions** come from `data/site.json`, so don't hard-code `<title>`.
- **Headings and breadcrumbs:** use `<h1 class="page-title">` and `<p class="page-lede">`. Breadcrumbs come from `<?= site_breadcrumbs(site_current_path()) ?>`.

## Testing
PHP isn't installed on this PC. Download portable PHP 8.3 (NTS x64) from downloads.php.net into the session scratchpad and check the SHA256 published with it.
1. Run `php -S 127.0.0.1:8099 -t .` from the repo root.
2. `php -l` every file you changed.
3. Load the page in headless Chrome over CDP at 1366px and 390px wide. There must be:
   - no PHP warnings that come from your change (API failures from this PC are expected)
   - no JS errors
   - no sideways scroll
   - no 404s for same-site files
4. Look at both screenshots before you commit.
5. If you change `.htaccess`, test it with real Apache 2.4. PHP's server ignores `.htaccess`, and a single bad line takes the whole live site down with a 500.

## Before each session
1. Run `git diff <last commit of yours> HEAD -- .htaccess data/site.json includes assets`. On 2026-10-18 a sync from the server put old copies of shared files back, and `8cf4a7d` restored them. If it happens again, stop and tell the user.
2. Commit each page on its own, with a message ending in `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Don't push or merge without being asked.

## When you finish a page, report
- which page or pages you restyled, with the commit
- what looks different, ideally with a screenshot
- anything you couldn't test (pages that need live API data)
- which page you'd do next
