import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile, stat } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import { BOOKS } from '../src/config/books.js';
import {
  PARAGRAPH_MAX_LENGTH,
  PARAGRAPH_MIN_LENGTH
} from '../src/config/settings.js';
import { parseEpub, isEligibleParagraph, normalizeParagraph, resolveArchivePath } from '../src/content/epub-parser.js';
import { loadLibrary } from '../src/content/library-loader.js';
import { createQuestionPool } from '../src/domain/question-pool.js';
import { GameSession } from '../src/domain/game-session.js';
import { shuffle } from '../src/domain/shuffle.js';
import { createHighScoreStore } from '../src/services/high-scores.js';
import { GameController } from '../src/application/game-controller.js';
import { FixtureDOMParser, makeSyntheticEpub } from './fixture-dom.js';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const fixtureBook = BOOKS[0];
const parserOptions = fixture => ({
  JSZipLibrary: fixture.JSZipLibrary,
  DOMParserClass: FixtureDOMParser
});

function testQuestion(id, bookId = 'hp-1', chapterId = 'chapter-a') {
  return {
    id,
    bookId,
    chapterId,
    chapterTitle: chapterId,
    passage: `passage ${id}`,
    chapterChoices: [{ id: chapterId, title: chapterId }, { id: 'other-chapter', title: 'Other chapter' }]
  };
}

test('paragraph normalization and inclusive eligibility boundaries are retained', () => {
  assert.equal(normalizeParagraph('  one \n  two\tthree  '), 'one two three');
  assert.equal(isEligibleParagraph('x'.repeat(PARAGRAPH_MIN_LENGTH)), true);
  assert.equal(isEligibleParagraph('x'.repeat(PARAGRAPH_MAX_LENGTH)), true);
  assert.equal(isEligibleParagraph('x'.repeat(PARAGRAPH_MIN_LENGTH - 1)), false);
  assert.equal(isEligibleParagraph('x'.repeat(PARAGRAPH_MAX_LENGTH + 1)), false);
});

test('archive paths resolve nested relative references and strip fragments and queries', () => {
  assert.equal(resolveArchivePath('OPS/nav/nav.xhtml', '../Text/chapter.xhtml#part'), 'OPS/Text/chapter.xhtml');
  assert.equal(resolveArchivePath('OPS/package.opf', '/Text/chapter.xhtml?mode=read'), 'Text/chapter.xhtml');
  assert.equal(resolveArchivePath('OPS/package.opf', 'Text/Some%20Chapter.xhtml'), 'OPS/Text/Some Chapter.xhtml');
});

test('EPUB 3 navigation extracts stable chapters and normalized paragraph identities', async () => {
  const fixture = makeSyntheticEpub();
  const book = await parseEpub(fixture.data, fixtureBook, parserOptions(fixture));

  assert.deepEqual(book.chapters.map(chapter => chapter.title), ['Chapter One', 'Chapter Two']);
  assert.equal(book.chapters[0].href, 'OPS/Text/one.xhtml');
  assert.equal(book.chapters[0].paragraphs.length, 2);
  assert.equal(book.chapters[0].paragraphs[0].text, fixture.paragraph);
  assert.equal(book.chapters[0].paragraphs[0].id, 'hp-1:chapter-one:p1');
  assert.equal(book.chapters[1].paragraphs[0].id, 'hp-1:chapter-two:p1');
  assert.equal(book.chapters[0].paragraphs[1].text.length, 2500);
});

test('EPUB 2 NCX navigation is used when EPUB 3 navigation is malformed', async () => {
  const fixture = makeSyntheticEpub({ navigation: 'broken' });
  const book = await parseEpub(fixture.data, fixtureBook, parserOptions(fixture));
  assert.deepEqual(book.chapters.map(chapter => chapter.title), ['Fallback One', 'Fallback Two']);
});

test('invalid EPUB package structures produce actionable parser failures', async () => {
  const missingContainer = {
    data: [],
    JSZipLibrary: { loadAsync: async () => ({ file: () => null }) }
  };
  await assert.rejects(
    parseEpub(missingContainer.data, fixtureBook, parserOptions(missingContainer)),
    /container\.xml/
  );

  const malformed = makeSyntheticEpub({ malformedPackage: true });
  await assert.rejects(parseEpub(malformed.data, fixtureBook, parserOptions(malformed)), /malformed XML/);
});

