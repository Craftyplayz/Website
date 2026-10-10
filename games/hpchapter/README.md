# Harry Potter Chapter Oracle

The Chapter Oracle asks players to identify a passage's book and chapter. The
landing screen offers four modes and public, server-persisted leaderboards.
`/games/hpchapter/`, `index.html`, `new.html`, and `hpchapter.html` remain entry
points for the same native JavaScript application. This is not
`games/newhpchapter/`.

## Mode rules

All modes retain the two-step rules: identifying the book unlocks the chapter
without awarding a point; a wrong book ends the run. A correct chapter awards
one point. A wrong chapter reveals the answer, awards no point, and continues.
Passages never repeat in a run. Exhausting the pool completes the run.

- **Normal:** the passage pool uses all seven existing EPUBs.
- **Timed:** the same rules, with a default 60-second deadline. Loading and the
  menu do not consume time. The deadline begins when the first question is
  issued. Feedback and network time do consume time.
- **Minimum Context:** all seven books, but excerpts contain at most 150 Unicode
  code points by default. A deterministic word-boundary prefix must contain at
  least 60 code points and eight words containing Unicode letters. Empty,
  punctuation-only and unusable excerpts are rejected. The source paragraph
  must first satisfy the normal paragraph limits. Duplicate displayed excerpts
  are excluded.
- **Specific Book(s):** select one or more books before starting. Only those
  books contribute passages; a selected book failing to load is an error, not
  permission to substitute another book. Select All and Clear All are provided;
  the selection remains available when returning to the menu.

Specific Book(s) has **one** leaderboard, not a category for every combination.
Selected books appear alongside each score. Smaller selections have smaller
available pools; scores are not normalized by pool size.

## Global rankings

Display names are **public leaderboard content**, not accounts or unique
identities. Names are trimmed and validated on both sides: 1–20 Unicode code
points, meaningful non-whitespace content, and no control characters. Names are
rendered as text, never interpreted as HTML.

Each mode has its own leaderboard. The menu shows the top 10 and the full
leaderboard shows up to 100. Ranking is deterministic: **score descending,
elapsed milliseconds ascending, achievement time ascending, then database ID
ascending**. All timestamps are UTC; the UI identifies the date convention.
All Specific Book(s) combinations use the same ordering.

Scores are saved automatically when the server completes a run. A result shown
locally is not a successful submission until the server confirms acceptance.
The UI distinguishes pending/failed confirmation from accepted scores, offers
retry actions, refreshes after acceptance and when opening a leaderboard, and
keeps the last successfully fetched entries visible after a failed refresh.
Other browsers receive the same records from SQLite; no browser score storage
is used as a global ranking.

## Architecture

The original working domain and EPUB modules are retained:

- `src/config/books.js` is the frontend registry for the seven books, stable IDs, series identity, titles, order, and relative EPUB paths. The backend validates against its fixed seven-book registry.
- `src/config/settings.js` contains the paragraph bounds, existing high-score key, and feedback delays.
- `src/content/library-loader.js` fetches all books concurrently, returns successful books and per-book failures, and reports progress.
- `src/content/epub-parser.js` opens ZIP archives through the JSZip global, reads the OPF manifest/spine, resolves archive-relative paths, uses EPUB 3 navigation with EPUB 2 NCX fallback, and returns normalized chapters and paragraphs.
- `src/domain/question-pool.js` filters and globally deduplicates passages, preserves the first book/chapter attribution, records the eligible chapter choices, and shuffles questions independently of the DOM.
- `src/domain/game-session.js` owns question progression, legal answer stages, score changes, and completed-run reasons.
- `src/domain/shuffle.js` implements Fisher–Yates with an injectable random source.
- `src/application/game-controller.js` retains the original browser-only controller and regression coverage. The live entry point uses the server-backed controller, with generation guards against stale requests and feedback callbacks.
- `src/services/high-scores.js` retains safe legacy `hpOracle_hs` localStorage handling; it is not used for online rankings.
- `src/ui/game-view.js` retains the original safe text renderer. The online view adds the main menu, mode controls, results, and leaderboard navigation using text nodes.
- `script.js` is the shared browser-module bootstrap used by all three entry pages. `style.css` retains the dark and gold visual design.

