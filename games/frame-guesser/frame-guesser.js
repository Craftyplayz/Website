// Frame Guesser: the Chapter Oracle's game script with frames for passages,
// seasons for books and episodes for chapters. The server picks every frame,
// keeps the answers and scores each guess (api.php); this file only shows
// what it sends. Functions are global for the onclick attributes, as in the Oracle.

// ═══════════════════════════════════════════════════════
// CONSTANTS
// ═══════════════════════════════════════════════════════
const MODES = {
  normal:   { title: 'Normal' },
  timed:    { title: 'Timed' },
  minimum:  { title: 'Minimum Context' },
  specific: { title: 'Specific Seasons' }
};

const SERIES_KEY = 'frameGuesser_series';
const NAME_KEY   = 'frameGuesser_name';

const COUNT_WORDS = ['No', 'One', 'Two', 'Three', 'Four', 'Five', 'Six',
                     'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve'];

const TIMED_SECONDS = 60;
const ZOOM_STEPS    = window.FG.zooms; // minimum context: a right episode scores ZOOM_STEPS at the first step, 1 at the full frame

// Feedback pauses in ms. Timed mode keeps them short because the clock keeps running.
const DELAYS = {
  normal:  { bookCorrect: 900, bookWrong: 1800, chapterCorrect: 1200, chapterWrong: 2200 },
  timed:   { bookCorrect: 400, bookWrong: 1100, chapterCorrect: 500,  chapterWrong: 1100 },
  minimum: { bookCorrect: 900, bookWrong: 2400, chapterCorrect: 2000, chapterWrong: 2600 }
};
DELAYS.specific = DELAYS.normal;

// ═══════════════════════════════════════════════════════
// STATE
// ═══════════════════════════════════════════════════════
const seriesList  = window.FG.series; // [{id, name, seasons:[{name, episodes:[names]}]}]; a film series has one season named null
let series        = null;  // selected entry of seriesList
let picked        = new Set(); // specific seasons mode: ticked season indices

let playerName    = storageGet(NAME_KEY) || '';
let lastRun       = null;  // {series, mode, score, seasons} of the last finished run
let lbState       = { kind: 'idle' };
let boardMode     = 'normal';
let boardError    = '';
let boardLoading  = false;
let boardRequest  = 0;
const boardCache  = {};    // "series|mode" → last entries fetched

let state         = null;  // the server's view of the run (never holds the current answer)
let score         = 0;
let mode          = 'normal';
let quizStage     = 'book'; // 'book' (season) | 'chapter' (episode)
let pickedSeason  = null;  // index of the season answered right at step 1
let answerLocked  = false;
let busy          = false; // a guess or zoom is on its way to the server
let timeUpPending = false; // the clock ran out while a guess was on its way

let gameId        = 0;     // bumped on every start/end so stale timeouts do nothing
let timerInterval = null;
let deadline      = 0;
let attempted     = 0;
let correctCount  = 0;

// ═══════════════════════════════════════════════════════
// SCREEN MANAGEMENT
// ═══════════════════════════════════════════════════════
function showScreen(id) {
  document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
  const el = document.getElementById(id);
  void el.offsetHeight;
  el.classList.add('active');
  syncHistory(id);
}

// ── Browser back button ────────────────────────────────
// Inside the game the page keeps one extra history entry, so the browser's
// back button does the screen's own back step instead of leaving the page.
const BACK_STEPS = {
  menuScreen:     () => goToSeries(),
  replayScreen:   () => backToBoard(),
  pickScreen:     () => goToMenu(),
  boardScreen:    () => goToMenu(),
  loadingScreen:  () => goToMenu(),
  errorScreen:    () => goToMenu(),
  quizScreen:     () => goToMenu(),
  gameoverScreen: () => goToMenu()
};

function syncHistory(id) {
  const armed = history.state?.frameGuesser;
  if (id === 'seriesScreen') {
    if (armed) history.back();
  } else if (!armed) {
    history.pushState({ frameGuesser: true }, '');
  }
}

window.addEventListener('popstate', () => {
  const current = document.querySelector('.screen.active')?.id;
  BACK_STEPS[current]?.();
});

// Esc steps back too, like the back button
document.addEventListener('keydown', ev => {
  if (ev.key !== 'Escape' || ev.defaultPrevented) return;
  const current = document.querySelector('.screen.active')?.id;
  BACK_STEPS[current]?.();
});

const isFilms   = s => s.seasons.length === 1 && s.seasons[0].name === null;
const plural    = (n, word) => `${n} ${word}${n === 1 ? '' : 's'}`;
const seasonName = (s, i) => s.seasons[i].name ?? s.name;