test('question generation filters and globally deduplicates while keeping first attribution', async () => {
  const fixture = makeSyntheticEpub();
  const parsed = await parseEpub(fixture.data, fixtureBook, parserOptions(fixture));
  const secondBook = {
    ...BOOKS[1],
    chapters: [{
      id: 'second-chapter',
      title: 'Same chapter label',
      paragraphs: [{ id: 'second-p1', text: fixture.paragraph }, { id: 'second-p2', text: 'E'.repeat(120) }]
    }]
  };
  const pool = createQuestionPool([parsed, secondBook], () => 0);

  assert.equal(pool.length, 4);
  assert.equal(pool.filter(question => question.passage === fixture.paragraph).length, 1);
  assert.equal(pool.find(question => question.passage === fixture.paragraph).bookId, fixtureBook.id);
  assert.equal(pool.find(question => question.passage === 'E'.repeat(120)).bookId, secondBook.id);
  assert.equal(pool.find(question => question.passage === fixture.paragraph).chapterChoices.length, 2);
});

test('Fisher–Yates shuffle is deterministic when a random source is supplied', () => {
  const items = ['a', 'b', 'c'];
  assert.deepEqual(shuffle(items, () => 0), ['b', 'c', 'a']);
  assert.deepEqual(items, ['a', 'b', 'c']);
});

test('a failed book does not block a partially loaded library and failures are reported', async () => {
  const fixture = makeSyntheticEpub();
  const outcomes = [];
  const result = await loadLibrary(BOOKS.slice(0, 2), {
    fetchImpl: async url => {
      if (url.endsWith('book2.epub')) return { ok: false, status: 503 };
      return { ok: true, arrayBuffer: async () => fixture.data };
    },
    JSZipLibrary: fixture.JSZipLibrary,
    DOMParserClass: FixtureDOMParser,
    onProgress: progress => outcomes.push(progress)
  });

  assert.equal(result.books.length, 1);
  assert.equal(result.failures.length, 1);
  assert.equal(result.failures[0].book.id, BOOKS[1].id);
  assert.match(result.failures[0].error.message, /503/);
  assert.equal(outcomes.length, 2);
  assert.deepEqual(outcomes.map(outcome => outcome.completed).sort(), [1, 2]);
});

test('valid EPUBs with no eligible paragraph yield an empty question pool', async () => {
  const fixture = makeSyntheticEpub();
  fixture.files['OPS/Text/one.xhtml'] = '<html><body><p>too short</p></body></html>';
  fixture.files['OPS/Text/nested/two.xhtml'] = '<html><body><p>still too short</p></body></html>';
  const parsed = await parseEpub(fixture.data, fixtureBook, parserOptions(fixture));
  assert.equal(createQuestionPool([parsed]).length, 0);
});

test('book and chapter rules reject invalid/repeated actions and score only correct chapters', () => {
  const validatedSession = new GameSession([testQuestion('validated')], { bookIds: ['hp-1', 'hp-2'] });
  validatedSession.start();
  assert.equal(validatedSession.answerBook('not-a-book').accepted, false);
  assert.equal(validatedSession.answerBook('hp-2').correct, false);

  const session = new GameSession([testQuestion('one'), testQuestion('two')]);
  assert.equal(session.answerBook('hp-1').accepted, false);
  assert.equal(session.start().question.id, 'one');
  const correctBook = session.answerBook('hp-1');
  assert.equal(correctBook.correct, true);
  assert.equal(correctBook.score, 0);
  assert.equal(session.answerBook('hp-1').accepted, false);
  assert.equal(session.answerChapter('chapter-a').accepted, false);
  assert.equal(session.beginChapterSelection().accepted, true);
  assert.equal(session.answerChapter('not-a-choice').accepted, false);
  assert.equal(session.answerChapter('chapter-a').score, 1);
  assert.equal(session.answerChapter('chapter-a').accepted, false);
  assert.equal(session.advance().question.id, 'two');
  assert.equal(session.snapshot().score, 1);
});

test('wrong book ends a run, but a wrong chapter continues without a point', () => {
  const session = new GameSession([testQuestion('one'), testQuestion('two')]);
  session.start();
  assert.equal(session.answerBook('wrong').outcome, 'book-incorrect');
  assert.equal(session.advance().result.reason, 'incorrect-book');
  assert.equal(session.answerBook('hp-1').accepted, false);

  const secondSession = new GameSession([testQuestion('one'), testQuestion('two')]);
  secondSession.start();
  secondSession.answerBook('hp-1');
  secondSession.beginChapterSelection();
  assert.equal(secondSession.answerChapter('other-chapter').score, 0);
  assert.equal(secondSession.advance().question.id, 'two');
  assert.equal(secondSession.snapshot().score, 0);
});