The added live modules have separate responsibilities:

- `src/modes/rules.js`: mode definitions and validated menu settings.
- `src/application/online-controller.js`: run lifecycle and guarded transitions.
- `src/timer/countdown.js`: monotonic countdown lifecycle.
- `src/ui/online-view.js`: accessible mode menu, gameplay and leaderboard screens.
- `src/services/api-client.js`: JSON transport and useful network failures.
- `src/services/leaderboards.js`: independent mode results and refresh handling.
- `src/services/request-id.js`: CSPRNG UUIDs, including the existing HTTP site's
  `getRandomValues` fallback when secure-context `randomUUID` is unavailable.
- `api/Parser.php`: canonical EPUB parsing and deterministic excerpts.
- `api/Service.php`: validated run state, scoring, deadlines and ranking queries.
- `api/Storage.php`: private SQLite initialization, transactions and rate limits.
- `api/index.php`: HTTP routing, JSON limits and same-origin checks.
- `rules.json`: owner-controlled timed duration and minimum-context limit.

The online API is authoritative for passage selection, answer correctness,
scoring, and finalization. It reads the same `books/book1.epub` through
`books/book7.epub`, mirroring the OPF/spine/navigation parsing and stable
identities. It sends a passage without its answer; chapter choices are sent only
after a correct book answer. This avoids trusting a client-uploaded question
registry or score. EPUB assets and the existing browser parser remain intact.

The countdown uses an elapsed-time monotonic browser clock rather than a
decrementing counter. The server separately enforces its persisted start time and
duration before accepting answers. Closing the page, backgrounding a tab,
refreshing, or delaying an API request cannot reset that deadline. Returning to
the menu abandons an unfinished run without ranking it. Timers and delayed
callbacks are cleared when leaving a run.

## Backend and hosting prerequisites

The repository already uses PHP entry pages and Apache configuration. A small
same-origin PHP JSON API with SQLite fits those conventions without adding a
framework, build system, external leaderboard provider, API keys, or player
registration. The repository does **not** establish that production PHP has all
the required extensions or a writable persistent directory; the owner must
verify those.

- PHP 8.1 or newer with PDO SQLite, ZIP, DOM/libxml, and mbstring.
- A persistent directory outside the website document root writable by PHP.
  Use `HPCHAPTER_DB_PATH` to choose an absolute SQLite database path on hosts
  where the default location is not writable.
- The PHP process must be able to read all seven EPUBs.
- Keep the application and API on the same origin and serve over HTTPS in
  production. Do not configure wildcard CORS.
- SQLite storage must be local to the serving application and persistent across
  deployments/restarts. Multiple isolated hosts require a single shared API;
  copying independent database files to each host does not synchronize scores.
- If a reverse proxy/CDN hides visitor addresses, configure trusted address
  restoration in the web server. The API deliberately does not trust arbitrary
  client-supplied forwarding headers. Without that configuration, visitors behind
  the proxy share its rate-limit allowance.

The database must never be placed in a publicly served directory, including its
`-wal` and `-shm` companion files. Back up the private directory using SQLite-safe
backup procedures. Do not commit databases or production records.
By default it is stored in the parent of the outer public root, under
`.hpchapter-private/<installation-path hash>/scores.sqlite`. The EPUB cache is
stored in that same private database. The API rejects database paths inside the
repository or document root, including public symlink aliases.
For deployments that change release directories, configure a stable
`HPCHAPTER_DB_PATH`; the default installation-path hash changes with the path.

### Owner deployment

1. Deploy the game directory and unchanged EPUB assets to the existing PHP site.
2. Enable the required PHP extensions and create a private persistent directory
   owned/writable by the PHP service user, with **0700 directory permissions**.
   Existing database files must have the same owner; symlink path components and
   insecure precreated directories are rejected.