// Show list, rendered from the index the page was sent with
function renderSeriesScreen() {
  const grid   = document.getElementById('seriesGrid');
  const status = document.getElementById('seriesStatus');
  grid.innerHTML = '';
  status.classList.remove('error');
  document.getElementById('seriesRetry').hidden = true;

  if (window.FG.error) {
    status.textContent = "Could not reach the frame library. Try again in a few minutes.";
    status.classList.add('error');
    document.getElementById('seriesRetry').hidden = false;
    return;
  }
  if (!seriesList.length) {
    status.textContent = 'No shows found yet. Each show needs its own folder of frames, such as Show/Season 1/Episode 1/.';
    return;
  }

  status.textContent = window.FG.stale ? `Showing the list of shows from ${window.FG.stale}; the frame library couldn't be reached to update it.` : '';
  for (const s of seriesList) {
    const btn = document.createElement('button');
    btn.className = 'mode-card series-card';
    const name = document.createElement('span');
    name.className   = 'mode-name';
    name.textContent = s.name;
    const count = document.createElement('span');
    count.className   = 'mode-best';
    const eps = s.seasons.reduce((n, g) => n + g.episodes.length, 0);
    count.textContent = isFilms(s) ? plural(eps, 'film') : `${plural(s.seasons.length, 'season')} · ${plural(eps, 'episode')}`;
    btn.appendChild(name);
    btn.appendChild(count);
    btn.addEventListener('click', () => selectSeries(s.id));
    grid.appendChild(btn);
  }
}

// Mode menu for the selected show
function renderMenu() {
  for (const id of Object.keys(MODES)) {
    document.getElementById(`best-${id}`).textContent = getBest(series.id, id);
  }
  document.querySelectorAll('[data-series-name]').forEach(el => el.textContent = series.name);
  document.getElementById('playerName').value = playerName;
  const count = isFilms(series) ? series.seasons[0].episodes.length : series.seasons.length;
  const word  = isFilms(series) ? 'film' : 'season';
  document.getElementById('menuSub').textContent =
    `${COUNT_WORDS[count] ?? count} ${word}${count === 1 ? '' : 's'}. Countless frames. How well do you know the show?`;
  document.getElementById('desc-normal').textContent = series.seasons.length > 1
    ? "Name the season, then the episode. One wrong season and it's over."
    : `Name the ${isFilms(series) ? 'film' : 'episode'}. One wrong guess and it's over.`;
}

function goToMenu() {
  gameId++;
  stopTimer();
  renderMenu();
  showScreen('menuScreen');
}

function goToSeries() {
  gameId++;
  stopTimer();
  applyTheme(null);
  renderSeriesScreen();
  showScreen('seriesScreen');
}

// "Game of Thrones" → "gameofthrones"
function seriesSlug(name) { return String(name).toLowerCase().replace(/[^a-z0-9]/g, ''); }

// themes.css picks the colours from the show's slug on <html>, and fades between them.
// A show without its own theme there gets one of the shared themes instead.
function applyTheme(s) {
  const root = document.documentElement;
  if (!s) {
    delete root.dataset.series;
    delete root.dataset.theme;
    return;
  }
  const slug = seriesSlug(s.id);
  root.dataset.series = slug;
  const { series: themed, shared } = readThemes();
  if (themed.has(slug) || !shared.length) delete root.dataset.theme;
  else root.dataset.theme = shared[slugHash('theme16:' + slug) % shared.length];
}

