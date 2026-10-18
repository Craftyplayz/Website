# Handoff: restyle the Books tab

**Read `_dev/handoffs/README.md` first.** It holds the rules shared by every section: one or two pages at a time, the design guide in `/plan.html`, staying inside your tab, safe git commands and testing. Your test port is **8103** and your Chrome debug port is **9403**.

## Your files
- **Folders you may edit:** `books/` (everything below it).
- **Stylesheets that are yours:** `assets/css/pages/books.css`, and any new `assets/css/pages/books-*.css`.
- **Book cover images** live in `media/books/covers/`. Don't move or rename them.

## Don't touch
- `books/harry-potter/json/characters.json`: data, not UI.

## Pages, in suggested order
| Page | File | Notes |
|---|---|---|
| Books I've Read | `books/index.php` | Big (about 580 lines of hand-written cover tiles) and the most visited page. It uses `books.css` and `/books/sort.js` (sort dropdown). It has a "Live stats from Kindle" heading. One cover, `media/books/covers/Standalone/chr.jpg`, is missing from the repo; leave it or ask. |
| Bulk Cover Downloader | `books/covers/bulk/index.php` | A purple gradient with a white card, off-palette. Its body styles were moved onto `.tool-wrap`. Covers come from Open Library, and `${book.title}` is inserted unescaped into innerHTML: escape it while you're there. |
| Book Cover Search | `books/covers/index.php` | Already dark and fairly close; polish it to the tokens. Its old `body` rule was renamed to `main.page.page-legacy`. |
| Harry Potter Characters | `books/harry-potter/index.php` | A search form that posts to `character/`. Reads `json/characters.json`. |
| Harry Potter Character | `books/harry-potter/character/index.php` | Currently only a "Go back" stub with a 5-second refresh. Ask the user what it should show before building anything. |
| Harry Potter Info | `books/harry-potter/info/index.php` | A small page. |
| Harry Potter Quiz (old) | `books/harry-potter/quiz/index.php` | The old version; the real quiz is in Games. Ask before spending time on it. |