test('exhausting every question completes normally with no stale passage result', () => {
  const session = new GameSession([testQuestion('only')]);
  session.start();
  session.answerBook('hp-1');
  session.beginChapterSelection();
  session.answerChapter('chapter-a');
  const completion = session.advance();
  assert.equal(completion.completed, true);
  assert.deepEqual(completion.result, { reason: 'pool-exhausted', score: 1, question: null });
  assert.equal(session.answerBook('hp-1').accepted, false);
});

test('high-score storage is compatible, safe on malformed values, and strictly greater for records', () => {
  const values = new Map([['hpOracle_hs', '8']]);
  const storage = {
    getItem: key => values.get(key) ?? null,
    setItem: (key, value) => values.set(key, value)
  };
  const scores = createHighScoreStore(storage);
  assert.equal(scores.read(), 8);
  assert.equal(scores.record(8).isRecord, false);
  assert.deepEqual(scores.record(9), { isRecord: true, highScore: 9 });
  assert.equal(values.get('hpOracle_hs'), '9');
  values.set('hpOracle_hs', 'not-a-score');
  assert.equal(scores.read(), 0);

  const unavailable = createHighScoreStore(() => { throw new Error('blocked'); });
  assert.equal(unavailable.read(), 0);
  assert.deepEqual(unavailable.record(2), { isRecord: true, highScore: 2 });
  const writeFailure = createHighScoreStore({ getItem: () => '0', setItem: () => { throw new Error('blocked'); } });
  writeFailure.read();
  assert.deepEqual(writeFailure.record(1), { isRecord: true, highScore: 1 });
});

test('restarting reuses the loaded library and old delayed transitions cannot affect the new run', async () => {
  let libraryLoads = 0;
  const timers = [];
  const questions = [
    { id: 'first', bookId: 'hp-1', chapterId: 'a', chapterTitle: 'A', passage: 'one', chapterChoices: [] },
    { id: 'second', bookId: 'hp-1', chapterId: 'b', chapterTitle: 'B', passage: 'two', chapterChoices: [] }
  ];
  const library = [{
    ...BOOKS[0],
    chapters: questions.map(question => ({
      id: question.chapterId,
      title: question.chapterTitle,
      paragraphs: [{ id: question.id, text: question.id === 'first' ? 'A'.repeat(120) : 'B'.repeat(120) }]
    }))
  }];
  const view = {
    showLoading() {},
    updateLoading() {},
    showQuestion() {},
    showBookAnswer() {},
    showLoadError() {},
    showResults() {}
  };
  const controller = new GameController(view, {
    document: { baseURI: 'https://example.test/games/hpchapter/' },
    highScoreStore: { read: () => 0, record: score => ({ isRecord: false, highScore: score }) },
    loadLibraryImpl: async () => {
      libraryLoads += 1;
      return { books: library, failures: [] };
    },
    setTimeoutImpl: callback => {
      const timer = { callback, cancelled: false };
      timers.push(timer);
      return timer;
    },
    clearTimeoutImpl: timer => { timer.cancelled = true; },
    wait: async () => {},
    random: () => 0
  });

  await controller.load();
  const oldQuestionId = controller.session.snapshot().currentQuestion.id;
  controller.answerBook('hp-1');
  const oldTimer = timers[0];
  controller.restart();
  const restartedQuestion = controller.session.snapshot().currentQuestion.id;
  oldTimer.callback();
  assert.equal(oldTimer.cancelled, true);
  assert.equal(controller.session.snapshot().state, 'book-selection');
  assert.equal(controller.session.snapshot().currentQuestion.id, restartedQuestion);
  assert.equal(libraryLoads, 1);
  assert.ok(['first', 'second'].includes(oldQuestionId));
});

test('all legacy entry URLs load the same external stylesheet and module bootstrap', async () => {
  for (const page of ['index.html', 'new.html', 'hpchapter.html']) {
    const html = await readFile(resolve(root, page), 'utf8');
    assert.match(html, /href="\.\/style\.css"/);
    assert.match(html, /type="module" src="\.\/script\.js"/);
    assert.match(html, /jszip\.min\.js/);
    for (const id of ['loadingScreen', 'errorScreen', 'quizScreen', 'gameoverScreen']) {
      assert.match(html, new RegExp(`id="${id}"`));
    }
  }
  assert.match(await readFile(resolve(root, 'script.js'), 'utf8'), /import '\.\/src\/main\.js'/);
  for (const book of BOOKS) {
    assert.ok((await stat(resolve(root, book.assetPath))).size > 0, `${book.assetPath} exists`);
  }
});
