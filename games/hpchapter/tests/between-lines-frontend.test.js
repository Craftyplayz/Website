import test from 'node:test';
import assert from 'node:assert/strict';
import { OnlineController } from '../src/application/online-controller.js';
import { OnlineView } from '../src/ui/online-view.js';
import { renderDescriptorQuestion } from '../src/ui/descriptor-question.js';
import { MODES, modeDescription, validateSelection } from '../src/modes/rules.js';
import { leaderboardRows } from '../src/services/leaderboards.js';

function question(extra = {}) {
  return { runId: 'private-run', name: 'Reader', mode: 'between-the-lines',
    state: 'descriptor-selection', score: 0, answered: 0, questionCount: 2,
    question: { id: 'opaque-one', passage: '“Stop!” the wizard ______.\n\nThe room fell quiet.' },
    result: null, ...extra };
}
function harness() {
  const calls = [], tasks = new Map();
  let snapshot = question(), controller, id = 0;
  const api = { call: async (action, body, query) => {
    calls.push({ action, body, query });
    if (action === 'config') return { duration: 60, contextLimit: 150 };
    if (action === 'preview') return { boards: { 'between-the-lines': [] } };
    if (action === 'create') return question({ state: 'ready', question: null });
    if (action === 'start') return snapshot;
    if (action === 'answer') {
      snapshot = { ...snapshot, state: 'answer-feedback', answered: snapshot.answered + 1,
        score: snapshot.score + Number(body.answerId === 'shouted'),
        feedback: { type: 'descriptor', descriptor: 'shouted', correct: body.answerId === 'shouted' } };
    }
    if (action === 'next') {
      snapshot = snapshot.answered === snapshot.questionCount
        ? { ...snapshot, state: 'completed', question: null,
          result: { accepted: true, score: snapshot.score, answered: snapshot.answered,
            accuracy: snapshot.score / snapshot.answered, reason: 'pool-exhausted', rank: 1 } }
        : { ...snapshot, state: 'descriptor-selection', feedback: null,
          question: { id: 'opaque-two', passage: '“Please!” the wizard ______. It was quiet.' } };
    }
    if (action === 'leaderboard') return { entries: [] };
    return snapshot;
  } };
  controller = new OnlineController({ render() {}, updateTimer() {} }, {
    api, requestId: () => 'request-uuid',
    setTimeoutImpl: callback => { tasks.set(++id, callback); return id; },
    clearTimeoutImpl: key => tasks.delete(key)
  });
  return { controller, api, calls, tasks };
}
async function start(h) {
  await h.controller.init();
  h.controller.selectMode('between-the-lines');
  h.controller.setName('Reader');
  await h.controller.start();
}
function node(tag, text = '', className = '') {
  return { tag, textContent: text, className, children: [], attributes: {}, events: {}, dataset: {},
    append(...children) { this.children.push(...children); },
    setAttribute(name, value) { this.attributes[name] = value; },
    addEventListener(name, callback) { this.events[name] = callback; }
  };
}
function find(root, id) {
  if (root.id === id) return root;
  return root.children?.map(child => find(child, id)).find(Boolean);
}
function text(root) { return root.textContent + (root.children || []).map(text).join(''); }
function viewHarness() {
  const document = { querySelector: () => ({}), createElement: tag => node(tag),
    createTextNode: value => node('#text', value) };
  const view = new OnlineView(document);
  const calls = [];
  view.controller = {
    answer: (...args) => calls.push(args), next: () => calls.push(['next']),
    menu() {}, selectMode: mode => calls.push(['mode', mode]), loadPreview() {},
    setName() {}, start() {}, leaderboard() {}
  };
  return { view, calls, screen: node('section') };
}