// Which slugs have a series theme, and the shared theme names in file order,
// read from the selectors in themes.css
let themeInfo = null;
function readThemes() {
  if (themeInfo) return themeInfo;
  const series = new Set(), shared = [];
  for (const sheet of document.styleSheets || []) {
    let rules;
    try { rules = sheet.cssRules; } catch { continue; } // other sites' sheets (fonts) can't be read
    for (const rule of rules) {
      const sel = rule.selectorText || '';
      for (const m of sel.matchAll(/\[data-series="([^"]+)"\]/g)) series.add(m[1]);
      for (const m of sel.matchAll(/\[data-theme="([^"]+)"\]/g)) if (!shared.includes(m[1])) shared.push(m[1]);
    }
  }
  const info = { series, shared };
  if (shared.length || series.size) themeInfo = info;
  return info;
}

// FNV-1a: the same text always gives the same number (same as the Oracle's)
function slugHash(slug) {
  let h = 0x811c9dc5;
  for (const ch of slug) h = Math.imul(h ^ ch.codePointAt(0), 0x01000193);
  return h >>> 0;
}

function selectSeries(id) {
  const next = seriesList.find(s => s.id === id);
  if (!next) return;
  series = next;
  storageSet(SERIES_KEY, id);
  applyTheme(series);
  renderMenu();
  showScreen('menuScreen');
}

// ═══════════════════════════════════════════════════════
// SPECIFIC SEASONS
// ═══════════════════════════════════════════════════════
// Remembered per show by name, so adding a season doesn't shift the choice
function pickKey() { return `frameGuesser_pick:${series.id}`; }

function loadPicked() {
  try {
    const saved = JSON.parse(storageGet(pickKey()));
    if (Array.isArray(saved)) {
      return new Set(series.seasons.map((g, i) => saved.includes(g.name) ? i : -1).filter(i => i >= 0));
    }
  } catch { /* nothing usable saved */ }
  return new Set(series.seasons.map((_, i) => i));
}

function savePicked() {
  storageSet(pickKey(), JSON.stringify([...picked].map(i => series.seasons[i].name)));
}

function openSeasonPicker() {
  gameId++;
  picked = loadPicked();
  renderPicker();
  showScreen('pickScreen');
}

function renderPicker() {
  const list = document.getElementById('pickList');
  list.innerHTML = '';
  series.seasons.forEach((season, i) => {
    const item = document.createElement('label');
    item.className = 'pick-item' + (picked.has(i) ? ' checked' : '');
    const box = document.createElement('input');
    box.type    = 'checkbox';
    box.checked = picked.has(i);
    box.addEventListener('change', () => togglePicked(i, box.checked));
    const text = document.createElement('span');
    text.innerHTML = `<span class="book-num">${plural(season.episodes.length, isFilms(series) ? 'film' : 'episode')}</span>${escHtml(seasonName(series, i))}`;
    item.appendChild(box);
    item.appendChild(text);
    list.appendChild(item);
  });
  document.getElementById('pickStartBtn').disabled = picked.size === 0;
}

function togglePicked(i, on) {
  if (on) picked.add(i); else picked.delete(i);
  savePicked();
  renderPicker();
}

function setPicked(all) {
  picked = new Set(all ? series.seasons.map((_, i) => i) : []);
  savePicked();
  renderPicker();
}

// ═══════════════════════════════════════════════════════
// SERVER
// ═══════════════════════════════════════════════════════
async function api(body) {
  let resp;
  try {
    resp = await fetch('api.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
  } catch {
    throw new Error('Could not reach the game server. Check your connection.');
  }
  let data = null;
  try { data = await resp.json(); } catch { /* not JSON */ }
  if (!resp.ok || !data) throw new Error(data?.error || `The game server returned HTTP ${resp.status}.`);
  return data;
}

// ═══════════════════════════════════════════════════════
// FRAMES
// ═══════════════════════════════════════════════════════
let frameUrl = '';

// Shows a frame once it has loaded, so it never draws in halfway
function setFrame(url, animate) {
  const img = document.getElementById('frameImg');
  const card = document.getElementById('frameCard');
  const spinner = document.getElementById('frameSpinner');
  frameUrl = url;
  document.getElementById('frameMsg').hidden = true;
  img.classList.add('loading');
  const slow = setTimeout(() => { if (frameUrl === url) spinner.hidden = false; }, 200);
  const done = ok => {
    clearTimeout(slow);
    if (frameUrl !== url) return;
    spinner.hidden = true;
    if (!ok) { document.getElementById('frameMsg').hidden = false; return; }
    img.classList.remove('loading');
    if (animate) {
      card.classList.remove('animate');
      void card.offsetHeight;
      card.classList.add('animate');
    }
  };
  img.onload  = () => done(true);
  img.onerror = () => done(false);
  img.src = url;
}

function retryFrame() { setFrame(`${frameUrl.split('&r=')[0]}&r=${Date.now()}`, false); }

// Resolves once the image is in the browser cache (or failed), for the loading screen and preloading
function preload(url) {
  return new Promise(resolve => {
    const img = new Image();
    img.onload = img.onerror = () => resolve();
    img.src = url;
  });
}

// ═══════════════════════════════════════════════════════
// GAME START
// ═══════════════════════════════════════════════════════
async function startGame(selectedMode) {
  mode = selectedMode;
  const id = ++gameId;
  stopTimer();
  if (mode === 'specific' && picked.size === 0) { openSeasonPicker(); return; }

  showScreen('loadingScreen');
  setProgress(10);
  setLoadingBook('Picking a frame');
  let st;
  try {
    const body = { action: 'start', mode, title: series.id };
    if (mode === 'specific') body.seasons = [...picked].sort((a, b) => a - b).map(i => series.seasons[i].name);
    st = await api(body);
  } catch (err) {
    if (id === gameId) showLoadError(err.message);
    return;
  }
  const started = performance.now();
  if (id !== gameId) return;
  setProgress(55);
  setLoadingBook('Developing the film');
  await preload(st.frame);
  if (id !== gameId) return;
  setProgress(100);
  await sleep(250);
  if (id !== gameId) return;

  score         = 0;
  attempted     = 0;
  correctCount  = 0;
  busy          = false;
  timeUpPending = false;

  document.getElementById('modeTag').textContent = `${series.name} · ${MODES[mode].title}`;
  document.getElementById('timerWrap').hidden    = mode !== 'timed';
  document.getElementById('contextBar').hidden   = mode !== 'minimum';
  updateScoreUI();
  showScreen('quizScreen');

  if (mode === 'timed') startTimer(started + st.timeLeft);
  nextQuestion(st);
}

function showLoadError(message) {
  document.getElementById('errorMsg').textContent = message;
  showScreen('errorScreen');
}

// Runs fn after ms, unless the game has since ended or restarted
function later(fn, ms) {
  const id = gameId;
  setTimeout(() => { if (id === gameId) fn(); }, ms);
}

// ═══════════════════════════════════════════════════════
// TIMER (timed mode)
// ═══════════════════════════════════════════════════════
function startTimer(end) {
  deadline = end;
  updateTimer();
  timerInterval = setInterval(updateTimer, 100);
}

function stopTimer() {
  clearInterval(timerInterval);
  timerInterval = null;
}

function updateTimer() {
  const remaining = Math.max(0, deadline - performance.now());
  const secs = Math.ceil(remaining / 1000);
  document.getElementById('timerDisplay').textContent =
    `${Math.floor(secs / 60)}:${String(secs % 60).padStart(2, '0')}`;
  document.getElementById('timerWrap').classList.toggle('low', secs <= 10);
  if (remaining <= 0) {
    stopTimer();
    answerLocked = true;
    if (busy) timeUpPending = true; // the answer on its way finishes the run
    else finishTimed();
  }
}

async function finishTimed() {
  const id = gameId;
  try {
    const st = await api({ action: 'finish' });
    if (id === gameId) triggerGameOver(st);
  } catch (err) {
    if (id === gameId) showLoadError(err.message);
  }
}

// ═══════════════════════════════════════════════════════
// MINIMUM CONTEXT
// ═══════════════════════════════════════════════════════
function contextPoints() { return ZOOM_STEPS - (state.zoom ?? 0); }

function updateContextBar() {
  if (mode !== 'minimum' || !state) return;
  const btn = document.getElementById('zoomBtn');
  btn.disabled = answerLocked || busy || state.zoom >= ZOOM_STEPS - 1;
  const pts = contextPoints();
  document.getElementById('contextInfo').textContent =
    `Zoom ${state.zoom + 1} of ${ZOOM_STEPS} · correct episode worth ${plural(pts, 'point')}`;
}

async function zoomOut() {
  if (mode !== 'minimum' || answerLocked || busy || state.zoom >= ZOOM_STEPS - 1) return;
  const id = gameId;
  busy = true;
  updateContextBar();
  try {
    const st = await api({ action: 'zoom' });
    if (id !== gameId) return;
    if (st.timeUp) { triggerGameOver(st.state); return; }
    state = st;
    setFrame(st.frame, false);
  } catch (err) {
    if (id === gameId) setFeedback(err.message, 'fb-wrong');
  } finally {
    busy = false;
    if (id === gameId) updateContextBar();
  }
}

// ═══════════════════════════════════════════════════════
// QUIZ GAME
// ═══════════════════════════════════════════════════════
function nextQuestion(st) {
  state        = st;
  answerLocked = false;
  pickedSeason = null;
  setFrame(st.frame, true);
  preload(st.next);
  setFeedback('', '');
  updateContextBar();

  // A show with one season (or a film series) has nothing to ask at the season step
  if (st.stage === 'season') renderBookStage();
  else renderChapterStage();
}

function isSingleBook() { return state.seasons.length === 1; }
function seasonIndex(name) { return series.seasons.findIndex(g => g.name === name); }

function renderBookStage() {
  quizStage = 'book';
  document.getElementById('stageBadge').textContent = 'Step 1 of 2';
  document.getElementById('stageText').textContent  = `Which season of ${series.name} is this from?`;

  const area = document.getElementById('choicesArea');
  area.innerHTML = '';
  const grid = document.createElement('div');
  grid.className = 'book-grid';

  for (const name of state.seasons) {
    const i = seasonIndex(name);
    const btn = document.createElement('button');
    btn.className = 'book-btn';
    btn.dataset.season = name;
    btn.innerHTML = `<span class="book-num">${plural(series.seasons[i].episodes.length, 'episode')}</span>${escHtml(name)}`;
    btn.addEventListener('click', () => handleBookAnswer(btn, i));
    grid.appendChild(btn);
  }
  area.appendChild(grid);
}

// Sends a guess; on a network error the buttons come back so the player can try again
async function send(body, buttons) {
  const id = gameId;
  busy = true;
  try {
    return await api(body);
  } catch (err) {
    if (id === gameId) {
      answerLocked = false;
      buttons.forEach(b => b.disabled = false);
      setFeedback(err.message, 'fb-wrong');
      updateContextBar();
    }
    return null;
  } finally {
    busy = false;
  }
}

// After an answer: a run that ended or ran out of time goes to game over
function settle(data, then, ms) {
  if (data.timeUp || timeUpPending) {
    if (data.state.over) later(() => triggerGameOver(data.state), Math.min(ms, 900));
    else later(finishTimed, Math.min(ms, 900));
    return;
  }
  if (data.state.over) later(() => triggerGameOver(data.state), ms);
  else later(then, ms);
}

async function handleBookAnswer(btn, chosenIndex) {
  if (answerLocked) return;
  answerLocked = true;
  updateContextBar();

  const allBtns = [...document.querySelectorAll('.book-btn')];
  allBtns.forEach(b => b.disabled = true);
  const id = gameId;
  const data = await send({ action: 'season', season: series.seasons[chosenIndex].name }, allBtns);
  if (!data || id !== gameId) return;
  if (data.timeUp) { triggerGameOver(data.state); return; }

  const delays = DELAYS[mode];
  if (data.correct) {
    pickedSeason = chosenIndex;
    state = data.state;
    btn.classList.add('state-correct');
    setFeedback('✓ Correct — now identify the episode', 'fb-info');
    if (timeUpPending) { later(finishTimed, 400); return; }
    later(() => {
      setFeedback('', '');
      renderChapterStage();
    }, delays.bookCorrect);
    return;
  }

  btn.classList.add('state-wrong');
  allBtns.forEach(b => { if (b.dataset.season === data.answer.season) b.classList.add('state-reveal'); });
  if (mode === 'minimum') setFrame(data.revealed, false);
  if (mode === 'timed') {
    attempted++;
    setFeedback(`✗ It was ${data.answer.season} — no point`, 'fb-wrong');
  } else {
    setFeedback(`✗ Wrong — it was from ${data.answer.season}`, 'fb-wrong');
  }
  settle(data, () => nextQuestion(data.state), delays.bookWrong);
}

function renderChapterStage() {
  quizStage    = 'chapter';
  answerLocked = false;
  updateContextBar();

  const films = isFilms(series);
  document.getElementById('stageBadge').textContent = isSingleBook() ? 'Step 1 of 1' : 'Step 2 of 2';
  document.getElementById('stageText').textContent  = films ? 'Which film is this from?' : 'Now pick the exact episode:';

  const season = pickedSeason ?? seasonIndex(state.seasons[0]);
  const area = document.getElementById('choicesArea');
  area.innerHTML = '';

  const scrollWrap = document.createElement('div');
  scrollWrap.className = 'chapter-scroll-wrap';
  const grid = document.createElement('div');
  grid.className = 'chapter-grid';

  series.seasons[season].episodes.forEach((title, i) => {
    const btn = document.createElement('button');
    btn.className = 'chapter-btn';
    btn.style.animationDelay = Math.min(i * 18, 300) + 'ms';
    btn.textContent = title;
    btn.addEventListener('click', () => handleChapterAnswer(btn, title));
    grid.appendChild(btn);
  });

  scrollWrap.appendChild(grid);
  area.appendChild(scrollWrap);
}

async function handleChapterAnswer(btn, chosenTitle) {
  if (answerLocked) return;
  answerLocked = true;
  updateContextBar();

  const allBtns = [...document.querySelectorAll('.chapter-btn')];
  allBtns.forEach(b => b.disabled = true);
  const id = gameId;
  const zoomed = (state.zoom ?? 0) + 1;
  const data = await send({ action: 'episode', episode: chosenTitle }, allBtns);
  if (!data || id !== gameId) return;
  if (data.timeUp) { triggerGameOver(data.state); return; }
  attempted++;

  const delays = DELAYS[mode];
  if (mode === 'minimum') setFrame(data.revealed, false);
  if (data.correct) {
    btn.classList.add('state-correct');
    score = data.state.score;
    correctCount++;
    updateScoreUI();
    setFeedback(
      mode === 'minimum'
        ? `✦ Perfect — ${plural(data.points, 'point')} at zoom ${zoomed} of ${ZOOM_STEPS}!`
        : '✦ Perfect — 1 point!',
      'fb-correct'
    );
    settle(data, () => nextQuestion(data.state), delays.chapterCorrect);
  } else {
    btn.classList.add('state-wrong');
    allBtns.forEach(b => {
      if (b.textContent.trim() === data.answer.episode) {
        b.classList.add('state-reveal');
        b.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      }
    });
    setFeedback(
      data.state.over
        ? `✗ Wrong — it was "${data.answer.episode}"`
        : `✗ Not quite — it was "${data.answer.episode}". No point this round.`,
      data.state.over ? 'fb-wrong' : 'fb-partial'
    );
    settle(data, () => nextQuestion(data.state), delays.chapterWrong);
  }
}

// ═══════════════════════════════════════════════════════
// GAME OVER
// ═══════════════════════════════════════════════════════
// st.end: 'wrong-season' | 'wrong-episode' | 'time'
function triggerGameOver(st) {
  gameId++; // cancel any pending feedback timeouts
  stopTimer();
  answerLocked = true;
  state = st;
  score = st.score;

  const isRecord = score > getBest(series.id, mode);
  if (isRecord) setBest(series.id, mode, score);

  document.getElementById('goEyebrow').textContent = st.end === 'time' ? "Time's Up" : 'Game Over';
  document.getElementById('goScore').textContent    = score;
  document.getElementById('goScoreLbl').textContent =
    mode === 'minimum' ? 'points'
    : mode === 'timed' ? `episodes correctly identified in ${TIMED_SECONDS} seconds`
    : 'episodes correctly identified';
  document.getElementById('goRecord').style.display = isRecord ? 'inline-flex' : 'none';

  // Context: show what they got wrong
  const ctx  = document.getElementById('goContext');
  const last = st.rounds[st.rounds.length - 1];
  ctx.style.borderLeftColor = st.end === 'time' ? 'var(--gold)' : '';
  if (st.end === 'time') {
    ctx.innerHTML = attempted
      ? `You answered <strong>${attempted}</strong> frame${attempted === 1 ? '' : 's'} and named
         <strong>${correctCount}</strong> episode${correctCount === 1 ? '' : 's'} correctly.`
      : 'The clock ran out before you answered a frame.';
  } else if (last) {
    ctx.innerHTML = isFilms(series)
      ? `The frame was from <strong>${escHtml(last.episode)}</strong>.`
      : `The frame was from <strong>${escHtml(last.season)}</strong>, <strong>${escHtml(last.episode)}</strong>.`;
  } else {
    ctx.innerHTML = '';
  }

  updateScoreUI();
  lastRun = { series, mode, score };
  submitLastRun();
  showScreen('gameoverScreen');
}

// ═══════════════════════════════════════════════════════
// UI HELPERS
// ═══════════════════════════════════════════════════════
function updateScoreUI() {
  document.getElementById('scoreDisplay').textContent = score;
  document.getElementById('hsDisplay').textContent    = getBest(series.id, mode);
}

// ═══════════════════════════════════════════════════════
// LEADERBOARDS (shared on the server via leaderboard.php, one per show and mode)
// ═══════════════════════════════════════════════════════
function boardKey(seriesId, m) { return `${seriesId}|${m}`; }

function cleanName(raw) { return String(raw).replace(/\s+/g, ' ').trim(); }

function nameProblem(name) {
  if (!name) return 'Enter a name first.';
  if ([...name].length > 20) return 'Names can be at most 20 characters.';
  if (/\p{C}/u.test(name)) return 'Names can’t contain control characters.';
  return '';
}

function setPlayerName(raw) {
  playerName = cleanName(raw);
  storageSet(NAME_KEY, playerName);
}

// GET with params, or POST body; resolves with the JSON reply, rejects with a readable message
async function leaderboardRequest(params, body) {
  const url = 'leaderboard.php' + (params ? '?' + new URLSearchParams(params) : '');
  let resp;
  try {
    resp = await fetch(url, body
      ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }
      : { cache: 'no-store' });
  } catch {
    throw new Error('Could not reach the leaderboard. Check your connection.');
  }
  let data = null;
  try { data = await resp.json(); } catch { /* not JSON */ }
  if (!resp.ok || !data) throw new Error(data?.error || `The leaderboard returned HTTP ${resp.status}.`);
  return data;
}

function openLeaderboard(m) {
  gameId++;
  stopTimer();
  boardMode = m;
  renderBoard();
  showScreen('boardScreen');
  refreshBoard();
}

function showBoardMode(m) {
  boardMode = m;
  renderBoard();
  refreshBoard();
}

async function refreshBoard() {
  const key = boardKey(series.id, boardMode);
  const req = ++boardRequest;
  boardLoading = true;
  boardError   = '';
  renderBoard();
  try {
    const data = await leaderboardRequest({ series: series.id, mode: boardMode });
    if (req !== boardRequest) return;
    boardCache[key] = data.entries;
  } catch (err) {
    if (req !== boardRequest) return;
    boardError = err.message;
  }
  boardLoading = false;
  renderBoard();
}

function renderBoard() {
  const tabs = document.getElementById('boardTabs');
  tabs.innerHTML = '';
  for (const [id, info] of Object.entries(MODES)) {
    const tab = document.createElement('button');
    tab.className   = 'board-tab' + (id === boardMode ? ' active' : '');
    tab.textContent = info.title;
    tab.addEventListener('click', () => showBoardMode(id));
    tabs.appendChild(tab);
  }
  document.getElementById('boardSub').textContent =
    `${series.name} · ${MODES[boardMode].title} · shared by everyone who plays`;

  const entries = boardCache[boardKey(series.id, boardMode)];
  const list = document.getElementById('boardList');
  list.innerHTML = '';
  (entries || []).forEach(entry => list.appendChild(boardRow(entry)));

  const status = document.getElementById('boardStatus');
  status.classList.toggle('error', !!boardError);
  status.textContent =
    boardError ? boardError + (entries ? ' Showing the last scores loaded.' : '')
    : boardLoading && !entries ? 'Loading…'
    : entries && entries.length === 0 ? 'No scores yet. Be the first!'
    : '';
}

function boardRow(entry) {
  const row = document.createElement('li');
  row.className = 'board-row' + (playerName && entry.name.toLowerCase() === playerName.toLowerCase() ? ' me' : '');
  const rank = document.createElement('span');
  rank.className   = 'board-rank';
  rank.textContent = `#${entry.rank}`;
  const who = document.createElement('span');
  who.className   = 'board-name';
  who.textContent = entry.name;
  const meta = document.createElement('span');
  meta.className   = 'board-meta';
  meta.textContent = new Date(entry.date).toLocaleDateString()
    + (entry.seasons ? ` · ${plural(entry.seasons, 'season')}` : '');
  who.appendChild(meta);
  const pts = document.createElement('span');
  pts.className   = 'board-score';
  pts.textContent = entry.score;
  row.appendChild(rank);
  row.appendChild(who);
  row.appendChild(pts);

  if (entry.replay) {
    const tag = document.createElement('span');
    tag.className   = 'board-replay';
    tag.textContent = ' · ▶ replay';
    meta.appendChild(tag);
    row.classList.add('has-replay');
    row.tabIndex = 0;
    row.setAttribute('role', 'button');
    row.setAttribute('aria-label', `Replay ${entry.name}'s run, score ${entry.score}`);
    const open = () => openReplay(entry, boardMode);
    row.addEventListener('click', open);
    row.addEventListener('keydown', ev => {
      if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); open(); }
    });
  }
  return row;
}

