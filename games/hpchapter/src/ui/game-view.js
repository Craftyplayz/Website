import { BOOKS } from '../config/books.js';

const SCREEN_IDS = ['loadingScreen', 'errorScreen', 'quizScreen', 'gameoverScreen'];

function requiredElement(document, id) {
  const element = document.getElementById(id);
  if (!element) throw new Error(`The game page is missing its "${id}" element.`);
  return element;
}

export class GameView {
  constructor(document, { onBookAnswer, onChapterAnswer, onRetry, onRestart }) {
    this.document = document;
    this.onBookAnswer = onBookAnswer;
    this.onChapterAnswer = onChapterAnswer;
    this.onRetry = onRetry;
    this.onRestart = onRestart;
    this.screens = new Map(SCREEN_IDS.map(id => [id, requiredElement(document, id)]));
    this.bindEvents();
  }

  bindEvents() {
    requiredElement(this.document, 'choicesArea').addEventListener('click', event => {
      const button = event.target.closest('button[data-answer-type]');
      if (!button || button.disabled) return;
      if (button.dataset.answerType === 'book') this.onBookAnswer(button.dataset.answerId);
      if (button.dataset.answerType === 'chapter') this.onChapterAnswer(button.dataset.answerId);
    });
    requiredElement(this.document, 'retryButton').addEventListener('click', this.onRetry);
    requiredElement(this.document, 'restartButton').addEventListener('click', this.onRestart);
  }

  showScreen(id) {
    for (const [screenId, screen] of this.screens) {
      const active = screenId === id;
      screen.classList.toggle('active', active);
      screen.setAttribute('aria-hidden', String(!active));
    }
  }

  showLoading() {
    this.showScreen('loadingScreen');
    requiredElement(this.document, 'loadingMessage').textContent = 'Opening the books…';
    requiredElement(this.document, 'progressFill').style.width = '0%';
    requiredElement(this.document, 'loadingFailures').replaceChildren();
    requiredElement(this.document, 'loadingCount').textContent = `0 of ${BOOKS.length} books loaded`;
    for (const dot of this.document.querySelectorAll('.book-dot')) {
      dot.classList.remove('loaded', 'failed');
      dot.removeAttribute('aria-label');
    }
  }

  updateLoading({ completed, total, book, error }) {
    const dot = this.document.querySelector(`[data-book-dot="${book.id}"]`);
    if (dot) {
      dot.classList.toggle('loaded', !error);
      dot.classList.toggle('failed', Boolean(error));
      dot.setAttribute('aria-label', `${book.title}: ${error ? 'failed to load' : 'loaded'}`);
    }
    const progress = requiredElement(this.document, 'progressFill');
    progress.style.width = `${Math.round(completed / total * 100)}%`;
    progress.parentElement.setAttribute('aria-valuenow', String(completed));
    requiredElement(this.document, 'loadingCount').textContent = `${completed} of ${total} books processed`;
    requiredElement(this.document, 'loadingMessage').textContent = error
      ? `${book.title} could not be loaded.`
      : `${book.title} is ready.`;
    if (error) this.appendFailure(requiredElement(this.document, 'loadingFailures'), book, error);
  }

  appendFailure(list, book, error) {
    const item = this.document.createElement('li');
    item.textContent = `${book.assetPath}: ${error.message}`;
    list.append(item);
  }

  showLoadError(message, failures) {
    this.showScreen('errorScreen');
    requiredElement(this.document, 'errorMessage').textContent = message;
    const list = requiredElement(this.document, 'errorFailures');
    list.replaceChildren();
    failures.forEach(({ book, error }) => this.appendFailure(list, book, error));
    requiredElement(this.document, 'errorTitle').focus();
  }

  showQuestion(snapshot, bestScore, { notice = '' } = {}) {
    this.showScreen('quizScreen');
    this.updateScore(snapshot.score, bestScore);
    const question = snapshot.currentQuestion;
    requiredElement(this.document, 'passageCard').textContent = question.passage;
    this.setFeedback(notice, notice ? 'fb-info' : '');
    if (snapshot.state === 'chapter-selection') this.renderChapterChoices(question);
    else this.renderBookChoices();
    this.focusStage();
  }

  renderBookChoices() {
    requiredElement(this.document, 'stageBadge').textContent = 'Step 1 of 2';
    requiredElement(this.document, 'stageText').textContent = 'Which Harry Potter book is this from?';
    const grid = this.document.createElement('div');
    grid.className = 'book-grid';
    for (const book of BOOKS) {
      const button = this.document.createElement('button');
      button.type = 'button';
      button.className = 'book-btn';
      button.dataset.answerType = 'book';
      button.dataset.answerId = book.id;
      const number = this.document.createElement('span');
      number.className = 'book-num';
      number.textContent = `Book ${book.order}`;
      button.append(number, this.document.createTextNode(book.shortTitle));
      grid.append(button);
    }
    requiredElement(this.document, 'choicesArea').replaceChildren(grid);
  }

