<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    select, input {
      padding: 4px;
      min-width: 120px;
    }
  </style>
</head>
<body>
  <input type="file" id="fileInput" accept=".json">
  <button id="resetBtn" style="display:none;">Reset Special Values</button>
  <h3>Stats Table:</h3>
  <div id="editor"></div>
  <h3>Live JavaScript Object:</h3>
  <pre id="codeOutput"></pre>
  <button id="copyBtn" style="display:none;">Copy JSON</button>

<script>
let globalJson = null;

document.getElementById('fileInput').addEventListener('change', function(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                const jsonData = JSON.parse(e.target.result);
                globalJson = jsonData;

                buildEditor(jsonData);

                document.getElementById('resetBtn').style.display = "inline-block";
                document.getElementById('copyBtn').style.display = "inline-block";

                updateCodeOutput(jsonData);

            } catch (error) {
                alert("Error parsing JSON: " + error.message);
            }
        };
        reader.readAsText(file);
    }
});

function updateCodeOutput(data) {
    document.getElementById('codeOutput').textContent = JSON.stringify(data, null, 2);
}

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

function buildEditor(data) {
    const stats = data.stats || {};
    const editorDiv = document.getElementById('editor');
    editorDiv.innerHTML = "";

    // Collect all items across categories
    const itemMap = {};
    for (const category in stats) {
        for (const item in stats[category]) {
            if (!itemMap[item]) {
                itemMap[item] = [];
            }
            itemMap[item].push(category);
        }
    }

    // Build table
    const table = document.createElement('table');
    const header = document.createElement('tr');
    header.innerHTML = "<th>Item</th><th>Category</th><th>Value</th>";
    table.appendChild(header);

    for (const item in itemMap) {
        const tr = document.createElement('tr');

        // Item column
        const tdItem = document.createElement('td');
        tdItem.textContent = item;
        tr.appendChild(tdItem);

        // Category column with dropdown if multiple
        const tdCategory = document.createElement('td');
        const select = document.createElement('select');
        itemMap[item].forEach(cat => {
            const option = document.createElement('option');
            option.value = cat;
            option.textContent = cat;
            select.appendChild(option);
        });
        tdCategory.appendChild(select);
        tr.appendChild(tdCategory);

        // Value column with input
        const tdValue = document.createElement('td');
        const input = document.createElement('input');
        input.type = "text";
        input.value = stats[select.value][item];
        tdValue.appendChild(input);
        tr.appendChild(tdValue);

        // Event: category switch updates input
        select.addEventListener('change', function() {
            input.value = stats[this.value][item];
        });

        // Event: input change updates selected category (with number detection)
        input.addEventListener('input', function() {
            const val = this.value.trim();
            stats[select.value][item] = (/^-?\d+(\.\d+)?$/.test(val)) ? Number(val) : val;
            updateCodeOutput(data);
        });

        table.appendChild(tr);
    }

    editorDiv.appendChild(table);
}

// Reset button logic
document.getElementById('resetBtn').onclick = function() {
    if (!globalJson) return;
    resetSpecialValues(globalJson);
    buildEditor(globalJson);
    updateCodeOutput(globalJson);
};

// Copy button logic
document.getElementById('copyBtn').onclick = function() {
    if (!globalJson) return;
    const text = JSON.stringify(globalJson, null, 2);
    navigator.clipboard.writeText(text).then(() => {
        alert("JSON copied to clipboard!");
    }).catch(err => {
        alert("Failed to copy: " + err);
    });
};
</script>
</body>
</html>
