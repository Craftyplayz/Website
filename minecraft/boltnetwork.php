<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minecraft Rail Router</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #1a1a1a;
            color: #e0e0e0;
            overflow: hidden;
        }

        .container {
            display: flex;
            height: 100vh;
        }

        .sidebar {
            width: 300px;
            background: #252525;
            border-right: 2px solid #333;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .sidebar h2 {
            padding: 20px;
            background: #2a2a2a;
            border-bottom: 2px solid #333;
            font-size: 18px;
        }

        .controls {
            padding: 20px;
        }

        .control-group {
            margin-bottom: 20px;
        }

        .control-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: #aaa;
        }

        input[type="text"] {
            width: 100%;
            padding: 10px;
            background: #1a1a1a;
            border: 1px solid #444;
            color: #e0e0e0;
            border-radius: 4px;
            font-size: 14px;
        }

        input[type="text"]:focus {
            outline: none;
            border-color: #4a9eff;
        }

        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 600;
        }

        .btn-primary {
            background: #4a9eff;
            color: white;
        }

        .btn-primary:hover {
            background: #3a8eef;
        }

        .btn-secondary {
            background: #666;
            color: white;
            margin-top: 8px;
        }

        .btn-secondary:hover {
            background: #555;
        }

        .node-list {
            padding: 0 20px 20px 20px;
        }

        .node-item {
            padding: 12px;
            background: #1a1a1a;
            margin-bottom: 8px;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid #4a9eff;
            cursor: pointer;
            transition: all 0.2s;
        }

        .node-item.intersection {
            border-left-color: #ff9800;
        }

        .node-item:hover {
            background: #2a2a2a;
        }

        .node-item.selected {
            background: #2a2a2a;
            box-shadow: 0 0 0 2px #4a9eff;
        }

        .node-item-name {
            flex: 1;
            font-size: 14px;
        }

        .btn-delete {
            background: #d32f2f;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-delete:hover {
            background: #b71c1c;
        }

        .canvas-container {
            flex: 1;
            position: relative;
            overflow: hidden;
            background: #1a1a1a;
        }

        canvas {
            cursor: grab;
            display: block;
            width: 100%;
            height: 100%;
        }

        canvas:active {
            cursor: grabbing;
        }

        .config-panel {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 350px;
            background: #252525;
            border: 2px solid #333;
            border-radius: 8px;
            padding: 20px;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
            box-shadow: 0 4px 12px rgba(0,0,0,0.5);
        }

        .config-panel h3 {
            margin-bottom: 20px;
            font-size: 18px;
            color: #4a9eff;
        }

        .direction-display {
            margin-bottom: 15px;
            padding: 12px;
            background: #1a1a1a;
            border-radius: 4px;
            border-left: 3px solid #666;
        }

        .direction-display.has-item {
            border-left-color: #4caf50;
        }

        .direction-label {
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
            font-weight: 600;
            color: #aaa;
        }

        .direction-value {
            font-size: 13px;
            color: #4a9eff;
        }

        .direction-value.empty {
            color: #666;
            font-style: italic;
        }

        .validation-panel {
            position: absolute;
            bottom: 20px;
            left: 320px;
            right: 20px;
            background: #252525;
            border: 2px solid #333;
            border-radius: 8px;
            padding: 15px;
            max-height: 200px;
            overflow-y: auto;
        }

        .validation-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #4a9eff;
        }

        .validation-item {
            padding: 8px;
            margin-bottom: 6px;
            border-radius: 4px;
            font-size: 13px;
        }

        .validation-error {
            background: rgba(211, 47, 47, 0.2);
            border-left: 3px solid #d32f2f;
        }

        .validation-warning {
            background: rgba(255, 152, 0, 0.2);
            border-left: 3px solid #ff9800;
        }

        .validation-success {
            background: rgba(76, 175, 80, 0.2);
            border-left: 3px solid #4caf50;
        }

        .toolbar {
            position: absolute;
            top: 20px;
            left: 320px;
            background: #252525;
            border: 2px solid #333;
            border-radius: 8px;
            padding: 10px;
            display: flex;
            gap: 10px;
        }

        .toolbar button {
            padding: 8px 16px;
            background: #1a1a1a;
            border: 1px solid #444;
            color: #e0e0e0;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
        }

        .toolbar button:hover {
            background: #2a2a2a;
        }

        .legend {
            position: absolute;
            top: 80px;
            left: 320px;
            background: #252525;
            border: 2px solid #333;
            border-radius: 8px;
            padding: 15px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 13px;
        }

        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 3px;
            margin-right: 10px;
        }

        .close-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #d32f2f;
            color: white;
            border: none;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
            line-height: 1;
        }

        .close-btn:hover {
            background: #b71c1c;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <h2>Rail Network Designer</h2>
            <div class="controls">
                <div class="control-group">
                    <label>Station Name</label>
                    <input type="text" id="stationName" placeholder="Enter name...">
                </div>
                <div class="control-group">
                    <label>Station Item</label>
                    <input type="text" id="stationItem" placeholder="e.g., diamond">
                </div>
                <button class="btn btn-primary" onclick="addStation()">Add Station</button>
                <button class="btn btn-secondary" onclick="addIntersection()">Add Junction</button>
            </div>
            <h2>Import/Export</h2>
            <div class="controls">
                <button class="btn btn-secondary" onclick="exportNetwork()">Export to JSON</button>
                <button class="btn btn-secondary" onclick="document.getElementById('importFile').click()">Import from JSON</button>
                <input type="file" id="importFile" accept=".json" style="display: none;" onchange="importNetwork(event)">
            </div>
            <h2>Nodes</h2>
            <div class="node-list" id="nodeList"></div>
        </div>

        <div class="canvas-container">
            <canvas id="canvas"></canvas>
            
            <div class="toolbar">
                <button onclick="resetView()">Reset View</button>
            </div>

            <div class="legend">
                <div class="legend-item">
                    <div class="legend-color" style="background: #4a9eff;"></div>
                    <span>Station</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #ff9800;"></div>
                    <span>Junction</span>
                </div>
            </div>

            <div class="validation-panel" id="validationPanel" style="display: none;"></div>
        </div>
    </div>

    <script>
        const GRID_SIZE = 100;
        
        let nodes = [];
        let connections = [];
        let selectedNode = null;
        let nextId = 1;

        const canvas = document.getElementById('canvas');
        const ctx = canvas.getContext('2d');
        let offsetX = 0, offsetY = 0;
        let scale = 1;
        let isDragging = false;
        let dragStartX = 0, dragStartY = 0;
        let isDraggingNode = false;
        let draggedNode = null;
        let dragOffsetX = 0, dragOffsetY = 0;

        function resizeCanvas() {
            const container = canvas.parentElement;
            canvas.width = container.clientWidth;
            canvas.height = container.clientHeight;
            draw();
        }

        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();

        function snapToGrid(value) {
            return Math.round(value / GRID_SIZE) * GRID_SIZE;
        }

        function addStation() {
            const name = document.getElementById('stationName').value.trim();
            const item = document.getElementById('stationItem').value.trim();
            
            if (!name) {
                alert('Please enter a station name');
                return;
            }
            if (!item) {
                alert('Please enter a station item');
                return;
            }
            
            const node = {
                id: nextId++,
                type: 'station',
                name: name,
                x: snapToGrid(canvas.width / 2 / scale - offsetX / scale),
                y: snapToGrid(canvas.height / 2 / scale - offsetY / scale),
                item: item
            };
            
            nodes.push(node);
            document.getElementById('stationName').value = '';
            document.getElementById('stationItem').value = '';
            
            updateConnections();
            updateNodeList();
            draw();
            validateNetwork();
        }

        function addIntersection() {
            const node = {
                id: nextId++,
                type: 'intersection',
                x: snapToGrid(canvas.width / 2 / scale - offsetX / scale),
                y: snapToGrid(canvas.height / 2 / scale - offsetY / scale),
                directions: {
                    north: [],
                    east: [],
                    south: [],
                    west: []
                }
            };
            
            nodes.push(node);
            
            updateConnections();
            calculateIntersectionItems();
            updateNodeList();
            draw();
            validateNetwork();
        }

        function deleteNode(id) {
            nodes = nodes.filter(n => n.id !== id);
            connections = connections.filter(c => c.from !== id && c.to !== id);
            if (selectedNode && selectedNode.id === id) {
                selectedNode = null;
                closeConfigPanel();
            }
            
            updateConnections();
            calculateIntersectionItems();
            updateNodeList();
            draw();
            validateNetwork();
        }

        function selectNode(id) {
            selectedNode = nodes.find(n => n.id === id);
            updateNodeList();
            draw();
            showConfigPanel();
        }

        function updateConnections() {
            connections = [];
            
            for (let i = 0; i < nodes.length; i++) {
                for (let j = i + 1; j < nodes.length; j++) {
                    const n1 = nodes[i];
                    const n2 = nodes[j];
                    
                    // Don't connect two stations together
                    if (n1.type === 'station' && n2.type === 'station') {
                        continue;
                    }
                    
                    const sameX = n1.x === n2.x;
                    const sameY = n1.y === n2.y;
                    
                    if (sameX || sameY) {
                        const hasObstacle = nodes.some(n => {
                            if (n.id === n1.id || n.id === n2.id) return false;
                            
                            if (sameX && n.x === n1.x) {
                                const minY = Math.min(n1.y, n2.y);
                                const maxY = Math.max(n1.y, n2.y);
                                return n.y > minY && n.y < maxY;
                            }
                            
                            if (sameY && n.y === n1.y) {
                                const minX = Math.min(n1.x, n2.x);
                                const maxX = Math.max(n1.x, n2.x);
                                return n.x > minX && n.x < maxX;
                            }
                            
                            return false;
                        });
                        
                        if (!hasObstacle) {
                            connections.push({ from: n1.id, to: n2.id });
                        }
                    }
                }
            }
        }

        function getDirection(fromNode, toNode) {
            const dx = toNode.x - fromNode.x;
            const dy = toNode.y - fromNode.y;
            
            if (Math.abs(dy) > Math.abs(dx)) {
                return dy < 0 ? 'north' : 'south';
            } else {
                return dx > 0 ? 'east' : 'west';
            }
        }

        function calculateIntersectionItems() {
            const intersections = nodes.filter(n => n.type === 'intersection');
            const stations = nodes.filter(n => n.type === 'station');
            
            // Reset all directions
            intersections.forEach(intersection => {
                intersection.directions = {
                    north: [],
                    east: [],
                    south: [],
                    west: []
                };
            });
            
            // For each intersection, find the shortest path to each station
            intersections.forEach(intersection => {
                stations.forEach(station => {
                    const path = findShortestPath(intersection, station);
                    if (path && path.length > 1) {
                        // The next node in the path tells us which direction
                        const nextNode = path[1];
                        const direction = getDirection(intersection, nextNode);
                        if (direction && !intersection.directions[direction].includes(station.item)) {
                            intersection.directions[direction].push(station.item);
                        }
                    }
                });
            });
        }
        
        function findShortestPath(start, end) {
            if (start.id === end.id) return [start];
            
            const queue = [[start]];
            const visited = new Set([start.id]);
            
            while (queue.length > 0) {
                const path = queue.shift();
                const current = path[path.length - 1];
                
                // Get all connected nodes
                const connectedIds = connections
                    .filter(c => c.from === current.id || c.to === current.id)
                    .map(c => c.from === current.id ? c.to : c.from);
                
                for (const id of connectedIds) {
                    if (visited.has(id)) continue;
                    
                    const node = nodes.find(n => n.id === id);
                    if (!node) continue;
                    
                    const newPath = [...path, node];
                    
                    if (node.id === end.id) {
                        return newPath;
                    }
                    
                    visited.add(id);
                    queue.push(newPath);
                }
            }
            
            return null; // No path found
        }

        function updateNodeList() {
            const list = document.getElementById('nodeList');
            list.innerHTML = '';
            
            nodes.forEach(node => {
                const item = document.createElement('div');
                item.className = `node-item ${node.type}`;
                if (selectedNode && selectedNode.id === node.id) {
                    item.className += ' selected';
                }
                
                if (node.type === 'station') {
                    item.innerHTML = `
                        <span class="node-item-name">${node.name} (${node.item})</span>
                        <button class="btn-delete" onclick="event.stopPropagation(); deleteNode(${node.id})">×</button>
                    `;
                } else {
                    item.innerHTML = `
                        <span class="node-item-name">Junction #${node.id}</span>
                        <button class="btn-delete" onclick="event.stopPropagation(); deleteNode(${node.id})">×</button>
                    `;
                }
                
                item.onclick = () => selectNode(node.id);
                list.appendChild(item);
            });
        }

        canvas.addEventListener('mousedown', e => {
            const rect = canvas.getBoundingClientRect();
            const mx = (e.clientX - rect.left - offsetX) / scale;
            const my = (e.clientY - rect.top - offsetY) / scale;
            
            const clickedNode = getNodeAt(mx, my);
            
            if (clickedNode) {
                isDraggingNode = true;
                draggedNode = clickedNode;
                dragOffsetX = mx - clickedNode.x;
                dragOffsetY = my - clickedNode.y;
                selectNode(clickedNode.id);
            } else {
                isDragging = true;
                dragStartX = e.clientX - offsetX;
                dragStartY = e.clientY - offsetY;
            }
        });

        canvas.addEventListener('mousemove', e => {
            if (isDraggingNode && draggedNode) {
                const rect = canvas.getBoundingClientRect();
                const mx = (e.clientX - rect.left - offsetX) / scale;
                const my = (e.clientY - rect.top - offsetY) / scale;
                
                draggedNode.x = snapToGrid(mx - dragOffsetX);
                draggedNode.y = snapToGrid(my - dragOffsetY);
                
                updateConnections();
                calculateIntersectionItems();
                draw();
            } else if (isDragging) {
                offsetX = e.clientX - dragStartX;
                offsetY = e.clientY - dragStartY;
                draw();
            }
        });

        canvas.addEventListener('mouseup', () => {
            if (isDraggingNode) {
                validateNetwork();
            }
            isDragging = false;
            isDraggingNode = false;
            draggedNode = null;
        });

        canvas.addEventListener('click', e => {
            // Click event removed - selection now happens on mousedown
        });

        canvas.addEventListener('wheel', e => {
            e.preventDefault();
            const rect = canvas.getBoundingClientRect();
            const mouseX = e.clientX - rect.left;
            const mouseY = e.clientY - rect.top;
            
            const zoom = e.deltaY < 0 ? 1.1 : 0.9;
            const newScale = Math.max(0.3, Math.min(3, scale * zoom));
            
            offsetX = mouseX - (mouseX - offsetX) * (newScale / scale);
            offsetY = mouseY - (mouseY - offsetY) * (newScale / scale);
            scale = newScale;
            
            draw();
        });

        function getNodeAt(x, y) {
            for (let i = nodes.length - 1; i >= 0; i--) {
                const node = nodes[i];
                const size = node.type === 'station' ? 15 : 20;
                if (Math.abs(node.x - x) < size && Math.abs(node.y - y) < size) {
                    return node;
                }
            }
            return null;
        }

        function resetView() {
            offsetX = 0;
            offsetY = 0;
            scale = 1;
            draw();
        }

        function draw() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.save();
            ctx.translate(offsetX, offsetY);
            ctx.scale(scale, scale);
            
            // Draw grid
            ctx.strokeStyle = '#2a2a2a';
            ctx.lineWidth = 1;
            const startX = Math.floor(-offsetX / scale / GRID_SIZE) * GRID_SIZE;
            const startY = Math.floor(-offsetY / scale / GRID_SIZE) * GRID_SIZE;
            const endX = startX + canvas.width / scale + GRID_SIZE;
            const endY = startY + canvas.height / scale + GRID_SIZE;
            
            for (let x = startX; x < endX; x += GRID_SIZE) {
                ctx.beginPath();
                ctx.moveTo(x, startY);
                ctx.lineTo(x, endY);
                ctx.stroke();
            }
            
            for (let y = startY; y < endY; y += GRID_SIZE) {
                ctx.beginPath();
                ctx.moveTo(startX, y);
                ctx.lineTo(endX, y);
                ctx.stroke();
            }
            
            // Draw connections
            ctx.strokeStyle = '#444';
            ctx.lineWidth = 3;
            connections.forEach(conn => {
                const from = nodes.find(n => n.id === conn.from);
                const to = nodes.find(n => n.id === conn.to);
                if (from && to) {
                    ctx.beginPath();
                    ctx.moveTo(from.x, from.y);
                    ctx.lineTo(to.x, to.y);
                    ctx.stroke();
                }
            });
            
            // Draw nodes
            nodes.forEach(node => {
                if (node.type === 'station') {
                    ctx.fillStyle = selectedNode && selectedNode.id === node.id ? '#6ab8ff' : '#4a9eff';
                    ctx.beginPath();
                    ctx.arc(node.x, node.y, 15, 0, Math.PI * 2);
                    ctx.fill();
                    
                    ctx.fillStyle = '#fff';
                    ctx.font = 'bold 14px sans-serif';
                    ctx.textAlign = 'center';
                    ctx.fillText(node.name, node.x, node.y + 35);
                    ctx.font = '11px sans-serif';
                    ctx.fillStyle = '#aaa';
                    ctx.fillText(node.item, node.x, node.y + 50);
                } else {
                    ctx.fillStyle = selectedNode && selectedNode.id === node.id ? '#ffad33' : '#ff9800';
                    ctx.fillRect(node.x - 20, node.y - 20, 40, 40);
                    
                    ctx.fillStyle = '#fff';
                    ctx.font = '11px sans-serif';
                    ctx.textAlign = 'center';
                    ctx.fillText(`J${node.id}`, node.x, node.y + 35);
                }
            });
            
            ctx.restore();
        }

        function showConfigPanel() {
            if (!selectedNode) return;
            
            closeConfigPanel();
            
            const panel = document.createElement('div');
            panel.className = 'config-panel';
            panel.id = 'configPanel';
            
            if (selectedNode.type === 'station') {
                panel.innerHTML = `
                    <button class="close-btn" onclick="closeConfigPanel()">×</button>
                    <h3>Station: ${selectedNode.name}</h3>
                    <div class="direction-display has-item">
                        <span class="direction-label">Item in Minecart</span>
                        <div class="direction-value">${selectedNode.item}</div>
                    </div>
                `;
            } else {
                const northItems = selectedNode.directions.north.join(', ') || 'Empty';
                const eastItems = selectedNode.directions.east.join(', ') || 'Empty';
                const southItems = selectedNode.directions.south.join(', ') || 'Empty';
                const westItems = selectedNode.directions.west.join(', ') || 'Empty';
                
                panel.innerHTML = `
                    <button class="close-btn" onclick="closeConfigPanel()">×</button>
                    <h3>Junction #${selectedNode.id}</h3>
                    <p style="color: #888; font-size: 13px; margin-bottom: 15px;">Items are automatically assigned based on shortest paths to all stations</p>
                    <div class="direction-display ${selectedNode.directions.north.length > 0 ? 'has-item' : ''}">
                        <span class="direction-label">⬆ North</span>
                        <div class="direction-value ${selectedNode.directions.north.length === 0 ? 'empty' : ''}">${northItems}</div>
                    </div>
                    <div class="direction-display ${selectedNode.directions.east.length > 0 ? 'has-item' : ''}">
                        <span class="direction-label">➡ East</span>
                        <div class="direction-value ${selectedNode.directions.east.length === 0 ? 'empty' : ''}">${eastItems}</div>
                    </div>
                    <div class="direction-display ${selectedNode.directions.south.length > 0 ? 'has-item' : ''}">
                        <span class="direction-label">⬇ South</span>
                        <div class="direction-value ${selectedNode.directions.south.length === 0 ? 'empty' : ''}">${southItems}</div>
                    </div>
                    <div class="direction-display ${selectedNode.directions.west.length > 0 ? 'has-item' : ''}">
                        <span class="direction-label">⬅ West</span>
                        <div class="direction-value ${selectedNode.directions.west.length === 0 ? 'empty' : ''}">${westItems}</div>
                    </div>
                `;
            }
            
            document.body.appendChild(panel);
        }

        function closeConfigPanel() {
            const panel = document.getElementById('configPanel');
            if (panel) panel.remove();
        }

        function validateNetwork() {
            const panel = document.getElementById('validationPanel');
            const errors = [];
            const warnings = [];
            
            const stations = nodes.filter(n => n.type === 'station');
            const intersections = nodes.filter(n => n.type === 'intersection');
            
            intersections.forEach(intersection => {
                const allItems = [
                    ...intersection.directions.north,
                    ...intersection.directions.east,
                    ...intersection.directions.south,
                    ...intersection.directions.west
                ];
                
                // Check if any item appears in multiple directions
                const itemCounts = {};
                ['north', 'east', 'south', 'west'].forEach(dir => {
                    intersection.directions[dir].forEach(item => {
                        if (!itemCounts[item]) itemCounts[item] = [];
                        itemCounts[item].push(dir);
                    });
                });
                
                Object.keys(itemCounts).forEach(item => {
                    if (itemCounts[item].length > 1) {
                        errors.push(`Junction #${intersection.id}: Item "${item}" appears in multiple directions (${itemCounts[item].join(', ')})`);
                    }
                });
            });
            
            if (errors.length === 0 && warnings.length === 0) {
                panel.style.display = 'none';
            } else {
                panel.style.display = 'block';
                panel.innerHTML = '<div class="validation-title">Validation Results</div>';
                
                errors.forEach(err => {
                    panel.innerHTML += `<div class="validation-item validation-error">❌ ${err}</div>`;
                });
                
                warnings.forEach(warn => {
                    panel.innerHTML += `<div class="validation-item validation-warning">⚠️ ${warn}</div>`;
                });
                
                if (errors.length === 0) {
                    panel.innerHTML += `<div class="validation-item validation-success">✓ Network configuration is valid</div>`;
                }
            }
        }

        function exportNetwork() {
            const data = {
                nodes: nodes,
                nextId: nextId,
                version: 1
            };
            
            const json = JSON.stringify(data, null, 2);
            const blob = new Blob([json], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            
            const a = document.createElement('a');
            a.href = url;
            a.download = `minecraft-rail-network-${Date.now()}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        function importNetwork(event) {
            const file = event.target.files[0];
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const data = JSON.parse(e.target.result);
                    
                    if (!data.nodes || !Array.isArray(data.nodes)) {
                        alert('Invalid file format: missing nodes array');
                        return;
                    }
                    
                    nodes = data.nodes;
                    nextId = data.nextId || (Math.max(...nodes.map(n => n.id), 0) + 1);
                    selectedNode = null;
                    
                    // Ensure all nodes have proper structure
                    nodes.forEach(node => {
                        if (node.type === 'intersection' && !node.directions) {
                            node.directions = { north: [], east: [], south: [], west: [] };
                        }
                    });
                    
                    closeConfigPanel();
                    updateConnections();
                    calculateIntersectionItems();
                    updateNodeList();
                    draw();
                    validateNetwork();
                    
                    alert('Network imported successfully!');
                } catch (err) {
                    alert('Error importing file: ' + err.message);
                }
            };
            reader.readAsText(file);
            
            // Reset the file input so the same file can be imported again
            event.target.value = '';
        }

        draw();
    </script>
</body>
</html>