  renderChapterChoices(question) {
    requiredElement(this.document, 'stageBadge').textContent = 'Step 2 of 2';
    requiredElement(this.document, 'stageText').textContent = 'Now pick the exact chapter:';
    const grid = this.document.createElement('div');
    grid.className = 'chapter-grid';
    question.chapterChoices.forEach((chapter, index) => {
      const button = this.document.createElement('button');
      button.type = 'button';
      button.className = 'chapter-btn';
      button.dataset.answerType = 'chapter';
      button.dataset.answerId = chapter.id;
      button.style.animationDelay = `${Math.min(index * 18, 300)}ms`;
      button.textContent = chapter.title;
      grid.append(button);
    });
    const wrapper = this.document.createElement('div');
    wrapper.className = 'chapter-scroll-wrap';
    wrapper.append(grid);
    requiredElement(this.document, 'choicesArea').replaceChildren(wrapper);
  }

  showBookAnswer(outcome) {
    this.disableChoices();
    this.markAnswer('book', outcome.correct ? outcome.correctBookId : null, outcome.question.bookId);
    this.setFeedback(outcome.correct
      ? '✓ Correct — now identify the chapter'
      : `✗ Wrong — it was ${BOOKS.find(book => book.id === outcome.correctBookId)?.title ?? 'another book'}`,
    outcome.correct ? 'fb-info' : 'fb-wrong');
  }

  showChapterAnswer(outcome) {
    this.disableChoices();
    this.markAnswer('chapter', outcome.correct ? outcome.correctChapterId : null, outcome.correctChapterId);
    if (!outcome.correct) {
      const correctButton = this.document.querySelector(`[data-answer-type="chapter"][data-answer-id="${CSS.escape(outcome.correctChapterId)}"]`);
      correctButton?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
    this.setFeedback(outcome.correct
      ? '✦ Perfect — 1 point!'
      : `✗ Not quite — it was "${outcome.question.chapterTitle}". No point this round.`,
    outcome.correct ? 'fb-correct' : 'fb-partial');
    this.updateScore(outcome.score, this.bestScore);
  }

  disableChoices() {
    for (const button of requiredElement(this.document, 'choicesArea').querySelectorAll('button')) {
      button.disabled = true;
    }
  }

  markAnswer(type, selectedId, correctId) {
    for (const button of this.document.querySelectorAll(`[data-answer-type="${type}"]`)) {
      if (selectedId && button.dataset.answerId === selectedId) button.classList.add('state-correct');
      else if (selectedId && button.dataset.answerId !== correctId) button.classList.add('state-wrong');
      if (button.dataset.answerId === correctId && button.dataset.answerId !== selectedId) {
        button.classList.add('state-reveal');
      }
    }
  }

  updateScore(score, bestScore) {
    this.bestScore = bestScore;
    requiredElement(this.document, 'scoreDisplay').textContent = score;
    requiredElement(this.document, 'hsDisplay').textContent = bestScore;
  }

  setFeedback(message, className) {
    const feedback = requiredElement(this.document, 'feedbackBar');
    feedback.textContent = message;
    feedback.className = `feedback-bar${message ? ` ${className} show` : ''}`;
  }

  focusStage() {
    requiredElement(this.document, 'stageLabel').focus({ preventScroll: true });
  }

  showResults(result, highScore, isRecord) {
    this.showScreen('gameoverScreen');
    const completed = result.reason === 'pool-exhausted';
    requiredElement(this.document, 'resultTitle').textContent = completed ? 'Library Conquered' : 'Game Over';
    requiredElement(this.document, 'goScore').textContent = result.score;
    requiredElement(this.document, 'goRecord').hidden = !isRecord;
    requiredElement(this.document, 'resultContext').replaceChildren();

    if (completed) {
      requiredElement(this.document, 'resultContext').textContent = 'You conquered every passage in the library!';
      requiredElement(this.document, 'resultContext').classList.add('completed-context');
    } else if (result.question) {
      const book = BOOKS.find(item => item.id === result.question.bookId);
      const context = requiredElement(this.document, 'resultContext');
      context.classList.remove('completed-context');
      context.append(this.document.createTextNode('The passage was from '));
      const bookName = this.document.createElement('strong');
      bookName.textContent = book?.title ?? 'an unavailable book';
      const chapterName = this.document.createElement('strong');
      chapterName.textContent = result.question.chapterTitle;
      context.append(bookName, this.document.createTextNode(', chapter '), chapterName, this.document.createTextNode('.'));
    }
    this.updateScore(result.score, highScore);
    requiredElement(this.document, 'resultTitle').focus();
  }
}