// ═══════════════════════════════════════════════════════
// REPLAYS (a leaderboard run, frame by frame)
// ═══════════════════════════════════════════════════════
let replayRequest = 0;

function backToBoard() {
  replayRequest++;
  renderBoard();
  showScreen('boardScreen');
}

async function openReplay(entry, m) {
  const req = ++replayRequest;
  document.getElementById('replayTitle').textContent = `${entry.name}'s run`;
  document.getElementById('replaySub').textContent =
    `${series.name} · ${MODES[m].title} · ${plural(entry.score, 'point')}`
    + ` · ${new Date(entry.date).toLocaleDateString()}`;
  document.getElementById('replaySummary').textContent = '';
  document.getElementById('replayList').innerHTML = '';
  const status = document.getElementById('replayStatus');
  status.classList.remove('error');
  status.textContent = 'Loading replay…';
  showScreen('replayScreen');

  try {
    const { replay } = await leaderboardRequest({ replay: entry.replay });
    if (req !== replayRequest) return;
    status.textContent = '';
    renderReplay(replay, m);
  } catch (err) {
    if (req !== replayRequest) return;
    status.classList.add('error');
    status.textContent = err.message;
  }
}

function renderReplay(replay, m) {
  const qs = replay.rounds || [];
  const right = qs.filter(q => q.result === 'correct').length;
  const ended = {
    'wrong-season':  'Ended on a wrong season.',
    'wrong-episode': 'Ended on a wrong episode.',
    'time':          'Ended when the clock ran out.'
  }[replay.end] || '';

  const summary = document.getElementById('replaySummary');
  summary.innerHTML = '';
  const line = html => { const d = document.createElement('div'); d.innerHTML = html; summary.appendChild(d); };
  line(`<strong>${qs.length}</strong> frame${qs.length === 1 ? '' : 's'} · `
     + `<strong>${right}</strong> right · ${ended}`
     + (replay.duration ? ` ${replay.duration}-second clock.` : ''));
  if (m === 'specific') {
    line(`Seasons selected: ${replay.seasons.map(s => `<strong>${escHtml(s ?? replay.title)}</strong>`).join(', ')}`);
  }

  const list = document.getElementById('replayList');
  list.innerHTML = '';
  qs.forEach((q, n) => list.appendChild(replayFrameItem(q, n, replay.seasons.length === 1)));
  if (!qs.length) document.getElementById('replayStatus').textContent = 'No frames were answered in this run.';
}

