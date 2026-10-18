<?php
$transcriptsDir = __DIR__ . '/transcripts';

// ── Handle new transcript submission ──
$uploadError   = '';
$uploadSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['markdown'])) {
    $md = trim($_POST['markdown']);
    if (empty($md)) {
        $uploadError = 'No markdown content provided.';
    } else {
        // Try to derive a filename from the Date + Channel fields
        preg_match('/\*\*Date\*\*[:\s]+(.+?)(?:\n|$)/i', $md, $dm);
        preg_match('/\*\*Channel\*\*[:\s]+(.+?)(?:\n|$)/i', $md, $cm);
        $dateStr = isset($dm[1]) ? trim($dm[1]) : '';
        $channel = isset($cm[1]) ? trim($cm[1]) : 'recording';
        $ts      = $dateStr ? strtotime($dateStr) : time();
        if (!$ts) $ts = time();
        $datePart    = date('Y-m-d', $ts);
        $channelSlug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $channel));
        $base        = $datePart . '_' . $channelSlug;
        $filename    = $base . '.md';
        $target      = $transcriptsDir . '/' . $filename;

        // Avoid collisions
        $i = 1;
        while (file_exists($target)) {
            $filename = $base . '_' . $i . '.md';
            $target   = $transcriptsDir . '/' . $filename;
            $i++;
        }

        if (!is_dir($transcriptsDir)) mkdir($transcriptsDir, 0755, true);

        if (file_put_contents($target, $md) !== false) {
            // Redirect to the new transcript
            header('Location: ?file=' . urlencode($filename));
            exit;
        } else {
            $uploadError = 'Could not write file. Check that the transcripts/ folder is writable.';
        }
    }
}

// ── Parse all message timestamps out of content to get duration & speakers ──
function parseMessages(string $content): array {
    $lines    = explode("\n", $content);
    $messages = [];
    $msgIndex = 0;
    $i = 0;
    while ($i < count($lines)) {
        $line = $lines[$i];
        if (preg_match('/^\*(.+?)\s+is present\s+-\s+(\d{1,2}:\d{2}:\d{2}\s+[AP]M)\*$/', $line, $m)) {
            $messages[] = ['type'=>'presence','id'=>'msg-'.$msgIndex++,'speaker'=>$m[1],'time'=>$m[2],'text'=>''];
            $i++; continue;
        }
        if (preg_match('/^\*\*(.+?)\*\*\s+-\s+(\d{1,2}:\d{2}:\d{2}\s+[AP]M)\s*$/', $line, $m)) {
            $speaker = $m[1]; $time = $m[2];
            $textLines = []; $j = $i + 1;
            while ($j < count($lines)) {
                $next = $lines[$j];
                if ($next === '' && !empty($textLines)) { $j++; break; }
                if (preg_match('/^\*\*(.+?)\*\*\s+-\s+\d/', $next)) break;
                if (preg_match('/^\*(.+?)\s+is present/', $next)) break;
                if ($next !== '') $textLines[] = $next;
                $j++;
            }
            $messages[] = ['type'=>'message','id'=>'msg-'.$msgIndex++,'speaker'=>$speaker,'time'=>$time,'text'=>implode(' ',$textLines)];
            $i = $j; continue;
        }
        $i++;
    }
    return $messages;
}

function getCallDuration(array $messages, string $dateBase): string {
    // dateBase = e.g. "March 1, 2026" to anchor AM/PM times
    $times = [];
    foreach ($messages as $msg) {
        if (empty($msg['time'])) continue;
        $ts = strtotime($dateBase . ' ' . $msg['time']);
        if ($ts) $times[] = $ts;
    }
    if (count($times) < 2) return '';
    $secs = max($times) - min($times);
    $h = floor($secs / 3600);
    $m = floor(($secs % 3600) / 60);
    $s = $secs % 60;
    if ($h > 0) return "{$h}h {$m}m";
    if ($m > 0) return "{$m}m {$s}s";
    return "{$s}s";
}

function getUniqueSpeakers(array $messages): array {
    $seen = [];
    foreach ($messages as $msg) {
        if ($msg['type'] === 'message' && !isset($seen[$msg['speaker']])) {
            $seen[$msg['speaker']] = true;
        }
    }
    return array_keys($seen);
}

