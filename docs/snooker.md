# Snooker rules

Rules for the scoreboard at `/games/pub-games/snooker/` (`games/pub-games/snooker/`).
The primary rule set is the WPBSA Official Rules of Snooker (the rules used by World Snooker and most leagues). Pub, home and league variants are listed in [Rule sets and house rules](#rule-sets-and-house-rules).

## 1. Equipment and setup

- Full-size table: 12 ft × 6 ft (playing area 11 ft 8½ in × 5 ft 10 in). Pubs and homes often have 6, 7, 8 or 10 ft tables, usually played with fewer reds (see variants).
- 22 balls: one white cue ball, 15 reds and six colours.

| Ball | Value | Spot |
|---|---|---|
| Red (×15) | 1 | Triangle behind the pink, apex ball as close to the pink as possible without touching |
| Yellow | 2 | Right-hand corner of the D (looking up the table from the baulk end) |
| Green | 3 | Left-hand corner of the D |
| Brown | 4 | Middle of the baulk line |
| Blue | 5 | Centre of the table |
| Pink | 6 | Pyramid spot, halfway between the centre spot and the top cushion |
| Black | 7 | Black spot, 12¾ in from the top cushion |

- The "D" is a semicircle of radius 11½ in centred on the brown spot, behind the baulk line.
- Players lag (or toss) for who breaks the first frame; after that the break alternates each frame.

## 2. Break-off and the order of play

- The first stroke of each frame is played with the cue ball **in hand**: placed anywhere on or inside the lines of the D.
- The striker must hit a red first. Reds are the first ball on.
- **Alternate red then colour.** After potting a red, the striker nominates and plays a colour (any of the six). After a colour, the striker is on a red again.
- More than one red can go down in the same stroke: each counts 1 point, and the striker then plays one colour. Potting a red and a colour in the same stroke when on a red is a foul.
- **Colours are re-spotted** while any reds remain on the table (and after a colour is potted as the colour after the last red). Reds are never put back, even after a foul.
- **Spot occupied:** a colour whose own spot is taken goes on the highest-value free spot. If every spot is taken, it goes as near its own spot as possible on the straight line between that spot and the top cushion (or, failing that, below it towards the baulk cushion).
- **After the last red** has been potted, the striker may take any colour. From then on the colours are cleared in order of value, and stay down: **yellow, green, brown, blue, pink, black**.
- If the last red goes down on a foul, the next player is on the yellow.
- A **visit** (turn) continues until the striker fails to pot a ball on, or fouls. The points scored in one visit are a **break**.

## 3. End of the frame

- When only the black is left, **the first score or foul ends the frame**, and the higher score wins.
- **Re-spotted black:** if the scores are level when the black is potted (or fouled), the black is re-spotted. The players toss a coin; the winner chooses who plays first. That player has the cue ball in hand in the D. The next score or foul ends the frame.
- A frame can also end by **concession**: a player may concede a frame at any time and the opponent wins it. Etiquette: concede once you need more snookers than you can realistically get; don't concede while you can still win on the table. Conceding a match concedes all its remaining frames.
- A match is "best of N" frames: first to more than half wins.

## 4. Scoring and points remaining

- Each pot scores the ball's value; fouls give penalty points to the opponent.
- **Points remaining** on the table:
  - while a red is on: `reds × 8 + 27` (each red followed by a black, then the six colours 2+3+4+5+6+7 = 27)
  - after a red has been potted and the colour is on: `reds × 8 + 7 + 27`
  - in the clearance: the sum of the colours still on the table (yellow on = 27, green on = 25, brown on = 22, blue on = 18, pink on = 13, black only = 7)
- **Snookers required**: when a player is behind by more than the points remaining, they cannot win without the opponent giving away foul points. Number of snookers needed ≈ `ceil((deficit − points remaining) ÷ foul value)`, where the foul value is 4 while reds or the yellow/green/brown are on, 5 on the blue, 6 on the pink, 7 on the black. If the deficit exactly equals the points remaining, clearing the table ties the frame and forces a re-spotted black.
- **Maximum break:** 147 = 15 reds each with the black (15 × 8 = 120) + all the colours (27). With a **free ball** at the start of the frame the free ball counts as a 16th red: 155 (16 × 8 + 27). With 10 reds the maximum is 107; with 6 reds it's 75.

## 5. Fouls and penalties

Every foul is worth **at least 4 points**, or the value of the **ball on**, or the value of the **highest-value ball involved** in the foul, whichever is highest. So fouls are worth 4 to 7 points. Penalty points go to the opponent; the striker's visit ends and nothing scored in the foul stroke counts.

Common fouls (penalty in brackets):

- Potting the cue ball (in-off) (value of the ball on, minimum 4). The next player then has the cue ball in hand in the D.
- Missing every ball (value of the ball on, min 4).
- Hitting a ball not on first, e.g. a colour when a red is on (higher of the ball on and the ball hit, min 4).
- Potting a ball not on, e.g. a colour when on a red, or a red when on a colour (higher of the balls involved, min 4).
- Potting a red **and** a colour in the same stroke when on a red (value of the colour, min 4). Two or more reds together is not a foul.
- Hitting two balls simultaneously, other than two reds or the free ball and the ball on (higher value, min 4).
- **Push stroke**: the tip stays on the cue ball as it hits an object ball, or after the cue ball starts moving (min 4 / value involved).
- **Jump shot**: the cue ball goes over any part of an object ball (value of the ball on, min 4).
- Moving the touching ball, or any ball, other than with the tip of the cue on the cue ball (value of the ball moved, min 4).
- Playing with both feet off the floor, before the balls have stopped, or out of turn (min 4).
- Forcing a ball off the table (value of that ball, min 4).
- Playing at reds (or a free ball then a red) in **successive strokes** (7).
- Using a ball off the table for any purpose, or any ball other than the white as the cue ball (7).
- Not declaring which ball you're on when asked, or fouling after potting a red before nominating a colour (7).
- Using any object to measure a gap (7).

**After a foul**, the next player chooses:

1. to play from where the balls stopped (cue ball in hand in the D if it was potted), or
2. to make the offender **play again** from where the balls stopped. Once asked, the request can't be withdrawn.

### Free ball

- If the next player is **snookered** after a foul (can't hit both extreme edges of every ball on in a straight line), the referee calls **free ball**.
- The player may then nominate **any colour** as the ball on. It counts as the ball on in every way: potting it scores the value of the ball on (1 if reds are on, else the value of the colour on). A nominated free ball that's potted is re-spotted.
- If the free ball and the real ball on are both potted, only the ball on scores (unless it was a red, when each red scores).
- It's a foul (value of the ball on) to **snooker the opponent behind the free ball**, except when only the pink and black remain.
- If the next player asks the offender to play again, the free ball call becomes void.
- A free ball taken while reds are on counts as a red: the striker then nominates a colour, but no red has left the table. That's why the maximum with a free ball is 155.

### The miss rule ("foul and a miss")

- The striker must always make their best attempt to hit a ball on. If the referee thinks they didn't, it's called **foul and a miss**, and the next player may also ask for the **balls to be put back** where they were before the stroke, with the offender to play again.
- A miss is not called when either player needs snookers before or after the foul (the referee may still call it if the miss looked deliberate), or when the snooker is impossible to escape and the stroke was a genuine attempt.
- **Three-miss rule**: if the striker had a clear path to hit the **full ball** (both edges) of a ball on and misses, a second failure from the same position is again a foul and a miss, the referee warns them, and a **third failure loses the frame**.

### Touching ball

- If the cue ball comes to rest touching a ball that is (or could be) on, the referee calls **touching ball**.
- The striker must play **away** from it without moving it (moving it is a push stroke). Playing away isn't a foul even if no ball is then hit, as long as the touching ball was on or nominated.
- If the touching ball isn't on, the striker must still hit a ball on in the normal way.

## 6. Stalemate and re-rack

If the referee thinks a stalemate exists or is close (neither player can reasonably avoid playing safe for ever), the players are offered an immediate re-start of the frame. If play goes on and the position doesn't change after a set number of strokes, the frame is re-racked anyway. Scores in that frame are cancelled and the **same player breaks**. In casual games the players simply agree to re-rack.

## 7. Etiquette

- Don't stand in the striker's eye-line or move while they play.
- Call your own fouls in games without a referee.
- Shake hands before and after; conceding a frame is fine once it can't be won, but don't concede while you still can.
- In the pub, the person at the table chalks up. Mark the score after each visit.

---

## Rule sets and house rules

Everything below lists what each set **changes compared with the WPBSA rules**; anything not mentioned is the same.

### WPBSA Official Rules (primary)

The rules above. 15 reds, 12 ft table, minimum 4-point fouls, free ball, put back in, miss rule with three-miss rule, re-spotted black, maximum 147 (155). Used by World Snooker Tour events and most national bodies.

### Six-red snooker (WPBSA six-reds rules, Six-red World Championship)

- 6 reds racked in a smaller triangle; maximum break 75.
- Foul and a miss: no more than **five consecutive** foul-and-a-miss calls. After four in a row the offender is warned; if a fifth is called the non-striker may play from where the balls are, put the offender back in, or take the cue ball **in hand anywhere on the table** (unless snookers are required).
- A player can't snooker behind a nominated colour **at any time** (not just behind a free ball).
- Some Indian / IBSF state events using six reds delete the foul-and-a-miss rule entirely.
- Everything else (penalties, free ball, re-spotted black) as WPBSA.

### Ten-red snooker

- 10 reds; maximum break 107. Popular in women's events (World Women's 10-Red Championship, 2017–2019). Otherwise WPBSA rules.

### Snooker Shoot Out (World Snooker ranking event)

- One frame, **10 minutes** maximum; the higher score at the end wins.
- **Shot clock**: 15 seconds per shot for the first 5 minutes, 10 seconds for the last 5. A time foul is at least 5 points (or the value of the ball on, if higher).
- **Every foul** gives the opponent the cue ball **in hand anywhere on the table**; they can't put the offender back in.
- Every stroke must **pot a ball or hit a cushion** with any ball, otherwise it's a foul.
- **No miss rule.**
- A level score is decided by a **blue-ball shoot-out** (blue on its spot, cue ball in the D, alternate attempts, sudden death) instead of a re-spotted black.
- Lag decides who breaks.

### Amateur and league variants

Local leagues publish their own "local rules"; the common changes are:

- **No miss rule** (or the miss rule only if both players agree). Very common in handicap and pub leagues.
- **Fixed 4-point fouls**: every foul is 4 points whatever ball is involved (seen in some social leagues and pubs).
- **Handicap starts**: each frame starts with the weaker player on a points start, the gap between the two handicaps (e.g. −21 against +14 gives a 35-point start).
- **Mercy / time rules**: frames or matches capped by time; when time is called the current frame finishes or the leader wins.
- Most leagues otherwise play WPBSA rules.

### Snooker Plus (Joe Davis, 1959)

- Adds two extra colours: **orange (8 points)**, spotted between the pink and blue, and **purple (10 points)**, spotted between the blue and brown.
- Each red can be followed by any of the eight colours; maximum break 210 (15 × 11 + 45), 221 with a free ball.
- A tied frame was settled on the purple rather than a re-spotted black.
- Only played at the 1959 News of the World Snooker Plus tournament; not supported by this scoreboard.

### Pub and home small-table conventions

- **Table size**: 6–8 ft pub or home tables are normal; they're usually played with **6 or 10 reds** so frames finish in a pint, and with smaller (1 7⁄8 in or 2 in) balls.
- **No referee**: players call their own fouls; free balls and misses are agreed between players or dropped.
- **No miss rule** and often **4-point fouls** (see league variants).
- **No free ball** in many casual games.
- **No put-back**: the next player always plays after a foul.
- **Cue ball in hand anywhere** after an in-off on very small tables where the D is cramped (borrowed from pool).
- **Concede freely** when snookers are needed.

### House rules supported on this page

| Setting | WPBSA | Change |
|---|---|---|
| Reds | 15 | 10 or 6 |
| Free ball after a foul | on | off |
| Opponent can put you back in | on | off |
| Miss rule (warning after two, frame lost on the third) | on | off |
| Foul values | 4–7 | always 4 |
| Handicap start | 0 | points start for either player |

Presets: **WPBSA official** (all WPBSA), **League** (no miss rule), **Pub / home** (no miss rule, 4-point fouls, no put-back). The shot clock and time limit of the Shoot Out aren't tracked.

---

## Sources

- WPBSA, *Official Rules of the Games of Snooker and English Billiards* (rulebook, revised May 2022): https://wpbsa.com/wp-content/uploads/Rulebook-Website-Updated-May-2022.pdf
- EBC / EBSA snooker rules (WPBSA text): https://www.ebc-billiard.com/rules/snooker
- CERN Snooker Club, WPBSA rules transcription: https://club-snooker.web.cern.ch/rules.html
- WPBSA rulebook (2019 edition, hosted copy): https://apes2023.co.nz/s/WPBSA-Snooker-and-Billiards-Rules-25.pdf
- EBSA rule clarifications: https://www.ebsa.tv/rule-clarifications/
- Wikipedia, Rules of snooker: https://en.wikipedia.org/wiki/Rules_of_snooker
- Wikipedia, Six-red snooker: https://en.wikipedia.org/wiki/Six-red_snooker
- RILSA, Rules of snooker and 6-red snooker: https://rilsa.ie/cue-sport-rules-snooker-billiards-89-black-ball-pool-carom/
- Wikipedia, Snooker Shoot Out: https://en.wikipedia.org/wiki/Snooker_Shoot_Out
- SnookerHQ, Snooker Shoot Out rules explained: https://snookerhq.com/explainers/snooker-shoot-out-rules-explained/
- SnookerHQ, Miss rule explained: https://snookerhq.com/explainers/miss-rule/
- Nottingham Snooker, handicap event rules (no miss rule unless agreed, handicap starts): https://www.nottinghamsnooker.com/?p=4422
- Wikipedia, 1959 News of the World Snooker Plus Tournament: https://en.wikipedia.org/wiki/1959_News_of_the_World_Snooker_Plus_Tournament
