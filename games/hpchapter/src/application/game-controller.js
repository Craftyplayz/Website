import { BOOKS } from '../config/books.js';
import {
  BOOK_FEEDBACK_DELAY_MS,
  CORRECT_CHAPTER_FEEDBACK_DELAY_MS,
  INCORRECT_CHAPTER_FEEDBACK_DELAY_MS,
  LOADING_SCREEN_DELAY_MS,
  WRONG_BOOK_FEEDBACK_DELAY_MS
} from '../config/settings.js';
import { loadLibrary } from '../content/library-loader.js';
import { createQuestionPool } from '../domain/question-pool.js';
import { GameSession } from '../domain/game-session.js';

export class GameController {
  constructor(view, {
    fetchImpl = globalThis.fetch,
    JSZipLibrary = globalThis.JSZip,
    DOMParserClass = globalThis.DOMParser,
    highScoreStore,
    random = Math.random,
    document = globalThis.document,
    setTimeoutImpl = globalThis.setTimeout,
    clearTimeoutImpl = globalThis.clearTimeout,
    wait = ms => new Promise(resolve => setTimeoutImpl(resolve, ms))
  }) {
    this.view = view;
    this.fetchImpl = fetchImpl;
    this.JSZipLibrary = JSZipLibrary;
    this.DOMParserClass = DOMParserClass;
    this.highScoreStore = highScoreStore;
    this.random = random;
    this.document = document;
    this.setTimeoutImpl = setTimeoutImpl;
    this.clearTimeoutImpl = clearTimeoutImpl;
    this.wait = wait;
    this.library = [];
    this.session = null;
    this.bestScore = 0;
    this.runToken = 0;
    this.loadToken = 0;
    this.pendingTimers = new Set();
    this.partialLoadNotice = '';
  }

  async load() {
    const token = ++this.loadToken;
    this.cancelTransitions();
    this.view.showLoading();
    const libraryPromise = loadLibrary(BOOKS, {
      fetchImpl: this.fetchImpl,
      resolveAsset: path => new URL(path, this.document.baseURI).href,
      JSZipLibrary: this.JSZipLibrary,
      DOMParserClass: this.DOMParserClass,
      onProgress: progress => {
        if (token === this.loadToken) this.view.updateLoading(progress);
      }
    });
    const [loaded] = await Promise.all([libraryPromise, this.wait(LOADING_SCREEN_DELAY_MS)]);
    if (token !== this.loadToken) return;

    this.library = loaded.books;
    const questions = createQuestionPool(this.library, this.random);
    if (questions.length === 0) {
      const message = loaded.failures.length
        ? 'None of the books could be opened. Check the files and try again.'
        : 'No readable passages were found in the available books.';
      this.view.showLoadError(message, loaded.failures);
      return;
    }

    this.bestScore = this.highScoreStore.read();
    this.partialLoadNotice = loaded.failures.length
      ? `Some books could not be loaded: ${loaded.failures.map(({ book }) => book.assetPath).join(', ')}.`
      : '';
    this.startRun(questions);
  }

  restart() {
    if (this.library.length === 0) return;
    this.startRun(createQuestionPool(this.library, this.random));
  }

  startRun(questions) {
    this.cancelTransitions();
    this.runToken += 1;
    this.session = new GameSession(questions);
    const started = this.session.start();
    if (!started.accepted || started.completed) {
      this.view.showLoadError('No readable passages are available to start a game.', []);
      return;
    }
    this.view.showQuestion(this.session.snapshot(), this.bestScore, { notice: this.partialLoadNotice });
  }

  answerBook(bookId) {
    const outcome = this.session?.answerBook(bookId);
    if (!outcome?.accepted) return;
    this.view.showBookAnswer(outcome);
    const token = this.runToken;
    const delay = outcome.correct ? BOOK_FEEDBACK_DELAY_MS : WRONG_BOOK_FEEDBACK_DELAY_MS;
    this.schedule(() => {
      if (token !== this.runToken) return;
      if (outcome.correct) {
        const transition = this.session.beginChapterSelection();
        if (transition.accepted) this.view.showQuestion(this.session.snapshot(), this.bestScore);
      } else {
        const transition = this.session.advance();
        if (transition.accepted && transition.completed) this.finish(transition.result);
      }
    }, delay);
  }

  answerChapter(chapterId) {
    const outcome = this.session?.answerChapter(chapterId);
    if (!outcome?.accepted) return;
    this.view.showChapterAnswer(outcome);
    const token = this.runToken;
    const delay = outcome.correct
      ? CORRECT_CHAPTER_FEEDBACK_DELAY_MS
      : INCORRECT_CHAPTER_FEEDBACK_DELAY_MS;
    this.schedule(() => {
      if (token !== this.runToken) return;
      const transition = this.session.advance();
      if (!transition.accepted) return;
      if (transition.completed) this.finish(transition.result);
      else this.view.showQuestion(this.session.snapshot(), this.bestScore);
    }, delay);
  }

  finish(result) {
    const record = this.highScoreStore.record(result.score);
    this.bestScore = record.highScore;
    this.view.showResults(result, this.bestScore, record.isRecord);
  }

  schedule(callback, delay) {
    const timer = this.setTimeoutImpl(() => {
      this.pendingTimers.delete(timer);
      callback();
    }, delay);
    this.pendingTimers.add(timer);
  }

  cancelTransitions() {
    for (const timer of this.pendingTimers) this.clearTimeoutImpl(timer);
    this.pendingTimers.clear();
  }
}
