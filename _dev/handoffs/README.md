# Restyling handoffs: the rules every section session follows

The site was reorganised on branch `reorg` (phases 1–8 of `/plan.html`). Every page now has the new header and footer, but most pages still **look** old. One session restyles one header tab:

| Tab | Handoff | Test port | Chrome debug port |
|---|---|---|---|
| Minecraft | `minecraft.md` | 8101 | 9401 |
| Games | `games.md` | 8102 | 9402 |
| Books | `books.md` | 8103 | 9403 |
| Tools | `tools.md` | 8104 | 9404 |
| Extras | `extras.md` | 8105 | 9405 |

Several sessions run at the same time in the same folder, so the rules below matter.

## 1. Work rules
- **Rework one or two pages per session turn, no more.** Finish them, test them, commit them, then stop and report back. Don't start the next page until the user says so.
- **Follow `building-pages.md`** (next to this file): design philosophy, `site.json`/header rules, colour tokens, type sizes, spacing, layout, API caching, components, motion and the per-page checklist. Every page must pass that checklist. (`/plan.html` > Design guide is the original version with live demos.)
- **Reference design:** `/minecraft/tools/nbt-viewer/` is the page every other page should look and feel like.
- **Change the look, not the behaviour.** Keep features, URLs, query parameters and data files as they are.

## 2. Stay inside your tab
Other sessions are editing other tabs at the same time.
- **Only edit files inside your section's folders** (listed in your handoff), plus **new** stylesheets named `assets/css/pages/<section>-<name>.css`.
- **Don't edit shared files:**
  - `data/site.json`, `.htaccess`
  - `includes/` and `php/`
  - `assets/css/global.css`, `assets/css/components.css` and `assets/css/legacy.css`
  - `assets/js/`, `index.php`, `404.php`
  - another section's files or another section's page stylesheet
- **If a shared file really must change:**
  1. Don't make the change yourself.
  2. Append the exact change you need, and why, to `_dev/handoffs/requests.md`, under a heading with your section name.
  3. Tell the user in your report.
  4. Work around it inside your own files in the meantime, for example with a page stylesheet.
- **An existing `assets/css/pages/*.css` that only your pages use** (check with `git grep`) counts as yours. **One that pages in other tabs also use** (`gallery.css`, `homepage.css`, `steamnew.css` and so on) is shared: copy what you need into a new `<section>-*.css` instead of editing it.

## 3. Git, when several sessions share one folder
- **Never use `git add -A`, `git add .`, `git commit -a`, `git stash` or `git reset`, and never `git checkout`/`git restore` a file outside your tab.** They would sweep up or wipe another session's uncommitted work.
- **Stage and commit only your own paths:** `git add <your files>`, then `git commit -m "..." -- <your files>`.
- **Before committing,** run `git diff --cached --name-only` and make sure every file listed is yours.
- **If a commit fails because the index is locked,** wait a few seconds and try again. Never delete `.git/index.lock`.
- **End commit messages with** `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- **Don't push, merge or rebase** unless the user asks.
- **Before each page,** run `git log --oneline -5` and `git diff HEAD~5 -- .htaccess data/site.json includes assets/css/global.css` and look for surprises. On 2026-10-18 a sync from the server put old copies of shared files back. If it happens again, stop and tell the user.

## 4. How a page is built now
- **Top of the file:** `$site_page = [...]` and then `require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';`. In `<head>` call `page_head()`, and in `<body>` call `site_header()` … `site_footer()`. Every option is in `_templates/page.php`.
- **Legacy pages:** pages not restyled yet have `'legacy' => true` and `<main class="page page-legacy">`. That loads `assets/css/legacy.css`, the old styles.
- **Restyling a page means:**
  - Remove `'legacy' => true` and use `<main class="page">`.
  - Add `'components' => true` and build with the classes in `assets/css/components.css` (`.card`, `.btn`, `.tiles`/`.tile`, `.badge`, `.rows`/`.rowi`, `.kv`, `.stabs`, `.progress`, `.drop`, `.drawer`, `.note`/`.err` and so on).
  - Use only the colour variables from `assets/css/global.css` (`--bg`, `--card`, `--card2`, `--sunken`, `--line`, `--text`, `--muted`, `--accent`, `--link`, `--highlight` and the rest). Never hard-code colours.
  - Page-specific CSS goes in `assets/css/pages/<section>-<name>.css`, loaded with `'css' => [...]`.
- **Optional JS helpers:** `'ui' => true` loads `assets/js/ui.js` (`UI.$`, `UI.esc`, `UI.toast`, `UI.copy`, `UI.store` and so on).
- **API keys:** `'config' => true` loads the keys from `php/config.php`. Never print a key into the page.
- **Titles and descriptions** come from `data/site.json`, so don't hard-code `<title>`.
- **Headings:** use `<h1 class="page-title">` and `<p class="page-lede">`. Breadcrumbs come from `<?= site_breadcrumbs(site_current_path()) ?>`.
- **Full-screen tools** (100vh apps) get no footer. Pages without the site header must not get one unless the user agrees.

## 5. Testing
PHP isn't installed on this PC. Download portable PHP 8.3 (NTS x64) from downloads.php.net into **your own session scratchpad** and check the SHA256 published with it.
1. Serve the repo on **your port from the table above**, for example `php -S 127.0.0.1:8102 -t .`, so you don't collide with other sessions.
2. `php -l` every file you changed.
3. Load each changed page in headless Chrome over CDP, on **your Chrome debug port** with **your own `--user-data-dir`**, at 1366px and 390px wide. There must be:
   - no PHP warnings from your change (API failures from this PC are expected)
   - no JS errors
   - no sideways scroll
   - no 404s for same-site files
4. Look at both screenshots before committing.
5. **Pages that write live data** create files when you test them locally. Delete those files before committing. They're listed in each handoff.
6. Stop your servers when you're done.

## 6. When you finish a page, report
- which page or pages, and the commit
- what looks different, with the screenshot path in your scratchpad
- anything you couldn't test (pages that need live API data)
- any request you added to `requests.md`
- which page you'd do next
