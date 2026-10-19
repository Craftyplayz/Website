<?php
$site_page = [];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php page_head(); ?>
<style>
    /* full-screen tool: no footer, the card sits in the middle of what's left under the header */
    body {
        display: flex;
        flex-direction: column;
        height: 100vh;
        overflow: hidden;
        color: var(--text);
    }

    .topbar {
        box-sizing: border-box;
        flex-shrink: 0;
        width: 100%;
        max-width: 1280px;
        margin: 0 auto;
        padding: 16px 16px 0;
    }

    .topbar h1 { margin: 0; font-size: clamp(24px, 4vw, 34px); line-height: 1.2; }
    .topbar p { margin: 4px 0 0; color: var(--muted); }

    .content {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }

    .card {
        box-sizing: border-box;
        width: min(480px, 100%);
        display: flex;
        flex-direction: column;
        gap: 12px;
        padding: 20px;
        background: var(--card);
        border: 1px solid var(--line);
        border-radius: var(--radius-lg);
    }

    .card * { box-sizing: border-box; }

    input[type="text"] {
        width: 100%;
        margin: 0;
        padding: 10px 14px;
        background: #161616;
        border: 1px solid #4a4a4a;
        border-radius: var(--radius);
        color: var(--text);
        font: 18px var(--mono);
        outline: none;
        transition: border-color var(--fast);
    }

    input[type="text"]::placeholder { color: var(--muted); }
    input[type="text"]:focus { border-color: var(--accent); }

    .dim-options {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 8px;
    }

    /* visually hidden but still focusable, so the arrow keys work */
    .dim-option input { position: absolute; opacity: 0; width: 1px; height: 1px; }

    .dim-option label {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 40px;
        padding: 8px 12px;
        background: var(--card2);
        border: 1px solid var(--line);
        border-radius: 999px;
        color: var(--muted);
        font-size: 14px;
        cursor: pointer;
        transition: background var(--fast), color var(--fast);
    }

    .dim-option label:hover { color: var(--text); }
    .dim-option input:checked + label { background: var(--accent-soft); border-color: var(--btn-line); color: var(--text); }
    .dim-option input:focus-visible + label { outline: 2px solid var(--accent); outline-offset: 2px; }

    .result-box {
        display: flex;
        align-items: center;
        min-height: 48px;
        padding: 10px 14px;
        background: var(--sunken);
        border: 1px solid var(--line);
        border-radius: var(--radius);
        font: 18px var(--mono);
    }

    .result-box.empty { color: var(--muted); font: 14px var(--font); }

    .error { min-height: 18px; color: #ff8a8a; font-size: 13px; }

    button {
        width: 100%;
        margin: 0;
        padding: 10px 16px;
        background: var(--btn);
        border: 1px solid var(--btn-line);
        border-radius: var(--radius);
        color: #fff;
        font: 15px var(--font);
        cursor: pointer;
        transition: background var(--fast), transform .06s;
    }

    button:hover { background: #4a9033; }
    button:active { transform: translateY(1px); }
    button:disabled { opacity: .5; cursor: default; transform: none; }
    button:disabled:hover { background: var(--btn); }
    button:focus-visible, input:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }

    .toast {
        position: fixed;
        right: 16px;
        bottom: 16px;
        z-index: 200;
        padding: 10px 14px;
        background: #2d4a24;
        border: 1px solid #4f8a3a;
        border-radius: var(--radius);
        font-size: 14px;
        opacity: 0;
        transform: translateY(4px);
        transition: opacity var(--fast) ease-out, transform var(--fast) ease-out;
        pointer-events: none;
    }

    .toast.show { opacity: 1; transform: none; }
</style>
</head>
<body>
    <?php site_header(); ?>

<div class="topbar">
    <h1>Nether Portal Converter</h1>
    <p>Convert Overworld and Nether coordinates from a /tp command.</p>
</div>

<main class="content" id="main">
    <div class="card">
        <input type="text" id="input" placeholder="/tp -8676 ~ 4405" oninput="convert()" autofocus>

        <div class="dim-options">
            <div class="dim-option">
                <input type="radio" name="dim" id="ow" value="overworld" checked onchange="convert()">
                <label for="ow">Overworld → Nether</label>
            </div>
            <div class="dim-option">
                <input type="radio" name="dim" id="ne" value="nether" onchange="convert()">
                <label for="ne">Nether → Overworld</label>
            </div>
        </div>

        <div class="result-box empty" id="result-box">Paste a /tp command above</div>
        <div class="error" id="error"></div>

        <button id="copy-btn" onclick="copyResult()" disabled>Copy to clipboard</button>
    </div>
</main>

<div class="toast" id="toast"></div>

<script>
    let lastResult = '';

    function parseCoords(raw) {
        const s = raw.trim();
        const patterns = [
            /\/tp\s+(-?\d+(?:\.\d+)?)\s+(~|-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)/i,
            /(-?\d+(?:\.\d+)?)\s+(~|-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)/,
        ];
        for (const p of patterns) {
            const m = s.match(p);
            if (m) return { x: parseFloat(m[1]), y: m[2], z: parseFloat(m[3]) };
        }
        return null;
    }

    function convert() {
        const raw = document.getElementById('input').value;
        const dim = document.querySelector('input[name="dim"]:checked').value;
        const box = document.getElementById('result-box');
        const errorEl = document.getElementById('error');
        const copyBtn = document.getElementById('copy-btn');

        errorEl.textContent = '';
        lastResult = '';
        copyBtn.disabled = true;

        if (!raw.trim()) {
            box.textContent = 'Paste a /tp command above';
            box.className = 'result-box empty';
            return;
        }

        const coords = parseCoords(raw);
        if (!coords) {
            box.textContent = 'Paste a /tp command above';
            box.className = 'result-box empty';
            errorEl.textContent = 'Could not read coordinates. Expected: /tp -8676 ~ 4405';
            return;
        }

        const factor = dim === 'overworld' ? (1 / 8) : 8;
        const newX = Math.round(coords.x * factor);
        const newZ = Math.round(coords.z * factor);
        const yStr = coords.y === '~' ? '~' : Math.round(parseFloat(coords.y));

        lastResult = `/tp ${newX} ${yStr} ${newZ}`;
        box.textContent = lastResult;
        box.className = 'result-box';
        copyBtn.disabled = false;
    }

    function copyResult() {
        if (!lastResult) return;
        navigator.clipboard.writeText(lastResult).then(() => {
            const t = document.getElementById('toast');
            t.textContent = 'Copied!';
            t.classList.add('show');
            setTimeout(() => t.classList.remove('show'), 2000);
        });
    }
</script>
</body>
</html>
