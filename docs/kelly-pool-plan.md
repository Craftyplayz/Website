# Kelly Pool for craftyplayz.com: build plan

A pass-and-play Kelly Pool tracker for one phone at the table. The game flow follows the
reference app in the screenshots (home → players and balls → names → pass and reveal → board).
The look, markup, wording and code follow the Crafty Playz design guide (craftyplayz.com/design).

Contents

1. [What the reference app does](#1-what-the-reference-app-does)
2. [What we keep, change and drop](#2-what-we-keep-change-and-drop)
3. [Where it lives on the site](#3-where-it-lives-on-the-site)
4. [Style merge: reference app → Crafty Playz](#4-style-merge-reference-app--crafty-playz)
5. [Game modes and rules](#5-game-modes-and-rules)
6. [Public and private information](#6-public-and-private-information)
7. [Screen-by-screen spec](#7-screen-by-screen-spec)
8. [Dialog and message copy](#8-dialog-and-message-copy)
9. [State model](#9-state-model)
10. [Game logic (pseudo-code)](#10-game-logic-pseudo-code)
11. [Markup skeleton](#11-markup-skeleton)
12. [CSS plan](#12-css-plan)
13. [JavaScript plan](#13-javascript-plan)
14. [Accessibility](#14-accessibility)
15. [Edge cases](#15-edge-cases)
16. [Test plan](#16-test-plan)
17. [Build order](#17-build-order)
18. [Open questions](#18-open-questions)

---

## 1. What the reference app does

| # | Screen | What's on it | Behaviour |
|---|---|---|---|
| 1 | Home | Big 3-ball art with crossed cues. Four full-width buttons: **Sink Balls You Win**, **Last Man Standing**, **Rules**, **Settings**. News icon top left, crown (premium) top right. | Picks a game mode. |
| 2 | Player Details | Grid of player counts **2–15** (selected tile is solid white). **Number Of Balls** grid **1–5**. **Next** button pinned to the bottom. | Sets players and balls each. |
| 3 | Player Names | Row of recent-name chips (Tom, Luke, Lewis, Rose, Ally). One labelled field per player ("Player 1", "Name" placeholder). **Next** pinned to the bottom. | Tapping a chip fills the next empty field. |
| 4 | Pass to Tom | Black screen, huge "Pass to Tom", **I am Tom** button. | Hand-off before anything private shows. |
| 5 | Tom | "Your numbers are" and Tom's three balls (8, 12, 6). **OK**. | Private reveal. OK moves to the next player. |
| 6 | Board | Title "Last Man Standing" with history, info and close icons. **Players** as two-column buttons: "Tom (3)", "Lewis (3)", "Ally (3)". **Balls** 1–15 in a 3-column grid of large balls. | Tap a ball when it's potted. Tap a player to see their numbers again (via the pass screen). |
| 7 | Ball potted (owned) | Dialog: "Tom has lost a ball." / "This was Tom's ball." / "Tom has 2 balls left." / **OK**. | Owner revealed to everyone and loses a life. |
| 8 | Ball potted (unowned) | Dialog: "No one's ball." / "Ball Number 1 is not allocated to anyone." / **OK**. | The ball dims on the board afterwards. |

Things to notice:

- The app never asks **who** potted a ball. In Last Man Standing that doesn't matter (the owner loses a ball whoever pots it).
- Each player's ball count is public: "Tom (3)".
- Who owns a potted ball is announced to everyone. Who owns a ball still on the table is never shown.
- Potted balls stay in the grid but are dimmed.
- Small bug in the reference: behind the "Tom has 2 balls left" dialog, the tile still says "Tom (3)". Our version updates the board before the dialog opens.

## 2. What we keep, change and drop

**Keep**
- The flow: mode → players and balls → names → pass/reveal for each player → board.
- Big tap targets, the 3-column ball grid on phones, and the pass screen before any private info.
- Recent names as quick-fill chips.
- Both modes: **Sink balls you win** and **Last man standing**.
- History, rules and exit from the board's top bar.

**Change**
- Pure black and white → the site's tokens (`--bg`, `--card`, `--sunken`, `--accent`).
- Title Case labels ("Number Of Balls") → sentence case ("Number of balls"). Headings and buttons drop full stops.
- Selected tile (solid white) → `--accent-soft` fill with an `--accent` border, the same as `.stabs button.on`.
- Pinned bottom **Next** button → `.btn.primary` in an `.actions` row (sticky on phones, see 12).
- Alerts → native `<dialog class="dlg">`.
- History and rules → `aside.drawer` panels.
- The home screen becomes a page hero with art, plus mode cards.
- Add undo in history, a "Resume game" card, a game-over screen that reveals everyone's numbers, and an optional PIN.

**Drop**
- News and crown/premium buttons. They have no use on the site.
- "Settings" as its own screen. It becomes a drawer.

## 3. Where it lives on the site

**URL:** `/games/pool/kelly-pool/`. The guide says `/<section>/<group>/<page>/`, so `pool` is the group. Use an existing group under Games if a better one exists.

**Files**

| File | Purpose |
|---|---|
| `games/pool/kelly-pool/index.php` | Copied from `_templates/page.php` |
| `assets/css/pages/games-kelly-pool.css` | Page styles. All classes prefixed `kp-`. Too big for an inline `<style>` (over 40 lines). |
| `assets/js/pages/games-kelly-pool.js` | Page script, `defer`. The guide doesn't say where page JS goes; this mirrors the CSS path. |
| `media/games/kelly-pool/hero-960.webp`, `hero-1920.webp` | Hero art: the 3-ball and crossed cues, with width and height set. Must be our own art, not the reference app's. |
| `includes/functions.php` | Add `history`, `info`, `close`, `undo`, `shuffle` icons to `site_icon()` if they aren't there yet. |

**`$site_page` in `index.php`**

```php
<?php
$site_page = [
  'components' => true,              // .btn, .card, .badge, .field, .stabs, .drawer, .dlg
  'ui' => true,                      // window.UI: esc(), toast(), drawer/dialog data-act handling
  'css' => ['pages/games-kelly-pool.css'],
];
require __DIR__ . '/../../../includes/bootstrap.php';
?>
```

**`data/site.json` entry**

```json
{ "title": "Kelly Pool", "url": "/games/pool/kelly-pool/", "description": "Pass-and-play Kelly Pool with secret numbers, lives and a public board", "keywords": ["kelly pool", "pool", "billiards", "pub game", "last man standing", "pea pool"] }
```

**`<main>`:** `<main class="page kp" id="main">`. `kp` is the page tag for scoping.

## 4. Style merge: reference app → Crafty Playz

| Reference app | Crafty Playz version | Token or class |
|---|---|---|
| Pure black page | Site background | `--bg #202020` |
| Black pass screen | Full-screen sunken panel | `--sunken #1b1b1b` |
| Dark grey rounded buttons | Secondary buttons, taller on this page | `.btn` (`--btn2`, hover `--btn2-hover`), radius 8px |
| **Next** | One primary per view | `.btn.primary` (`--btn`, border `--btn-line`) |
| White selected tile | Accent selected tile | `.kp-pick.on`: `--accent-soft` fill, `--accent` border |
| Bold system headings | Same, at the site scale | Page title `clamp(24px, 4vw, 34px)` 700. Card `h2` 17px 600. |
| "Tom (3)" pill buttons | Player tiles with name, life pips and a count badge | `.kp-player` on `--card2` |
| Ball images | CSS-drawn balls (no images, crisp at any size) | `.kp-ball` (see 12) |
| Popup alert | Native modal | `dialog.dlg` |
| History screen | Right-hand drawer | `aside.drawer#kp-history` |
| Rules screen | Drawer | `aside.drawer#kp-rules` |
| Settings screen | Drawer | `aside.drawer#kp-settings` |
| Home art | Page hero with art, scrim and title shadow | `header.page-hero` |
| Title Case everywhere | Sentence case except the page title | Writing rules |

Pool ball colours and the cloth green are the only literal colours. They fall under "colours with real meaning".

## 5. Game modes and rules

Both modes share setup: **2–15 players**, **1–5 balls each**, and **players × balls ≤ 15**.
Balls are dealt at random from 1–15. Leftover balls belong to no one.

### 5.1 Sink balls you win (classic Kelly)

- Shoot in turn order. The lowest-numbered ball on the table must be hit first (house rule, shown in Rules).
- When a ball is potted, tap it on the board.
  - **No one's ball:** dialog, ball dims, play on.
  - **Owned ball:** the app asks **"Who potted it?"** with two buttons: the owner's name and "Someone else".
    - Owner potted it → **owner wins** → game over.
    - Someone else potted it → depends on the setting **"When someone else pots your ball"**:
      - **You're out** (default, traditional): the owner loses that ball. With 0 balls left they're out. If one player is left, they win.
      - **Nothing happens:** the ball is just dimmed.
- Foul pots: the ball goes back on the table, so nobody taps it. Rules text says this.

Why the extra question: the reference app can't tell who sank a ball, but this mode needs to know. A single two-button step keeps it quick.

### 5.2 Last man standing

- Every potted ball that belongs to someone costs its owner one ball (a life), **whoever potted it**.
- When a player reaches 0 they're out. Their tile goes grey with an "Out" badge.
- The last player with balls left wins.
- Each ball has one owner, so a single pot can knock out at most one player. The win check runs after every pot.

### 5.3 Turn tracking

The reference app doesn't track turns, and neither do we by default. This keeps tapping to one action per pot.
A setting **"Show whose turn it is"** (off by default) adds a "Tom to shoot" line and a **Next player** button. Leave this for phase 2 (see 17).

## 6. Public and private information

| Information | Who sees it | Where |
|---|---|---|
| Players and order | Everyone | Player tiles |
| How many balls each player has left | Everyone | "Tom · 2 left" + pips |
| Which balls are on the table or potted | Everyone | Ball grid (potted = dimmed + ✓ caption) |
| Owner of a **potted** ball | Everyone | Outcome dialog, history, board caption |
| Owner of a ball **still on the table** | Only that player | Reveal screen behind the pass screen |
| Who's out | Everyone | Grey tile, "Out" badge, history |
| Unallocated balls | Nobody until potted | Revealed one at a time as they drop |
| Everyone's numbers | Everyone, **after game over only** | Game-over reveal table |

Privacy rules:
- Private numbers show only after a **Pass to [name]** screen and an **I am [name]** tap.
- Optional PIN (setting, off by default): set on first reveal, asked on later reveals.
- The reveal hides itself after 15 seconds and when the tab is hidden (`visibilitychange`).
- After a reload, the app never reopens on a reveal. It goes back to the pass screen or the board.
- Board tiles never show anything that tells you which balls are someone's.

## 7. Screen-by-screen spec

All screens sit inside `<main class="page kp">`. Only one "view" is visible at a time, toggled with the `hidden` attribute (the guide's `[hidden]` fix applies to custom `display` values).

### 7.0 Hero (always on top of the home view)

- `header.page-hero` with `img.page-hero-bg` (3-ball and crossed cues, `fetchpriority="high"`, `decoding="async"`, width and height set) and a black scrim.
- `h1.page-title`: **Kelly Pool**. `p.page-lede`: "Draw secret numbers, sink balls and be the last player standing."
- During a game the hero shrinks to a compact toolbar (7.4). It doesn't scroll away with the art.

### 7.1 Home: choose a mode

- **Resume card** (only if a saved game exists): `section.card` with `h2` "Game in progress", a meta line ("Last man standing · 3 players · 9 balls on the table"), then actions **Resume game** (primary) and **Abandon game**. When this card shows, the mode buttons below are secondary.
- **Mode cards:** `.grid` of two `section.card`s.
  - **Sink balls you win**: "Pot one of your own balls to win straight away."
  - **Last man standing**: "Lose a ball every time one of yours goes down. Last player left wins."
  - Each card's button: **Start sink balls you win** / **Start last man standing**. With no resume card, the first is primary; otherwise both are `.btn`.
- Under the cards, a `.actions` row: **Read the rules** (opens rules drawer) and **Settings** (opens settings drawer).
- No stat row on the home view: there's no data yet, and the guide says not to show empty cards.

### 7.2 Players and balls

`form.form-card` with `h2` "Players and balls".

- **Number of players:** label text, then `div.kp-picks` with buttons **2–15** (4 columns on phones, `auto-fill` on wider screens). Default **3**. Selected: `.on` + `aria-pressed="true"`.
- **Balls each:** buttons **1–5**. Default **1** for Sink balls you win, **3** for Last man standing.
- Impossible combinations are **disabled**, not hidden. For example, 6 players allows up to 2 balls. Below the picks, a `small`: "6 players can have up to 2 balls each (15 balls in the rack)."
- If the player count changes and the current ball count no longer fits, drop it to the highest allowed number and say so in that `small`.
- Summary line: "3 players × 3 balls = 9 of 15 balls dealt. 6 belong to no one."
- `.actions`: **Next** (primary), **Back** (`.btn`).

### 7.3 Player names

`form.form-card` with `h2` "Player names".

- **Recent names:** a `.kp-chips` row of `.btn.small` buttons (from storage, newest first, up to 12). Tapping fills the first empty field. Used names disappear from the row. Long-press or × is not needed; add a **Clear recent names** link in Settings instead.
- One `label` per player wrapping its field (no `id` needed):
  `Player 1 <input class="field" name="p1" autocomplete="off" maxlength="16" enterkeyhint="next">`
- **Shuffle order** (`.btn.small` with the shuffle icon) randomises the order of the typed names.
- Validation on submit only:
  - Empty → `aria-invalid="true"`, label `small`: "Enter a name."
  - Duplicate (case-insensitive) → "Each player needs a different name."
- `.actions`: **Deal balls** (primary), **Back**.
- On submit, save the names to recent names.

### 7.4 Pass and reveal (deal)

For each player in order, two full-screen states on `--sunken`. The site header and the hero are hidden during this step.

**Pass screen**
- Small badge: "Dealing · 1 of 3".
- Huge centred text: "Pass to **Tom**" (`clamp(32px, 9vw, 56px)`, 700).
- Muted line: "Everyone else, look away."
- Bottom: **I am Tom** (primary, full width on phones, 56px tall).

**Reveal screen**
- `h2` "Tom" (left aligned like the reference), then "Your numbers".
- Balls at 96px in a centred row that wraps. Up to 5 balls fit at 320px across 2 rows.
- One line explaining the mode:
  - Sink: "Pot one of these yourself to win. Keep them secret."
  - LMS: "You lose a ball each time one of these goes down. Keep them secret."
- If PIN is on: `label` "Set a 4-digit PIN to check your numbers later" wrapping a numeric field.
- Bottom: **Hide and pass on** (primary). On the last player, the label is **Hide and start the game**.

### 7.5 Board

**Top bar** (compact, replaces the hero during play):
- Left: `h1.page-title` with the mode name ("Last man standing"). The page title stays "Kelly Pool" in `<title>`.
- Right: three icon buttons, 40×40, inline SVG at 22px, each with `aria-label` and `title`:
  - History (`data-act="drawer"` → `#kp-history`)
  - Rules (`data-act="drawer"` → `#kp-rules`)
  - Leave game (opens `#kp-exit` dialog)

**Stat row** (`.stat-row` with 3 `.stat-card`s; these are `div`s, not links, because there's no data source page):
- **Players in**: "2" / "of 3".
- **Balls on the table**: "11" / "4 potted".
- **Last ball**: "7" / "Tom's" or "No one's". Hidden until something is potted (no empty cards).

**Players** (`section.card` with `h2` "Players" and `span.n` "3"):
- `.kp-players` grid: 2 columns on phones (like the reference), `auto-fill minmax(200px, 1fr)` wider.
- Each tile is a `button.kp-player` on `--card2`:
  - Name (truncated with ellipsis)
  - Life pips: one dot per starting ball, filled for balls left (`aria-hidden`)
  - Badge: "3 left" (default), "Out" (`.badge.r`), "Winner" (`.badge.gold`)
- Tapping a tile starts **Check numbers** for that player: the same pass → (PIN) → reveal flow as dealing, with **Hide my numbers** as the action. Out players' tiles aren't buttons.

**Balls** (`section.card` with `h2` "Balls" and `span.n` "11 left"):
- `.kp-rack` on the cloth colour. Grid: `repeat(3, 1fr)` below 480px, `repeat(5, 1fr)` from 480px, `repeat(8, 1fr)` from 1024px.
- Each ball is a `button.kp-slot` with the CSS ball (88px phone, 64px desktop) and a caption under it.
  - On the table: caption empty. `aria-label="Ball 7, on the table"`.
  - Potted: ball at 30% opacity and greyscale, caption "Tom's" or "No one's", `disabled`, `aria-label="Ball 7, potted, Tom's ball"`.
- Tap → outcome flow (section 8).

### 7.6 Outcome dialogs

`dialog.dlg#kp-outcome`, filled in by JS and opened with `showModal()`. The board updates **before** the dialog opens. Text and buttons for each case are in section 8. Every outcome dialog has one primary button (**OK** is replaced with a verb: **Carry on**).

### 7.7 History drawer

`aside.drawer#kp-history` with `h2` "History":
- Newest first. Each row: ball icon (24px), sentence, and time ("2 min ago" via `UI.ago()`).
- Top of the drawer: **Undo last pot** (`.btn`, disabled if nothing to undo). Undo shows a toast: "Ball 7 is back on the table."
- The drawer is public: it only lists potted balls and their owners.

### 7.8 Rules drawer

`aside.drawer#kp-rules`, using `.stabs` to switch between **Sink balls you win** and **Last man standing** (current mode selected on open). Short numbered lists, British spelling. See section 8 for the text.

### 7.9 Settings drawer

`aside.drawer#kp-settings`, as a `form.form` with checkboxes and selects (labels wrap fields):
- **When someone else pots your ball** (Sink balls you win only): select, "You're out" / "Nothing happens".
- **Lock numbers with a PIN**: checkbox, off.
- **Hide numbers after**: select, 10 / 15 / 30 seconds. Default 15.
- **Vibrate when a ball is potted**: checkbox, on (uses `navigator.vibrate(30)` where supported; silently skipped elsewhere).
- **Clear recent names**: `.btn.small`, then toast "Recent names cleared."
- Changes save on change. Toast "Settings saved." Settings that matter mid-game are locked during a game, with a `small` saying why.

### 7.10 Leave game

`dialog.dlg#kp-exit`: `h2` "Leave this game?", text "The game is saved on this device, so you can resume it from the Kelly Pool page."
Actions: **Keep playing** (primary), **Leave and save**, **End game** (`.btn`, opens a second confirm: "End this game for good?" → **End game** / **Cancel**).

### 7.11 Game over

When a winner is decided, the outcome dialog announces it (section 8). Closing it shows the **Game over** view:
- Hero area: `h2` "Tom wins" with a gold badge, and the reason ("Last player standing" / "Potted their own ball, the 12").
- **Everyone's numbers** card: a `.table-wrap` table with columns Player, Numbers (small balls; potted ones dimmed), Result (Winner / Out / In).
- `.actions`: **Play again** (primary; same players and settings, fresh deal), **Change players**, **Back to Kelly Pool**.
- History stays available from the toolbar.

## 8. Dialog and message copy

British spelling, sentence case, no exclamation marks, no full stops on headings or buttons.

### Outcome dialogs

| Case | Heading | Body | Buttons |
|---|---|---|---|
| No one's ball | No one's ball | Ball 1 isn't anyone's number. | **Carry on** |
| LMS, owner has balls left | Tom loses a ball | Ball 8 was Tom's. Tom has 2 balls left. | **Carry on** |
| LMS, owner's last ball | Tom is out | Ball 6 was Tom's last ball. | **Carry on** |
| LMS, one player left | Lewis wins | Ball 6 knocked out Tom. Lewis is the last player standing. | **See results** |
| Sink, owned ball (question) | Who potted the 8? | Ball 8 is Tom's. | **Tom potted it** (primary), **Someone else** |
| Sink, owner potted it | Tom wins | Tom potted the 8, one of their own balls. | **See results** |
| Sink, someone else, "You're out" rule, balls left | Tom loses a ball | Someone else potted the 8. Tom has 1 ball left. | **Carry on** |
| Sink, someone else, last ball | Tom is out | Someone else potted Tom's last ball, the 8. | **Carry on** |
| Sink, someone else, "Nothing happens" rule | Ball 8 was Tom's | Someone else potted it, so Tom stays in. | **Carry on** |
| All balls potted, no winner | No winner | Every ball has been potted. | **See results** |

### Other strings

| Where | Text |
|---|---|
| Combination limit note | 6 players can have up to 2 balls each (15 balls in the rack). |
| Ball count adjusted | Balls each changed to 2 so everyone gets a full set. |
| Deal summary | 3 players × 3 balls = 9 of 15 balls dealt. 6 belong to no one. |
| Empty name | Enter a name. |
| Duplicate name | Each player needs a different name. |
| PIN invalid | Enter 4 numbers. |
| PIN wrong | That PIN doesn't match. Try again. |
| Auto-hide note | Your numbers hide after 15 seconds. |
| Undo toast | Ball 7 is back on the table. |
| Resume meta | Last man standing · 3 players · 9 balls on the table |

### Rules text

**Sink balls you win**
1. Everyone is dealt secret numbers. Keep them to yourself.
2. Take turns. Hit the lowest-numbered ball on the table first.
3. Pot one of your own numbers and you win straight away.
4. If someone else pots one of your numbers, you lose it (unless the table plays "Nothing happens"). Lose them all and you're out.
5. A ball potted on a foul goes back on the table. Don't tap it.

**Last man standing**
1. Everyone is dealt secret numbers. Each one is a life.
2. Take turns. Hit the lowest-numbered ball on the table first.
3. Whenever one of your numbers goes down, whoever potted it, you lose a life.
4. Lose all your lives and you're out.
5. The last player with a life left wins.

## 9. State model

One object, saved to `localStorage` under `cp.kellypool.v1` after every change (wrapped in try/catch; the page works without storage).

```js
{
  v: 1,
  mode: 'sink' | 'lms',
  phase: 'setup' | 'names' | 'deal' | 'play' | 'over',
  settings: {
    othersPotYourBall: 'out' | 'nothing', // sink mode only
    pin: false,
    hideAfter: 15,
    vibrate: true
  },
  setup: { players: 3, balls: 3 },
  players: [
    { id: 'p1', name: 'Tom', balls: [6, 8, 12], lost: [8], out: false, pin: null }
  ],
  deal: { index: 0, stage: 'pass' | 'reveal' },  // stage is reset to 'pass' on load
  potted: { '8': { owner: 'p1', pottedBy: 'owner' | 'other' | null, at: 1761250000000 } },
  winner: null | 'p2',
  endReason: null | 'own-ball' | 'last-standing' | 'no-winner',
  history: [ { ball: 8, owner: 'p1', text: 'Ball 8 was Tom’s. Tom has 2 balls left.', at: 1761250000000 } ],
  undo: [ /* JSON snapshots of the state above minus undo, capped at 50 */ ],
  startedAt: 1761249000000
}
```

Separate keys:
- `cp.kellypool.names`: recent names array (max 12).
- `cp.kellypool.settings`: last-used settings, applied to new games.

The PIN is stored in plain text on the device. It stops casual peeking; say so in the settings `small`: "Stops casual peeking. It isn't secure storage."

## 10. Game logic (pseudo-code)

```text
maxBalls(players) = min(5, floor(15 / players))

deal():
  rack = shuffle([1..15])           // crypto.getRandomValues for fairness
  for each player i: player.balls = sort(rack.slice(i*n, (i+1)*n))
  phase = 'deal'; deal = { index: 0, stage: 'pass' }

ballsLeft(p) = p.balls.length - p.lost.length
alive() = players where !out

tapBall(n):
  if potted[n] or phase != 'play': return
  owner = player whose balls include n
  if !owner:
    record(n, null); show('no-one'); return
  if mode == 'lms':
    loseBall(owner, n)
  else (sink):
    ask "Who potted the n?" →
      owner      → record(n, owner, 'owner'); win(owner, 'own-ball')
      someone else →
        if settings.othersPotYourBall == 'out': loseBall(owner, n, 'other')
        else: record(n, owner, 'other'); show('nothing-happens')

loseBall(owner, n, by):
  snapshot()
  record(n, owner, by); owner.lost.push(n)
  if ballsLeft(owner) == 0:
    owner.out = true
    if alive().length == 1: win(alive()[0], 'last-standing'); return
    show('out')
  else show('lose-ball')
  if all 15 potted and no winner: endNoWinner()

win(p, reason): winner = p.id; endReason = reason; phase = 'over'; show('win')

undo(): state = undo.pop() (keep the rest of the undo stack); re-render; toast
```

Order of work on every tap: snapshot for undo → update state → save → re-render board → open dialog.

## 11. Markup skeleton

```php
<!doctype html>
<html lang="en-GB">
<head><?php page_head(); ?></head>
<body>
<?php site_header(); ?>
<main class="page kp" id="main">

  <!-- Home -->
  <header class="page-hero kp-hero" data-view="home">
    <img class="page-hero-bg" src="/media/games/kelly-pool/hero-960.webp"
         srcset="/media/games/kelly-pool/hero-960.webp 960w, /media/games/kelly-pool/hero-1920.webp 1920w"
         width="1920" height="800" alt="" fetchpriority="high" decoding="async">
    <div class="page-hero-body">
      <div class="grow">
        <h1 class="page-title">Kelly Pool</h1>
        <p class="page-lede">Draw secret numbers, sink balls and be the last player standing.</p>
      </div>
    </div>
  </header>

  <section data-view="home" class="kp-home">
    <section class="card kp-resume" hidden> … Game in progress … </section>
    <div class="grid">
      <section class="card">
        <h2>Sink balls you win</h2>
        <p class="muted">Pot one of your own balls to win straight away.</p>
        <div class="actions"><button class="btn primary" data-act="kp-mode" data-id="sink">Start sink balls you win</button></div>
      </section>
      <section class="card">
        <h2>Last man standing</h2>
        <p class="muted">Lose a ball every time one of yours goes down. Last player left wins.</p>
        <div class="actions"><button class="btn" data-act="kp-mode" data-id="lms">Start last man standing</button></div>
      </section>
    </div>
    <div class="actions">
      <button class="btn" data-act="drawer" aria-controls="kp-rules">Read the rules</button>
      <button class="btn" data-act="drawer" aria-controls="kp-settings">Settings</button>
    </div>
  </section>

  <!-- Players and balls -->
  <form class="form form-card" data-view="setup" hidden> … </form>

  <!-- Names -->
  <form class="form form-card" data-view="names" hidden> … </form>

  <!-- Pass / reveal: full screen -->
  <section class="kp-private" data-view="private" hidden aria-live="polite"> … </section>

  <!-- Board -->
  <section class="kp-board" data-view="play" hidden>
    <div class="kp-bar">
      <h1 class="page-title kp-mode-title">Last man standing</h1>
      <div class="kp-tools">
        <button class="kp-icon" data-act="drawer" aria-controls="kp-history" aria-label="History" title="History"><?= site_icon('history') ?></button>
        <button class="kp-icon" data-act="drawer" aria-controls="kp-rules" aria-label="Rules" title="Rules"><?= site_icon('info') ?></button>
        <button class="kp-icon" data-act="dialog" aria-controls="kp-exit" aria-label="Leave game" title="Leave game"><?= site_icon('close') ?></button>
      </div>
    </div>
    <div class="stat-row"> … </div>
    <section class="card"><h2>Players <span class="n">3</span></h2><div class="kp-players"></div></section>
    <section class="card"><h2>Balls <span class="n">15 left</span></h2><div class="kp-rack"></div></section>
  </section>

  <!-- Game over -->
  <section data-view="over" hidden> … </section>

  <!-- Drawers and dialogs -->
  <aside class="drawer" id="kp-history" aria-label="History" aria-hidden="true" tabindex="-1">
    <button class="close" data-act="drawer-close" aria-label="Close">×</button> …
  </aside>
  <aside class="drawer" id="kp-rules" aria-label="Rules" aria-hidden="true" tabindex="-1"> … </aside>
  <aside class="drawer" id="kp-settings" aria-label="Settings" aria-hidden="true" tabindex="-1"> … </aside>
  <dialog class="dlg" id="kp-outcome" aria-labelledby="kp-outcome-title"> … </dialog>
  <dialog class="dlg" id="kp-exit" aria-labelledby="kp-exit-title"> … </dialog>
</main>
<script src="/assets/js/pages/games-kelly-pool.js" defer></script>
<?php site_footer(null, false); ?>
</body>
</html>
```

Check the `aria-controls` drawer and dialog hook against what `ui.js` actually reads. The guide only names `data-act="drawer"` and `data-act="dialog"`.

## 12. CSS plan

File `assets/css/pages/games-kelly-pool.css`. The first line is a one-line comment. `var(--…)` everywhere except the ball and cloth colours. Spacing only from 4/6/8/10/12/14/16/20/24/28. Radii 4/8/12/999.

```css
/* Kelly Pool: pass-and-play tracker (games/pool/kelly-pool) */
.kp { --kp-cloth: #1f5a3c; --kp-cloth-edge: #163f2b; --kp-ivory: #f7f3e8; --kp-ink: #151515; }
```

| Class | Purpose and key rules |
|---|---|
| `.kp-picks` | `display:grid; grid-template-columns:repeat(4,1fr); gap:8px`. From 480px, `repeat(auto-fill, minmax(64px,1fr))`. |
| `.kp-pick` | Button, 56px tall, `--btn2` background, radius 8px, 20px 600 text, `tabular-nums`. `.on` = `--accent-soft` + `--accent` border. `:disabled` = 40% opacity. |
| `.kp-chips` | `display:flex; flex-wrap:wrap; gap:8px`. |
| `.actions` (page override) | On phones in setup, names and private views: `position:sticky; bottom:0; padding-block:12px; background:var(--bg)`. Matches the reference's pinned **Next**. |
| `.kp-private` | `position:fixed; inset:0; z-index:40; background:var(--sunken); display:grid; grid-template-rows:auto 1fr auto`. Padding uses safe-area insets. |
| `.kp-pass-name` | `font-size:clamp(32px,9vw,56px); font-weight:700; text-align:center; text-wrap:balance`. |
| `.kp-bar` | Flex, space-between, `gap:12px`. The title truncates with an ellipsis. |
| `.kp-icon` | 40×40, radius 999px, `border:1px solid var(--line-strong)`, transparent, `color:var(--text)`. Hover: `--btn2-hover` + `--accent` border. SVG 22px. |
| `.kp-players` | `grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px`. From 720px, `repeat(auto-fill,minmax(200px,1fr))`. |
| `.kp-player` | `--card2` background, radius 8px, 56px tall, grid of name / pips / badge. `.is-out` = muted text and line-through. |
| `.kp-pips i` | 8px dots, `--line-strong` empty, `--accent` filled. |
| `.kp-rack` | `background:var(--kp-cloth); border:6px solid var(--kp-cloth-edge); border-radius:12px; padding:12px; display:grid; gap:8px; grid-template-columns:repeat(3,1fr)`. 5 columns from 480px, 8 from 1024px. |
| `.kp-slot` | Bare button reset, `display:grid; justify-items:center; gap:4px`, radius 8px. Hover `rgb(255 255 255 / .08)` (overlay on art is allowed). |
| `.kp-ball` | `--d` size custom property. Solid = radial highlight over `--ball`. Stripe = ivory/colour/ivory bands. Number disc 56% ivory with `--mono` digits at `calc(var(--d)*.3)`. The 8-ball gets `box-shadow: 0 0 0 1px rgb(255 255 255 / .5)` so it shows on dark backgrounds (like the reference's white ring). |
| `.kp-ball.potted` | `opacity:.3; filter:grayscale(.7)`. |
| `.kp-cap` | 12px muted, ellipsis. |

Ball colours (literal, real meaning):

| Ball | 1/9 | 2/10 | 3/11 | 4/12 | 5/13 | 6/14 | 7/15 | 8 |
|---|---|---|---|---|---|---|---|---|
| Colour | `#f3c623` yellow | `#1d4fb8` blue | `#d0342c` red | `#57308f` purple | `#f07f1d` orange | `#178049` green | `#7d1f2b` maroon | `#151515` black |

Motion: the drawer uses the site's 180ms. Pot feedback is a 120ms fade on the ball. No entrance animations. `@media (prefers-reduced-motion: reduce)` removes the fade.

## 13. JavaScript plan

`assets/js/pages/games-kelly-pool.js`, plain script, `defer`, wrapped in an IIFE. Start inside `DOMContentLoaded` because `ui.js` is deferred too.

**Modules (sections of the one file)**

| Section | Functions |
|---|---|
| Store | `load()`, `save()`, `loadNames()`, `saveNames(list)`, `loadSettings()`. All in try/catch. |
| Rules | `maxBalls(n)`, `deal()`, `ownerOf(ball)`, `ballsLeft(p)`, `alive()`, `tapBall(n)`, `answerWho(who)`, `loseBall()`, `win()`, `endNoWinner()`, `snapshot()`, `undo()` |
| Views | `show(view)`, `renderSetup()`, `renderNames()`, `renderPrivate()`, `renderBoard()`, `renderHistory()`, `renderOver()`, `renderResume()` |
| Dialogs | `openOutcome(kind, data)`: fills `#kp-outcome`, then `showModal()`. Close handlers go back to the board or game over. |
| Privacy | `startReveal(playerId)`, `hideReveal()`, auto-hide timer, `visibilitychange` → hide |
| Events | One delegated `click` on `.kp` for `data-act` values: `kp-mode`, `kp-pick-players`, `kp-pick-balls`, `kp-next`, `kp-back`, `kp-chip`, `kp-shuffle`, `kp-iam`, `kp-hide`, `kp-player`, `kp-ball`, `kp-who`, `kp-undo`, `kp-resume`, `kp-abandon`, `kp-again`, `kp-change`. One `submit` handler for the forms. One `change` handler for settings. |

**Conventions from the guide**
- Escape every name with `UI.esc()` before it goes into HTML.
- No `console.log` and no inline handlers.
- `UI.toast()` for undo, settings and recent names messages.
- `UI.ago()` for history times.
- The board re-renders by replacing the `.kp-players` and `.kp-rack` contents only, so focus stays on the toolbar.
- After a ball tap, focus returns to the same `.kp-slot` when the dialog closes (the native dialog does this).

**Browser back button**
- Push a history entry for each step (`history.pushState({kp: 'names'}, '')`). `popstate` moves back a step during setup.
- During play, back opens the **Leave game** dialog instead of leaving.
- During pass/reveal, back always goes to the pass screen, never to someone else's numbers.

## 14. Accessibility

- Ball buttons have full `aria-label`s ("Ball 7, on the table" / "Ball 7, potted, Tom's ball").
- Picks use `aria-pressed`. The groups use `role="group"` with `aria-labelledby` pointing at their heading.
- Dialogs: native `<dialog>` + `showModal()` gives focus trapping and Esc. Heading is linked with `aria-labelledby`.
- Drawers: `aria-hidden` toggled, focus moves to the drawer, Esc closes, focus returns to the opener.
- The private screen announces "Pass to Tom" via `aria-live="polite"`. Numbers are read as "Your numbers: 6, 8 and 12".
- Colour is never the only signal: potted balls have captions, out players have a badge and line-through.
- Touch targets ≥ 40px (picks and balls are much bigger).
- Visible `:focus-visible` ring from the site.

## 15. Edge cases

| Case | Handling |
|---|---|
| players × balls > 15 | Impossible: ball picks above `maxBalls` are disabled. |
| Reload during a reveal | `deal.stage` resets to `pass`, so nobody's numbers show on load. |
| Reload mid-game | Resume card on the home view. Game continues exactly where it was. |
| Tapped the wrong ball | **Undo last pot** in History. Undo also reverses outs and wins. |
| Wrong answer to "Who potted it?" | Same undo. |
| All 15 balls potted, no winner | Possible in Sink mode with "Nothing happens". Show "No winner" and game over. |
| One player left after someone else's ball goes down | Win check runs after every loss. |
| Duplicate or blank names | Blocked on submit with field messages. |
| Very long names | `maxlength="16"`, ellipsis in tiles, full name in `title`. |
| Storage blocked (private browsing) | The game works for the session. A `p.note` on the home view says "This browser won't save the game if you leave the page." |
| Tab hidden while numbers are showing | Numbers hide at once. |
| Two devices | Not supported; one device is passed around. Say so in the rules drawer. |
| Settings changed mid-game | Game-affecting settings are locked during play. |

## 16. Test plan

**Screens:** no sideways scroll at 320, 390, 1366 and 2560px for every view (home, setup, names, pass, reveal with 5 balls, board with 15 players, both drawers, every dialog, game over).

**Scenarios**
1. LMS, 3 players × 3 balls: pot an unallocated ball, then one of Tom's twice, Tom's last ball (out), then Lewis's last ball. Ally wins. Results table shows all numbers.
2. Sink, 4 × 1: owner pots their own ball → instant win.
3. Sink, "You're out": someone else pots Tom's only ball → Tom out. Repeat until one player is left.
4. Sink, "Nothing happens": pot all 15 balls without the owner → "No winner".
5. Undo across an out and across a win.
6. 15 players × 1 ball, and 3 players × 5 balls (full rack, no unallocated balls).
7. Reload at each phase. A reveal never shows after a reload.
8. PIN on: wrong PIN, right PIN, auto-hide, hiding when switching tabs.
9. Recent names: fill, use, clear.
10. Keyboard only: complete a full game with Tab, Enter and Esc.
11. Screen reader smoke test (TalkBack on Android, VoiceOver on iOS) for the pass and reveal flow and a pot.
12. Reduced motion on: no fades.

**Checklist from the guide:** in `site.json`, in menus/search/breadcrumbs, one `h1.page-title` with a one-line lede, one primary button per view, only components plus prefixed CSS, no `console.log`, everything escaped, Esc closes everything, scripts deferred, hero art high priority with dimensions.

## 17. Build order

| Step | Work | Done when |
|---|---|---|
| 1 | Page scaffold: folder, `index.php` from the template, `site.json` entry, empty CSS and JS, hero with placeholder art | The page shows in menus and search, with breadcrumbs |
| 2 | Rules engine (section 10) with no UI. Test it from the console in dev only. | All test scenarios 1–6 pass in logic |
| 3 | Setup and names views, recent names, validation | A game can be dealt |
| 4 | Pass and reveal, auto-hide, reload safety | Scenario 7 passes |
| 5 | Board: players, ball grid, stat row, outcome dialogs | Scenarios 1–4 pass in the UI |
| 6 | History drawer with undo, rules drawer, leave dialog, resume card | Scenario 5 passes |
| 7 | Game over view and play again | Full loop works |
| 8 | Settings drawer and PIN | Scenario 8 passes |
| 9 | Hero art, polish, accessibility pass, all screen widths | Section 16 fully passes |
| 10 (phase 2) | Optional "Show whose turn it is" setting | |

## 18. Open questions

1. **Group in the URL:** is there an existing group under Games this belongs in, or is `/games/pool/` fine?
2. **Hero art:** do you have (or want) your own 3-ball and cues artwork? It shouldn't be copied from the reference app.
3. **Default rule** for Sink balls you win when someone else pots your ball: "You're out" or "Nothing happens"?
4. **`ui.js` hooks:** confirm how drawers and dialogs find their target (`aria-controls`, `data-id` or `href`), so the markup in section 11 matches.
5. **Where page JS lives:** is `assets/js/pages/` right, or is there an existing folder?
6. Should finished games be kept as a short list of recent results on the home view (on this device only)?
