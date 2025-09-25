<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Minecraft Stats Editor</title>
</head>
<body>
  <input type="file" id="fileInput" accept=".json">
  <div id="editor"></div>
  <button id="downloadBtn" style="display:none;">Download Updated JSON</button>
  <h3>Live JavaScript Object:</h3>
  <pre id="codeOutput"></pre>

<script>
document.getElementById('fileInput').addEventListener('change', function(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const content = e.target.result;
            try {
                const jsonData = JSON.parse(content);
                const stats = jsonData.stats || {};
                const editorDiv = document.getElementById('editor');
                editorDiv.innerHTML = "";

                // loop over every category in stats
                for (const category in stats) {
                    const categoryDiv = document.createElement('div');
                    categoryDiv.innerHTML = `<h4>${category}</h4>`;
                    
                    for (const item in stats[category]) {
                        const wrapper = document.createElement('div');
                        
                        const label = document.createElement('label');
                        label.textContent = item + ": ";
                        
                        const input = document.createElement('input');
                        input.type = "text";
                        input.value = stats[category][item];
                        input.dataset.category = category;
                        input.dataset.key = item;
                        
                        input.addEventListener('input', function() {
                            stats[this.dataset.category][this.dataset.key] = this.value;
                            updateCodeOutput(jsonData);
                        });
                        
                        wrapper.appendChild(label);
                        wrapper.appendChild(input);
                        categoryDiv.appendChild(wrapper);
                    }
                    editorDiv.appendChild(categoryDiv);
                }

                // enable download
                const downloadBtn = document.getElementById('downloadBtn');
                downloadBtn.style.display = "inline-block";
                downloadBtn.onclick = function() {
                    const blob = new Blob([JSON.stringify(jsonData, null, 2)], {type: "application/json"});
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement("a");
                    a.href = url;
                    a.download = file.name.replace(/\.json$/i, '') + "_updated.json";
                    a.click();
                    URL.revokeObjectURL(url);
                };

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
</script>
</body>
</html>