3. If necessary, configure `HPCHAPTER_DB_PATH` in the hosting/PHP process
   environment, not in browser JavaScript. Use a dedicated database file, not
   another website application's database. Do not expose the directory via an
   alias or static file server.
4. Open `api/index.php?action=config` and `?action=preview`. The API initializes
   the schema on first use; no manual SQL import or player setup is required.
5. Play a run, confirm acceptance, and view its leaderboard from an independent
   browser. Restart the PHP process and verify the record remains.
6. Confirm private database/cache paths cannot be retrieved by HTTP and review
   the host's persistent-volume and backup behavior.

If PHP/SQLite/private storage is unavailable, the leaderboard reports an error;
it does not silently replace global rankings with localStorage. A static-only
host is not sufficient for this deployment.

### Owner configuration

Edit the game's `rules.json` to set `duration` (an integer from **10–600 seconds**)
and `contextLimit` (an integer from **60–500 Unicode code points**). The defaults
are 60 and 150. Only these two keys are accepted. Invalid configuration produces
a safe server error rather than quietly changing the rules.

New runs use the updated settings; existing runs retain their original deadline
and excerpts. The private EPUB cache is keyed by source hash, parser version and
context limit, so changed limits do not reuse incorrectly truncated passages.
Leaderboard records retain their actual duration/context limit.

### API

All endpoints use `api/index.php?action=ACTION`, return JSON, and are same-origin.
Write operations require `POST` with `Content-Type: application/json`.

| Action | Method | Purpose |
| --- | --- | --- |
| `config` | GET | Server-configured timed duration and excerpt limit |
| `preview` | GET | Top 10 entries in each of the four modes |
| `leaderboard` | GET | `mode` and bounded `limit` (up to 100) |
| `create` | POST | Validate name, mode and optional specific-book selection; create a ready run |
| `start` | POST | Start once and issue the first playable question |
| `answer` | POST | Validate the current book/chapter answer; derive score server-side |
| `next` | POST | Advance after chapter feedback; never skip unanswered questions |
| `state` | POST | Reconcile a response failure without resetting the deadline |
| `finish` | POST | Finalize an expired timed run, or retrieve an already finalized result |
| `abandon` | POST | Leave an unfinished run without submitting it |

Run actions use the opaque `runId` returned by `create`; keep it private to the
page. Answer requests include an idempotency `requestId`. The API does not accept
a trusted client `score`, alternate timed duration, or client answer key.
Repeat finalization cannot create a second leaderboard entry.
Answer replays return fresh authoritative state/time rather than replaying a
stale countdown; reusing a UUID for a different answer is a conflict.

### Persistence and integrity

Schema version 1 initializes automatically (`PRAGMA user_version`); additive
score-metadata migration handles earlier score tables, and unsupported newer
schemas are rejected. SQLite stores server-owned runs, their
selected question sequence and answer state, finalized leaderboard entries,
idempotent answer responses, and rate-limit counters. Indexes support
per-mode ranking and cleanup. Prepared statements handle values; transactions
serialize answer/scoring/finalization and enforce one entry per run. Errors
produce safe JSON rather than database paths or SQL stack traces.

| Table | Contents |
| --- | --- |
| `book_versions` | Private versioned EPUB chapter-choice cache |
| `questions` | Canonical paragraph identities, text, excerpts and answer attribution |
| `runs` | Opaque run ID, server-owned JSON state and retention timestamps |
| `requests` | Answer idempotency keys and recorded outcomes, scoped to a run |
| `scores` | Unique run record, public name, mode, score, UTC achievement, elapsed time, duration, context limit and books |
| `rate_limits` | Per-address request windows and counters |

Modes and selected books are validated against a fixed registry. Duration and
context limits come from server configuration, not submitted values. Request
size limits, HTTP method checks, same-origin checks and per-address rate limits
bound public use. The service does not use cookie authentication, so there is no
account session for an attacker to forge through cross-origin requests.
JSON request bodies are limited to 4,096 bytes; the direct visitor address is
limited to 240 requests per minute and separately to **20 new-run requests per
five minutes**. Answer cadence is not subject to the new-run budget. At most
2,000 run states are retained; closed/abandoned state is evicted before rejecting
new runs at capacity, without deleting scores.
Run states have a 24-hour lifetime. Request-driven cleanup removes general
rate-limit windows after two minutes and create windows after ten minutes.
Finalized scores persist independently of expired run states.

