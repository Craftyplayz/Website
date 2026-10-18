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
        <h1 class="page-title">JSON File Viewer</h1>
        <p class="page-lede">View a chat filter JSON file, add sections to it and download the result.</p>

        <label class="drop" id="drop">
            <input type="file" id="fileInput" accept="application/json" hidden>
            <b>Choose a .json file</b> <span class="muted">or drop it here</span>
        </label>

        <div class="data-cards hide" id="loaded">
            <section class="card">
                <h2>Add new section</h2>
                <form class="add-form" id="newSectionForm">
                    <label>Name
                        <input class="field" type="text" id="nameInput" required>
                    </label>
                    <label>Action
                        <select class="field" id="actionSelect">
                            <option value="SECOND_CHAT">SECOND_CHAT</option>
                            <option value="COPY_SECOND_CHAT">COPY_SECOND_CHAT</option>
                        </select>
                    </label>
                    <button class="btn" id="addSectionButton" type="submit">Add section</button>
                </form>
            </section>

            <section class="card">
                <h2>File <button class="btn primary small n-btn" id="downloadButton" type="button">Download JSON</button></h2>
                <pre class="code" id="jsonDisplay"></pre>
            </section>
        </div>
    </main>
    <?php site_footer(); ?>
    <script>
        const fileInput = document.getElementById('fileInput');
        const jsonDisplay = document.getElementById('jsonDisplay');
        const nameInput = document.getElementById('nameInput');
        const actionSelect = document.getElementById('actionSelect');

        let currentJson = null;

        function syntaxHighlight(json) {
            // escape first so the file can't inject markup (quotes are left alone), then colour
            // every token in one pass so a replacement is never matched again
            return json.replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/("(?:[^"\\]|\\.)*")(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?/g, (m, str, colon, word) =>
                    str ? `<span class="${colon ? 'key' : 'string'}">${str}</span>${colon || ''}`
                        : `<span class="${word ? (word === 'null' ? 'null' : 'boolean') : 'number'}">${m}</span>`);
        }

        function show() {
            jsonDisplay.innerHTML = syntaxHighlight(JSON.stringify(currentJson, null, 4));
        }

        function loadFile(file) {
            if (!file) return;
            const reader = new FileReader();

            reader.onload = (e) => {
                try {
                    currentJson = JSON.parse(e.target.result);
                    show();
                    document.getElementById('loaded').classList.remove('hide');
                    document.querySelector('#drop b').textContent = file.name;
                } catch (error) {
                    UI.toast("Error parsing JSON: " + error.message, 'err');
                }
            };

            reader.onerror = () => {
                UI.toast("Error reading file.", 'err');
            };

            reader.readAsText(file);
        }

        function addSection() {
            if (!currentJson) return UI.toast("Please load a JSON file first.", 'err');
            const nameValue = nameInput.value;

            if (nameValue.trim() === "") {
                UI.toast("Name field cannot be empty.", 'err');
                return;
            }

            const newSection = {
                name: nameValue,
                message: nameValue, // Use the name as the message
                except: "",
                servers: [],
                action: actionSelect.value,
                useRegex: false,
                autoTextDelay: 0
            };

            currentJson.chatMessages.push(newSection);
            show();

            nameInput.value = "";
            actionSelect.value = "SECOND_CHAT";
            UI.toast('Added ' + nameValue, 'ok');
        }

        function download() {
            const blob = new Blob([JSON.stringify(currentJson, null, 4)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);

            const a = document.createElement('a');
            a.href = url;
            a.download = 'chatFilter.json';
            a.click();

            URL.revokeObjectURL(url);
        }

        // ui.js is deferred
        document.addEventListener('DOMContentLoaded', () => {
            const { $ } = UI;
            const drop = $('#drop');
            fileInput.addEventListener('change', e => loadFile(e.target.files[0]));
            drop.addEventListener('dragover', e => { e.preventDefault(); drop.classList.add('over'); });
            drop.addEventListener('dragleave', () => drop.classList.remove('over'));
            drop.addEventListener('drop', e => { e.preventDefault(); drop.classList.remove('over'); loadFile(e.dataTransfer.files[0]); });
            $('#newSectionForm').addEventListener('submit', e => { e.preventDefault(); addSection(); });
            $('#downloadButton').addEventListener('click', download);
        });
    </script>
</body>

</html>