// One collapsible frame; failed ones start open
function replayItem(n, cls, what, extras, open, fillBody) {
  const li = document.createElement('li');
  const details = document.createElement('details');
  details.className = `rp-q ${cls}`;
  details.open = open;
  const summary = document.createElement('summary');
  const span = (c, t) => { const s = document.createElement('span'); s.className = c; s.textContent = t; return s; };
  summary.append(span('rp-num', `F${n + 1}`), span('rp-what', what), ...extras.map(([c, t]) => span(c, t)));
  const body = document.createElement('div');
  body.className = 'rp-body';
  details.addEventListener('toggle', () => { if (details.open && !body.firstChild) fillBody(body); });
  if (open) fillBody(body);
  details.append(summary, body);
  li.appendChild(details);
  return li;
}

function replayFrameItem(q, n, singleSeason) {
  const res = q.result;
  const cls  = res === 'correct' ? 'ok' : res === 'wrong-episode' ? 'partial' : 'bad';
  const what = { correct: '✓ Correct', 'wrong-episode': '✗ Wrong episode',
                 'wrong-season': '✗ Wrong season', timeout: '◷ Time ran out' }[res];
  const extras = [];
  if (q.timeLeft !== undefined) extras.push(['rp-time', `${(q.timeLeft / 1000).toFixed(1)}s left`]);
  if (q.zoomUsed !== undefined && res === 'correct') extras.push(['rp-time', `zoom ${q.zoomUsed} of ${ZOOM_STEPS}`]);
  extras.push(['rp-pts', q.points ? `+${q.points}` : '']);

  return replayItem(n, cls, what, extras, res !== 'correct', body => {
    const img = document.createElement('img');
    img.className = 'rp-frame';
    img.alt = '';
    img.loading = 'lazy';
    img.width = 960;
    img.height = 540;
    img.src = q.image;
    img.onerror = () => img.remove();
    body.appendChild(img);
    const season = escHtml(q.season ?? ''), episode = escHtml(q.episode);
    const lines = [];
    if (!singleSeason) {
      if (q.pickedSeason === null) lines.push(`Season: <span class="bad">not answered</span> — it was <strong>${season}</strong>`);
      else if (q.pickedSeason === q.season) lines.push(`Season: <span class="good">✓ ${season}</span>`);
      else lines.push(`Season: <span class="bad">✗ ${escHtml(q.pickedSeason)}</span> — it was <strong>${season}</strong>`);
    }
    if (q.pickedEpisode === null) {
      if (res !== 'wrong-season') lines.push(`Episode: <span class="bad">not answered</span> — it was <strong>${episode}</strong>`);
      else lines.push(`Episode: <strong>${episode}</strong>`);
    } else if (q.pickedEpisode === q.episode) {
      lines.push(`Episode: <span class="good">✓ ${episode}</span>`);
    } else {
      lines.push(`Episode: <span class="bad">✗ ${escHtml(q.pickedEpisode)}</span> — it was <strong>${episode}</strong>`);
    }
    const div = document.createElement('div');
    div.className = 'rp-answers';
    div.innerHTML = lines.join('<br>');
    body.appendChild(div);
  });
}