function getTranscriptMeta(string $path): array {
    $filename = basename($path);
    $content  = file_get_contents($path);

    preg_match('/^#\s+(.+)$/m', $content, $titleMatch);
    $title = $titleMatch[1] ?? preg_replace('/\.[^.]+$/', '', $filename);

    preg_match('/\*\*Date\*\*[:\s]+(.+?)(?:\n|$)/i', $content, $dateMatch);
    $dateStr   = isset($dateMatch[1]) ? trim($dateMatch[1]) : null;
    $timestamp = $dateStr ? strtotime($dateStr) : filemtime($path);
    if (!$timestamp) $timestamp = filemtime($path);

    preg_match('/\*\*Channel\*\*[:\s]+(.+?)(?:\n|$)/i', $content, $chanMatch);
    $channel = isset($chanMatch[1]) ? trim($chanMatch[1]) : 'Unknown';

    preg_match('/\*\*Speakers\*\*[:\s]+(\d+)/i', $content, $speakersMatch);
    $speakerCount = $speakersMatch[1] ?? '?';

    preg_match('/\*\*Total Messages\*\*[:\s]+(\d+)/i', $content, $msgMatch);
    $msgCount = $msgMatch[1] ?? '?';

    // Sidebar label: YYYY-MM-DD HH:MM AM/PM · Channel Name
    $datePart = date('Y-m-d', $timestamp);
    $timePart = date('g:i A', $timestamp);
    $sidebarLabel = "{$datePart} {$timePart} · {$channel}";

    return [
        'filename'     => $filename,
        'path'         => $path,
        'title'        => $title,
        'date'         => $dateStr ?? date('M j, Y', $timestamp),
        'dateBase'     => date('F j, Y', $timestamp),
        'timestamp'    => $timestamp,
        'channel'      => $channel,
        'channelSlug'  => strtolower(str_replace(' ', '-', $channel)),
        'speakerCount' => $speakerCount,
        'msgCount'     => $msgCount,
        'sidebarLabel' => $sidebarLabel,
    ];
}

function speakerColor(string $name): string {
    $colors = ['#c9cdfb','#f28fad','#a8d8a8','#faa61a','#eb459e','#57f287','#fee75c','#ed4245','#5865f2','#00b0f4'];
    $hash = 0;
    for ($k = 0; $k < strlen($name); $k++) $hash = ($hash * 31 + ord($name[$k])) & 0xFFFFFF;
    return $colors[$hash % count($colors)];
}

// ── Load & sort transcripts ──
$transcripts = [];
if (is_dir($transcriptsDir)) {
    foreach (glob($transcriptsDir . '/*.md') as $f) $transcripts[] = getTranscriptMeta($f);
}
usort($transcripts, fn($a,$b) => $b['timestamp'] - $a['timestamp']);

