# Handoff: restyle the Minecraft tab

**Read `_dev/handoffs/README.md` first.** It holds the rules shared by every section: one or two pages at a time, the design guide (`building-pages.md`, and `/design/` for live samples), staying inside your tab, safe git commands and testing. Your test port is **8101** and your Chrome debug port is **9401**.

## Your files
- **Folders you may edit:** `minecraft/` (everything below it).
- **Stylesheets that are yours:** `assets/css/pages/minecraft.css`, `minecraft/shop/styles.css`, and any new `assets/css/pages/minecraft-*.css`.
- **Shared, don't edit:** `assets/css/pages/gallery.css` (Extras uses it too) and `assets/css/pages/hero.css` (home page and BRB). Copy what you need into a `minecraft-*.css`.

## Don't touch
- `minecraft/tools/nbt-viewer/`: it's the second reference design (after `/games/steam/`).
- `minecraft/index.php`: it's already modern (slideshow and card grid).
- `minecraft/tools/index.php`, `minecraft/projects/index.php`, `minecraft/downloads/`: already modern.
- **Data files written by the live site:** `minecraft/projects/metro/stations.json`, `minecraft/projects/milestones/milestones.json`, and `minecraft/tools/nbt-viewer/{cache,shares}/`. Testing locally can change them. Never commit them; if one shows as modified after testing, restore just that file with `git restore <that file>`.

## Pages, in suggested order
**Needs rework (old styles, default grey controls, light panels):**
| Page | File | Notes |
|---|---|---|
| Screenshots | `minecraft/screenshots/index.php` | A gallery plus a `?id=` full-size viewer. `?id=` goes through `basename()`, so keep that. |
| Skins | `minecraft/skins/index.php` | Uses `gallery.css` (shared). |
| Tips | `minecraft/tips/index.php` | A text and link list. |
| Day Calculator | `minecraft/tools/day-calculator/index.php` | A small form. |
| Dynmap Marker Creator | `minecraft/tools/dynmap-marker/index.php` | A form that outputs a command. Marker icons are in `assets/img/markers/`. |
| Book Generator | `minecraft/tools/book-generator/index.php` | 14 inputs. Its body layout was moved onto `.tool-wrap`. |
| Transaction Generator | `minecraft/tools/transaction-generator/index.php` | Loads `names.csv` next to it. |
| Stats Editor | `minecraft/tools/stats-editor/index.php` | Its own textarea scrolls the page sideways by 2px. |
| JSON Viewer | `minecraft/tools/json-viewer/index.php` | Syntax colours already darkened. |
| Litematica Material Converter | `minecraft/tools/litematica-converter/index.php` | Material List was merged into it (archived in `_dev/archive/minecraft/material-list/`). Stack sizes come from `shulker-calculator/schematic.js`. |
| Item List | `minecraft/tools/item-list/index.php` | 1,507 lazy-loaded icons. Keep `loading="lazy"`. |
| Map Art | `minecraft/projects/map-art/index.php` | A gallery from `media/minecraft/mapart/`. |
| Stonecrest | `minecraft/projects/stonecrest/index.php` | Download links. |
| Life Series index and Sessions 1–3 | `minecraft/projects/life-series/**/index.php` | One shared style for all 4. |
| Shop, Enchanted Books, Special Requests, Casino | `minecraft/shop/**/index.php` | They share `minecraft/shop/styles.css` (yours). The header shows the shop logo and tabs automatically. |

**Close (own dark style; polish it to the site tokens and fonts):** Nether Portal Converter, Storage Sorter, TNT Cannon Targeting, Metro Network, Milestones, Rail Router, Shulker Calculator, Litematica Converter, Instance Playtime, Frame Extractor, all in `minecraft/tools/*` and `minecraft/projects/*`.
- Several load the Roboto font from Google Fonts, but the design guide wants the system font.
- Metro, Milestones, Rail Router and Nether Portal are full-screen apps with no footer. Keep them that way.
- **Rail Router** shows a "Could not load items.json" pop-up because `minecraft/tools/item-list/items.json` was deleted in commit `adb2ca7` (Feb 2026). Restoring it from git history is in scope if the user agrees.