// Sends the finished run automatically once there's a name to put it under.
// The server takes the score and replay from the run it kept, not from here.
function submitLastRun() {
  const run = lastRun;
  if (!run) return;
  if (run.score <= 0) { lbState = { kind: 'zero' }; renderLbPanel(); return; }
  const problem = nameProblem(playerName);
  if (problem) { lbState = { kind: 'noname', error: playerName ? problem : '' }; renderLbPanel(); return; }

  lbState = { kind: 'saving' };
  renderLbPanel();
  leaderboardRequest(null, { name: playerName }).then(data => {
    if (run !== lastRun) return;
    boardCache[boardKey(run.series.id, run.mode)] = data.entries;
    lbState = { kind: 'saved', ...data };
    renderLbPanel();
  }, err => {
    if (run !== lastRun) return;
    lbState = { kind: 'error', message: err.message };
    renderLbPanel();
  });
}

function renderLbPanel() {
  const panel = document.getElementById('lbPanel');
  panel.innerHTML = '';
  const line = text => {
    const p = document.createElement('div');
    p.textContent = text;
    panel.appendChild(p);
    return p;
  };
  const board = lastRun ? `${lastRun.series.name} · ${MODES[lastRun.mode].title}` : '';

  if (lbState.kind === 'zero') {
    line('Score a point to join the leaderboard.');
  } else if (lbState.kind === 'noname') {
    line('Enter a name to put this score on the leaderboard.');
    if (lbState.error) line(lbState.error).className = 'error';
    const form = document.createElement('div');
    form.className = 'lb-form';
    const input = document.createElement('input');
    input.className   = 'name-input';
    input.maxLength   = 20;
    input.placeholder = 'Your name';
    input.value       = playerName;
    const save = document.createElement('button');
    save.className   = 'btn btn-primary btn-sm';
    save.textContent = 'Save score';
    const go = () => { setPlayerName(input.value); submitLastRun(); };
    save.addEventListener('click', go);
    input.addEventListener('keydown', ev => { if (ev.key === 'Enter') go(); });
    form.appendChild(input);
    form.appendChild(save);
    panel.appendChild(form);
  } else if (lbState.kind === 'saving') {
    line('Saving to the leaderboard…');
  } else if (lbState.kind === 'saved') {
    if (lbState.rank === null) {
      line(`Saved as ${playerName}, but it's outside the top 100 on the ${board} leaderboard.`);
    } else if (lbState.improved) {
      line(`Saved as ${playerName}: #${lbState.rank} on the ${board} leaderboard.`);
    } else {
      line(`${playerName}'s best of ${lbState.best} stays at #${lbState.rank} on the ${board} leaderboard.`);
    }
  } else if (lbState.kind === 'error') {
    line(lbState.message).className = 'error';
    const retry = document.createElement('button');
    retry.className   = 'btn btn-ghost btn-sm';
    retry.textContent = 'Retry';
    retry.addEventListener('click', submitLastRun);
    const form = document.createElement('div');
    form.className = 'lb-form';
    form.appendChild(retry);
    panel.appendChild(form);
  }
}

