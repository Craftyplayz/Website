import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { OnlineController } from '../src/application/online-controller.js';
import { Countdown } from '../src/timer/countdown.js';
import { ApiError, createApiClient } from '../src/services/api-client.js';
import { validateName, validateSelection, validateServerConfig, minimumContext, modeDescription } from '../src/modes/rules.js';
import { leaderboardRows, globalRank } from '../src/services/leaderboards.js';
import { createRequestId } from '../src/services/request-id.js';
import { OnlineView, libraryFailureMessages } from '../src/ui/online-view.js';

const deferred = () => {
  let resolve, reject;
  const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
  return { promise, resolve, reject };
};
const flush = async () => { for (let i = 0; i < 10; i++) await Promise.resolve(); };
const snapshot = (extra = {}) => ({
  runId: 'run-1', mode: 'normal', name: 'Hermione', duration: 60, contextLimit: 150,
  state: 'book-selection', score: 0, elapsedMs: 0, remainingMs: 60000,
  question: { id: 'q1', passage: 'A server-selected passage.' }, result: null, ...extra
});
function clock() {
  let time = 0, id = 0;
  const tasks = new Map(), cancelled = [];
  return {
    now: () => time,
    setTimeoutImpl: (fn, delay) => { tasks.set(++id, { fn, due: time + delay }); return id; },
    clearTimeoutImpl: key => { if (tasks.has(key)) cancelled.push(tasks.get(key).fn); tasks.delete(key); },
    async advance(ms) {
      time += ms;
      const due = [...tasks].filter(([, task]) => task.due <= time);
      for (const [key, task] of due) { tasks.delete(key); task.fn(); }
      await flush();
    },
    jump: ms => { time += ms; },
    tasks, cancelled
  };
}
function harness(handler = () => {}) {
  const calls = [], renders = [], ticks = [], time = clock();
  const api = { call: async (action, body, query) => {
    calls.push({ action, body, query });
    if (action === 'config') return { duration: 60, contextLimit: 150 };
    if (action === 'preview') return { boards: { normal: [], timed: [], minimum: [], specific: [] } };
    if (action === 'create') return snapshot({ state: 'ready', mode: body.mode, name: body.name });
    if (action === 'start') return snapshot({ mode: controller.model.mode });
    if (action === 'leaderboard') return { entries: [] };
    return handler(action, body, query);
  } };
  const controller = new OnlineController({
    render: model => renders.push({ screen: model.screen, pending: model.pending }),
    updateTimer: ms => ticks.push(ms)
  }, { api, ...time, requestId: () => 'request-unique' });
  return { controller, api, calls, renders, ticks, time };
}

