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
        <h1 class="page-title">Stats Editor</h1>
        <p class="page-lede">Load a player stats file, add a material list to its "used" counts, edit any value, then copy the JSON back.</p>

        <label class="drop" id="drop">
            <input type="file" id="fileInput" accept=".json" hidden>
            <b>Choose a stats .json file</b> <span class="muted">or drop it here</span>
        </label>

        <div class="data-cards hide" id="loaded">
            <section class="card">
                <h2>Material list</h2>
                <textarea class="field data-text" id="materialInput" placeholder="Paste material list here..." spellcheck="false"></textarea>
                <div class="actions">
                    <button class="btn primary" id="processMaterialsBtn" type="button">Process materials</button>
                    <button class="btn" id="resetBtn" type="button">Reset special values</button>
                </div>
            </section>

            <section class="card">
                <h2>Stats table <span class="n" id="statcount"></span></h2>
                <div class="table-wrap" id="editor"></div>
            </section>

            <section class="card">
                <h2>Live JavaScript object <button class="btn small n-btn" id="copyBtn" type="button">Copy JSON</button></h2>
                <pre class="code" id="codeOutput"></pre>
            </section>
        </div>

        <dialog class="dlg" id="changesPopup">
            <h2>Changed values</h2>
            <pre class="code" id="changesList"></pre>
            <form method="dialog" class="actions"><button class="btn" id="popupClose">Close</button></form>
        </dialog>
    </main>
    <?php site_footer(); ?>
    <script>
        let globalJson = null;

        function loadFile(file) {
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function (e) {
                try {
                    const jsonData = JSON.parse(e.target.result);
                    globalJson = jsonData;

                    buildEditor(jsonData);
                    updateCodeOutput(jsonData);

                    document.getElementById('loaded').classList.remove('hide');
                    document.querySelector('#drop b').textContent = file.name;
                } catch (err) {
                    UI.toast("Error parsing JSON: " + err.message, 'err');
                }
            };
            reader.readAsText(file);
        }

        // Convert item name to namespace key
        function formatItemName(name) {
            return "minecraft:" + name.toLowerCase().replace(/ /g, "_");
        }

        // Process materials + track changes
        function processMaterials() {
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
                document.getElementById('changesPopup').showModal();
            }
        }

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
            table.className = 'data-table';
            table.innerHTML = `<thead><tr><th>Item</th><th>Category</th><th>Value</th></tr></thead>`;
            const tbody = table.createTBody();

            for (const item in itemMap) {
                const tr = document.createElement('tr');

                const tdItem = document.createElement('td');
                tdItem.className = 'mono';
                tdItem.textContent = item;
                tr.appendChild(tdItem);

                const tdCategory = document.createElement('td');
                const select = document.createElement('select');
                select.className = 'field';
                select.setAttribute('aria-label', 'Category for ' + item);
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
                input.className = 'field';
                input.setAttribute('aria-label', 'Value for ' + item);
                input.value = stats[select.value][item];
                tdValue.appendChild(input);
                tr.appendChild(tdValue);

                select.addEventListener('change', function () {
                    input.value = stats[this.value][item];
                });

                input.addEventListener('input', function () {
                    const val = this.value.trim();
                    stats[select.value][item] = (/^-?\d+(\.\d+)?$/.test(val)) ? Number(val) : val;
                    updateCodeOutput(data);
                });

                tbody.appendChild(tr);
            }

            editorDiv.appendChild(table);
            document.getElementById('statcount').textContent = Object.keys(itemMap).length.toLocaleString() + ' items';
        }

        // ui.js is deferred
        document.addEventListener('DOMContentLoaded', () => {
            const { $, copy } = UI;
            const drop = $('#drop');
            $('#fileInput').addEventListener('change', e => loadFile(e.target.files[0]));
            drop.addEventListener('dragover', e => { e.preventDefault(); drop.classList.add('over'); });
            drop.addEventListener('dragleave', () => drop.classList.remove('over'));
            drop.addEventListener('drop', e => { e.preventDefault(); drop.classList.remove('over'); loadFile(e.dataTransfer.files[0]); });
            $('#processMaterialsBtn').addEventListener('click', processMaterials);
            $('#resetBtn').addEventListener('click', () => {
                if (!globalJson) return;
                resetSpecialValues(globalJson);
                buildEditor(globalJson);
                updateCodeOutput(globalJson);
            });
            $('#copyBtn').addEventListener('click', e => globalJson && copy(JSON.stringify(globalJson, null, 2), e.currentTarget));
        });
    </script>
</body>

</html>
