<?php
$site_page = [
    'components' => true,
    'ui' => true,
    'css' => ['/assets/css/pages/minecraft-data.css'],
];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php page_head(); ?>
</head>

<body>
    <?php site_header(); ?>
    <main class="page" id="main">
        <?= site_breadcrumbs(site_current_path()) ?>
        <h1 class="page-title">Litematica Material Converter</h1>
        <p class="page-lede">Paste a Litematica material list to see stacks and shulker boxes, tick what you have, then copy it to Google Sheets.</p>

        <div class="data-cards">
            <section class="card">
                <h2>Material list</h2>
                <textarea class="field data-text" id="input" placeholder="Paste Litematica material list here…" spellcheck="false"></textarea>
                <div class="actions">
                    <button class="btn primary" type="button" onclick="convert()">Convert</button>
                </div>
            </section>

            <section class="card hide" id="result">
                <h2>Rows <span class="muted" id="rowcount"></span> <button class="btn small n-btn" type="button" id="copy" onclick="copyTSV(this)">Copy for Sheets</button></h2>
                <div class="table-wrap" id="preview"></div>
                <p class="muted lc-note">Stacks = ×64 (×16 for signs, banners and ender pearls). Shulker boxes = 27 stacks each. "Have it?" copies as TRUE/FALSE.</p>
            </section>
        </div>

<script>
const STACK1 = new Set([
  "written_book","enchanted_book","potion","splash_potion","lingering_potion",
  "tipped_arrow","knowledge_book","bundle","goat_horn","crossbow","bow",
  "fishing_rod","flint_and_steel","shears","shield","trident","elytra",
  "compass","clock","recovery_compass","spyglass","map","filled_map",
  "bucket","water_bucket","lava_bucket","milk_bucket","powder_snow_bucket",
  "axolotl_bucket","cod_bucket","salmon_bucket","tropical_fish_bucket","pufferfish_bucket",
  "tadpole_bucket","snow_ball","egg","honey_bottle","suspicious_stew",
  "cake","bed","white_bed","orange_bed","magenta_bed","light_blue_bed","yellow_bed",
  "lime_bed","pink_bed","gray_bed","light_gray_bed","cyan_bed","purple_bed",
  "blue_bed","brown_bed","green_bed","red_bed","black_bed",
  "armor_stand","item_frame","glow_item_frame","painting","flower_pot",
  "dragon_egg","beacon","conduit","bell","lodestone","respawn_anchor",
  "turtle_egg","turtle_scute","bamboo_raft","bamboo_chest_raft",
  "oak_boat","oak_chest_boat","spruce_boat","spruce_chest_boat",
  "birch_boat","birch_chest_boat","jungle_boat","jungle_chest_boat",
  "acacia_boat","acacia_chest_boat","dark_oak_boat","dark_oak_chest_boat",
  "mangrove_boat","mangrove_chest_boat","cherry_boat","cherry_chest_boat",
  "pale_oak_boat","pale_oak_chest_boat",
  "minecart","chest_minecart","furnace_minecart","tnt_minecart","hopper_minecart",
  "saddle","carrot_on_a_stick","warped_fungus_on_a_stick",
  "totem_of_undying","nether_star","heart_of_the_sea",
  "netherite_sword","netherite_axe","netherite_pickaxe","netherite_shovel","netherite_hoe",
  "netherite_helmet","netherite_chestplate","netherite_leggings","netherite_boots",
  "diamond_sword","diamond_axe","diamond_pickaxe","diamond_shovel","diamond_hoe",
  "diamond_helmet","diamond_chestplate","diamond_leggings","diamond_boots",
  "iron_sword","iron_axe","iron_pickaxe","iron_shovel","iron_hoe",
  "iron_helmet","iron_chestplate","iron_leggings","iron_boots",
  "golden_sword","golden_axe","golden_pickaxe","golden_shovel","golden_hoe",
  "golden_helmet","golden_chestplate","golden_leggings","golden_boots",
  "stone_sword","stone_axe","stone_pickaxe","stone_shovel","stone_hoe",
  "wooden_sword","wooden_axe","wooden_pickaxe","wooden_shovel","wooden_hoe",
  "chainmail_helmet","chainmail_chestplate","chainmail_leggings","chainmail_boots",
  "leather_helmet","leather_chestplate","leather_leggings","leather_boots",
  "turtle_helmet","wolf_armor",
]);
const STACK16 = new Set([
  "sign","oak_sign","spruce_sign","birch_sign","jungle_sign","acacia_sign",
  "dark_oak_sign","mangrove_sign","cherry_sign","bamboo_sign","pale_oak_sign",
  "crimson_sign","warped_sign",
  "hanging_sign","oak_hanging_sign","spruce_hanging_sign","birch_hanging_sign",
  "jungle_hanging_sign","acacia_hanging_sign","dark_oak_hanging_sign",
  "mangrove_hanging_sign","cherry_hanging_sign","bamboo_hanging_sign",
  "pale_oak_hanging_sign","crimson_hanging_sign","warped_hanging_sign",
  "ender_pearl","snowball",
  "banner","white_banner","orange_banner","magenta_banner","light_blue_banner",
  "yellow_banner","lime_banner","pink_banner","gray_banner","light_gray_banner",
  "cyan_banner","purple_banner","blue_banner","brown_banner","green_banner",
  "red_banner","black_banner",
]);