// ── Active transcript ──
$activeFile = isset($_GET['file']) ? basename($_GET['file']) : null;
$activeMeta = null; $messages = null; $duration = ''; $speakers = [];
if ($activeFile) {
    foreach ($transcripts as $t) { if ($t['filename'] === $activeFile) { $activeMeta = $t; break; } }
    if ($activeMeta) {
        $messages = parseMessages(file_get_contents($activeMeta['path']));
        $duration = getCallDuration($messages, $activeMeta['dateBase']);
        $speakers = getUniqueSpeakers($messages);
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $activeMeta ? '#'.htmlspecialchars($activeMeta['channelSlug']).' — ' : '' ?>Transcript Archive</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;500;600;700&display=swap');

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --dc-900: #1e1f22;
  --dc-800: #2b2d31;
  --dc-700: #313338;
  --dc-600: #383a40;
  --dc-500: #404249;
  --dc-400: #43444b;
  --dc-200: #80848e;
  --dc-100: #b5bac1;
  --dc-050: #dbdee1;
  --dc-white: #f2f3f5;
  --dc-blue: #5865f2;
  --dc-green: #23a55a;
  --dc-mention: rgba(88,101,242,.15);
  --dc-mention-bar: #5865f2;
  --font: 'Noto Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif;
}

html, body { height: 100%; overflow: hidden; }
body { font-family: var(--font); background: var(--dc-700); color: var(--dc-050); font-size: 16px; }

.app { display: flex; height: 100vh; }

/* ─── SIDEBAR ─── */
.sidebar {
  width: 252px;
  flex-shrink: 0;
  background: var(--dc-800);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.sidebar-header {
  height: 48px;
  border-bottom: 1px solid var(--dc-900);
  display: flex;
  align-items: center;
  padding: 0 16px;
  flex-shrink: 0;
  font-size: 15px;
  font-weight: 700;
  color: var(--dc-white);
  box-shadow: 0 1px 0 rgba(0,0,0,.2);
  cursor: default;
  user-select: none;
}

.sidebar-section-label {
  font-size: 11px;
  font-weight: 700;
  color: var(--dc-200);
  text-transform: uppercase;
  letter-spacing: .02em;
  padding: 16px 8px 4px 8px;
  flex-shrink: 0;
}

.channel-list {
  flex: 1;
  overflow-y: auto;
  padding: 0 8px 8px;
}
.channel-list::-webkit-scrollbar { width: 4px; }
.channel-list::-webkit-scrollbar-thumb { background: var(--dc-400); border-radius: 2px; }

.ch-item {
  display: flex;
  align-items: flex-start;
  gap: 6px;
  padding: 5px 8px;
  border-radius: 4px;
  text-decoration: none;
  color: var(--dc-200);
  font-size: 13px;
  font-weight: 500;
  margin: 1px 0;
  transition: background .08s, color .08s;
  overflow: hidden;
  cursor: pointer;
  line-height: 1.3;
}
.ch-item:hover { background: var(--dc-500); color: var(--dc-100); }
.ch-item.active { background: var(--dc-600); color: var(--dc-white); font-weight: 600; }
.ch-item .ch-icon { font-size: 14px; flex-shrink: 0; margin-top: 1px; }
.ch-item .ch-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* ─── MAIN ─── */
.main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }

/* Channel header */
.ch-header {
  height: 48px;
  border-bottom: 1px solid var(--dc-900);
  display: flex;
  align-items: center;
  padding: 0 16px;
  gap: 8px;
  flex-shrink: 0;
  background: var(--dc-700);
  box-shadow: 0 1px 0 rgba(0,0,0,.2);
  overflow: hidden;
}
.ch-header-hash { font-size: 24px; color: var(--dc-200); font-weight: 300; line-height: 1; flex-shrink: 0; }
.ch-header-name { font-size: 16px; font-weight: 700; color: var(--dc-white); flex-shrink: 0; }
.ch-header-divider { width: 1px; height: 20px; background: var(--dc-400); flex-shrink: 0; }
.ch-header-topic {
  font-size: 14px;
  color: var(--dc-200);
  display: flex;
  align-items: center;
  gap: 0;
  overflow: hidden;
  white-space: nowrap;
  flex-shrink: 1;
  min-width: 0;
}
.ch-header-topic-text {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Members dropdown trigger */
.members-trigger {
  position: relative;
  display: inline-flex;
  align-items: center;
  cursor: default;
  flex-shrink: 0;
}
.members-trigger .trigger-label {
  color: var(--dc-200);
  font-size: 14px;
  transition: color .1s;
  border-bottom: 1px dashed var(--dc-400);
  padding-bottom: 1px;
  cursor: default;
}
.members-trigger:hover .trigger-label { color: var(--dc-050); }

.members-dropdown {
  display: none;
  position: absolute;
  top: calc(100% + 10px);
  left: 50%;
  transform: translateX(-50%);
  background: var(--dc-800);
  border: 1px solid var(--dc-600);
  border-radius: 6px;
  box-shadow: 0 8px 24px rgba(0,0,0,.5);
  z-index: 100;
  min-width: 160px;
  padding: 6px 0;
}
.members-trigger:hover .members-dropdown { display: block; }

.members-dropdown::before {
  content: '';
  position: absolute;
  top: -5px;
  left: 50%;
  transform: translateX(-50%);
  width: 10px; height: 10px;
  background: var(--dc-800);
  border-left: 1px solid var(--dc-600);
  border-top: 1px solid var(--dc-600);
  transform: translateX(-50%) rotate(45deg);
}

.members-dropdown-title {
  font-size: 11px;
  font-weight: 700;
  color: var(--dc-200);
  text-transform: uppercase;
  letter-spacing: .05em;
  padding: 4px 12px 6px;
}
.members-dropdown-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 5px 12px;
  font-size: 14px;
  color: var(--dc-100);
  white-space: nowrap;
}
.members-dropdown-item::before {
  content: '';
  width: 8px; height: 8px;
  border-radius: 50%;
  background: var(--dc-green);
  flex-shrink: 0;
}

/* Messages */
.messages-scroll {
  flex: 1;
  overflow-y: auto;
  padding-bottom: 24px;
}
.messages-scroll::-webkit-scrollbar { width: 8px; }
.messages-scroll::-webkit-scrollbar-thumb { background: var(--dc-900); border-radius: 4px; border: 2px solid var(--dc-700); }

/* Welcome banner */
.welcome-box { padding: 40px 16px 16px; }
.welcome-icon {
  width: 68px; height: 68px;
  border-radius: 50%;
  background: var(--dc-600);
  display: flex; align-items: center; justify-content: center;
  font-size: 36px;
  margin-bottom: 16px;
}
.welcome-box h2 { font-size: 32px; font-weight: 800; color: var(--dc-white); margin-bottom: 6px; }
.welcome-box p  { font-size: 15px; color: var(--dc-100); line-height: 1.5; }

/* Date divider */
.date-divider {
  display: flex;
  align-items: center;
  margin: 16px 16px;
  color: var(--dc-200);
  font-size: 12px;
  font-weight: 600;
}
.date-divider::before, .date-divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--dc-600);
}
.date-divider span { padding: 0 8px; white-space: nowrap; }

