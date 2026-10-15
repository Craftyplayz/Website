import { BOOKS } from '../config/books.js';
import { MODES, modeDescription, SPECIFIC_NOTE, RANKING_NOTE } from '../modes/rules.js';
import { leaderboardRows } from '../services/leaderboards.js';

export function libraryFailureMessages(failures = []) {
  return failures.map(failure => {
    const bookId = failure.bookId ?? failure.book?.id;
    const title = BOOKS.find(book => book.id === bookId)?.title ?? bookId ?? 'A book';
    return `${title}: ${failure.message ?? failure.error?.message ?? failure.error ?? 'Unavailable'}`;
  });
}

export class OnlineView {
  constructor(document) {
    this.document = document;
    this.root = document.querySelector('.app-wrap');
    this.lastScreen = null;
    this.lastAnnouncement = null;
  }
  node(tag, text = '', className = '') {
    const node = this.document.createElement(tag);
    node.textContent = text;
    node.className = className;
    return node;
  }
  button(text, id, action, className = 'btn btn-ghost') {
    const button = this.node('button', text, className);
    button.type = 'button';
    if (id) button.id = id;
    button.addEventListener('click', action);
    return button;
  }
  render(model) {
    const focused = this.document.activeElement;
    const focusId = focused?.id;
    const selection = focused?.selectionStart;
    const changedScreen = this.lastScreen !== model.screen;
    this.lastScreen = model.screen;
    const heading = this.node('header', '', 'wordmark');
    heading.append(this.node('div', 'Harry Potter · The seven-book challenge', 'wordmark-eyebrow'));
    const title = this.node('h1', 'Chapter ', 'wordmark-title');
    title.append(this.node('em', 'Oracle'));
    heading.append(title, this.node('div', '', 'wordmark-divider'));
    const screen = this.node('section', '', 'screen active');
    screen.id = { menu: 'menuScreen', loading: 'loadingScreen', error: 'errorScreen',
      quiz: 'quizScreen', result: 'gameoverScreen', leaderboard: 'leaderboardScreen' }[model.screen];
    screen.setAttribute('aria-hidden', 'false');
    screen.setAttribute('aria-label', model.screen === 'menu' ? 'Choose your challenge' : model.screen);
    this.root.replaceChildren(heading, screen);
    this[model.screen](screen, model);
    const stageKey = model.screen === 'quiz'
      ? `${model.snapshot.question?.id}:${model.snapshot.state}:${model.pending}` : null;
    const newStage = stageKey !== this.lastStageKey && !model.pending;
    this.lastStageKey = stageKey;
    if (changedScreen) this.document.defaultView?.scrollTo(0, 0);
    if (changedScreen || newStage) screen.querySelector('[tabindex="-1"]')?.focus({ preventScroll: true });
    else if (focusId) {
      const restored = this.document.getElementById(focusId);
      restored?.focus({ preventScroll: true });
      if (selection != null && restored?.setSelectionRange) restored.setSelectionRange(selection, selection);
    }
    if (changedScreen && model.screen === 'quiz') this.lastAnnouncement = null;
  }
  notice(parent, message, error = false) {
    const node = this.node('p', message, error ? 'network-message error-msg' : 'network-message');
    node.setAttribute('role', 'status');
    parent.append(node);
  }
  menu(screen, model) {
    const card = this.node('div', '', 'card menu-card');
    const heading = this.node('h2', 'Choose your challenge', 'section-title');
    heading.tabIndex = -1;
    card.append(heading, this.node('p', 'Seven books. Countless chapters. How well do you know the story?', 'intro'));
    const modes = this.node('div', '', 'mode-grid');
    modes.setAttribute('aria-label', 'Game mode');
    for (const mode of MODES) {
      const button = this.button('', `mode-${mode.id}`, () => this.controller.selectMode(mode.id), 'mode-card');
      button.dataset.mode = mode.id;
      button.setAttribute('aria-pressed', String(model.mode === mode.id));
      button.append(this.node('span', mode.symbol, 'mode-symbol'), this.node('strong', mode.title),
        this.node('span', {
          normal: 'The complete library', timed: `${model.config.duration}-second challenge`,
          minimum: `${model.config.contextLimit} characters of context`, specific: 'Your chosen books'
        }[mode.id], 'mode-caption'));
      modes.append(button);
    }
    card.append(modes);
    const rules = this.node('p', modeDescription(model.mode, model.config), 'mode-rules');
    rules.id = 'modeDescription';
    card.append(rules);
    if (model.configStatus === 'loading') this.notice(card, 'Fetching official server rules…');
    if (model.configStatus === 'error') {
      this.notice(card, model.configError, true);
      card.append(this.button('Retry server rules', 'retryConfigButton', () => this.controller.loadConfig()));
    }
    if (model.mode === 'specific') {
      const fieldset = this.node('fieldset', '', 'book-picker');
      fieldset.append(this.node('legend', 'Passage pool · choose at least one book'));
      const toolbar = this.node('div', '', 'toolbar');
      toolbar.append(this.button('Select all', 'selectAllBooks', () => this.controller.selectBooks(BOOKS.map(book => book.id))),
        this.button('Clear all', 'clearAllBooks', () => this.controller.selectBooks([])));
      fieldset.append(toolbar);
      for (const book of BOOKS) {
        const label = this.node('label', '', 'book-check');
        const input = this.node('input');
        input.type = 'checkbox';
        input.id = `select-${book.id}`;
        input.value = book.id;
        input.checked = model.selectedBooks.includes(book.id);
        input.addEventListener('change', () => {
          const ids = [...this.root.querySelectorAll('.book-check input:checked')].map(node => node.value);
          this.controller.selectBooks(ids);
        });
        label.append(input, this.document.createTextNode(`${book.order}. ${book.shortTitle}`));
        fieldset.append(label);
      }
      card.append(fieldset);
      this.notice(card, SPECIFIC_NOTE);
    }
    const form = this.node('form', '', 'start-form');
    const label = this.node('label', 'Your public leaderboard name');
    label.htmlFor = 'playerName';
    const input = this.node('input', '', 'name-input');
    input.id = 'playerName';
    input.name = 'name';
    input.type = 'text';
    input.value = model.name;
    input.autocomplete = 'nickname';
    input.setAttribute('aria-describedby', 'nameHint');
    input.addEventListener('input', () => this.controller.setName(input.value));
    const hint = this.node('p', '1–20 Unicode characters. Your name and score will be public. Scores are stored only on the server.', 'field-hint');
    hint.id = 'nameHint';
    const start = this.button('Begin challenge →', 'startButton', () => {}, 'btn btn-primary');
    start.type = 'submit';
    start.disabled = model.configStatus !== 'ready';
    form.addEventListener('submit', event => { event.preventDefault(); this.controller.start(); });
    form.append(label, input, hint, start);
    if (model.error) this.notice(form, model.error, true);
    card.append(form);
    screen.append(card);
    const preview = this.node('div', '', 'card leaderboard-card');
    preview.append(this.node('h2', `${MODES.find(mode => mode.id === model.mode)?.title} · Global top 10`, 'section-title'));
    if (model.previewStatus === 'loading') this.notice(preview, 'Loading global preview…');
    else if (model.previewStatus !== 'ready') {
      this.notice(preview, model.previewError, true);
      preview.append(this.button('Retry preview', 'retryPreviewButton', () => this.controller.loadPreview()));
    }
    this.table(preview, model.preview[model.mode] || [], model.previewStatus === 'ready', model.mode);
    preview.append(this.button('Full leaderboard · top 100', 'leaderboardButton', () => this.controller.leaderboard()));
    screen.append(preview);
  }
  partialLibrary(parent, snapshot) {
    const failures = libraryFailureMessages(snapshot?.failures);
    if (!snapshot?.notice && !failures.length) return;
    const partial = this.node('aside', '', 'card partial-library');
    partial.id = 'partialLibraryNotice';
    partial.setAttribute('aria-label', 'Partial library notice');
    this.notice(partial, String(snapshot.notice || 'Some books could not be opened. This run uses only the successfully loaded books.'));
    if (failures.length) {
      const list = this.node('ul', '', 'error-failures');
      for (const message of failures) list.append(this.node('li', message));
      partial.append(list);
    }
    partial.append(this.button('Return to menu / retry full library', 'retryLibraryButton', () => this.controller.menu()));
    parent.append(partial);
  }
  loading(screen, model) {
    const card = this.node('div', '', 'card loading-inner');
    const heading = this.node('h2', 'Preparing your challenge…', 'section-title');
    heading.tabIndex = -1;
    card.append(this.node('div', '', 'spinner-ring'), heading,
      this.node('p', 'The server is opening the canonical library. The timer has not started.', 'intro'),
      this.button('Return to menu', 'loadingMenuButton', () => this.controller.menu()));
    screen.append(card);
    this.partialLibrary(screen, model.snapshot);
  }
  error(screen, model) {
    const card = this.node('div', '', 'card error-card');
    const title = this.node('h2', 'Challenge unavailable', 'section-title');
    title.id = 'errorTitle';
    title.tabIndex = -1;
    const message = this.node('p', model.error, 'error-msg');
    message.id = 'errorMessage';
    message.setAttribute('role', 'alert');
    card.append(title, message, this.button('Retry / check run state', 'retryButton', () => this.controller.retry(), 'btn btn-primary'),
      this.button('Return to menu', 'errorMenuButton', () => this.controller.menu()));
    screen.append(card);
  }
  quiz(screen, model) {
    const snapshot = model.snapshot;
    const header = this.node('header', '', 'quiz-header');
    const score = this.node('div', '', 'score-wrap');
    const scoreValue = this.node('span', String(snapshot.score), 'score-num');
    scoreValue.id = 'scoreDisplay';
    score.append(scoreValue, this.node('span', 'Score', 'score-lbl'));
    header.append(score, this.node('span', `${snapshot.name} · ${MODES.find(mode => mode.id === snapshot.mode)?.title}`, 'run-label'));
    if (snapshot.mode === 'timed' && snapshot.state !== 'completed') {
      const timer = this.node('span', '', 'countdown');
      timer.id = 'timerDisplay';
      timer.setAttribute('aria-label', 'Time remaining');
      const live = this.node('span', '', 'sr-only');
      live.id = 'timerAnnouncement';
      live.setAttribute('aria-live', 'polite');
      header.append(timer, live);
    }
    screen.append(header);
    this.partialLibrary(screen, snapshot);
    const passage = this.node('blockquote', snapshot.question?.passage || '', 'passage-card');
    passage.id = 'passageCard';
    passage.tabIndex = 0;
    const chapter = snapshot.state === 'chapter-selection' || snapshot.feedback?.type === 'chapter';
    const stage = this.node('h2', '', 'stage-label');
    stage.id = 'stageLabel';
    stage.tabIndex = -1;
    stage.append(this.node('span', chapter ? 'Step 2 of 2' : 'Step 1 of 2', 'stage-badge'),
      this.node('span', chapter ? 'Which chapter is this from?' : 'Which book is this from?'));
    const choices = this.node('div', '', chapter ? 'chapter-grid chapter-scroll-wrap' : 'book-grid');
    choices.id = 'choicesArea';
    const items = chapter ? snapshot.question?.chapterChoices || [] : BOOKS;
    for (const item of items) {
      const type = chapter ? 'chapter' : 'book';
      const button = this.button(chapter ? item.title : `Book ${item.order} · ${item.shortTitle}`, '',
        () => this.controller.answer(type, item.id), chapter ? 'chapter-btn' : 'book-btn');
      button.dataset.answerType = type;
      button.dataset.answerId = item.id;
      button.disabled = model.pending || snapshot.state === 'answer-feedback';
      const correctId = snapshot.feedback?.correctChapterId || snapshot.feedback?.correctBookId;
      if (model.pending && item.id === correctId) button.classList.add('state-reveal');
      choices.append(button);
    }
    screen.append(passage, stage, choices);
    const feedback = this.node('div', '', 'feedback-bar show fb-info');
    feedback.id = 'feedbackBar';
    feedback.setAttribute('role', 'status');
    if (model.pending && snapshot.feedback) {
      const answer = snapshot.feedback;
      feedback.textContent = answer.correct
        ? answer.type === 'book' ? '✓ Correct book — now identify the chapter.' : '✦ Correct chapter — one point!'
        : answer.type === 'book'
          ? `Incorrect book — ${BOOKS.find(book => book.id === answer.correctBookId)?.title || 'another book'}${answer.chapterTitle ? ` · ${answer.chapterTitle}` : ''}.`
          : `Not quite — ${answer.chapterTitle || 'incorrect answer'}. No point this round.`;
    } else feedback.textContent = model.pending ? 'Checking with the server…' : '';
    screen.append(feedback, this.button('Abandon · return to menu', 'quizMenuButton', () => this.controller.menu()));
  }
  updateTimer(ms) {
    const seconds = Math.ceil(ms / 1000);
    const display = this.document.getElementById('timerDisplay');
    if (display) {
      display.textContent = `${seconds}s`;
      display.classList.toggle('urgent', seconds <= 10);
    }
    if ((seconds <= 10 || seconds % 15 === 0) && seconds !== this.lastAnnouncement) {
      const live = this.document.getElementById('timerAnnouncement');
      if (live) live.textContent = `${seconds} seconds remaining`;
      this.lastAnnouncement = seconds;
    }
  }
  result(screen, model) {
    const result = model.snapshot?.result || model.provisional || {};
    const accepted = model.snapshot?.state === 'completed' && result.accepted === true;
    const card = this.node('div', '', 'card go-inner');
    const title = this.node('h2', accepted ? 'Challenge complete' : 'Result not confirmed', 'section-title');
    title.id = 'resultTitle';
    title.tabIndex = -1;
    const score = this.node('span', String(result.score ?? model.snapshot.score), 'go-score');
    score.id = 'goScore';
    const reason = { 'incorrect-book': 'Incorrect book', 'pool-exhausted': 'Every passage completed', 'time-expired': 'Time expired' }[result.reason] || 'Awaiting server confirmation';
    card.append(title, score, this.node('p', 'chapters correctly identified', 'go-score-lbl'),
      this.node('p', `${reason} · ${(Number(result.elapsedMs || 0) / 1000).toFixed(2)} seconds`, 'go-context'),
      this.node('p', accepted ? 'Accepted by the server · saved to the global leaderboard.' : 'Provisional only — not confirmed, not a saved global score.', 'network-message'));
    const feedback = model.snapshot?.feedback;
    if (feedback?.correctBookId) {
      card.append(this.node('p', `The passage was from ${BOOKS.find(book => book.id === feedback.correctBookId)?.title || feedback.correctBookId}${feedback.chapterTitle ? ` · ${feedback.chapterTitle}` : ''}.`, 'intro'));
    }
    if (model.error) this.notice(card, model.error, true);
    if (!accepted) {
      const retry = this.button('Check state / retry confirmation', 'retryFinishButton', () => this.controller.confirmFinish(), 'btn btn-primary');
      retry.disabled = model.pending;
      card.append(retry);
    } else {
      this.notice(card, model.rankStatus === 'loading' ? 'Refreshing global rank…' : model.rankStatus === 'error'
        ? model.rankError : model.rank ? `Global rank${model.rankAtAcceptance ? ' at acceptance' : ''}: #${model.rank}` : 'Saved globally. Your entry may be outside the top 100.');
      if (model.rankStatus === 'error') card.append(this.button('Retry global rank', 'retryRankButton', () => this.controller.refreshRank()));
      card.append(this.button('View global leaderboard', 'resultLeaderboardButton', () => this.controller.leaderboard(model.snapshot.mode)));
    }
    card.append(this.button('Play again', 'restartButton', () => this.controller.start(), 'btn btn-primary'),
      this.button('Return to menu', 'resultMenuButton', () => this.controller.menu()));
    screen.append(card);
    this.partialLibrary(screen, model.snapshot);
  }
  leaderboard(screen, model) {
    const card = this.node('div', '', 'card leaderboard-card');
    const title = this.node('h2', 'Global leaderboard · top 100', 'section-title');
    title.tabIndex = -1;
    const label = this.node('label', 'Game mode');
    label.htmlFor = 'leaderboardMode';
    const select = this.node('select', '', 'name-input');
    select.id = 'leaderboardMode';
    for (const mode of MODES) {
      const option = this.node('option', mode.title);
      option.value = mode.id;
      option.selected = model.boardMode === mode.id;
      select.append(option);
    }
    select.addEventListener('change', () => this.controller.leaderboard(select.value));
    card.append(title, label, select);
    if (model.boardStatus === 'loading') this.notice(card, 'Loading global scores…');
    else if (model.boardStatus !== 'ready') this.notice(card, `${model.boardError} Previously loaded entries remain below.`, true);
    this.table(card, model.boards[model.boardMode] || [], model.boardStatus === 'ready', model.boardMode);
    card.append(this.button('Refresh / retry', 'retryLeaderboardButton', () => this.controller.leaderboard(model.boardMode)),
      this.button('Return to menu', 'leaderboardMenuButton', () => this.controller.menu()));
    screen.append(card);
  }
  table(parent, entries, loaded, mode) {
    if (mode === 'specific') this.notice(parent, SPECIFIC_NOTE);
    parent.append(this.node('p', RANKING_NOTE, 'field-hint'));
    if (!entries.length) {
      if (loaded) this.notice(parent, 'No scores yet. Be the first to finish this challenge.');
      return;
    }
    const wrapper = this.node('div', '', 'table-scroll');
    const table = this.node('table', '', 'leaderboard-table');
    const caption = this.node('caption', 'Public server-confirmed scores', 'sr-only');
    const head = this.node('thead');
    const labels = this.node('tr');
    const detailHeader = { specific: 'Books', minimum: 'Context limit', timed: 'Time limit' }[mode];
    for (const text of ['Rank', 'Name', 'Score', 'Elapsed', 'Date', ...(detailHeader ? [detailHeader] : [])]) {
      const cell = this.node('th', text);
      cell.scope = 'col';
      labels.append(cell);
    }
    head.append(labels);
    const body = this.node('tbody');
    for (const row of leaderboardRows(entries)) {
      const item = this.node('tr');
      const detail = { specific: row.books, minimum: row.contextLimit, timed: row.duration }[mode];
      for (const value of [row.rank, row.name, row.score, row.elapsed, row.date, ...(detailHeader ? [detail] : [])]) {
        item.append(this.node('td', String(value)));
      }
      body.append(item);
    }
    table.append(caption, head, body);
    wrapper.append(table);
    parent.append(wrapper);
  }
}
