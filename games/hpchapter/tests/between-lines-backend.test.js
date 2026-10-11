import test from 'node:test';
import assert from 'node:assert/strict';
import { spawn, execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { mkdir, rm } from 'node:fs/promises';
import { createServer } from 'node:net';
import { randomUUID } from 'node:crypto';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const execute = promisify(execFile);
const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');

test('Between the Lines native PHP extraction, matching, provenance, migration and finite-run suite', async t => {
  const runtime = resolve(root, '../../../', `.hpchapter-btl-native-${randomUUID()}`);
  await mkdir(runtime, { mode: 0o700 });
  try {
    const { stdout } = await execute('php', [resolve(root, 'tests/between-lines-backend.test.php'), runtime], {
      maxBuffer: 1024 * 1024,
      timeout: 120000
    });
    const report = JSON.parse(stdout);
    assert.ok(report.assertions > 20000);
    assert.equal(Object.keys(report.counts).length, 7);
    assert.equal(report.totalCandidates, Object.values(report.counts).reduce((sum, count) => sum + count, 0));
    assert.equal(report.runQuestions, 20);
    assert.equal(report.score, 10);
    t.diagnostic(JSON.stringify(report));
  } finally {
    await rm(runtime, { recursive: true, force: true });
  }
});

test('Between the Lines HTTP contract: bound answers, no keys, finite run, persistence and shared boards', async () => {
  // Production rejects databases under the public repository/document root.
  const runtime = resolve(root, '../../../', `.hpchapter-btl-http-${randomUUID()}`);
  await mkdir(runtime, { mode: 0o700 });
  const database = resolve(runtime, 'http.sqlite');
  const socket = createServer();
  await new Promise(resolve => socket.listen(0, '127.0.0.1', resolve));
  const port = socket.address().port;
  await new Promise(resolve => socket.close(resolve));
  const server = spawn('php', ['-d', 'display_errors=0', '-S', `127.0.0.1:${port}`, '-t', resolve(root, 'api')], {
    cwd: root,
    env: { ...process.env, HPCHAPTER_DB_PATH: database },
    detached: true,
    stdio: 'ignore'
  });
  const url = `http://127.0.0.1:${port}/index.php`;
  const request = async (action, body) => {
    const response = await fetch(`${url}?action=${action}`, {
      method: body === undefined ? 'GET' : 'POST',
      headers: body === undefined ? {} : { 'Content-Type': 'application/json' },
      body: body === undefined ? undefined : JSON.stringify(body)
    });
    return { status: response.status, data: await response.json() };
  };
  try {
    let responsive = false;
    for (let i = 0; i < 100; i += 1) {
      try {
        const response = await request('config');
        if (response.status === 200) {
          assert.deepEqual(response.data, { duration: 60, contextLimit: 150 });
          responsive = true;
          break;
        }
      } catch {}
      await new Promise(resolve => setTimeout(resolve, 40));
    }
    assert.ok(responsive, 'PHP HTTP server is running and responsive');
    assert.deepEqual((await request('preview')).data.boards['between-the-lines'], []);
    const created = await request('create', { name: 'HTTP descriptor', mode: 'between-the-lines' });
    assert.equal(created.status, 200);
    const runId = created.data.runId;
    assert.equal(created.data.questionCount, 20);
    assert.equal(created.data.answered, 0);
    assert.equal(created.data.question, null);
    let snapshot = (await request('start', { runId })).data;
    assert.equal(snapshot.state, 'descriptor-selection');
    assert.deepEqual(Object.keys(snapshot.question), ['id', 'passage']);
    assert.equal(snapshot.question.passage.split('______').length, 2, 'one six-underscore blank matches the frontend renderer');
    const wrongStage = await request('answer', { runId, type: 'book', answerId: 'hp-1', requestId: randomUUID() });
    assert.equal(wrongStage.status, 409);
    const missingBinding = await request('answer', { runId, type: 'descriptor', answerId: 'whispered', requestId: randomUUID() });
    assert.equal(missingBinding.status, 400);
    const clientScore = await request('answer', { runId, type: 'descriptor', answerId: 'whispered', questionId: snapshot.question.id, requestId: randomUUID(), score: 100 });
    assert.equal(clientScore.status, 400);
    const seen = new Set();
    let previous;
    for (let i = 0; i < 20; i += 1) {
      assert.equal(snapshot.state, 'descriptor-selection');
      assert.equal(snapshot.answered, i);
      assert.deepEqual(Object.keys(snapshot.question), ['id', 'passage']);
      assert.match(snapshot.question.id, /^[a-f0-9]{64}$/);
      assert.ok(!seen.has(snapshot.question.id));
      seen.add(snapshot.question.id);
      if (previous) {
        assert.equal((await request('answer', { ...previous, requestId: randomUUID() })).status, 409);
      }
      const { stdout } = await execute('php', [resolve(root, 'tests/between-lines-backend.test.php'), '--question', database, runId]);
      const { descriptor } = JSON.parse(stdout);
      assert.ok(!snapshot.question.passage.includes(descriptor), 'canonical descriptor is absent before answer');
      const body = { runId, type: 'descriptor', answerId: ` “${descriptor.toUpperCase()}!” `, questionId: snapshot.question.id, requestId: randomUUID() };
      const response = await request('answer', body);
      assert.equal(response.status, 200);
      assert.equal(response.data.state, 'answer-feedback');
      assert.equal(response.data.result, null);
      assert.equal(response.data.score, i + 1);
      assert.equal(response.data.answered, i + 1);
      assert.deepEqual(response.data.feedback, { type: 'descriptor', correct: true, descriptor });
      const retry = (await request('answer', body)).data;
      assert.equal(retry.score, response.data.score);
      assert.equal(retry.answered, response.data.answered);
      assert.deepEqual(retry.feedback, response.data.feedback);
      assert.equal((await request('answer', { ...body, requestId: randomUUID() })).status, 409);
      snapshot = (await request('next', { runId })).data;
      assert.equal((await request('answer', body)).data.score, i + 1);
      previous = body;
    }
    assert.equal(snapshot.state, 'completed');
    assert.equal(snapshot.result.score, 20);
    assert.equal(snapshot.result.answered, 20);
    assert.equal(snapshot.result.questionCount, 20);
    assert.equal(snapshot.result.accuracy, 1);
    const afterEnd = await request('answer', { ...previous, requestId: randomUUID() });
    assert.equal(afterEnd.data.score, 20);
    assert.equal(afterEnd.data.answered, 20);
    const board = (await request('leaderboard&mode=between-the-lines')).data;
    assert.equal(board.entries.length, 1);
    assert.equal(board.entries[0].score, 20);
    assert.equal(board.entries[0].answered, 20);
    assert.equal(board.entries[0].questionCount, 20);
    assert.equal(board.entries[0].accuracy, 1);
    assert.deepEqual((await request('preview')).data.boards['between-the-lines'], board.entries);
  } finally {
    if (server.exitCode === null) {
      server.kill('SIGTERM');
      await new Promise(resolve => server.once('exit', resolve));
    }
    await rm(runtime, { recursive: true, force: true });
  }
});
