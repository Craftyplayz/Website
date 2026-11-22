# Handoff: card-game resource pack (playing cards + Uno) for the Minecraft server

**Read `_dev/handoffs/README.md` first** (shared rules: ports, staying in your files, git). This task lives in `_dev/card-pack/`. It is a Minecraft resource pack, not a web page, so `building-pages.md` does not apply.

## Goal
One resource pack for the owner's friends' server (**Java 26.3**, vanilla, no datapacks; the owner refused one) that turns **renamed gold nuggets** into playing cards and Uno cards:

| Where the card is | What the viewer sees |
|---|---|
| In **your own** inventory or hand | the card face |
| In **someone else's** hand | the card back |
| In an **item frame** | the face, for everyone (played or revealed) |
| On a **shelf** or **dropped** on the ground | the back, for everyone (face-down) |
| An unnamed gold nugget, or any other name | the normal gold nugget |

Every player runs the same pack (it is the server resource pack), so there are no per-seat packs and no player numbers in card names. Uno: a card is played by putting it in an item frame. Poker: hole cards stay in your inventory; community or face-down cards sit on shelves and are revealed by moving them to item frames.

## Card art (the owner downloads it; you do not)
- **Uno:** IvoryRed's **Pixel UNO Cards**, https://ivoryred.itch.io/pixel-uno-cards
- **Playing cards:** IvoryRed's pixel playing-card set (linked from https://ivoryred.itch.io/).
- The owner puts both zips in `C:\Users\craft\Downloads`. itch.io rate-limits scripts (HTTP 429), so don't try to download them yourself; ask the owner.
- Fallback if a set is missing cards: Kenney's **Playing Cards Pack**, CC0, https://kenney.nl/assets/playing-cards-pack
- **Licence:** the Uno art is Mattel's and IvoryRed's. The owner accepts that for a private friends' server. The art and the built pack must **never** go into git (the repo is public on GitHub): keep the art in `_dev/card-pack/art/` and the output in `_dev/card-pack/build/`, both gitignored.
- Keep the pixel art crisp: no blurry scaling. If cards are not square, centre them on a transparent square canvas, scaled up only by whole numbers (nearest neighbour).

## Card names (exact, case-sensitive: the pack matches the item's custom name)
- **Playing cards (54):** `Ace of Spades`, `2 of Spades` … `10 of Spades`, `Jack of Spades`, `Queen of Spades`, `King of Spades`, the same for `Hearts`, `Diamonds` and `Clubs`, plus 2 × `Joker`. Back: one shared playing-card back.
- **Uno (108 cards, 54 different faces):** for each of `Red`, `Yellow`, `Green` and `Blue`:
  - `<Colour> 0` (×1);
  - `<Colour> 1` … `<Colour> 9` (×2 each);
  - `<Colour> Skip`, `<Colour> Reverse`, `<Colour> +2` (×2 each).
  Plus `Wild` (×4) and `Wild +4` (×4). Back: the Uno back.
- `_dev/card-pack/deck_command.py` is the source of truth for these names. If an art set uses other words (for example "Draw Two"), map the files to these names; don't rename the cards.

## Facts already checked in the 26.3 client jar
The jar is `C:\Users\craft\AppData\Roaming\PrismLauncher\libraries\com\mojang\minecraft\26.3\minecraft-26.3-client.jar`.
- **Resource pack format:** 97.1 (`version.json` → `pack_version.resource_major/minor`).
- **`minecraft:display_context`** is a select property, with the cases `fixed` (item frame), `on_shelf`, `ground`, `gui`, the hand contexts and so on. The game's own `items/copper_spear.json` uses it.
- **`minecraft:view_entity`** exists: true when the item's holder is the viewer's own camera entity, meaning your own inventory or hand.
- **Not yet confirmed:** matching the name with `"property": "minecraft:component", "component": "minecraft:custom_name"` and a plain-string `when`, for both anvil-renamed items and `/give` items. Test this first. If it fails, look at how the jar's `items/*.json` match components and adapt.

## Model logic (`assets/minecraft/items/gold_nugget.json`)
```
select component minecraft:custom_name
  case "<card name>" ->
    select display_context
      fixed    -> face(card)
      on_shelf -> back(deck)
      fallback -> condition view_entity: true -> face(card), false -> back(deck)
  fallback -> vanilla item/gold_nugget model
```
Faces and backs are plain `minecraft:item/generated`-style models pointing at the card textures, under your own namespace (for example `castlecrafty:item/cards/ace_of_spades`). Generate the whole file and the ~110 model files with a script in `_dev/card-pack/`. Don't hand-write them.

## Steps
1. **Test pack first:** only `Ace of Spades` and `Red 7`, with placeholder textures if the art isn't downloaded yet. The owner checks in-game, with a friend or a second account if possible:
   - anvil-renamed and `/give` nuggets both show the face in your own inventory;
   - a friend holding one sees the back;
   - an item frame shows the face;
   - a shelf shows the back;
   - an unnamed nugget looks normal.
   Fix anything that fails before building the rest.
2. **Build script:** unzip the art, map the files to the names above, write the textures, models, `gold_nugget.json` and `pack.mcmeta` (format 97.1, description "Castle Crafty cards"), then zip. Add a self-check that every one of the 54 + 54 names has a face texture and a model.
3. **Hosting:** upload the zip over SSH (key `~/.ssh/ionos`, host and folder in `_dev/deploy.sh`) to an unlisted path on craftyplayz.com, for example `/files/cards-<random>.zip`.
   - Not under `/private/`: Minecraft can't send a password.
   - Not via `deploy.sh`: the file is gitignored, so deploy never sends it.
   - Give the owner the 2 lines for the server's `server.properties`: `resource-pack=<url>` and `resource-pack-sha1=<sha1 of the zip>`.
4. **Decks:** give the owner `_dev/card-pack/deck-chest-command.txt` (regenerate it with `py -I deck_command.py > deck-chest-command.txt` if the names change). It is one `/give` for a chest holding 4 shulker boxes: 2 red ones (54 playing cards) and 2 blue ones (108 Uno cards). It is about 11,400 characters and chat only takes 256, so an op pastes it into a **command block** and powers it once.

## Known limits (tell the owner, don't try to fix)
- **Cosmetic only:** card names travel with the items. A modified client or pack can read face-down cards, so this is for honest friends.
- **Forged cards:** anyone can make an Ace in an anvil (1 level). Keep one house deck per table and count it back in after each game.
- **Item frames show the item's name** when you look at them. That's fine, because a card in a frame is face-up anyway.
