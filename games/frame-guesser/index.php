<?php
// Frame Guesser: the Chapter Oracle (games/chapterguesser/index.html) for TV shows.
// Same screens, styles and themes; a still frame stands in for the passage, seasons
// for books and episodes for chapters. The server keeps every answer (see lib.php).
require __DIR__ . '/lib.php';

$index = fg_index(isset($_GET['refresh']));
$series = array_map(fn ($t) => [
    'id' => $t['name'],
    'name' => $t['name'],
    'seasons' => array_map(fn ($g) => ['name' => $g['name'], 'episodes' => array_column($g['episodes'], 'name')], $t['groups']),
], $index['titles']);
$boot = ['series' => $series, 'error' => $index['error'], 'stale' => $index['stale'] ? gmdate('j M Y', $index['built']) : null, 'zooms' => count(FG_ZOOMS)];
$v = fn ($f) => $f . '?v=' . filemtime(__DIR__ . '/' . $f);
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Frame Guesser — TV Quiz</title>
<meta name="description" content="Guess the episode from a single still frame">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= htmlspecialchars($v('themes.css')) ?>">
<link rel="stylesheet" href="<?= htmlspecialchars($v('oracle.css')) ?>">
<script defer src="https://cloud.umami.is/script.js" data-website-id="60bfdde6-b1fd-4fd3-b929-b7e14e2191c7"></script>
</head>
<body>
<div class="app-wrap">

  <!-- ══════════ SERIES SELECT SCREEN ══════════ -->
  <div class="screen active" id="seriesScreen">
    <div class="wordmark">
      <div class="wordmark-eyebrow">TV Quiz</div>
      <div class="wordmark-title">Frame <em>Guesser</em></div>
      <div class="wordmark-divider"></div>
    </div>
    <div class="card">
      <div class="menu-title">Choose a show</div>
      <p class="menu-sub">Pick the show you want to be quizzed on.</p>
      <div class="series-grid" id="seriesGrid"></div>
      <div class="menu-status" id="seriesStatus">Finding shows…</div>
      <div class="go-btns" id="seriesRetry" hidden>
        <button class="btn btn-ghost" onclick="location.reload()">Try Again</button>
      </div>
    </div>
    <p class="info-link"><a class="btn-link" href="info/">How to play &amp; what's new</a></p>
    <p class="info-link"><a class="btn-link" href="/games/chapterguesser/">📖 Try Chapter Oracle: name the book chapter from one passage →</a></p>
  </div>

  <!-- ══════════ MODE SELECT SCREEN ══════════ -->
  <div class="screen" id="menuScreen">
    <div class="wordmark">
      <div class="wordmark-eyebrow" data-series-name>&nbsp;</div>
      <div class="wordmark-title">Frame <em>Guesser</em></div>
      <div class="wordmark-divider"></div>
    </div>
    <div class="card">
      <button class="btn-link card-back" onclick="goToSeries()">← Change show</button>
      <div class="menu-title">Choose your challenge</div>
      <p class="menu-sub" id="menuSub">How well do you know the show?</p>
      <div class="mode-grid">
        <button class="mode-card" onclick="startGame('normal')">
          <span class="mode-symbol">✦</span>
          <span class="mode-name">Normal</span>
          <span class="mode-desc" id="desc-normal">Name the season, then the episode. One wrong season and it's over.</span>
          <span class="mode-best">Best <b id="best-normal">0</b></span>
        </button>
        <button class="mode-card" onclick="startGame('timed')">
          <span class="mode-symbol">◷</span>
          <span class="mode-name">Timed</span>
          <span class="mode-desc">60 seconds on the clock. Wrong answers don't end the run — they just don't score.</span>
          <span class="mode-best">Best <b id="best-timed">0</b></span>
        </button>
        <button class="mode-card" onclick="startGame('minimum')">
          <span class="mode-symbol">⌕</span>
          <span class="mode-name">Minimum Context</span>
          <span class="mode-desc">Start with a tiny piece of the frame and zoom out one step at a time. Less of the picture, more points.</span>
          <span class="mode-best">Best <b id="best-minimum">0</b></span>
        </button>
        <button class="mode-card" onclick="openSeasonPicker()">
          <span class="mode-symbol">▤</span>
          <span class="mode-name">Specific Seasons</span>
          <span class="mode-desc">Practise on only the seasons you choose. Same rules as Normal.</span>
          <span class="mode-best">Best <b id="best-specific">0</b></span>
        </button>
      </div>
      <div class="player-row">
        <label class="player-label" for="playerName">Leaderboard name</label>
        <input class="name-input" id="playerName" maxlength="20" autocomplete="nickname"
               placeholder="Enter a name to join" oninput="setPlayerName(this.value)">
        <button class="btn btn-ghost btn-sm" onclick="openLeaderboard('normal')">🏆 Leaderboards</button>
      </div>
    </div>
  </div>

  <!-- ══════════ LEADERBOARD SCREEN ══════════ -->
  <div class="screen" id="boardScreen">
    <div class="wordmark">
      <div class="wordmark-eyebrow" data-series-name>&nbsp;</div>
      <div class="wordmark-title">Frame <em>Guesser</em></div>
      <div class="wordmark-divider"></div>
    </div>
    <div class="card">
      <button class="btn-link card-back" onclick="goToMenu()">← Modes</button>
      <div class="menu-title">Leaderboards</div>
      <p class="menu-sub" id="boardSub">Shared by everyone who plays</p>
      <div class="board-tabs" id="boardTabs"></div>
      <ol class="board-list" id="boardList"></ol>
      <div class="menu-status" id="boardStatus"></div>
    </div>
  </div>

  <!-- ══════════ REPLAY SCREEN (one leaderboard run, frame by frame) ══════════ -->
  <div class="screen" id="replayScreen">
    <div class="wordmark">
      <div class="wordmark-eyebrow" data-series-name>&nbsp;</div>
      <div class="wordmark-title">Frame <em>Guesser</em></div>
      <div class="wordmark-divider"></div>
    </div>
    <div class="card">
      <button class="btn-link card-back" onclick="backToBoard()">← Leaderboard</button>
      <div class="menu-title" id="replayTitle">Replay</div>
      <p class="menu-sub" id="replaySub"></p>
      <div class="rp-summary" id="replaySummary"></div>
      <ol class="rp-list" id="replayList"></ol>
      <div class="menu-status" id="replayStatus"></div>
    </div>
  </div>

  <!-- ══════════ SEASON PICKER SCREEN (specific seasons mode) ══════════ -->
  <div class="screen" id="pickScreen">
    <div class="wordmark">
      <div class="wordmark-eyebrow" data-series-name>&nbsp;</div>
      <div class="wordmark-title">Frame <em>Guesser</em></div>
      <div class="wordmark-divider"></div>
    </div>
    <div class="card">
      <button class="btn-link card-back" onclick="goToMenu()">← Modes</button>
      <div class="menu-title">Choose your seasons</div>
      <p class="menu-sub">Frames come only from the seasons you tick.</p>
      <div class="pick-toolbar">
        <button class="btn-link" onclick="setPicked(true)">Select all</button>
        <button class="btn-link" onclick="setPicked(false)">Clear all</button>
      </div>
      <div class="pick-list" id="pickList"></div>
      <div class="go-btns">
        <button class="btn btn-primary" id="pickStartBtn" onclick="startGame('specific')">Start</button>
      </div>
    </div>
  </div>

  <!-- ══════════ LOADING SCREEN ══════════ -->
  <div class="screen" id="loadingScreen">
    <div class="wordmark">
      <div class="wordmark-eyebrow" data-series-name>&nbsp;</div>
      <div class="wordmark-title">Frame <em>Guesser</em></div>
      <div class="wordmark-divider"></div>
    </div>
    <div class="card">
      <div class="loading-inner">
        <div class="spinner-wrap"><div class="spinner-ring"></div></div>
        <div class="loading-title">Rolling the film…</div>
        <div class="loading-book" id="loadingBook">Picking a frame</div>
        <div class="progress-track">
          <div class="progress-fill" id="progressFill" style="width:0%"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════════ ERROR SCREEN ══════════ -->
  <div class="screen" id="errorScreen">
    <div class="wordmark">
      <div class="wordmark-eyebrow" data-series-name>&nbsp;</div>
      <div class="wordmark-title">Frame <em>Guesser</em></div>
      <div class="wordmark-divider"></div>
    </div>
    <div class="card">
      <div class="error-card">
        <div class="error-icon">🎞️</div>
        <div class="error-title">Frames not found</div>
        <div class="error-msg" id="errorMsg">Could not load the frames.</div>
        <div class="go-btns">
          <button class="btn btn-primary" onclick="startGame(mode)">Try Again</button>
          <button class="btn btn-ghost" onclick="goToMenu()">Change Mode</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════════ QUIZ SCREEN ══════════ -->
  <div class="screen" id="quizScreen">
    <div class="quiz-topbar">
      <button class="btn-link" onclick="goToMenu()">← Modes</button>
      <span class="mode-tag" id="modeTag">Normal</span>
    </div>

    <div class="quiz-header">
      <div class="score-wrap">
        <span class="score-num" id="scoreDisplay">0</span>
        <span class="score-lbl">Score</span>
      </div>
      <div class="timer-wrap" id="timerWrap" hidden>
        <span class="timer-num" id="timerDisplay">1:00</span>
        <span class="timer-lbl">Time left</span>
      </div>
      <div class="hs-wrap">
        <span class="hs-num" id="hsDisplay">0</span>
        <span class="hs-lbl">Best</span>
      </div>
    </div>

    <div class="passage-label">Frame</div>
    <div class="frame-card" id="frameCard">
      <img id="frameImg" alt="A still frame from the show" width="960" height="540">
      <div class="spinner-ring" id="frameSpinner" hidden></div>
      <div class="frame-msg" id="frameMsg" hidden>
        <span>Couldn't load this frame.</span>
        <button class="btn btn-ghost btn-sm" onclick="retryFrame()">Try again</button>
      </div>
    </div>

    <div class="context-bar" id="contextBar" hidden>
      <button class="btn btn-ghost btn-sm" id="zoomBtn" onclick="zoomOut()">− Zoom out</button>
      <span class="context-info" id="contextInfo"></span>
    </div>

    <div class="stage-label" id="stageLabel">
      <span class="stage-badge" id="stageBadge">Step 1</span>
      <span id="stageText">Which season is this frame from?</span>
    </div>

    <div id="choicesArea"></div>
    <div class="feedback-bar" id="feedbackBar"></div>
  </div>

  <!-- ══════════ GAME OVER SCREEN ══════════ -->
  <div class="screen" id="gameoverScreen">
    <div class="wordmark">
      <div class="wordmark-eyebrow" data-series-name>&nbsp;</div>
      <div class="wordmark-title">Frame <em>Guesser</em></div>
      <div class="wordmark-divider"></div>
    </div>
    <div class="card">
      <div class="go-inner">
        <div class="go-eyebrow" id="goEyebrow">Game Over</div>
        <span class="go-score" id="goScore">0</span>
        <div class="go-score-lbl" id="goScoreLbl">episodes correctly identified</div>
        <div class="go-record" id="goRecord" style="display:none">✦ New High Score</div>
        <div class="go-context" id="goContext"></div>
        <div class="lb-panel" id="lbPanel"></div>
        <div class="go-sep"></div>
        <div class="go-btns">
          <button class="btn btn-primary" onclick="startGame(mode)">Play Again</button>
          <button class="btn btn-ghost" onclick="openLeaderboard(mode)">Leaderboard</button>
          <button class="btn btn-ghost" onclick="goToMenu()">Change Mode</button>
        </div>
      </div>
    </div>
  </div>

</div><!-- /.app-wrap -->

<script>window.FG = <?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;</script>
<script src="<?= htmlspecialchars($v('frame-guesser.js')) ?>"></script>
</body>
</html>