/* Message group — no avatar, name-only style */
.msg-group {
  display: flex;
  padding: 2px 16px;
  gap: 0;
  position: relative;
}
.msg-group:hover { background: rgba(0,0,0,.06); }
.msg-group:hover .msg-hover-time { opacity: 1; }
.msg-group:hover .msg-link-btn { opacity: 1; }
.msg-group.with-header { margin-top: 17px; }
.msg-group.highlighted {
  background: var(--dc-mention);
  border-left: 2px solid var(--dc-mention-bar);
  padding-left: 14px;
}

/* Left time gutter */
.msg-time-gutter {
  width: 56px;
  flex-shrink: 0;
  display: flex;
  align-items: flex-start;
  justify-content: flex-end;
  padding-right: 12px;
  padding-top: 3px;
}
.msg-hover-time {
  font-size: 11px;
  color: var(--dc-200);
  opacity: 0;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
  line-height: 1.375;
  transition: opacity .1s;
}

/* Message content */
.msg-content { flex: 1; min-width: 0; }
.msg-header  { display: flex; align-items: baseline; gap: 8px; margin-bottom: 2px; }
.msg-author  { font-size: 16px; font-weight: 600; cursor: default; line-height: 1.375; }
.msg-timestamp { font-size: 11.5px; color: var(--dc-200); font-variant-numeric: tabular-nums; }
.msg-text { font-size: 16px; line-height: 1.375; color: var(--dc-050); word-break: break-word; }

/* Presence */
.msg-presence {
  padding: 2px 16px 2px 68px;
  font-size: 14px;
  color: var(--dc-200);
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 4px;
}
.msg-presence::before {
  content: '';
  width: 8px; height: 8px;
  border-radius: 50%;
  background: var(--dc-green);
  flex-shrink: 0;
}

