<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JSON File Viewer</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        #jsonDisplay {
            white-space: pre-wrap;
            background-color: #f4f4f4;
            border: 1px solid #ccc;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
            font-family: monospace;
            color: #333;
            position: relative;
        }
        .key {
            color: #d73a49;
        }
        .string {
            color: #032f62;
        }
        .number {
            color: #005cc5;
        }
        .boolean {
            color: #d73a49;
        }
        .null {
            color: #6a737d;
        }
        #newSectionForm {
            margin-top: 20px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        #newSectionForm input, #newSectionForm select, #newSectionForm button {
            margin: 5px 0;
            padding: 8px;
            width: 100%;
            box-sizing: border-box;
        }
        #downloadButton {
            position: absolute;
            bottom: 10px;
            right: 10px;
            padding: 10px 15px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        #downloadButton:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <h1>JSON File Viewer</h1>
    <input type="file" id="fileInput" accept="application/json">
    <div id="jsonDisplay">Your JSON content will appear here...</div>

    <div id="newSectionForm">
        <h2>Add New Section</h2>
        <label for="nameInput">Name:</label>
        <input type="text" id="nameInput" placeholder="Enter name" required>

        <label for="actionSelect">Action:</label>
        <select id="actionSelect">
            <option value="SECOND_CHAT">SECOND_CHAT</option>
            <option value="COPY_SECOND_CHAT">COPY_SECOND_CHAT</option>
        </select>

        <button id="addSectionButton">Add Section</button>
    </div>

    <script>
        const fileInput = document.getElementById('fileInput');
        const jsonDisplay = document.getElementById('jsonDisplay');
        const nameInput = document.getElementById('nameInput');
        const actionSelect = document.getElementById('actionSelect');
        const addSectionButton = document.getElementById('addSectionButton');

        let currentJson = null;

        function syntaxHighlight(json) {
            return json.replace(/"(\w+)":/g, '<span class="key">"$1"</span>:')
                       .replace(/"([^\"]*)"/g, '<span class="string">"$1"</span>')
                       .replace(/\b(true|false)\b/g, '<span class="boolean">$1</span>')
                       .replace(/\b(null)\b/g, '<span class="null">$1</span>')
                       .replace(/\b(\d+(\.\d+)?)\b/g, '<span class="number">$1</span>');
        }

        fileInput.addEventListener('change', (event) => {
            const file = event.target.files[0];

            if (file) {
                const reader = new FileReader();

                reader.onload = (e) => {
                    try {
                        currentJson = JSON.parse(e.target.result);
                        const prettyJson = JSON.stringify(currentJson, null, 4);
                        jsonDisplay.innerHTML = syntaxHighlight(prettyJson);
                        addDownloadButton();
                    } catch (error) {
                        jsonDisplay.textContent = "Error parsing JSON: " + error.message;
                    }
                };

                reader.onerror = () => {
                    jsonDisplay.textContent = "Error reading file.";
                };

                reader.readAsText(file);
            }
        });

        addSectionButton.addEventListener('click', () => {
            if (currentJson) {
                const nameValue = nameInput.value;

                if (nameValue.trim() === "") {
                    alert("Name field cannot be empty.");
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
                const prettyJson = JSON.stringify(currentJson, null, 4);
                jsonDisplay.innerHTML = syntaxHighlight(prettyJson);
                addDownloadButton();

                nameInput.value = "";
                actionSelect.value = "SECOND_CHAT";
            } else {
                alert("Please load a JSON file first.");
            }
        });

        function addDownloadButton() {
            let downloadButton = document.getElementById('downloadButton');

            if (!downloadButton) {
                downloadButton = document.createElement('button');
                downloadButton.id = 'downloadButton';
                downloadButton.textContent = 'Download JSON';
                jsonDisplay.appendChild(downloadButton);

                downloadButton.addEventListener('click', () => {
                    const blob = new Blob([JSON.stringify(currentJson, null, 4)], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);

                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'chatFilter.json';
                    a.click();

                    URL.revokeObjectURL(url);
                });
            }
        }
    </script>
</body>
</html>