test('initial screen is menu and only config/preview load before player start', async () => {
  const h = harness();
  await h.controller.init();
  assert.equal(h.controller.model.screen, 'menu');
  assert.deepEqual(h.calls.map(call => call.action), ['config', 'preview']);
  assert.equal(h.controller.timer.active, false);
});
test('mode selection retains specific books in memory; normal omits selectedBooks', async () => {
  const h = harness();
  await h.controller.init();
  h.controller.selectMode('specific');
  h.controller.selectBooks(['hp-3']);
  h.controller.selectMode('normal');
  h.controller.setName('  Harry  ');
  await h.controller.start();
  assert.deepEqual(h.calls.find(call => call.action === 'create').body, { mode: 'normal', name: 'Harry' });
  assert.deepEqual(h.controller.model.selectedBooks, ['hp-3']);
});
test('specific requires a selected book and sends canonical IDs only', async () => {
  const h = harness();
  await h.controller.init();
  h.controller.setName('魔法🪄');
  h.controller.selectMode('specific');
  h.controller.selectBooks([]);
  await h.controller.start();
  assert.match(h.controller.model.error, /at least one/);
  assert.equal(h.calls.some(call => call.action === 'create'), false);
  h.controller.selectBooks(['hp-2', 'invalid']);
  await h.controller.start();
  assert.deepEqual(h.calls.find(call => call.action === 'create').body.selectedBooks, ['hp-2']);
});
test('public names trim Unicode, count codepoints, and reject controls before trim', () => {
  assert.deepEqual(validateName('  🧙'.trimEnd() + '  '), { name: '🧙' });
  assert.equal(validateName('🪄'.repeat(20)).name, '🪄'.repeat(20));
  for (const name of ['', '   ', '🪄'.repeat(21), 'x\n', 'x\u0000', 'x\u202e']) assert.ok(validateName(name).error);
  assert.equal(validateName('<img src=x>').name, '<img src=x>');
});
test('minimum context is Unicode safe and mode descriptions use official config', () => {
  assert.equal(minimumContext('🪄'.repeat(151), 150), '🪄'.repeat(150));
  assert.match(modeDescription('minimum', { contextLimit: 123, duration: 42 }), /123 Unicode/);
  assert.match(modeDescription('timed', { contextLimit: 123, duration: 42 }), /42 seconds/);
  assert.match(modeDescription('normal'), /wrong book: game over/i);
  assert.equal(validateSelection('specific', []), 'Select at least one book.');
});
test('menu invalidates delayed create and abandons orphan without starting it', async () => {
  const h = harness();
  await h.controller.init();
  const pending = deferred();
  const original = h.api.call;
  h.api.call = (action, ...args) => action === 'create' ? pending.promise : original(action, ...args);
  h.controller.setName('Ron');
  const start = h.controller.start();
  h.controller.menu();
  pending.resolve(snapshot({ state: 'ready' }));
  await start;
  assert.equal(h.controller.model.screen, 'menu');
  assert.equal(h.calls.some(call => call.action === 'start'), false);
  assert.equal(h.calls.some(call => call.action === 'abandon'), true);
});
test('late start response cannot replace menu', async () => {
  const h = harness();
  await h.controller.init();
  const pending = deferred(), original = h.api.call;
  h.api.call = (action, ...args) => action === 'start' ? pending.promise : original(action, ...args);
  h.controller.setName('Ron');
  const start = h.controller.start();
  await flush();
  h.controller.menu();
  pending.resolve(snapshot());
  await start;
  assert.equal(h.controller.model.screen, 'menu');
});
test('timed countdown starts only on first playable response', async () => {
  const h = harness();
  await h.controller.init();
  const pending = deferred(), original = h.api.call;
  h.api.call = (action, ...args) => action === 'start' ? pending.promise : original(action, ...args);
  h.controller.selectMode('timed');
  h.controller.setName('Harry');
  const start = h.controller.start();
  await flush();
  assert.equal(h.controller.timer.active, false);
  h.time.jump(8000);
  pending.resolve(snapshot({ mode: 'timed', remainingMs: 59950 }));
  await start;
  assert.equal(h.controller.timer.remaining(), 59950);
});
test('monotonic deadline blocks answer immediately even before timeout callback', async () => {
  const h = harness(action => action === 'finish' ? snapshot({ mode: 'timed', state: 'completed',
    result: { accepted: true, score: 0, reason: 'time-expired', elapsedMs: 60000 } }) : undefined);
  await h.controller.init();
  h.controller.setName('Harry');
  h.controller.selectMode('timed');
  await h.controller.start();
  h.time.jump(60001);
  await h.controller.answer('book', 'hp-1');
  await flush();
  assert.equal(h.calls.some(call => call.action === 'answer'), false);
  assert.equal(h.controller.model.snapshot.result.score, 0);
  assert.equal(h.controller.model.screen, 'result');
  assert.equal(h.time.tasks.size, 0);
});
test('duplicate answers are blocked and chapter choice waits 900ms', async () => {
  const pending = deferred();
  const h = harness(action => action === 'answer' ? pending.promise : undefined);
  await h.controller.init();
  h.controller.setName('Harry');
  await h.controller.start();
  const answer = h.controller.answer('book', 'hp-1');
  await h.controller.answer('book', 'hp-1');
  assert.equal(h.calls.filter(call => call.action === 'answer').length, 1);
  assert.equal(h.calls.find(call => call.action === 'answer').body.requestId, 'request-unique');
  pending.resolve(snapshot({ state: 'chapter-selection', question: { id: 'q1', passage: 'Passage', chapterChoices: [{ id: 'c1', title: 'Chapter' }] },
    feedback: { type: 'book', correct: true, correctBookId: 'hp-1' } }));
  await answer;
  assert.equal(h.controller.model.pending, true);
  await h.time.advance(899);
  assert.equal(h.controller.model.pending, true);
  await h.time.advance(1);
  assert.equal(h.controller.model.pending, false);
});
test('chapter feedback advances only through server next after 1200/2200ms', async () => {
  for (const correct of [true, false]) {
    const h = harness(action => action === 'answer' ? snapshot({ state: 'answer-feedback', score: correct ? 1 : 0,
      feedback: { type: 'chapter', correct, chapterTitle: 'Chapter', correctChapterId: 'c1' } }) : snapshot({ question: { id: 'q2', passage: 'Next' } }));
    await h.controller.init();
    h.controller.setName('Harry');
    await h.controller.start();
    h.controller.apply(snapshot({ state: 'chapter-selection', question: { chapterChoices: [{ id: 'c1', title: 'Chapter' }] } }));
    await h.controller.answer('chapter', 'c1');
    const delay = correct ? 1200 : 2200;
    await h.time.advance(delay - 1);
    assert.equal(h.calls.some(call => call.action === 'next'), false);
    await h.time.advance(1);
    assert.equal(h.calls.some(call => call.action === 'next'), true);
    assert.equal(h.controller.model.snapshot.question.id, 'q2');
  }
});
test('stale feedback timeout and answer response cannot affect restarted run', async () => {
  const pending = deferred();
  const h = harness(action => action === 'answer' ? pending.promise : undefined);
  await h.controller.init();
  h.controller.setName('Harry');
  await h.controller.start();
  const answer = h.controller.answer('book', 'hp-1');
  h.controller.menu();
  await flush();
  await h.controller.start();
  pending.resolve(snapshot({ score: 99, state: 'chapter-selection' }));
  await answer;
  assert.equal(h.controller.model.snapshot.score, 0);
  h.controller.apply(snapshot({ state: 'chapter-selection', feedback: { type: 'book', correct: true } }));
  h.controller.menu();
  await flush();
  await h.controller.start();
  for (const callback of h.time.cancelled) callback();
  assert.equal(h.controller.model.snapshot.state, 'book-selection');
  assert.equal(h.time.tasks.size, 0);
});
test('ambiguous answer recovers authoritative state instead of inventing points', async () => {
  const h = harness(action => {
    if (action === 'answer') throw new ApiError('lost', 'network');
    if (action === 'state') return snapshot({ state: 'chapter-selection', score: 0 });
  });
  await h.controller.init();
  h.controller.setName('Harry');
  await h.controller.start();
  await h.controller.answer('book', 'hp-1');
  assert.equal(h.controller.model.snapshot.state, 'chapter-selection');
  assert.equal(h.controller.model.snapshot.score, 0);
  assert.ok(h.calls.some(call => call.action === 'state'));
});
test('offline create errors offer retry and never fabricate an empty playable pool', async () => {
  const h = harness();
  await h.controller.init();
  const original = h.api.call;
  h.api.call = (action, ...args) => {
    if (action === 'create') return Promise.reject(new ApiError('no connection', 'offline'));
    return original(action, ...args);
  };
  h.controller.setName('Harry');
  await h.controller.start();
  assert.equal(h.controller.model.screen, 'error');
  assert.match(h.controller.model.error, /offline/);
  assert.equal(h.controller.timer.active, false);
  h.api.call = original;
  await h.controller.retry();
  assert.equal(h.controller.model.screen, 'quiz');
});
test('empty canonical pool server error remains explicit', async () => {
  const h = harness();
  await h.controller.init();
  const original = h.api.call;
  h.api.call = (action, ...args) => action === 'create'
    ? Promise.reject(new ApiError('No eligible passages in the selected books.')) : original(action, ...args);
  h.controller.setName('Harry');
  await h.controller.start();
  assert.match(h.controller.model.error, /No eligible passages/);
  assert.equal(h.controller.model.screen, 'error');
});
test('finish offline stays provisional; retry confirms server score and refreshes rank', async () => {
  let offline = true;
  const h = harness(action => {
    if (offline) throw new ApiError('offline', 'offline');
    if (action === 'finish') return snapshot({ state: 'completed', score: 2,
      result: { score: 2, accepted: true, reason: 'time-expired', elapsedMs: 60000, id: 42 } });
  });
  await h.controller.init();
  h.controller.setName('Harry');
  h.controller.selectMode('timed');
  await h.controller.start();
  await h.time.advance(60000);
  assert.equal(h.controller.model.screen, 'result');
  assert.equal(h.controller.model.provisional.accepted, false);
  assert.match(h.controller.model.error, /offline/);
  offline = false;
  await h.controller.confirmFinish();
  await flush();
  assert.equal(h.controller.model.snapshot.result.accepted, true);
  assert.equal(h.controller.model.snapshot.result.score, 2);
  assert.ok(h.calls.some(call => call.action === 'leaderboard'));
  assert.ok(h.calls.filter(call => call.action === 'finish').every(call => !('score' in call.body)));
});
test('leaderboard failures preserve entries and late responses do not overwrite another mode', async () => {
  const h = harness();
  await h.controller.init();
  const first = deferred(), second = deferred(), original = h.api.call;
  h.api.call = (action, body, query) => action === 'leaderboard'
    ? query.mode === 'normal' ? first.promise : second.promise : original(action, body, query);
  h.controller.model.boards.normal = [{ id: 1, name: 'Saved', score: 3 }];
  const normal = h.controller.leaderboard('normal');
  const timed = h.controller.leaderboard('timed');
  first.resolve({ entries: [{ name: 'Stale' }] });
  second.reject(new ApiError('Offline', 'offline'));
  await Promise.all([normal, timed]);
  assert.equal(h.controller.model.boardMode, 'timed');
  assert.equal(h.controller.model.boardStatus, 'offline');
  assert.equal(h.controller.model.boards.normal[0].name, 'Saved');
});
test('API uses relative URL, same origin, no score uploads, and distinct offline/server errors', async () => {
  let request;
  const api = createApiClient({ baseURI: 'https://example.test/games/hpchapter/new.html',
    fetchImpl: async (url, options) => { request = { url, options }; return { ok: true, json: async () => ({ duration: 60 }) }; } });
  await api.call('config');
  assert.equal(request.url.href, 'https://example.test/games/hpchapter/api/index.php?action=config');
  assert.equal(request.options.credentials, 'same-origin');
  assert.equal(request.options.method, 'GET');
  const offline = createApiClient({ baseURI: 'https://example.test/', online: () => false, fetchImpl: async () => { throw new Error(); } });
  await assert.rejects(offline.call('config'), error => error.kind === 'offline');
  const failed = createApiClient({ baseURI: 'https://example.test/', fetchImpl: async () => ({ ok: false, json: async () => ({ error: 'No passages' }) }) });
  await assert.rejects(failed.call('create', {}), /No passages/);
});
test('countdown uses deadline, not tick decrement, and invalidated ticks do nothing', async () => {
  const time = clock(), ticks = [];
  let expired = 0;
  const timer = new Countdown({ ...time, onTick: ms => ticks.push(ms), onExpire: () => expired++ });
  timer.sync(1000);
  time.jump(777);
  assert.equal(timer.remaining(), 223);
  timer.stop();
  for (const callback of time.cancelled) callback();
  assert.equal(expired, 0);
  assert.equal(ticks.length, 1);
  timer.sync(0);
  assert.equal(expired, 1);
});
test('safe view nodes and leaderboard names are literal text, and rank uses server order', () => {
  const document = { querySelector: () => ({}), createElement: tag => ({
    tag, set innerHTML(_) { throw new Error('Unsafe HTML'); }, set textContent(value) { this.text = value; }
  }) };
  const view = new OnlineView(document);
  const unsafe = '<img src=x onerror=alert(1)>';
  assert.equal(view.node('td', unsafe).text, unsafe);
  const rows = leaderboardRows([{ id: 9, name: unsafe, score: 2, elapsedMs: 1234, achievedAt: '2026-10-10' }]);
  assert.equal(rows[0].name, unsafe);
  assert.equal(rows[0].elapsed, '1.23s');
  assert.equal(globalRank([{ id: 8 }, { id: 9 }], { id: 9 }), 2);
  assert.equal(globalRank([], { rank: 101 }), 101);
});
test('confirmed wrong book shows server reveal before result and never submits client score', async () => {
  const h = harness(action => action === 'answer' ? snapshot({ state: 'completed',
    feedback: { type: 'book', correct: false, correctBookId: 'hp-2', chapterTitle: 'The Chamber' },
    result: { accepted: true, score: 0, reason: 'incorrect-book', elapsedMs: 5000 } }) : undefined);
  await h.controller.init();
  h.controller.setName('Harry');
  await h.controller.start();
  await h.controller.answer('book', 'hp-1');
  assert.equal(h.controller.model.screen, 'quiz');
  assert.equal(h.controller.model.pending, true);
  await h.time.advance(900);
  assert.equal(h.controller.model.screen, 'result');
  assert.equal(h.time.tasks.size, 0);
  assert.equal(h.calls.some(call => call.action === 'finish'), false);
});
test('timed expiry cancels pending answer response and feedback next callbacks', async () => {
  const answer = deferred();
  const h = harness(action => {
    if (action === 'answer') return answer.promise;
    if (action === 'finish') return snapshot({ state: 'completed', mode: 'timed',
      result: { accepted: true, score: 0, reason: 'time-expired', elapsedMs: 60000 } });
  });
  await h.controller.init();
  h.controller.setName('Harry');
  h.controller.selectMode('timed');
  await h.controller.start();
  const pending = h.controller.answer('book', 'hp-1');
  await h.time.advance(60000);
  answer.resolve(snapshot({ mode: 'timed', score: 99, state: 'chapter-selection' }));
  await pending;
  assert.equal(h.controller.model.snapshot.result.score, 0);
  assert.equal(h.controller.model.screen, 'result');
  assert.equal(h.time.tasks.size, 0);
});
test('partial canonical library notices and failures survive authoritative snapshots', async () => {
  const h = harness();
  await h.controller.init();
  const failures = [{ bookId: 'hp-2', message: '<unavailable archive>' }];
  h.controller.apply(snapshot({ notice: 'Some books could not be loaded.', failures }));
  assert.equal(h.controller.model.snapshot.notice, 'Some books could not be loaded.');
  assert.equal(libraryFailureMessages(failures)[0], 'Harry Potter and the Chamber of Secrets: <unavailable archive>');
  assert.deepEqual(libraryFailureMessages(), []);
});
test('optional CDN archive library and Google fonts cannot block entry bootstrap', async () => {
  for (const page of ['index.html', 'new.html', 'hpchapter.html']) {
    const html = await readFile(new URL(`../${page}`, import.meta.url), 'utf8');
    assert.match(html, /<script async defer src="https:\/\/cdnjs[^"]*jszip\.min\.js"/);
    assert.match(html, /fonts\.googleapis\.com[^"]*" rel="stylesheet" media="print"/);
  }
});
test('accepted status survives rank refresh failure and entries outside top100 never get a fake rank', async () => {
  const h = harness();
  await h.controller.init();
  const original = h.api.call;
  h.api.call = (action, ...args) => action === 'leaderboard'
    ? Promise.resolve({ entries: Array.from({ length: 100 }, (_, index) => ({ id: index + 1 })) })
    : original(action, ...args);
  h.controller.apply(snapshot({ state: 'completed',
    result: { id: 999, score: 5, accepted: true, reason: 'incorrect-book', elapsedMs: 10000 } }));
  await flush();
  assert.equal(h.controller.model.rank, null);
  assert.equal(h.controller.model.rankStatus, 'ready');
  assert.equal(h.controller.model.snapshot.result.accepted, true);
  h.api.call = async () => { throw new ApiError('Connection lost', 'network'); };
  await h.controller.refreshRank();
  assert.equal(h.controller.model.rank, null);
  assert.equal(h.controller.model.rankStatus, 'error');
  assert.equal(h.controller.model.snapshot.result.accepted, true);
  assert.match(modeDescription('timed', { duration: 90, contextLimit: 220 }), /90 seconds/);
  assert.match(modeDescription('minimum', { duration: 90, contextLimit: 220 }), /220 Unicode/);
});
test('default timer adapters preserve native Window receiver requirements', () => {
  const oldSet = globalThis.setTimeout, oldClear = globalThis.clearTimeout;
  let scheduled = 0, cleared = 0;
  globalThis.setTimeout = function () { assert.equal(this, globalThis); scheduled++; return 1; };
  globalThis.clearTimeout = function () { assert.equal(this, globalThis); cleared++; };
  try {
    const timer = new Countdown({ now: () => 0 });
    timer.sync(1000);
    timer.stop();
    const controller = new OnlineController({ render() {}, updateTimer() {} }, { api: {} });
    controller.cleanup();
    controller.schedule(() => {}, 900);
    controller.cleanup();
    assert.equal(scheduled, 2);
    assert.ok(cleared >= 4);
  } finally {
    globalThis.setTimeout = oldSet;
    globalThis.clearTimeout = oldClear;
  }
});
test('preparation renders ready snapshot library failures before starting the playable run', async () => {
  const h = harness(), preparations = [];
  await h.controller.init();
  const original = h.api.call;
  h.api.call = (action, ...args) => action === 'create'
    ? Promise.resolve(snapshot({ state: 'ready', notice: 'Partial library', failures: [{ bookId: 'hp-7', error: 'Unavailable' }] }))
    : original(action, ...args);
  h.controller.view.render = model => {
    if (model.screen === 'loading' && model.snapshot) preparations.push(model.snapshot);
  };
  h.controller.setName('Harry');
  await h.controller.start();
  assert.equal(preparations.length, 1);
  assert.equal(preparations[0].notice, 'Partial library');
  assert.equal(preparations[0].failures[0].bookId, 'hp-7');
});
test('historical leaderboard mode details use each stored context limit and duration', () => {
  const rows = leaderboardRows([
    { name: 'Old minimum', contextLimit: 150, duration: 60 },
    { name: 'New minimum', contextLimit: 220, duration: 90 },
    { name: 'Legacy missing metadata' }
  ]);
  assert.equal(rows[0].contextLimit, '150 characters');
  assert.equal(rows[1].contextLimit, '220 characters');
  assert.equal(rows[0].duration, '60s');
  assert.equal(rows[1].duration, '90s');
  assert.equal(rows[2].contextLimit, 'Not recorded');
  assert.equal(rows[2].duration, 'Not recorded');
});
test('GET config requires exact fields and integer values within supported owner-configurable ranges', async () => {
  for (const config of [
    null, [], {}, { duration: '60', contextLimit: 150 }, { duration: 60, contextLimit: '150' },
    { duration: 60.5, contextLimit: 150 }, { duration: 60, contextLimit: 150.5 },
    { duration: 9, contextLimit: 150 }, { duration: 601, contextLimit: 150 },
    { duration: 60, contextLimit: 59 }, { duration: 60, contextLimit: 501 },
    { duration: 60, contextLimit: 150, extra: true }
  ]) assert.equal(validateServerConfig(config), false);
  for (const config of [
    { duration: 60, contextLimit: 150 }, { duration: 90, contextLimit: 220 },
    { duration: 10, contextLimit: 60 }, { duration: 600, contextLimit: 500 }
  ]) assert.equal(validateServerConfig(config), true);
  const h = harness(), original = h.api.call;
  h.api.call = (action, ...args) => action === 'config'
    ? Promise.resolve({ duration: '60', contextLimit: 150 }) : original(action, ...args);
  await h.controller.init();
  assert.equal(h.controller.model.configStatus, 'error');
  assert.match(h.controller.model.configError, /unsupported server rules/);
  h.controller.setName('Harry');
  await h.controller.start();
  assert.equal(h.calls.some(call => call.action === 'create'), false);
});
test('rank uses public entryId, prefers fresh server order, and never infers from runId', () => {
  assert.equal(globalRank([{ id: 8 }, { id: 9 }], { entryId: 9, rank: 1 }), 2);
  assert.equal(globalRank([], { entryId: 101, rank: 101 }), 101);
  assert.equal(globalRank([{ id: 'run-1' }], {}, 'run-1'), null);
  assert.equal(globalRank([{ id: undefined }], {}), null);
  assert.equal(globalRank([], { rank: -1 }), null);
  assert.equal(globalRank([], { rank: 0 }), null);
});
test('returning to menu refreshes owner settings without changing an existing run clock', async () => {
  const h = harness(), original = h.api.call;
  let updated = false;
  h.api.call = (action, body, query) => {
    if (updated && action === 'config') return Promise.resolve({ duration: 90, contextLimit: 220 });
    if (updated && action === 'create') return Promise.resolve(snapshot({ state: 'ready', mode: body.mode, duration: 90, contextLimit: 220, remainingMs: 90000 }));
    if (updated && action === 'start') return Promise.resolve(snapshot({ mode: 'timed', duration: 90, contextLimit: 220, remainingMs: 90000 }));
    return original(action, body, query);
  };
  await h.controller.init();
  h.controller.setName('Harry');
  h.controller.selectMode('timed');
  await h.controller.start();
  assert.equal(h.controller.timer.remaining(), 60000);
  updated = true;
  await h.controller.loadConfig();
  assert.equal(h.controller.model.config.duration, 90);
  assert.equal(h.controller.model.snapshot.duration, 60);
  assert.equal(h.controller.timer.remaining(), 60000);
  h.controller.menu();
  assert.equal(h.controller.model.configStatus, 'loading');
  await flush();
  assert.equal(h.controller.model.config.contextLimit, 220);
  await h.controller.start();
  assert.equal(h.controller.model.snapshot.duration, 90);
  assert.equal(h.controller.timer.remaining(), 90000);
});
test('request UUID fallback works without secure-context randomUUID and sets v4/variant bits', () => {
  const crypto = {
    getRandomValues(bytes) {
      assert.equal(this, crypto);
      bytes.fill(255);
      return bytes;
    }
  };
  const id = createRequestId(crypto);
  assert.equal(id, 'ffffffff-ffff-4fff-bfff-ffffffffffff');
  assert.match(id, /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
  const native = { randomUUID() { assert.equal(this, native); return 'native-uuid'; } };
  assert.equal(createRequestId(native), 'native-uuid');
  assert.throws(() => createRequestId({}), /secure request identifier/);
});