/* Copy link button */
.msg-link-btn {
  position: absolute;
  right: 8px;
  top: 50%;
  transform: translateY(-50%);
  background: var(--dc-800);
  border: 1px solid var(--dc-600);
  border-radius: 4px;
  color: var(--dc-100);
  font-size: 12px;
  font-family: var(--font);
  font-weight: 500;
  padding: 4px 10px;
  cursor: pointer;
  opacity: 0;
  transition: opacity .1s, background .1s, color .1s, border-color .1s;
  white-space: nowrap;
  display: flex;
  align-items: center;
  gap: 5px;
}
.msg-link-btn:hover { background: var(--dc-blue); border-color: var(--dc-blue); color: #fff; }
.msg-link-btn svg { width: 12px; height: 12px; }

/* Toast */
#toast {
  position: fixed;
  bottom: 24px;
  left: 50%;
  transform: translateX(-50%) translateY(8px);
  background: var(--dc-800);
  color: var(--dc-white);
  border: 1px solid var(--dc-600);
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  padding: 12px 20px;
  pointer-events: none;
  opacity: 0;
  transition: opacity .15s, transform .15s;
  z-index: 999;
  box-shadow: 0 8px 24px rgba(0,0,0,.5);
}
#toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

/* ─── ADD BUTTON ─── */
.sidebar-add {
  padding: 8px 8px 0;
  flex-shrink: 0;
}
.add-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  width: 100%;
  padding: 0 8px;
  height: 32px;
  border-radius: 4px;
  border: none;
  background: transparent;
  color: var(--dc-200);
  font-family: var(--font);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: background .08s, color .08s;
  text-align: left;
}
.add-btn:hover { background: var(--dc-500); color: var(--dc-100); }
.add-btn svg { flex-shrink: 0; }

/* ─── MODAL ─── */
.modal-backdrop {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.7);
  z-index: 200;
  align-items: center;
  justify-content: center;
}
.modal-backdrop.open { display: flex; }

.modal {
  background: var(--dc-800);
  border-radius: 8px;
  width: 560px;
  max-width: calc(100vw - 32px);
  max-height: calc(100vh - 48px);
  display: flex;
  flex-direction: column;
  box-shadow: 0 16px 48px rgba(0,0,0,.6);
  overflow: hidden;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px 0;
}
.modal-title {
  font-size: 20px;
  font-weight: 700;
  color: var(--dc-white);
}
.modal-close {
  background: none;
  border: none;
  color: var(--dc-200);
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
  display: flex;
  align-items: center;
  transition: color .1s;
}
.modal-close:hover { color: var(--dc-white); }

.modal-body { padding: 12px 20px 20px; display: flex; flex-direction: column; gap: 12px; overflow: hidden; }

.modal-hint {
  font-size: 14px;
  color: var(--dc-100);
  line-height: 1.5;
}

.modal-error {
  background: rgba(237,66,69,.15);
  border: 1px solid rgba(237,66,69,.4);
  color: #f28b8d;
  border-radius: 4px;
  font-size: 13px;
  padding: 8px 12px;
}

