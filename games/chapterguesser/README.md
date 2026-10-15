# Chapter Oracle

A book quiz: you're shown a passage and have to name the book it came from,
then the chapter. It started as a Harry Potter-only game and now works with any
series you drop into `books/`.

- **Play:** `/games/chapterguesser/`
- **This page:** `/games/chapterguesser/info/`
- The old address `/games/hpchapter/` redirects here and keeps any query string
  or hash.

## What's new

These are the most recent changes, newest first.

### Moved to `/games/chapterguesser/` (15 Oct 2026)

- The game now lives in `games/chapterguesser/`. `games/hpchapter/index.html` is
  a redirect, so old links and bookmarks still work.
- The prebuilt JSON passage files in `games/newhpchapter/` are gone. Every
  passage now comes straight from the EPUBs.
- The book library grew from seven Harry Potter EPUBs to a folder per series:
  A Song of Ice and Fire, Alex Rider, Harry Potter, His Dark Materials, Percy
  Jackson and the Olympians, Ready Player One, The Book of Dust, The Chronicles
  of Narnia, The Hunger Games and The Lord of the Rings.

### Multiple series, themes and shared leaderboards (15 Oct 2026)

- **Series picker.** The first screen lists every series that `series.php`
  finds in `books/`. Each folder is a series, and every `.epub` inside it is a
  book, sorted by natural filename order (`book2` comes before `book10`).
  Picking a series starts loading its books in the background while you choose
  a mode.
- **Series themes.** Each series gets its own colour scheme from `themes.css`.
  Series without a hand-made theme are given one of the shared themes, and the
  same series always gets the same one.
