# Handoff: restyle the Extras tab

**Read `_dev/handoffs/README.md` first.** It holds the rules shared by every section: one or two pages at a time, the design guide in `/plan.html`, staying inside your tab, safe git commands and testing. Your test port is **8105** and your Chrome debug port is **9405**.

## Your files
- **Folders you may edit:** `extras/` (everything below it).
- **Stylesheets that are yours:** `assets/css/pages/dnd.css` and `assets/css/pages/win10.css`, plus any new `assets/css/pages/extras-*.css`.
- **Shared, don't edit:** `assets/css/pages/gallery.css` (Minecraft uses it too) and `assets/css/pages/hero.css` (home page and Minecraft). Copy what you need into an `extras-*.css`.

## Don't touch
- `extras/index.php`: already a modern card grid.
- `extras/brb/`: already modern (slideshow hero).
- **Full-screen screens and pranks**, which have no site header on purpose:
  - `extras/flashing-colours/`
  - `extras/twitch-chat/` (an OBS overlay)
  - `extras/fake-updates/windows10/update.html` and `crash.html`
  - `extras/memes/` and `extras/memes/cat/` (the cat page is a Discord embed that redirects)
  - `extras/quotes/` (returns a PNG, not a page)

## Pages, in suggested order
| Page | File | Notes |
|---|---|---|
| Trump Quotes | `extras/trump-quotes/index.php` | Calls an external quote API. The bouncing mugshot is `media/memes/trump-mugshot.webp`. |
| Insults | `extras/insults/index.php` | Calls an external API. |
| Random Fact | `extras/facts/index.php` | Calls an external API, which may need the API Ninjas key: check for `$apininjakey`, and if it's used add `'config' => true`. |
| Live Laugh Love | `extras/live-laugh-love/index.php` | Big (about 250 lines, with a table). |
| Pets | `extras/pets/index.php` | A gallery of `media/pets/*/thumb.webp`. Uses `gallery.css` (shared). |
| Pet | `extras/pets/pet/index.php` | Needs `?pet=<folder>`, otherwise it redirects to the gallery. `?pet=` goes through `basename()` and is escaped, so keep both. |
| Fake Update Screens index | `extras/fake-updates/index.php` | Links to `windows10/`. |
| Windows 10 Screens index | `extras/fake-updates/windows10/index.php` | Preview tiles of the 2 screens. |
| Cyberpunk 2077 | `extras/cyberpunk/index.php` | A small page. |
| D&D Character | `extras/dnd/index.php` | Uses `dnd.css`. It reads `/extras/examples/dndcharacter.json`, which has never existed; ask the user. |
| Bee Movie | `extras/bee-movie/index.php` | The whole script, about 4,000 lines. Only the surrounding layout needs restyling. |