.modal-textarea {
  width: 100%;
  height: 320px;
  background: var(--dc-900);
  border: 1px solid var(--dc-600);
  border-radius: 4px;
  color: var(--dc-050);
  font-family: 'Consolas', 'Menlo', monospace;
  font-size: 13px;
  line-height: 1.5;
  padding: 10px 12px;
  resize: vertical;
  outline: none;
  transition: border-color .15s;
}
.modal-textarea:focus { border-color: var(--dc-blue); }
.modal-textarea::placeholder { color: var(--dc-400); }

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
.btn-cancel {
  background: none;
  border: none;
  color: var(--dc-100);
  font-family: var(--font);
  font-size: 14px;
  font-weight: 500;
  padding: 8px 16px;
  border-radius: 4px;
  cursor: pointer;
  transition: background .1s, color .1s;
}
.btn-cancel:hover { background: var(--dc-600); color: var(--dc-white); }
.btn-save {
  background: var(--dc-blue);
  border: none;
  color: #fff;
  font-family: var(--font);
  font-size: 14px;
  font-weight: 600;
  padding: 8px 20px;
  border-radius: 4px;
  cursor: pointer;
  transition: background .1s;
}
.btn-save:hover { background: #4752c4; }

/* No transcripts */
.no-transcripts { padding: 60px 32px; text-align: center; color: var(--dc-200); }
.no-transcripts h3 { font-size: 20px; font-weight: 700; color: var(--dc-100); margin-bottom: 8px; }

* { scrollbar-color: var(--dc-900) transparent; scrollbar-width: thin; }
</style>
</head>
<body>

<div id="toast">🔗 Link copied to clipboard</div>

<div class="app">

  <!-- SIDEBAR -->
  <nav class="sidebar">
    <div class="sidebar-header">Transcript Archive</div>
    <div class="sidebar-section-label">Voice Recordings</div>
    <div class="sidebar-add">
      <button class="add-btn" onclick="openModal()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Transcript
      </button>
    </div>
    <div class="channel-list">
      <?php if (empty($transcripts)): ?>
        <div style="padding:8px;font-size:13px;color:var(--dc-200)">No transcripts yet.</div>
      <?php else: ?>
        <?php foreach ($transcripts as $t): ?>
          <?php $active = ($activeMeta && $activeMeta['filename'] === $t['filename']); ?>
          <a class="ch-item <?= $active ? 'active' : '' ?>"
             href="?file=<?= urlencode($t['filename']) ?>"
             title="<?= htmlspecialchars($t['sidebarLabel']) ?>">
            <span class="ch-icon">🎙</span>
            <span class="ch-name"><?= htmlspecialchars($t['sidebarLabel']) ?></span>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </nav>

  <!-- MAIN -->
  <main class="main">

    <?php if ($activeMeta && $messages !== null): ?>

      <?php
        $membersLabel = count($speakers) . ' member' . (count($speakers) !== 1 ? 's' : '');
      ?>
      <div class="ch-header">
        <span class="ch-header-hash">#</span>
        <span class="ch-header-name"><?= htmlspecialchars($activeMeta['channelSlug']) ?></span>
        <div class="ch-header-divider"></div>
        <div class="ch-header-topic">
          <span class="ch-header-topic-text">
            <?= htmlspecialchars($activeMeta['date']) ?>
            &nbsp;·&nbsp;
            <?= htmlspecialchars($activeMeta['msgCount']) ?> messages
            <?php if ($duration): ?>
              &nbsp;·&nbsp;
              <?= htmlspecialchars($duration) ?>
            <?php endif; ?>
            &nbsp;·&nbsp;
          </span>
          <span class="members-trigger">
            <span class="trigger-label"><?= htmlspecialchars($membersLabel) ?></span>
            <div class="members-dropdown">
              <div class="members-dropdown-title">Members</div>
              <?php foreach ($speakers as $sp): ?>
                <div class="members-dropdown-item"><?= htmlspecialchars($sp) ?></div>
              <?php endforeach; ?>
            </div>
          </span>
        </div>
      </div>

      <div class="messages-scroll" id="msgScroll">
        <div class="welcome-box">
          <div class="welcome-icon">🎙</div>
          <h2># <?= htmlspecialchars($activeMeta['channelSlug']) ?></h2>
          <p>Recording from <strong><?= htmlspecialchars($activeMeta['channel']) ?></strong> on <?= htmlspecialchars($activeMeta['date']) ?>.<br>Hover any message to copy a shareable link.</p>
        </div>

        <div class="date-divider"><span><?= htmlspecialchars($activeMeta['date']) ?></span></div>

        <?php
        $speakerColors = [];
        $prevSpeaker   = null;

        foreach ($messages as $msg):
          if ($msg['type'] === 'presence'):
        ?>
          <div class="msg-presence" id="<?= $msg['id'] ?>">
            <?= htmlspecialchars($msg['speaker']) ?> joined &mdash; <?= htmlspecialchars($msg['time']) ?>
          </div>
          <?php $prevSpeaker = null; continue; ?>

        <?php else:
          if (!isset($speakerColors[$msg['speaker']])) {
            $speakerColors[$msg['speaker']] = speakerColor($msg['speaker']);
          }
          $color     = $speakerColors[$msg['speaker']];
          $isGrouped = ($msg['speaker'] === $prevSpeaker);
          $prevSpeaker = $msg['speaker'];
        ?>
          <div class="msg-group <?= $isGrouped ? '' : 'with-header' ?>" id="<?= $msg['id'] ?>">
            <div class="msg-time-gutter">
              <span class="msg-hover-time"><?= htmlspecialchars(substr($msg['time'],0,5)) ?></span>
            </div>
            <div class="msg-content">
              <?php if (!$isGrouped): ?>
                <div class="msg-header">
                  <span class="msg-author" style="color:<?= $color ?>"><?= htmlspecialchars($msg['speaker']) ?></span>
                  <span class="msg-timestamp">Today at <?= htmlspecialchars($msg['time']) ?></span>
                </div>
              <?php endif; ?>
              <div class="msg-text"><?= htmlspecialchars($msg['text']) ?></div>
            </div>
            <button class="msg-link-btn"
                    onclick="copyLink('<?= urlencode($activeMeta['filename']) ?>','<?= $msg['id'] ?>')"
                    title="Copy link to this message">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
              </svg>
              Copy Link
            </button>
          </div>

        <?php endif; endforeach; ?>

      </div>

    <?php else: ?>

      <div class="ch-header">
        <span class="ch-header-hash">#</span>
        <span class="ch-header-name">recordings</span>
      </div>

      <div class="messages-scroll">
        <?php if (empty($transcripts)): ?>
          <div class="no-transcripts">
            <h3>No transcripts found</h3>
            <p>Add <code>.md</code> transcript files to the <code>transcripts/</code> folder.</p>
          </div>
        <?php else: ?>
          <div class="welcome-box">
            <div class="welcome-icon">📋</div>
            <h2>Transcript Archive</h2>
            <p>Select a recording from the sidebar to read the transcript.<br>Hover any message and click <strong>Copy Link</strong> to share a direct link to it.</p>
          </div>
        <?php endif; ?>
      </div>

    <?php endif; ?>

  </main>
</div>

<!-- Paste modal -->
<div id="pasteModal" class="modal-backdrop" onclick="closeOnBackdrop(event)">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Add Transcript</span>
      <button class="modal-close" onclick="closeModal()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <p class="modal-hint">Paste the raw markdown from your Discord transcript bot below. The filename will be generated automatically from the date and channel.</p>
      <?php if ($uploadError): ?>
        <div class="modal-error"><?= htmlspecialchars($uploadError) ?></div>
      <?php endif; ?>
      <form method="POST" action="">
        <textarea name="markdown" class="modal-textarea" placeholder="# Recording Session&#10;&#10;## Recording Information&#10;&#10;- **Date**: March 1, 2026 at 07:30:39 PM GMT&#10;- **Channel**: Moderation VC&#10;..." spellcheck="false"></textarea>
        <div class="modal-footer">
          <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
          <button type="submit" class="btn-save">Save Transcript</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openModal() {
  document.getElementById('pasteModal').classList.add('open');
  setTimeout(() => document.querySelector('.modal-textarea').focus(), 50);
}
function closeModal() {
  document.getElementById('pasteModal').classList.remove('open');
}
function closeOnBackdrop(e) {
  if (e.target === document.getElementById('pasteModal')) closeModal();
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

<?php if ($uploadError): ?>
// Re-open modal if there was an error
window.addEventListener('DOMContentLoaded', openModal);
<?php endif; ?>

function copyLink(file, id) {
  const url = `${location.origin}${location.pathname}?file=${file}#${id}`;
  navigator.clipboard.writeText(url).catch(() => {
    const ta = document.createElement('textarea');
    ta.value = url; document.body.appendChild(ta); ta.select();
    document.execCommand('copy'); document.body.removeChild(ta);
  }).finally(showToast);
}
function showToast() {
  const el = document.getElementById('toast');
  el.classList.add('show');
  clearTimeout(el._t);
  el._t = setTimeout(() => el.classList.remove('show'), 2000);
}

window.addEventListener('DOMContentLoaded', () => {
  const hash = location.hash;
  if (!hash) return;
  const el = document.getElementById(hash.slice(1));
  if (!el) return;
  setTimeout(() => {
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    el.classList.add('highlighted');
  }, 80);
});

(function(){
  const list = document.querySelector('.channel-list');
  if (!list) return;
  const saved = sessionStorage.getItem('sbScroll');
  if (saved) list.scrollTop = +saved;
  window.addEventListener('beforeunload', () => sessionStorage.setItem('sbScroll', list.scrollTop));
})();
</script>
</body>
</html>