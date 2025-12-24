<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Minecraft Stats Editor</title>
  <style>
    table {
      border-collapse: collapse;
      margin-top: 10px;
    }
    th, td {
      border: 1px solid #ccc;
      padding: 6px 10px;
      text-align: left;
    }
    th {
      background: #f0f0f0;
    }
    select, input, textarea {
      padding: 4px;
      min-width: 120px;
    }
    #materialInput {
      width: 100%;
      height: 200px;
      margin-top: 20px;
      white-space: pre;
    }

    /* Popup styles */
    #changesPopup {
      display: none;
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: white;
      padding: 20px;
      border: 2px solid #666;
      max-height: 70vh;
      overflow-y: auto;
      width: 400px;
      box-shadow: 0 0 15px rgba(0,0,0,0.3);
      z-index: 9999;
    }
    #popupClose {
      margin-top: 10px;
      padding: 6px 12px;
    }
  </style>
</head>
<body>
  <input type="file" id="fileInput" accept=".json" />
  <button id="resetBtn" style="display:none;">Reset Special Values</button>

  <h3>Paste Material List:</h3>
  <textarea id="materialInput" placeholder="Paste material list here..."></textarea>
  <button id="processMaterialsBtn" style="display:none;">Process Materials</button>

  <h3>Stats Table:</h3>
  <div id="editor"></div>

  <h3>Live JavaScript Object:</h3>
  <pre id="codeOutput"></pre>
  <button id="copyBtn" style="display:none;">Copy JSON</button>

  <!-- Popup -->
  <div id="changesPopup">
    <h3>Changed Values</h3>
    <pre id="changesList"></pre>
    <button id="popupClose">Close</button>
  </div>

<script>
let globalJson = null;

// File loader
document.getElementById('fileInput').addEventListener('change', function(event) {
  const file = event.target.files[0];
  if (!file) return;

  const reader = new FileReader();
  reader.onload = function(e) {
    try {
      const jsonData = JSON.parse(e.target.result);
      globalJson = jsonData;

      buildEditor(jsonData);
      updateCodeOutput(jsonData);

      document.getElementById('resetBtn').style.display = "inline-block";
      document.getElementById('copyBtn').style.display = "inline-block";
      document.getElementById('processMaterialsBtn').style.display = "inline-block";

    } catch (err) {
      alert("Error parsing JSON: " + err.message);
    }
  };
  reader.readAsText(file);
});

// Convert item name to namespace key
function formatItemName(name) {
  return "minecraft:" + name.toLowerCase().replace(/ /g, "_");
}

// Process materials + track changes
document.getElementById('processMaterialsBtn').addEventListener('click', function() {
  if (!globalJson) return;

  const text = document.getElementById('materialInput').value;
  const lines = text.split(/\n/);

  const stats = globalJson.stats || {};
  const usedStats = stats["minecraft:used"] || (stats["minecraft:used"] = {});

  let changes = [];

  for (let line of lines) {
    const match = line.match(/^\|\s*(.*?)\s*\|\s*(\d+)\s*\|/);
    if (!match) continue;

    const rawName = match[1].trim();
    const totalNum = Number(match[2]);
    const mcKey = formatItemName(rawName);

    const oldVal = usedStats[mcKey] || 0;
    const newVal = oldVal + totalNum;

    usedStats[mcKey] = newVal;

    if (newVal !== oldVal) {
      changes.push(`${mcKey}  ${oldVal} → ${newVal}`);
    }
  }

  buildEditor(globalJson);
  updateCodeOutput(globalJson);

  // Show popup if there are changes
  if (changes.length > 0) {
    document.getElementById('changesList').textContent = changes.join("\n");
    document.getElementById('changesPopup').style.display = "block";
  }
});

// Close popup
document.getElementById('popupClose').onclick = function() {
  document.getElementById('changesPopup').style.display = "none";
};

// Update display JSON
function updateCodeOutput(data) {
  document.getElementById('codeOutput').textContent = JSON.stringify(data, null, 2);
}

// Reset logic
function resetSpecialValues(data) {
  const stats = data.stats || {};
  for (const category in stats) {
    for (const item in stats[category]) {
      if (/spawn_egg|barrier|command/i.test(item)) {
        stats[category][item] = 0;
      }
    }
  }
}

document.getElementById('resetBtn').onclick = function() {
  if (!globalJson) return;
  resetSpecialValues(globalJson);
  buildEditor(globalJson);
  updateCodeOutput(globalJson);
};

// Copy JSON button
document.getElementById('copyBtn').onclick = function() {
  if (!globalJson) return;
  navigator.clipboard.writeText(JSON.stringify(globalJson, null, 2));
};

// Build editor table
function buildEditor(data) {
  const stats = data.stats || {};
  const editorDiv = document.getElementById('editor');
  editorDiv.innerHTML = "";

  const itemMap = {};
  for (const category in stats) {
    for (const item in stats[category]) {
      (itemMap[item] ||= []).push(category);
    }
  }

  const table = document.createElement('table');
  table.innerHTML = `<tr><th>Item</th><th>Category</th><th>Value</th></tr>`;

  for (const item in itemMap) {
    const tr = document.createElement('tr');

    const tdItem = document.createElement('td');
    tdItem.textContent = item;
    tr.appendChild(tdItem);

    const tdCategory = document.createElement('td');
    const select = document.createElement('select');
    itemMap[item].forEach(cat => {
      const opt = document.createElement('option');
      opt.value = cat;
      opt.textContent = cat;
      select.appendChild(opt);
    });
    tdCategory.appendChild(select);
    tr.appendChild(tdCategory);

    const tdValue = document.createElement('td');
    const input = document.createElement('input');
    input.type = "text";
    input.value = stats[select.value][item];
    tdValue.appendChild(input);
    tr.appendChild(tdValue);

    select.addEventListener('change', function() {
      input.value = stats[this.value][item];
    });

    input.addEventListener('input', function() {
      const val = this.value.trim();
      stats[select.value][item] = (/^-?\d+(\.\d+)?$/.test(val)) ? Number(val) : val;
      updateCodeOutput(data);
    });

    table.appendChild(tr);
  }

  editorDiv.appendChild(table);
}
</script>
</body>
</html>