function stackSize(raw) {
  const key = raw.toLowerCase().replace(/[\s\-]+/g,'_').replace(/^minecraft:/,'');
  if (STACK1.has(key)) return 1;
  if (STACK16.has(key)) return 16;
  return 64;
}

function stackInfo(name, total) {
  const sz = stackSize(name);
  if (sz === 1) return {stacks:null, shulkers:null, nonStackable:true};
  const stacks = Math.ceil(total / sz);
  const shulkers = Math.ceil(stacks / 27);
  return {stacks, shulkers, nonStackable:false, sz};
}

let sheetData = [];
let parsedRows = [];

function convert() {
  const text = document.getElementById("input").value;
  const lines = text.split("\n");
  parsedRows = [];
  for (const line of lines) {
    if (!line.includes("|") || line.includes("Material List for schematic") || line.includes("+---")) continue;
    const parts = line.split("|").map(x=>x.trim()).filter(x=>x.length);
    if (parts.length !== 4) continue;
    if (parts[0]==="Item") continue;
    const name = parts[0];
    const total = parseInt(parts[1].replace(/,/g,''), 10) || 0;
    const info = stackInfo(name, total);
    parsedRows.push({name, total, ...info});
  }
  renderTable();
  buildTSV();
}

function renderTable() {
  const el = document.getElementById("preview");
  document.getElementById("result").classList.toggle('hide', !parsedRows.length);
  if (!parsedRows.length) { el.innerHTML=''; UI.toast('No material rows found in that text.', 'err'); return; }
  document.getElementById("rowcount").textContent = parsedRows.length.toLocaleString() + ' items';
  let html = `<table class="data-table"><thead><tr>
    <th>Item</th><th class="num">Total</th><th class="num">Stacks</th><th class="num">Shulker boxes</th><th class="cb-cell">Have it?</th>
  </tr></thead><tbody>`;
  parsedRows.forEach((r,i) => {
    const stackCell = r.nonStackable
      ? `<span class="badge">not stackable</span>`
      : `${r.stacks} <span class="badge b">×${r.sz}</span>`;
    const shulkerCell = r.nonStackable
      ? `—`
      : r.shulkers > 1
        ? `${r.shulkers} <span class="badge g">shulkers</span>`
        : `<1`;
    html += `<tr>
      <td>${UI.esc(r.name)}</td>
      <td class="num">${r.total.toLocaleString()}</td>
      <td class="num">${stackCell}</td>
      <td class="num">${shulkerCell}</td>
      <td class="cb-cell"><input type="checkbox" data-i="${i}" aria-label="Have ${UI.esc(r.name)}"></td>
    </tr>`;
  });
  el.innerHTML = html + `</tbody></table>`;
}

function buildTSV() {
  sheetData = parsedRows.map((r,i) => {
    const checked = document.querySelector(`input[data-i="${i}"]`)?.checked ?? false;
    const stacks = r.nonStackable ? "not stackable" : r.stacks;
    const shulkers = r.nonStackable ? "" : r.shulkers > 1 ? r.shulkers : "<1";
    return [r.name, r.total, stacks, shulkers, checked ? "TRUE" : "FALSE"];
  });
  const header = ["Item","Total","Stacks","Shulker boxes","Have it?"];
  return [header, ...sheetData].map(r=>r.join("\t")).join("\n");
}

function copyTSV(btn) {
  if (!parsedRows.length) { UI.toast("Convert something first.", 'err'); return; }
  UI.copy(buildTSV(), btn);
}
</script>
    </main>
    <?php site_footer(); ?>
</body>

</html>
