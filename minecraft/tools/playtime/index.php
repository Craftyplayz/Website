<?php
$site_page = [];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!doctype html>
<html lang="en">
<head>
    <?php page_head(); ?>
  <style>
    :root { color-scheme: dark; font-family: system-ui, sans-serif; }
    .tool-wrap { margin: 0; padding: 2rem; background: #111827; color: #e5e7eb; }
    main { max-width: 1100px; margin: auto; }
    h1 { margin-top: 0; }
    .hint { color: #9ca3af; }
    .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; }
    article { background: #1f2937; border: 1px solid #374151; border-radius: 12px; padding: 1rem; }
    h2 { font-size: 1.1rem; overflow-wrap: anywhere; margin: 0 0 1rem; }
    dl { display: grid; grid-template-columns: 1fr; gap: .35rem; margin: 0; }
    dt { color: #9ca3af; font-size: .85rem; }
    dd { margin: 0 0 .65rem; overflow-wrap: anywhere; }
    .error { color: #fca5a5; }
  </style>
</head>
<body>
    <?php site_header(); ?>
    <div class="tool-wrap">
<main>
  <h1>Minecraft Instance Stats</h1>
  <p class="hint">Data loaded from <code>stats.json</code>. Run <code>collect_stats.py</code> again to refresh it.</p>
  <div id="status" class="hint">Loading stats…</div>
  <section id="instances" class="cards"></section>
</main>
<script>
const statusEl = document.querySelector("#status");
const listEl = document.querySelector("#instances");

function formatDate(value) {
  if (value === "" || value == null) return "Unknown";
  const n = Number(value);
  if (!Number.isFinite(n)) return String(value);
  // Prism Launcher timestamps are usually Unix milliseconds.
  const date = new Date(n);
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
}
function formatDuration(value) {
  if (value === "" || value == null) return "Unknown";
  const seconds = Number(value);
  if (!Number.isFinite(seconds)) return String(value);
  const days = Math.floor(seconds / 86400);
  const hours = Math.floor((seconds % 86400) / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  const secs = Math.floor(seconds % 60);
  return `${days ? days + "d " : ""}${hours}h ${minutes}m ${secs}s`;
}
async function loadStats() {
  try {
    const response = await fetch("stats.json", { cache: "no-store" });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const instances = await response.json();
    if (!Array.isArray(instances)) throw new Error("stats.json should contain a JSON array.");
    statusEl.textContent = `${instances.length} instance(s) found`;
    listEl.replaceChildren();
    for (const item of instances) {
      const card = document.createElement("article");
      const title = document.createElement("h2");
      title.textContent = item.name || item.folder || "Unnamed instance";
      card.append(title);
      const dl = document.createElement("dl");
      const rows = [
        ["Last launch", formatDate(item.lastlaunchtime)],
        ["Last time played", formatDuration(item.lasttimeplayed)],
        ["Total time played", formatDuration(item.totaltimeplayed)],
        ["Folder", item.folder || "Unknown"]
      ];
      for (const [label, value] of rows) {
        const dt = document.createElement("dt"); dt.textContent = label;
        const dd = document.createElement("dd"); dd.textContent = value;
        dl.append(dt, dd);
      }
      card.append(dl); listEl.append(card);
    }
  } catch (error) {
    statusEl.className = "error";
    statusEl.textContent = `Couldn't load stats.json (${error.message}). Open this page through a local web server, not directly as a file.`;
  }
}
loadStats();
</script>
    </div>
    <?php site_footer(); ?>
</body>
</html>