test('Between the Lines is the fifth registered mode with descriptor rules and keyboard menu card', () => {
  assert.equal(MODES.length, 5);
  assert.equal(MODES[4].id, 'between-the-lines');
  assert.equal(validateSelection('between-the-lines', []), '');
  assert.match(modeDescription('between-the-lines'), /not the book or chapter/);
  const h = viewHarness();
  h.view.menu(h.screen, { mode: 'between-the-lines', config: { duration: 60, contextLimit: 150 },
    configStatus: 'ready', name: '', preview: {}, previewStatus: 'ready' });
  const card = find(h.screen, 'mode-between-the-lines');
  assert.equal(card.tag, 'button');
  assert.equal(card.type, 'button');
  assert.equal(card.attributes['aria-pressed'], 'true');
  assert.match(text(card), /Type the missing expressive descriptor/);
  card.events.click();
  assert.deepEqual(h.calls[0], ['mode', 'between-the-lines']);
});
test('start uses shared server run and no book selection or countdown', async () => {
  const h = harness();
  await start(h);
  assert.equal(h.controller.model.screen, 'quiz');
  assert.equal(h.controller.model.snapshot.state, 'descriptor-selection');
  assert.equal(h.controller.timer.active, false);
  assert.deepEqual(h.calls.find(call => call.action === 'create').body, { name: 'Reader', mode: 'between-the-lines' });
});
test('empty descriptor is blocked; trimmed answer includes opaque current question ID, never a score', async () => {
  const h = harness();
  await start(h);
  await h.controller.answer('descriptor', '  ');
  assert.match(h.controller.model.error, /Type a descriptor/);
  assert.equal(h.calls.some(call => call.action === 'answer'), false);
  await h.controller.answer('descriptor', '  shouted  ');
  assert.deepEqual(h.calls.find(call => call.action === 'answer').body,
    { runId: 'private-run', type: 'descriptor', answerId: 'shouted', requestId: 'request-uuid', questionId: 'opaque-one' });
});
test('correct answer awards once and requires explicit next; incorrect answer awards zero', async () => {
  const h = harness();
  await start(h);
  await h.controller.answer('descriptor', 'shouted');
  await h.controller.answer('descriptor', 'shouted');
  assert.equal(h.controller.model.snapshot.score, 1);
  assert.equal(h.calls.filter(call => call.action === 'answer').length, 1);
  assert.equal(h.tasks.size, 0);
  assert.equal(h.controller.model.pending, false);
  assert.equal(h.controller.model.answerDraft, 'shouted');
  await h.controller.next();
  assert.equal(h.controller.model.answerDraft, '');
  assert.equal(h.controller.model.snapshot.question.id, 'opaque-two');
  await h.controller.answer('descriptor', 'said');
  assert.equal(h.controller.model.snapshot.score, 1);
  assert.equal(h.controller.model.snapshot.answered, 2);
  assert.equal(h.controller.model.screen, 'quiz');
  await h.controller.next();
  assert.equal(h.controller.model.screen, 'result');
  assert.equal(h.controller.model.snapshot.result.accuracy, 0.5);
  assert.ok(h.calls.some(call => call.action === 'leaderboard' && call.query.mode === 'between-the-lines'));
  await h.controller.answer('descriptor', 'shouted');
  assert.equal(h.controller.model.snapshot.score, 1);
});
test('pending descriptor requests and Next clicks cannot be repeated', async () => {
  const h = harness();
  await start(h);
  let release;
  const original = h.api.call;
  h.api.call = (action, ...args) => action === 'answer'
    ? new Promise(resolve => { release = resolve; }) : original(action, ...args);
  const pending = h.controller.answer('descriptor', 'shouted');
  await h.controller.answer('descriptor', 'shouted');
  await h.controller.next();
  assert.equal(h.controller.model.pending, true);
  release(question({ state: 'answer-feedback', answered: 1, score: 1,
    feedback: { type: 'descriptor', descriptor: 'shouted', correct: true } }));
  await pending;
  let releaseNext, nextCalls = 0;
  h.api.call = action => {
    if (action === 'next') { nextCalls++; return new Promise(resolve => { releaseNext = resolve; }); }
    return original(action);
  };
  const next = h.controller.next();
  await h.controller.next();
  assert.equal(nextCalls, 1);
  releaseNext(question({ question: { id: 'second', passage: '______' } }));
  await next;
});
test('abandon and restart invalidate late descriptor responses and do not schedule stale callbacks', async () => {
  for (const destination of ['menu', 'restart']) {
    const h = harness();
    await start(h);
    const original = h.api.call;
    let release;
    h.api.call = (action, ...args) => action === 'answer'
      ? new Promise(resolve => { release = resolve; }) : original(action, ...args);
    const pending = h.controller.answer('descriptor', 'shouted');
    h.controller.menu();
    if (destination === 'restart') {
      await h.controller.loadConfig();
      await h.controller.start();
    }
    release(question({ state: 'answer-feedback', score: 99,
      feedback: { type: 'descriptor', descriptor: 'shouted', correct: true } }));
    await pending;
    assert.equal(h.controller.model.screen, destination === 'menu' ? 'menu' : 'quiz');
    assert.notEqual(h.controller.model.snapshot?.score, 99);
    assert.equal(h.tasks.size, 0);
  }
});
test('lost answer response reconciles through shared state without re-awarding', async () => {
  const h = harness();
  await start(h);
  const original = h.api.call;
  h.api.call = async (action, ...args) => {
    if (action === 'answer') { await original(action, ...args); throw new Error('lost response'); }
    return original(action, ...args);
  };
  await h.controller.answer('descriptor', 'shouted');
  assert.equal(h.controller.model.snapshot.score, 1);
  assert.equal(h.controller.model.snapshot.state, 'answer-feedback');
  assert.equal(h.controller.model.pending, false);
  assert.equal(h.calls.filter(call => call.action === 'answer').length, 1);
});
test('unrecorded answer failure keeps draft and passage and explains that resubmission is needed', async () => {
  const h = harness();
  await start(h);
  const original = h.api.call;
  h.api.call = async (action, ...args) => {
    if (action === 'answer') throw new Error('Network interrupted');
    return original(action, ...args);
  };
  await h.controller.answer('descriptor', 'shouted');
  assert.equal(h.controller.model.snapshot.state, 'descriptor-selection');
  assert.equal(h.controller.model.answerDraft, 'shouted');
  assert.match(h.controller.model.error, /not recorded.*Submit again/);
  assert.match(h.controller.model.snapshot.question.passage, /______/);
});
test('descriptor leaderboard metadata distinguishes answered pool sizes and source accuracy', () => {
  const rows = leaderboardRows([{ score: 13, answered: 20, questionCount: 20 }, { score: 1, answered: 2, questionCount: 2 }]);
  assert.equal(rows[0].questions, '20/20 · 65%');
  assert.equal(rows[1].questions, '2/2 · 50%');
});
test('one-question run resolves before finalizing and saves only the correct leaderboard', async () => {
  const h = harness();
  await start(h);
  const single = question({ questionCount: 1 });
  h.controller.apply(single);
  const original = h.api.call;
  h.api.call = async (action, ...args) => {
    if (action === 'answer') return { ...single, score: 1, answered: 1, state: 'answer-feedback',
      feedback: { type: 'descriptor', correct: true, descriptor: 'shouted' } };
    if (action === 'next') return { ...single, question: null, state: 'completed',
      result: { accepted: true, answered: 1, score: 1, accuracy: 1, rank: 1 } };
    return original(action, ...args);
  };
  await h.controller.answer('descriptor', 'shouted');
  assert.equal(h.controller.model.screen, 'quiz');
  await h.controller.next();
  assert.equal(h.controller.model.snapshot.result.accepted, true);
  assert.equal(h.controller.model.screen, 'result');
});
test('descriptor UI preserves context, labels input, submits Enter through form and reveals only after resolution', () => {
  const h = viewHarness();
  const model = { snapshot: question(), answerDraft: '', pending: false };
  renderDescriptorQuestion(h.view, h.screen, model);
  assert.match(text(find(h.screen, 'passageCard')), /______.*The room fell quiet/s);
  assert.equal(find(h.screen, 'descriptorAnswer').disabled, false);
  assert.equal(find(h.screen, 'descriptorHint').id, 'descriptorHint');
  assert.equal(find(h.screen, 'descriptorAnswer').attributes['aria-describedby'], 'descriptorHint');
  assert.equal(find(h.screen, 'nextQuestionButton'), undefined);
  const form = h.screen.children.find(child => child.tag === 'form');
  assert.ok(form.children.some(child => child.htmlFor === 'descriptorAnswer'));
  const input = find(h.screen, 'descriptorAnswer');
  input.value = 'shouted';
  input.events.input();
  assert.equal(model.answerDraft, 'shouted');
  let prevented = false;
  form.events.submit({ preventDefault() { prevented = true; } });
  assert.equal(prevented, true);
  assert.deepEqual(h.calls[0], ['descriptor', 'shouted']);
  assert.doesNotMatch(text(find(h.screen, 'feedbackBar')), /shouted/);
});
test('resolved UI locks input, renders source descriptor literally and offers finish on last question', () => {
  const h = viewHarness();
  renderDescriptorQuestion(h.view, h.screen, { pending: false, answerDraft: 'wrong',
    snapshot: question({ state: 'answer-feedback', answered: 2,
      feedback: { correct: false, descriptor: '<script>never HTML</script>' } }) });
  assert.equal(find(h.screen, 'descriptorAnswer').disabled, true);
  assert.equal(find(h.screen, 'submitDescriptor').disabled, true);
  assert.match(text(find(h.screen, 'feedbackBar')), /Original descriptor: <script>never HTML<\/script>/);
  assert.equal(find(h.screen, 'nextQuestionButton').textContent, 'Finish challenge');
  find(h.screen, 'nextQuestionButton').events.click();
  assert.deepEqual(h.calls[0], ['next']);
});
test('feedback progress stays on the resolved question until Next', () => {
  const h = viewHarness();
  renderDescriptorQuestion(h.view, h.screen, { pending: false, answerDraft: 'wrong',
    snapshot: question({ state: 'answer-feedback', answered: 1,
      feedback: { correct: false, descriptor: 'shouted' } }) });
  assert.equal(find(h.screen, 'questionProgress').textContent, 'Question 1 of 2 · 1 answered');
});
test('result shows source accuracy, answered count and confirmed rank without depending on accuracy units', () => {
  const h = viewHarness();
  h.view.result(h.screen, { pending: false, rank: 1, rankStatus: 'ready',
    snapshot: question({ state: 'completed', question: null,
      result: { accepted: true, answered: 20, score: 19, accuracy: 95, reason: 'pool-exhausted' } }) });
  assert.match(text(h.screen), /20 questions answered · 95% accuracy/);
  assert.match(text(h.screen), /Global rank: #1/);
  assert.match(text(h.screen), /All questions in this run completed/);
  assert.match(text(h.screen), /Accepted by the server/);
});