// ═══════════════════════════════════════════════════════
// BEST SCORES (one per show and mode)
// ═══════════════════════════════════════════════════════
function scoreKey(seriesId, m) { return `frameGuesser_hs:${seriesId}:${m}`; }
function getBest(seriesId, m) { return parseFloat(storageGet(scoreKey(seriesId, m)) || '0') || 0; }
function setBest(seriesId, m, value) { storageSet(scoreKey(seriesId, m), value); }

function setProgress(pct) {
  document.getElementById('progressFill').style.width = pct + '%';
}
function setLoadingBook(msg) {
  document.getElementById('loadingBook').textContent = msg;
}
function setFeedback(msg, cls) {
  const fb = document.getElementById('feedbackBar');
  fb.textContent = msg;
  fb.className   = 'feedback-bar' + (msg ? ` ${cls} show` : '');
}

// ═══════════════════════════════════════════════════════
// UTILS
// ═══════════════════════════════════════════════════════
function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }
function escHtml(s) {
  return String(s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
// localStorage can throw (private mode, blocked storage); scores just won't persist
function storageGet(key) {
  try { return localStorage.getItem(key); } catch { return null; }
}
function storageSet(key, value) {
  try { localStorage.setItem(key, value); } catch { /* ignore */ }
}

// ═══════════════════════════════════════════════════════
// START
// ═══════════════════════════════════════════════════════
// Reloaded mid-game: the page starts on the show list, so drop the extra history entry
if (history.state?.frameGuesser) history.back();
renderSeriesScreen();