API errors use 400 for invalid input, 403 for forbidden origins, 404 for unknown
actions/runs, 405 for incorrect methods, 409 for invalid transitions or conflicting
replays, 413 for oversized bodies, 415 for incorrect content types, 429 for rate
limits, and 503 for unavailable storage/content. Rate-limit responses include
`Retry-After` (60 seconds for general requests, 300 for new-run throttling).

These protections prevent forged numeric scores, repeated awards, skipped
stages, duplicate passage use and duplicate finalization. They are **not
complete anti-cheat**: EPUBs are public, so a determined player can search the
books or automate valid answers. Display names can be impersonated, and
address-based limits cannot stop distributed abuse. Do not treat this casual
leaderboard as a verified identity system or competitive prize platform.

## Local use and tests

Use PHP rather than a static-only HTTP server:

```sh
HPCHAPTER_DB_PATH=/absolute/private/path/chapter-oracle.sqlite \
  php -S 127.0.0.1:8000 -t /absolute/path/to/Website
```

Open `http://127.0.0.1:8000/games/hpchapter/`. There is no frontend build step.
Native modules and optional historical JSZip assets are retained; the live
server-backed game does not depend on a CDN to parse EPUBs.

Run frontend and HTTP integration tests with Node.js 22 or later:

```sh
node --experimental-default-type=module --test games/hpchapter/tests/*.test.js
```

The combined command includes HTTP API integration tests. PHP is required for
those tests. The PHP domain/storage suite can also be run separately:

```sh
php games/hpchapter/tests/backend.test.php
```

The original 15 tests use synthetic EPUB structures and a test-only DOM adapter.
They cover parsing/navigation/path behavior, paragraph limits and identities,
partial loading, question generation, session rules, legacy score compatibility,
stale transitions and public entry URLs. New tests cover online modes,
monotonic deadlines, names, leaderboard failures and server persistence.
Backend tests must use isolated temporary databases, never production data.
PHP test helpers are CLI-only and reject HTTP execution; they are not public
answer-key or database-reset endpoints.

## EPUB support and limitations

The parser handles ZIP-based EPUBs with `META-INF/container.xml`, an OPF manifest and spine, HTML/XHTML chapter items, EPUB 3 navigation links, or EPUB 2 NCX entries. It normalizes whitespace, reads paragraph (`p`) elements only, and retains paragraphs from 120 through 2,500 JavaScript string characters inclusive. Duplicate paragraphs are removed within each document and globally; when the same text appears more than once, the first book/chapter in registry and spine order owns it.

Chapter attribution requires a matching navigation entry. Some unusual EPUBs use alternate navigation structures, encrypted content, non-HTML chapters, or paragraphs outside `p` elements and may therefore provide no questions. Broken individual navigation/chapter documents are skipped or fall back where possible; malformed container or OPF metadata makes that book unavailable. The other books remain playable, and failures are shown so loading can be retried.

## Troubleshooting

- **Leaderboard unavailable:** check the network, retry, and confirm the PHP
  endpoint returns JSON instead of source code or a static-host 404.
- **Storage unavailable:** check extensions, private-directory permissions,
  disk space and the configured database path. Review server logs privately.
- **Run response lost:** use Retry to reconcile server state; do not start
  another run merely to resubmit a score.
- **Rate limited:** wait before retrying. Shared-network visitors share the
  address-based allowance.
- **Selected book unavailable/no passages:** repair the named EPUB asset and
  retry; do not substitute an unselected book.
- **Old local best:** `hpOracle_hs` remains supported by the preserved legacy
  service/tests but is not used or advertised as a global score in the live UI.

Production extension availability, writable persistent storage, reverse-proxy
routing and HTTPS must be checked by the owner. Local checks do not prove these
production hosting properties.
