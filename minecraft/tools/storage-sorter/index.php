<?php
$site_page = ['css' => ['/assets/css/pages/minecraft-storage-sorter.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php page_head(); ?>
</head>
<body>
    <?php site_header(); ?>
    <main class="page ss" id="main">
    <?= site_breadcrumbs(site_current_path()) ?>
  <h1 class="page-title">Storage Sorter</h1>
  <p class="page-lede">Find which Minecraft items haven't been categorised yet.</p>

  <div class="card">
    <label>Your sorted items (paste from Google Sheets)</label>
    <textarea id="sorted-input" placeholder="Paste your categorised item list here. One item per line, or tab/comma separated — item names will be extracted automatically."></textarea>
    <p class="hint">Item names are matched case-insensitively. Extra columns (categories, quantities) are ignored.</p>
    <button id="run-btn" onclick="run()">Find unsorted items</button>
    <div id="error-msg"></div>
  </div>

  <div class="card" id="duplicates-card">
    <h2>Duplicate entries in your sorted list</h2>
    <div id="duplicates-list"></div>
  </div>

  <div class="card" id="results-card">
    <div class="results-header">
      <h2>Unsorted items</h2>
      <span class="count-badge" id="count-label"></span>
      <button class="copy-btn" onclick="copyResults()">Copy list</button>
    </div>
    <input type="text" id="search-input" placeholder="Filter items..." oninput="filterItems()" style="display:none">
    <div class="item-grid" id="item-grid"></div>
    <div class="status-row" id="status-row"></div>
    <button class="generate-cmd-btn" onclick="generateCommands()">Generate /give command</button>
  </div>

  <div class="card" id="command-card">
    <h2>Shulker chest /give commands</h2>
    <p class="hint" id="command-hint"></p>
    <div id="command-list"></div>
  </div>

  <script>
    let allItems = null;
    let currentUnsorted = [];

    async function loadItems() {
      try {
        const res = await fetch('items.txt');
        if (!res.ok) throw new Error('items.txt not found (' + res.status + ')');
        const text = await res.text();
        allItems = new Set(
          text.split('\n')
            .map(l => l.trim().toLowerCase())
            .filter(l => l.length > 0)
        );
        return true;
      } catch (e) {
        showError('Could not load items.txt: ' + e.message);
        return false;
      }
    }

    function parseSorted(raw) {
      const lines = raw.split('\n').map(l => l.trim()).filter(l => l);
      const counts = new Map();
      for (const line of lines) {
        const cols = line.split(/[\t,]+/).map(c => c.trim().replace(/^"|"$/g, '').trim());
        for (const col of cols) {
          if (col.length > 0) {
            const key = col.toLowerCase();
            counts.set(key, (counts.get(key) || 0) + 1);
          }
        }
      }
      return counts;
    }

    async function run() {
      const btn = document.getElementById('run-btn');
      btn.disabled = true;
      btn.textContent = 'Loading...';
      hideError();

      const raw = document.getElementById('sorted-input').value.trim();
      if (!raw) {
        showError('Paste your sorted item list first.');
        btn.disabled = false;
        btn.textContent = 'Find unsorted items';
        return;
      }

      if (!allItems) {
        const ok = await loadItems();
        if (!ok) {
          btn.disabled = false;
          btn.textContent = 'Find unsorted items';
          return;
        }
      }

      const sorted = parseSorted(raw);
      const unsorted = [...allItems]
        .filter(item => !sorted.has(item))
        .sort((a, b) => a.localeCompare(b));

      const duplicates = [...sorted.entries()]
        .filter(([, count]) => count > 1)
        .sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]));

      showResults(unsorted, sorted.size);
      showDuplicates(duplicates);
      currentUnsorted = unsorted;
      document.getElementById('command-card').style.display = 'none';

      btn.disabled = false;
      btn.textContent = 'Find unsorted items';
    }

    function showResults(unsorted, sortedCount) {
      const card = document.getElementById('results-card');
      const grid = document.getElementById('item-grid');
      const countLabel = document.getElementById('count-label');
      const statusRow = document.getElementById('status-row');

      grid.innerHTML = '';
      unsorted.forEach(item => {
        const div = document.createElement('div');
        div.className = 'item';
        div.textContent = item;
        grid.appendChild(div);
      });

      countLabel.textContent = unsorted.length + ' item' + (unsorted.length !== 1 ? 's' : '');
      statusRow.innerHTML = '<span>' + sortedCount + '</span> matched &nbsp;&middot;&nbsp; <span>' + allItems.size + '</span> total in items.txt';
      card.style.display = 'block';
      const searchInput = document.getElementById('search-input');
      searchInput.style.display = 'block';
      searchInput.value = '';
      card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function showDuplicates(duplicates) {
      const card = document.getElementById('duplicates-card');
      const list = document.getElementById('duplicates-list');

      if (duplicates.length === 0) {
        card.style.display = 'none';
        return;
      }

      list.innerHTML = '';
      duplicates.forEach(([name, count]) => {
        const row = document.createElement('div');
        row.className = 'dup-row';
        const label = document.createElement('span');
        label.textContent = name;
        const n = document.createElement('span');
        n.className = 'dup-count';
        n.textContent = '×' + count;
        row.append(label, n);
        list.appendChild(row);
      });
      card.style.display = 'block';
    }

    function nameToItemId(name) {
      // Converts "Iron Ingot" / "iron_ingot" / "iron ingot" -> minecraft:iron_ingot
      let cleaned = name.trim().toLowerCase().replace(/^minecraft:/, '');
      cleaned = cleaned.replace(/[^a-z0-9\s_]/g, '');
      cleaned = cleaned.trim().replace(/\s+/g, '_');
      return 'minecraft:' + cleaned;
    }

    function buildShulkerComponent(items) {
      // items: array of item names (each gets its own slot, stack of 64), max 27 per shulker
      const slots = items.map((name, i) => {
        const id = nameToItemId(name);
        return '{slot:' + i + ',item:{id:"' + id + '",count:64}}';
      });
      return '[' + slots.join(',') + ']';
    }

    function generateCommands() {
      if (currentUnsorted.length === 0) {
        return;
      }

      const ITEMS_PER_SHULKER = 27;
      const MAX_COMMAND_LENGTH = 32500;

      // chunk items into groups of 27 (one shulker each)
      const shulkerGroups = [];
      for (let i = 0; i < currentUnsorted.length; i += ITEMS_PER_SHULKER) {
        shulkerGroups.push(currentUnsorted.slice(i, i + ITEMS_PER_SHULKER));
      }

      // build chest commands, adding shulkers one at a time and starting
      // a new chest whenever adding the next shulker would exceed the
      // max command length (also caps at 27 shulkers per chest)
      const commands = [];
      let currentShulkers = [];

      function buildCommand(shulkers) {
        const chestSlots = shulkers.map((shulkerItems, i) => {
          const containerComponent = buildShulkerComponent(shulkerItems);
          return '{slot:' + i + ',item:{id:"minecraft:shulker_box",count:1,components:{container:' + containerComponent + '}}}';
        });
        return '/give @s minecraft:chest[container=[' + chestSlots.join(',') + ']]';
      }

      for (const shulker of shulkerGroups) {
        const candidate = currentShulkers.concat([shulker]);
        const candidateCmd = buildCommand(candidate);

        if (currentShulkers.length > 0 &&
            (candidateCmd.length > MAX_COMMAND_LENGTH || candidate.length > 27)) {
          commands.push(buildCommand(currentShulkers));
          currentShulkers = [shulker];
        } else {
          currentShulkers = candidate;
        }
      }

      if (currentShulkers.length > 0) {
        commands.push(buildCommand(currentShulkers));
      }

      showCommands(commands, currentUnsorted.length);
    }

    function showCommands(commands, totalItems) {
      const card = document.getElementById('command-card');
      const list = document.getElementById('command-list');
      const hint = document.getElementById('command-hint');

      hint.textContent = totalItems + ' item' + (totalItems !== 1 ? 's' : '') + ', one stack (64) each, packed into ' + commands.length + ' chest' + (commands.length !== 1 ? 's' : '') + ' of shulker boxes. Run in chat or a command block (requires operator/creative permissions).';

      list.innerHTML = '';
      commands.forEach((cmd, i) => {
        const block = document.createElement('div');
        block.className = 'command-block';
        const label = document.createElement('div');
        label.className = 'command-block-label';
        label.textContent = commands.length > 1 ? ('Chest ' + (i + 1) + ' of ' + commands.length) : 'Command';
        const text = document.createElement('div');
        text.className = 'command-text';
        text.textContent = cmd;
        const btn = document.createElement('button');
        btn.className = 'command-copy-btn';
        btn.textContent = 'Copy command';
        btn.onclick = () => {
          navigator.clipboard.writeText(cmd).then(() => {
            btn.textContent = 'Copied';
            setTimeout(() => btn.textContent = 'Copy command', 1500);
          });
        };
        block.appendChild(label);
        block.appendChild(text);
        block.appendChild(btn);
        list.appendChild(block);
      });

      card.style.display = 'block';
      card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function filterItems() {
      const query = document.getElementById('search-input').value.toLowerCase().trim();
      const items = document.querySelectorAll('.item');
      let visible = 0;
      items.forEach(el => {
        const match = !query || el.textContent.toLowerCase().includes(query);
        el.style.display = match ? '' : 'none';
        if (match) visible++;
      });
      document.getElementById('count-label').textContent = visible + ' item' + (visible !== 1 ? 's' : '');
    }

    function copyResults() {
      const items = [...document.querySelectorAll('.item')]
        .filter(el => el.style.display !== 'none')
        .map(el => el.textContent);
      navigator.clipboard.writeText(items.join('\n')).then(() => {
        const btn = document.querySelector('.copy-btn');
        btn.textContent = 'Copied';
        setTimeout(() => btn.textContent = 'Copy list', 1500);
      });
    }

    function showError(msg) {
      const el = document.getElementById('error-msg');
      el.textContent = msg;
      el.style.display = 'block';
    }

    function hideError() {
      document.getElementById('error-msg').style.display = 'none';
    }
  </script>
    </main>
    <?php site_footer(); ?>
</body>
</html>