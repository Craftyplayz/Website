<?php
// Shows the Chapter Oracle README as a readable page at /games/chapterguesser/info/.
// The Markdown is embedded here and rendered in the browser, so editing
// README.md is all it takes to update this page.
$readme = @file_get_contents(__DIR__ . '/../README.md');
if ($readme === false) {
    http_response_code(503);
    $readme = "# Chapter Oracle\n\nThe game notes are unavailable right now.";
}
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chapter Oracle Info</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../themes.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/marked/12.0.2/marked.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.1.6/purify.min.js"></script>
<style>
*, *::before, *::after { box-sizing: border-box; }

body {
  margin: 0;
  background: var(--bg);
  color: var(--text);
  font-family: 'Outfit', sans-serif;
  font-size: 16px;
  line-height: 1.65;
  padding: 28px 16px 64px;
}
body::before {
  content: '';
  position: fixed; inset: 0;
  background:
    radial-gradient(ellipse 70% 55% at 15% 5%,  var(--gold-glow) 0%, transparent 55%),
    radial-gradient(ellipse 55% 70% at 85% 95%, var(--haze)      0%, transparent 55%);
  pointer-events: none; z-index: 0;
}

.wrap { position: relative; z-index: 1; max-width: 800px; margin: 0 auto; }

.top { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 20px; }
.top a { color: var(--text-muted); text-decoration: none; font-size: 14px; }
.top a:hover { color: var(--gold); }

.card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 32px 36px;
}
@media (max-width: 560px) { .card { padding: 22px 18px; } }

.doc h1, .doc h2, .doc h3 {
  font-family: 'Cormorant Garamond', serif;
  font-weight: 600;
  line-height: 1.2;
  color: var(--text);
  scroll-margin-top: 16px;
}
.doc h1 { font-size: 44px; margin: 0 0 12px; color: var(--gold); }
.doc h2 { font-size: 30px; margin: 40px 0 12px; padding-bottom: 6px; border-bottom: 1px solid var(--border); }
.doc h3 { font-size: 22px; margin: 28px 0 8px; color: var(--gold); }
.doc p, .doc ul, .doc ol { margin: 0 0 14px; }
.doc ul, .doc ol { padding-left: 22px; }
.doc li { margin: 4px 0; }
.doc a { color: var(--gold); }
.doc strong { color: var(--text); font-weight: 600; }

.doc code {
  font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
  font-size: 0.86em;
  background: var(--gold-glow);
  padding: 1px 6px;
  border-radius: 5px;
  overflow-wrap: anywhere;
}
.doc pre {
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 9px;
  padding: 14px 16px;
  overflow-x: auto;
  margin: 0 0 16px;
}
.doc pre code { background: none; padding: 0; overflow-wrap: normal; }

.table-scroll { overflow-x: auto; margin: 0 0 18px; }
.doc table { border-collapse: collapse; width: 100%; font-size: 14px; }
.doc th, .doc td { text-align: left; vertical-align: top; padding: 9px 12px; border-bottom: 1px solid var(--border); }
.doc th { color: var(--gold); font-weight: 600; white-space: nowrap; }

.fallback { white-space: pre-wrap; font-family: ui-monospace, Consolas, monospace; font-size: 13px; }
</style>
</head>
<body>
<div class="wrap">
  <nav class="top">
    <a href="../">← Back to the game</a>
  </nav>
  <main class="card">
    <article class="doc" id="doc">
      <pre class="fallback"><?= htmlspecialchars($readme, ENT_QUOTES, 'UTF-8') ?></pre>
    </article>
  </main>
</div>

<script>
const README = <?= json_encode($readme, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;

// Without the CDN scripts the plain-text fallback above stays in place
if (window.marked && window.DOMPurify) {
  const doc = document.getElementById('doc');
  doc.innerHTML = DOMPurify.sanitize(marked.parse(README));

  // GitHub-style heading ids, so links like #game-modes work
  const used = {};
  for (const h of doc.querySelectorAll('h1, h2, h3, h4')) {
    let id = h.textContent.trim().toLowerCase().replace(/[^\p{L}\p{N}\s-]/gu, '').replace(/\s+/g, '-');
    if (used[id] !== undefined) id += '-' + (++used[id]); else used[id] = 0;
    h.id = id;
  }

  // Wide tables scroll sideways instead of widening the page
  for (const t of doc.querySelectorAll('table')) {
    const box = document.createElement('div');
    box.className = 'table-scroll';
    t.replaceWith(box);
    box.appendChild(t);
  }

  if (location.hash) document.getElementById(decodeURIComponent(location.hash.slice(1)))?.scrollIntoView();
}
</script>
</body>
</html>
