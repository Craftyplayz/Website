<?php
$site_page = ['components' => true, 'css' => ['/assets/css/pages/minecraft-playtime.css']];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!doctype html>
<html lang="en">
<head>
    <?php page_head(); ?>
</head>
<body>
    <?php site_header(); ?>
    <main class="page" id="main">
  <?= site_breadcrumbs(site_current_path()) ?>
  <h1 class="page-title">Minecraft Instance Stats</h1>
  <p class="page-lede">Data loaded from <code>stats.json</code>. Run <code>collect_stats.py</code> again to refresh it.</p>
  <p id="status" class="muted">Loading stats…</p>
  <section id="instances" class="masonry"></section>
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
      card.className = "card";
      const title = document.createElement("h2");
      title.textContent = item.name || item.folder || "Unnamed instance";
      card.append(title);
      const dl = document.createElement("dl");
      dl.className = "kv";
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
    statusEl.className = "err";
    statusEl.textContent = `Couldn't load stats.json (${error.message}). Open this page through a local web server, not directly as a file.`;
  }
}
loadStats();
</script>
    </main>
    <?php site_footer(); ?>
</body>
</html>
