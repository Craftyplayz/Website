import test, { before, after } from 'node:test';
import assert from 'node:assert/strict';
import { spawn, execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { mkdir, mkdtemp, rm, writeFile } from 'node:fs/promises';
import { createServer } from 'node:net';
import { tmpdir } from 'node:os';
import { randomUUID, createHash, createHmac } from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { resolve, dirname, relative, sep } from 'node:path';
import { BOOKS } from '../src/config/books.js';
import { parseEpub } from '../src/content/epub-parser.js';
import { FixtureDOMParser, makeSyntheticEpub } from './fixture-dom.js';

const execute = promisify(execFile);
const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
let runtime;
let privateRuntime;
let database;
const helper = resolve(root, 'tests/backend-fixture.php');
let server;
let base;
let catalog;
const servers = [];

async function launch(dbPath, documentRoot = resolve(root, 'api')) {
  const socket = createServer();
  await new Promise(resolve => socket.listen(0, '127.0.0.1', resolve));
  const port = socket.address().port;
  await new Promise(resolve => socket.close(resolve));
  const child = spawn('php', ['-d', 'display_errors=0', '-S', `127.0.0.1:${port}`, '-t', documentRoot], {
    cwd: root,
    env: { ...process.env, HPCHAPTER_DB_PATH: dbPath, PHP_CLI_SERVER_WORKERS: '4' },
    detached: true,
    stdio: ['ignore', 'ignore', 'ignore']
  });
  servers.push(child);
  const apiPath = relative(documentRoot, resolve(root, 'api/index.php')).split(sep).join('/');
  const url = `http://127.0.0.1:${port}/${apiPath}`;
  for (let attempt = 0; attempt < 100; attempt += 1) {
    try {
      const response = await fetch(`${url}?action=config`);
      if (response.status === 200 || response.status === 503) return { child, url };
    } catch {}
    await new Promise(resolve => setTimeout(resolve, 40));
  }
  throw new Error('PHP HTTP server did not start.');
}

async function fixture(action, runId = '') {
  const { stdout } = await execute('php', [helper, action, database, runId], { maxBuffer: 10 * 1024 * 1024 });
  return stdout ? JSON.parse(stdout) : null;
}

async function request(action, body, options = {}) {
  const url = options.url ?? base;
  const method = options.method ?? (body === undefined ? 'GET' : 'POST');
  const response = await fetch(`${url}?action=${action}`, {
    method,
    headers: { ...(body === undefined ? {} : { 'Content-Type': 'application/json' }), ...options.headers },
    body: body === undefined ? undefined : options.raw ?? JSON.stringify(body)
  });
  const data = await response.json();
  assert.match(response.headers.get('content-type'), /application\/json/);
  assert.equal(response.headers.get('access-control-allow-origin'), null);
  return { status: response.status, data, headers: response.headers };
}

async function create(mode = 'normal', name = 'HTTP player', selectedBooks) {
  const body = { name, mode };
  if (selectedBooks) body.selectedBooks = selectedBooks;
  const response = await request('create', body);
  assert.equal(response.status, 200, JSON.stringify(response.data));
  return response.data;
}

async function answer(runId, type, answerId, requestId = randomUUID()) {
  return request('answer', { runId, type, answerId, requestId });
}

before(async () => {
  privateRuntime = await mkdtemp(resolve(tmpdir(), 'hpchapter-backend-tests-'));
  runtime = privateRuntime;
  database = resolve(privateRuntime, 'http.sqlite');
  ({ child: server, url: base } = await launch(database));
  catalog = JSON.parse((await execute('php', [helper, 'catalog'], { maxBuffer: 10 * 1024 * 1024 })).stdout);
});

after(async () => {
  for (const child of servers) {
    try { process.kill(-child.pid, 'SIGTERM'); } catch {}
  }
  await new Promise(resolve => setTimeout(resolve, 100));
  await rm(privateRuntime, { recursive: true, force: true });
});

test('native PHP rules, timer boundaries, all mode pools, persistence, sorting and retention', async t => {
  const { stdout } = await execute('php', [resolve(root, 'tests/backend.test.php'), resolve(privateRuntime, 'native')], {
    maxBuffer: 1024 * 1024,
    timeout: 120000
  });
  const report = JSON.parse(stdout);
  assert.ok(report.assertions > 30000);
  assert.equal(report.normal, report.timed);
  assert.ok(report.minimum <= report.normal);
  t.diagnostic(JSON.stringify(report));
});

for (const book of BOOKS) {
  test(`actual ${book.id} EPUB mirrors browser stable IDs, titles, paths and all normalized passages`, async () => {
    const archive = resolve(root, book.assetPath);
    const files = new Set((await execute('unzip', ['-Z1', archive])).stdout.split('\n'));
    const parsed = await parseEpub(null, book, {
      DOMParserClass: FixtureDOMParser,
      JSZipLibrary: {
        loadAsync: async () => ({
          file(path) {
            return files.has(path) ? {
              async: async () => (await execute('unzip', ['-p', archive, path], { maxBuffer: 10 * 1024 * 1024 })).stdout
            } : null;
          }
        })
      }
    });
    const chapters = parsed.chapters.map(chapter => {
      const hash = createHash('sha256');
      for (const paragraph of chapter.paragraphs) {
        hash.update(`${paragraph.id}\0${paragraph.text}\0`, 'utf8');
      }
      return { id: chapter.id, title: chapter.title, href: chapter.href, count: chapter.paragraphs.length, hash: hash.digest('hex') };
    });
    assert.deepEqual(chapters, catalog.find(entry => entry.id === book.id).chapters);
  });
}

test('synthetic EPUB3 and broken-nav NCX agree with browser parser and UTF16 boundaries', async () => {
  for (const navigation of ['epub3', 'broken']) {
    const fixture = makeSyntheticEpub({ navigation });
    const file = resolve(runtime, `${navigation}.json`);
    const zip = resolve(runtime, `${navigation}.epub`);
    const parsed = await parseEpub(fixture.data, BOOKS[0], {
      JSZipLibrary: fixture.JSZipLibrary, DOMParserClass: FixtureDOMParser
    });
    await writeFile(file, JSON.stringify(fixture.files));
    const php = `require $argv[1]; $files=json_decode(file_get_contents($argv[2]),true); $zip=new ZipArchive();$zip->open($argv[3],ZipArchive::CREATE);foreach($files as $name=>$text){$zip->addFromString($name,$text);}$zip->close();echo json_encode(HPChapter\\Parser::parse($argv[3],"hp-1"));`;
    const { stdout } = await execute('php', ['-r', php, resolve(root, 'api/Parser.php'), file, zip], { maxBuffer: 1024 * 1024 });
    assert.deepEqual(JSON.parse(stdout).chapters, parsed.chapters);
  }
});

test('HTTP config and empty boards follow exact contract with no CORS', async () => {
  const config = await request('config');
  assert.deepEqual(config.data, { duration: 60, contextLimit: 150 });
  assert.equal(config.headers.get('cache-control'), 'no-store');
  const preview = await request('preview');
  assert.deepEqual(preview.data, { boards: { normal: [], timed: [], minimum: [], specific: [] } });
  assert.deepEqual((await request('leaderboard&mode=normal&limit=100')).data, { mode: 'normal', entries: [] });
});

test('HTTP rejects methods, malformed and oversized JSON, Origins and unknown fields', async () => {
  assert.equal((await request('create')).status, 405);
  assert.equal((await request('config', {})).status, 405);
  assert.equal((await request('missing')).status, 404);
  assert.equal((await request('create', {}, { raw: '{' })).status, 400);
  assert.equal((await request('create', {}, { raw: '[]' })).status, 400);
  assert.equal((await request('create', {}, { raw: '"string"' })).status, 400);
  assert.equal((await request('create', {}, { raw: ' '.repeat(4097) })).status, 413);
  assert.equal((await request('create', {}, { headers: { 'Content-Type': 'text/plain' } })).status, 415);
  assert.equal((await request('config', undefined, { headers: { Origin: 'https://evil.example' } })).status, 403);
  assert.equal((await request('config', undefined, { headers: { Origin: 'null' } })).status, 403);
  assert.equal((await request('config', undefined, { headers: { Origin: new URL(base).origin } })).status, 200);
  assert.equal((await request('config', undefined, { headers: { 'Sec-Fetch-Site': 'cross-site' } })).status, 403);
  assert.equal((await request('config&score=999')).status, 400);
  assert.equal((await request('leaderboard&limit=101')).status, 400);
  assert.equal((await request('leaderboard&limit=-1')).status, 400);
  assert.equal((await request('leaderboard&mode[]=normal')).status, 400);
  for (const body of [
    { name: 'X', mode: 'normal', score: 900 },
    { name: 'X', mode: 'normal', score: -1 },
    { name: 'X', mode: 'timed', duration: 600 },
    { name: 'X', mode: 'normal', contextLimit: 999 },
    { name: 'X', mode: 'normal', selectedBooks: [] },
    { name: 'X', mode: 'specific', selectedBooks: {} },
    { name: 'X', mode: 'specific', selectedBooks: ['hp-8'] },
    { name: 'X', mode: 'specific', selectedBooks: ['hp-1', 'hp-1'] },
    { name: 'X', mode: 'specific', selectedBooks: [null] },
    { name: '\u0000X', mode: 'normal' },
    { name: 'x'.repeat(21), mode: 'normal' },
    { name: 'X', mode: 'invalid' }
  ]) assert.equal((await request('create', body)).status, 400);
  assert.equal((await request('state', { runId: 'invalid' })).status, 400);
  assert.equal((await request('state', { runId: 'f'.repeat(64) })).status, 404);
});

test('HTTP server sequence, request UUID replay and concurrent score/finalization are authoritative', async () => {
  const ready = await create();
  assert.equal(ready.state, 'ready');
  assert.equal(ready.question, null);
  assert.equal(ready.score, 0);
  assert.equal(ready.elapsedMs, 0);
  assert.equal(ready.result, null);
  const runId = ready.runId;
  const start = (await request('start', { runId })).data;
  assert.equal(start.state, 'book-selection');
  assert.deepEqual(Object.keys(start.question), ['id', 'passage']);
  assert.match(start.question.id, /^[a-f0-9]{64}$/);
  const canonical = await fixture('question', runId);
  assert.equal(start.question.passage, canonical.passage);
  assert.equal((await request('next', { runId })).status, 409);
  assert.equal((await request('finish', { runId })).status, 409);
  assert.equal((await answer(runId, 'chapter', canonical.chapter_id)).status, 409);
  const book = await answer(runId, 'book', canonical.book_id);
  assert.equal(book.data.state, 'chapter-selection');
  assert.equal(book.data.score, 0);
  const requestId = randomUUID();
  const duplicated = await Promise.all([
    answer(runId, 'chapter', canonical.chapter_id, requestId),
    answer(runId, 'chapter', canonical.chapter_id, requestId)
  ]);
  assert.equal(duplicated[0].status, 200);
  assert.equal(duplicated[1].status, 200);
  assert.equal(duplicated[0].data.state, duplicated[1].data.state);
  assert.equal(duplicated[0].data.score, duplicated[1].data.score);
  assert.deepEqual(duplicated[0].data.feedback, duplicated[1].data.feedback);
  assert.ok(duplicated[0].data.elapsedMs >= start.elapsedMs && duplicated[1].data.elapsedMs >= start.elapsedMs);
  assert.equal(duplicated[0].data.score, 1);
  const other = book.data.question.chapterChoices.find(choice => choice.id !== canonical.chapter_id).id;
  assert.equal((await answer(runId, 'chapter', other, requestId)).status, 409);
  assert.equal((await answer(runId, 'chapter', canonical.chapter_id)).status, 409);
  const advanced = (await request('next', { runId })).data;
  assert.notEqual(advanced.question.id, start.question.id);
  const second = await fixture('question', runId);
  await answer(runId, 'book', second.book_id);
  const competing = await Promise.all([
    answer(runId, 'chapter', second.chapter_id),
    answer(runId, 'chapter', second.chapter_id)
  ]);
  assert.deepEqual(competing.map(response => response.status).sort(), [200, 409]);
  assert.equal(competing.find(response => response.status === 200).data.score, 2);
  await request('next', { runId });
  const last = await fixture('question', runId);
  const wrong = last.book_id === 'hp-1' ? 'hp-2' : 'hp-1';
  const completed = await answer(runId, 'book', wrong);
  assert.equal(completed.data.state, 'completed');
  assert.equal(completed.data.result.reason, 'incorrect-book');
  assert.equal(completed.data.result.score, 2);
  assert.equal(completed.data.result.accepted, true);
  assert.equal(completed.data.result.rank, 1);
  assert.ok(Number.isInteger(completed.data.result.entryId));
  assert.equal(completed.data.result.id, completed.data.result.entryId);
  const finishes = await Promise.all(Array.from({ length: 4 }, () => request('finish', { runId })));
  for (const response of finishes) assert.deepEqual(response.data, completed.data);
  const board = (await request('leaderboard&mode=normal&limit=100', undefined, { headers: { 'User-Agent': 'other-client' } })).data;
  assert.equal(board.entries.filter(entry => entry.name === ready.name).length, 1);
  const entry = board.entries[0];
  assert.equal(entry.id, completed.data.result.entryId);
  assert.deepEqual(Object.keys(entry), ['id', 'name', 'score', 'achievedAt', 'elapsedMs', 'duration', 'selectedBooks', 'mode', 'contextLimit']);
  assert.equal(entry.contextLimit, 150);
  assert.equal(entry.score, 2);
  assert.match(entry.achievedAt, /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/);
  assert.ok(!JSON.stringify(board).includes(runId));
});

test('HTTP expiry, refresh/start resync and late answers cannot extend or score', async () => {
  const ready = await create('timed', 'HTTP timer');
  const runId = ready.runId;
  assert.equal(ready.remainingMs, 60000);
  const start = (await request('start', { runId })).data;
  const canonical = await fixture('question', runId);
  const bookRequestId = randomUUID();
  await answer(runId, 'book', canonical.book_id, bookRequestId);
  const refreshed = (await request('state', { runId })).data;
  assert.equal(refreshed.question.id, start.question.id);
  assert.ok(refreshed.remainingMs <= 60000);
  await new Promise(resolve => setTimeout(resolve, 20));
  const replayed = await answer(runId, 'book', canonical.book_id, bookRequestId);
  assert.ok(replayed.data.remainingMs <= refreshed.remainingMs);
  assert.ok(replayed.data.elapsedMs >= refreshed.elapsedMs);
  await fixture('expire', runId);
  const expiredReplay = await answer(runId, 'book', canonical.book_id, bookRequestId);
  assert.equal(expiredReplay.data.state, 'completed');
  assert.equal(expiredReplay.data.result.reason, 'time-expired');
  const late = await answer(runId, 'chapter', canonical.chapter_id);
  assert.equal(late.data.result.reason, 'time-expired');
  assert.equal(late.data.result.elapsedMs, 60000);
  assert.equal(late.data.score, 0);
  const resumed = (await request('start', { runId })).data;
  assert.deepEqual(resumed, late.data);
  assert.deepEqual((await request('state', { runId })).data, late.data);
  assert.equal((await request('leaderboard&mode=timed')).data.entries.length, 1);
  const resyncRun = await create('timed', 'resync expiry');
  await request('start', { runId: resyncRun.runId });
  await fixture('expire', resyncRun.runId);
  assert.equal((await request('state', { runId: resyncRun.runId })).data.result.reason, 'time-expired');
  const lockRun = await create('timed', 'lock delay expiry');
  await request('start', { runId: lockRun.runId });
  const lockQuestion = await fixture('question', lockRun.runId);
  await answer(lockRun.runId, 'book', lockQuestion.book_id);
  const lock = spawn('php', [helper, 'deadline-lock', database, lockRun.runId]);
  const lockDone = new Promise((resolve, reject) => {
    lock.on('error', reject);
    lock.on('exit', code => code === 0 ? resolve() : reject(new Error(`Lock helper exited ${code}`)));
  });
  await new Promise((resolve, reject) => {
    lock.stdout.once('data', chunk => chunk.toString().includes('locked') ? resolve() : reject(new Error('Lock helper did not acquire lock')));
    lock.on('error', reject);
  });
  const delayed = await answer(lockRun.runId, 'chapter', lockQuestion.chapter_id);
  await lockDone;
  assert.equal(delayed.data.result.reason, 'time-expired');
  assert.equal(delayed.data.score, 0);
  assert.equal(delayed.data.result.elapsedMs, 60000);
});

test('HTTP minimum, specific pool boundaries, selected details and abandon never rank', async () => {
  for (const [mode, selected] of [['minimum', undefined], ['specific', ['hp-3']]]) {
    const ready = await create(mode, `${mode} player`, selected);
    const runId = ready.runId;
    const started = (await request('start', { runId })).data;
    const canonical = await fixture('question', runId);
    if (mode === 'minimum') {
      assert.ok([...started.question.passage].length >= 60 && [...started.question.passage].length <= 150);
      assert.ok(started.question.passage.match(/\p{L}[\p{L}\p{M}]*/gu).length >= 8);
    } else {
      assert.equal(canonical.book_id, 'hp-3');
      assert.deepEqual(ready.selectedBooks, ['hp-3']);
    }
    const wrong = canonical.book_id === 'hp-1' ? 'hp-2' : 'hp-1';
    assert.equal((await answer(runId, 'book', wrong)).data.result.accepted, true);
    const board = (await request(`leaderboard&mode=${mode}`)).data;
    assert.equal(board.entries.length, 1);
    assert.equal(board.entries[0].mode, mode);
    assert.deepEqual(board.entries[0].selectedBooks, selected ?? []);
  }
  const abandoned = await create('normal', 'menu abandonment');
  await request('start', { runId: abandoned.runId });
  const response = (await request('abandon', { runId: abandoned.runId })).data;
  assert.equal(response.state, 'abandoned');
  assert.equal(response.result, null);
  assert.equal((await request('finish', { runId: abandoned.runId })).data.result, null);
  const preview = (await request('preview')).data.boards;
  assert.equal(preview.normal.filter(entry => entry.name === 'menu abandonment').length, 0);
  assert.deepEqual(Object.keys(preview), ['normal', 'timed', 'minimum', 'specific']);
  for (const entries of Object.values(preview)) assert.ok(entries.length <= 10);
});

test('HTTP rate limiting uses REMOTE_ADDR, ignores forwarding and returns useful errors', async () => {
  await fixture('clear-rate');
  await request('config', undefined, { headers: { 'X-Forwarded-For': '203.0.113.1' } });
  await request('config', undefined, { headers: { 'X-Forwarded-For': '203.0.113.2', Forwarded: 'for=203.0.113.3' } });
  assert.deepEqual(await fixture('rates'), [{ address: '127.0.0.1', count: 2 }]);
  await fixture('seed-rate');
  const limited = await request('config', undefined, { headers: { 'X-Forwarded-For': 'different' } });
  assert.equal(limited.status, 429);
  assert.equal(limited.headers.get('retry-after'), '60');
  assert.match(limited.data.error, /wait/i);
  await fixture('clear-rate');
});

test('HTTP separate creation allowance does not throttle current-game answers', async () => {
  const ready = await create('normal', 'creation allowance');
  await request('start', { runId: ready.runId });
  const question = await fixture('question', ready.runId);
  await fixture('seed-create-rate');
  const limited = await request('create', { name: 'limited', mode: 'normal' }, { headers: { 'X-Forwarded-For': '203.0.113.20' } });
  assert.equal(limited.status, 429);
  assert.equal(limited.headers.get('retry-after'), '300');
  assert.match(limited.data.error, /new runs/i);
  assert.equal((await request('config')).status, 200);
  const answered = await answer(ready.runId, 'book', question.book_id);
  assert.equal(answered.status, 200);
  assert.equal(answered.data.state, 'chapter-selection');
  await request('abandon', { runId: ready.runId });
  await fixture('clear-rate');
});

test('HTTP unavailable database is generic JSON 503 without filesystem or stack details', async () => {
  const blocked = resolve(privateRuntime, 'not-a-directory');
  await writeFile(blocked, 'block directory creation');
  const { url } = await launch(resolve(blocked, 'scores.sqlite'));
  const unavailable = await request('config', undefined, { url });
  assert.equal(unavailable.status, 503);
  assert.deepEqual(unavailable.data, { error: 'The game database or server is unavailable. Please try again later.' });
  assert.ok(!JSON.stringify(unavailable.data).includes(runtime));
});

test('HTTP refuses database paths under repository/document root, including symlink aliases', async () => {
  const publicDatabase = resolve(root, 'tests', `.backend-denied-${randomUUID()}.sqlite`);
  const { url } = await launch(publicDatabase);
  assert.equal((await request('config', undefined, { url })).status, 503);
  const { symlink, access } = await import('node:fs/promises');
  await assert.rejects(access(publicDatabase));
  const privateAlias = resolve(privateRuntime, 'public-alias');
  await symlink(root, privateAlias, 'dir');
  const aliasServer = await launch(resolve(privateAlias, 'aliased.sqlite'));
  assert.equal((await request('config', undefined, { url: aliasServer.url })).status, 503);
  await assert.rejects(access(resolve(root, 'aliased.sqlite')));
});

test('HTTP refuses insecure precreated private directories and private symbolic links', async () => {
  const insecure = resolve(privateRuntime, 'insecure');
  await mkdir(insecure, { mode: 0o755 });
  const insecureServer = await launch(resolve(insecure, 'scores.sqlite'));
  assert.equal((await request('config', undefined, { url: insecureServer.url })).status, 503);
  const { chmod, symlink, access } = await import('node:fs/promises');
  await assert.rejects(access(resolve(insecure, 'scores.sqlite')));
  await chmod(insecure, 0o700);
  const secureServer = await launch(resolve(insecure, 'scores.sqlite'));
  assert.equal((await request('config', undefined, { url: secureServer.url })).status, 200);
  const alias = resolve(privateRuntime, 'private-symlink');
  await symlink(insecure, alias, 'dir');
  const symlinkServer = await launch(resolve(alias, 'symlinked.sqlite'));
  assert.equal((await request('config', undefined, { url: symlinkServer.url })).status, 503);
  await assert.rejects(access(resolve(insecure, 'symlinked.sqlite')));
});

test('HTTP website-root deployment cannot execute CLI fixtures or native test suite', async () => {
  const { url } = await launch(database, resolve(root, '../..'));
  const origin = new URL(url).origin;
  for (const file of ['backend-fixture.php', 'backend.test.php']) {
    const response = await fetch(`${origin}/games/hpchapter/tests/${file}?action=catalog`);
    assert.equal(response.status, 404);
    assert.equal(await response.text(), '');
  }
  assert.equal((await request('config', undefined, { url })).status, 200);
});

test('HTTP question IDs use server-only randomness and upgrade legacy runs stably', async () => {
  await fixture('clear-rate');
  const ready = await create('normal', 'private question IDs');
  const runId = ready.runId;
  assert.match(runId, /^[a-f0-9]{64}$/);
  assert.equal(Object.hasOwn(ready, 'questionIdKey'), false);
  assert.equal(Object.hasOwn(ready, 'sequence'), false);
  assert.deepEqual(await fixture('question-key-metadata', runId), { keyBytes: 32 });
  const started = (await request('start', { runId })).data;
  const canonical = await fixture('question', runId);
  assert.notEqual(started.question.id, createHmac('sha256', runId).update(canonical.stable_id).digest('hex'));
  assert.notEqual(started.question.id, createHmac('sha256', Buffer.from(runId, 'hex')).update(canonical.stable_id).digest('hex'));
  assert.equal((await request('state', { runId })).data.question.id, started.question.id);
  await fixture('drop-question-key', runId);
  assert.deepEqual(await fixture('question-key-metadata', runId), { keyBytes: 0 });
  const upgraded = (await request('state', { runId })).data;
  assert.notEqual(upgraded.question.id, started.question.id);
  assert.deepEqual(await fixture('question-key-metadata', runId), { keyBytes: 32 });
  assert.equal((await request('state', { runId })).data.question.id, upgraded.question.id);
  const requestId = randomUUID();
  const accepted = (await answer(runId, 'book', canonical.book_id, requestId)).data;
  assert.equal(accepted.question.id, upgraded.question.id);
  const replayed = (await answer(runId, 'book', canonical.book_id, requestId)).data;
  assert.equal(replayed.question.id, upgraded.question.id);
  for (const snapshot of [started, upgraded, accepted, replayed]) {
    assert.equal(Object.hasOwn(snapshot, 'questionIdKey'), false);
    assert.equal(Object.hasOwn(snapshot, 'sequence'), false);
    assert.equal(Object.hasOwn(snapshot.question, 'bookId'), false);
    assert.equal(Object.hasOwn(snapshot.question, 'correctChapterId'), false);
  }
  await request('abandon', { runId });
  await fixture('clear-rate');
});

test('HTTP timed abandon finalizes expired runs once but never ranks before expiry', async () => {
  await fixture('clear-rate');
  const early = await create('timed', 'early timed abandon');
  await request('start', { runId: early.runId });
  const earlyQuestion = await fixture('question', early.runId);
  await answer(early.runId, 'book', earlyQuestion.book_id);
  await answer(early.runId, 'chapter', earlyQuestion.chapter_id);
  const abandoned = (await request('abandon', { runId: early.runId })).data;
  assert.equal(abandoned.state, 'abandoned');
  assert.equal(abandoned.score, 1);
  assert.equal(abandoned.result, null);
  assert.equal((await request('leaderboard&mode=timed')).data.entries.some(entry => entry.name === early.name), false);
  const expired = await create('timed', 'expired timed leave');
  await request('start', { runId: expired.runId });
  const expiredQuestion = await fixture('question', expired.runId);
  await answer(expired.runId, 'book', expiredQuestion.book_id);
  await answer(expired.runId, 'chapter', expiredQuestion.chapter_id);
  await fixture('expire', expired.runId);
  const ended = (await request('abandon', { runId: expired.runId })).data;
  assert.equal(ended.state, 'completed');
  assert.equal(ended.result.reason, 'time-expired');
  assert.equal(ended.result.elapsedMs, ended.duration * 1000);
  assert.equal(ended.result.score, 1);
  assert.equal(ended.result.accepted, true);
  for (const action of ['abandon', 'finish', 'state']) {
    assert.deepEqual((await request(action, { runId: expired.runId })).data, ended);
  }
  const entries = (await request('leaderboard&mode=timed')).data.entries;
  assert.equal(entries.filter(entry => entry.id === ended.result.id).length, 1);
  await fixture('clear-rate');
});