- **New mode: Response Descriptor.** See [Game modes](#game-modes).
- **Leaderboards rebuilt.** The SQLite API was replaced by one small
  `leaderboard.php` that stores JSON in `data/`. Every series and mode has its
  own board.
- **Replays.** A leaderboard score can include a replay showing every passage
  the player saw and how they answered. Select a leaderboard row to view it.
- **Best scores per series.** Personal bests are kept in the browser for each
  series and mode. Older Harry Potter bests (`hpOracle_hs`, `hpOracle_hs_timed`,
  `hpOracle_hs_minimum`) are still read.

### Inlined the app (12 Oct 2026)

- The ES-module architecture (`src/config`, `src/content`, `src/domain`,
  `src/ui`, …) and its tests were replaced with a single self-contained
  `index.html`. The `hpchapter.html` and `new.html` entry pages were removed.

## Game modes

In every mode except Response Descriptor you answer in two steps: name the book,
then the chapter. Getting the book right unlocks the chapter question but scores
nothing by itself. A passage never repeats within a run, and the run ends when
the passages run out.

| Mode | How it plays | Scoring |
| --- | --- | --- |
| **Normal** | A wrong book ends the run. A wrong chapter shows the answer and the run carries on. | 1 point per correct chapter |
| **Timed** | You get 60 seconds. Wrong answers don't end the run, they just score nothing. Feedback pauses are shorter because the clock keeps running. | 1 point per correct chapter |
| **Minimum Context** | The passage starts as a single word. Press **+ Add a word** to reveal more. A wrong book ends the run. | 20 points with one word showing, 1 less for each extra word, never below 1 |
| **Specific Books** | Same rules as Normal, but only the books you tick are used. You can select all or clear all. | 1 point per correct chapter |
| **Response Descriptor** | A line of dialogue has its speech word blanked out (*screamed*, *whispered*, *moaned*…). Type the missing word. You have three lives. | 1 point, or ½ point if you used the first-letter hint |

### Response Descriptor details

- Only dialogue tags next to a quotation and a speaker are blanked, for example
  `…,” Harry yelled`, `…,” yelled Harry` or `Harry yelled, “…`. Neutral tags
  like *said*, *asked* and *replied* are never used.
- A word is skipped if it appears more than once in the paragraph, because the
  second copy would give the answer away.
- Any form of the word counts: *whisper*, *whispers*, *whispered* and
  *whispering* are all correct.
- Small typos are allowed and grow with word length: none for words of up to 4
  letters, 1 for words of up to 7 letters, and 2 for longer words. Swapping two
  neighbouring letters counts as one typo. A guess that is a different word on
  the list (for example *shouted* when the answer is *yelled*) is wrong, not a
  typo.
- Up to three sentences of surrounding conversation are shown on each side of
  the blank.

## Leaderboards

- Each series and mode has its own board. The server keeps the top 100 and
  shows the top 50.
- A name keeps only its best score on each board. Names are 1–20 characters
  with no control characters. They aren't accounts, so anyone can use any name.
- Scores are saved automatically when a run ends, as long as you've entered a
  name and scored at least one point. If saving fails, a **Retry** button
  appears.
- Ties go to whoever reached the score first.
- Specific Books has one board for every selection. Each entry records how many
  books were selected.
- Replays are stored for Normal, Timed, Specific Books and Response Descriptor
  runs. Minimum Context runs don't have replays yet.

### `leaderboard.php` API

| Request | Response |
| --- | --- |
| `GET leaderboard.php?series=<id>&mode=<mode>` | `{ entries: [...] }` |
| `GET leaderboard.php?replay=<id>` | `{ replay: {...} }` |
| `POST leaderboard.php` with JSON `{series, mode, name, score[, books][, replay]}` | `{ entries, rank, best, improved }` |

Modes are `normal`, `timed`, `minimum`, `specific` and `descriptor`. The series
must be a folder in `books/` that holds at least one EPUB. Half points are
accepted only in `descriptor`. `books` (the number of books selected) is
required for `specific` and rejected for every other mode. Each visitor address
can submit 12 scores a minute, and a request body can be up to about 1.5 MB.

### Storage

- Boards are saved to `data/leaderboards.json` and replays to `data/replays/`,
  one file per entry. Files are locked while they're being read or written.
- `data/.htaccess` stops anyone from downloading these files directly.
- `.gitignore` keeps live scores out of the repository.
- PHP needs permission to write to `data/`.

This is a casual leaderboard, not an anti-cheat system. The EPUBs are public and
the browser reports the score, so a determined player can fake one.

## Adding a series

1. Create a folder in `books/` named after the series, for example
   `books/The Hunger Games/`.
2. Add the `.epub` files. Name them so they sort in reading order, for example
   `The Hunger Games - 01 - The Hunger Games.epub`.
3. Optional: add a series theme to `themes.css`. The comment at the top of that
   file explains how. The theme's slug is the folder name in lowercase with
   only letters and digits kept (`The Lord of the Rings` → `thelordoftherings`).

The series shows up immediately. No build step or registration is needed.
`.mobi` files are ignored, so the two Alex Rider books stored as `.mobi` aren't
used.

## Files

| Path | Purpose |
| --- | --- |
| `index.html` | The whole game: markup, styles and script (EPUB parsing, modes, scoring, leaderboards, replays) |
| `themes.css` | Colour variables, series themes and shared themes |
| `series.php` | Lists the series in `books/` as JSON |
| `leaderboard.php` | Shared leaderboards and replays |
| `books/<series>/*.epub` | The books |
| `data/` | Leaderboard storage (not public, not committed) |
| `info/index.php` | Displays this README as a web page |
| `../hpchapter/index.html` | Redirect from the old address |

`api/`, `src/`, `tests/`, `script.js`, `style.css` and `rules.json` are left
over from the earlier SQLite and ES-module version. The live game doesn't use
them.

## How passages are chosen

EPUBs are opened in the browser with JSZip 3.10.1, loaded from cdnjs. The
parser reads the OPF manifest and spine, then uses EPUB 3 navigation or falls
back to the EPUB 2 NCX to name chapters. Only `<p>` paragraphs between 120 and
2,500 characters are used, and duplicate paragraphs are dropped. If one book
fails to load, the rest of the series stays playable and the failed books are
listed.

## Running locally

The game needs PHP for `series.php` and `leaderboard.php`:

```sh
php -S 127.0.0.1:8000 -t /path/to/Website
```

Then open `http://127.0.0.1:8000/games/chapterguesser/`.

## Troubleshooting

- **No series listed:** check that `series.php` returns JSON, not PHP source
  code, and that `books/` contains folders with `.epub` files.
- **"The leaderboard is unavailable right now":** PHP can't open or lock
  `data/leaderboards.json`. Check that PHP can write to `data/`.
- **A book won't load:** the EPUB may be damaged, DRM-protected, or have no
  navigation that matches its chapters. The other books still work.
- **"No dialogue with a blankable speech word was found":** that series has no
  usable dialogue tags for Response Descriptor. Pick another mode or series.
