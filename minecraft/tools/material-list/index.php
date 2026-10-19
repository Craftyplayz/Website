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
        <h1 class="page-title">Litematica Material List → Google Sheets</h1>
        <p class="page-lede">Paste a Litematica material list and copy it out as rows ready for Google Sheets.</p>

        <div class="data-cards">
            <section class="card">
                <h2>Material list</h2>
                <textarea class="field data-text" id="input" placeholder="Paste material list here" spellcheck="false"></textarea>
                <div class="actions">
                    <button class="btn primary" id="convert" type="button">Convert</button>
                </div>
            </section>

            <section class="card hide" id="result">
                <h2>Rows <span class="muted" id="rowcount"></span> <button class="btn small n-btn" id="copy" type="button">Copy for Sheets</button></h2>
                <div class="table-wrap">
                    <table class="data-table" id="preview">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Total</th>
                                <th>Missing</th>
                                <th>Available</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <details class="raw">
                    <summary>Raw TSV</summary>
                    <textarea class="field data-text" id="output" aria-label="TSV output" readonly></textarea>
                </details>
            </section>
        </div>
    </main>
    <?php site_footer(); ?>
    <script>
        let sheetData = [];

        function convert() {
            const text = document.getElementById("input").value;
            const lines = text.split("\n");

            sheetData = [];
            const tbody = document.querySelector("#preview tbody");
            tbody.innerHTML = "";

            for (const line of lines) {

                if (
                    !line.includes("|") ||
                    line.includes("Material List for schematic") ||
                    line.includes("+---")
                ) continue;

                const parts = line
                    .split("|")
                    .map(x => x.trim())
                    .filter(x => x.length);

                if (parts.length !== 4) continue;
                if (parts[0] === "Item") continue;

                const row = [
                    parts[0],
                    parts[1],
                    parts[2],
                    parts[3]
                ];

                sheetData.push(row);

                const tr = document.createElement("tr");
                row.forEach(value => {
                    const td = document.createElement("td");
                    td.textContent = value;
                    tr.appendChild(td);
                });
                tbody.appendChild(tr);
            }

            const tsv = [
                ["Item", "Total", "Missing", "Available"],
                ...sheetData
            ]
                .map(r => r.join("\t"))
                .join("\n");

            document.getElementById("output").value = tsv;
            if (!sheetData.length) return UI.toast("No material rows found in that text.", 'err');
            document.getElementById("rowcount").textContent = sheetData.length.toLocaleString() + ' items';
            document.getElementById("result").classList.remove('hide');
        }

        // ui.js is deferred
        document.addEventListener('DOMContentLoaded', () => {
            const { $, copy } = UI;
            $('#convert').addEventListener('click', convert);
            $('#copy').addEventListener('click', e => copy($('#output').value, e.currentTarget));
        });
    </script>
</body>

</html>
