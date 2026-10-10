# Harry Potter Chapter Oracle

This is a static, browser-only passage quiz. It loads the seven EPUB files in `books/`, extracts eligible paragraphs, and asks players to identify each passage's book and chapter. All three historical entry URLs (`index.html`, `new.html`, and `hpchapter.html`) load the same implementation.

## Modules and data flow

- `src/config/books.js` is the authoritative registry for the seven books, stable IDs, series identity, titles, order, and relative EPUB paths.
- `src/config/settings.js` contains the paragraph bounds, existing high-score key, and feedback delays.
- `src/content/library-loader.js` fetches all books concurrently, returns successful books and per-book failures, and reports progress.
- `src/content/epub-parser.js` opens ZIP archives through the JSZip global, reads the OPF manifest/spine, resolves archive-relative paths, uses EPUB 3 navigation with EPUB 2 NCX fallback, and returns normalized chapters and paragraphs.
- `src/domain/question-pool.js` filters and globally deduplicates passages, preserves the first book/chapter attribution, records the eligible chapter choices, and shuffles questions independently of the DOM.
- `src/domain/game-session.js` owns question progression, legal answer stages, score changes, and completed-run reasons.
- `src/domain/shuffle.js` implements Fisher–Yates with an injectable random source.
- `src/application/game-controller.js` coordinates loading, feedback transitions, restarts, session results, and persistence. A run token prevents delayed callbacks from an old run affecting a restarted run.
- `src/services/high-scores.js` safely reads and updates the existing `hpOracle_hs` localStorage value; no other game data is persisted.
- `src/ui/game-view.js` renders the four screens, binds buttons by stable IDs, and only displays EPUB-derived text through text nodes.
- `script.js` is the shared browser-module bootstrap used by all three entry pages. `style.css` retains the dark and gold visual design.

### Gameplay rules

Each question begins with seven book options in series order. A correct book answer advances to that book's retained chapter choices without scoring; a wrong book answer ends the run. A correct chapter answer awards one point. A wrong chapter answer reveals the chapter, awards no point, and continues. A run normally completes when its question pool is exhausted. Restart creates and reshuffles a new pool from the already parsed library without fetching the EPUBs again.

## Local use and tests

Serve the site over HTTP from the repository root so browser module imports and EPUB fetches work. For example:

```sh
python -m http.server 8000
```

Open `http://localhost:8000/games/hpchapter/` (or either of the other two entry URLs). The pages use native ES modules and load the existing JSZip 3.10.1 browser build from cdnjs; there is no build step or server runtime.

Run the automated tests with Node.js 22 or later:

```sh
node --experimental-default-type=module --test games/hpchapter/tests/*.test.js
```

The tests use synthetic, non-book-content EPUB structures and a small test-only DOM parser adapter. They cover parsing/navigation/path behavior, paragraph limits and identities, partial loading, question generation, session rules, score compatibility, stale transitions, and all public entry URLs.

## EPUB support and limitations

The parser handles ZIP-based EPUBs with `META-INF/container.xml`, an OPF manifest and spine, HTML/XHTML chapter items, EPUB 3 navigation links, or EPUB 2 NCX entries. It normalizes whitespace, reads paragraph (`p`) elements only, and retains paragraphs from 120 through 2,500 JavaScript string characters inclusive. Duplicate paragraphs are removed within each document and globally; when the same text appears more than once, the first book/chapter in registry and spine order owns it.

Chapter attribution requires a matching navigation entry. Some unusual EPUBs use alternate navigation structures, encrypted content, non-HTML chapters, or paragraphs outside `p` elements and may therefore provide no questions. Broken individual navigation/chapter documents are skipped or fall back where possible; malformed container or OPF metadata makes that book unavailable. The other books remain playable, and failures are shown so loading can be retried.

## Extension points (not implemented)

Timed run policies belong beside the session lifecycle and controller transitions; alternate passage presentation belongs in the view over the same question identity; book filtering belongs in question-pool creation; additional series would require registry/content metadata rather than changes to scoring rules; and future completed-run rankings would consume the structured session result through a separate persistence service. None of these features or controls is currently active.
