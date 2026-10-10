<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/api/Parser.php';
require_once dirname(__DIR__) . '/api/Storage.php';
require_once dirname(__DIR__) . '/api/Service.php';

use HPChapter\ApiError;
use HPChapter\Parser;
use HPChapter\Service;
use HPChapter\Storage;

$ownsDirectory = !isset($argv[1]);
$directory = $argv[1] ?? sys_get_temp_dir() . '/hpchapter-backend-native-' . bin2hex(random_bytes(12));
if (!is_dir($directory)) {
    mkdir($directory, 0700, true);
}
if ($ownsDirectory) {
    register_shutdown_function(static function () use ($directory): void {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            if ($file->isLink() || $file->isFile()) {
                unlink($file->getPathname());
            } else {
                rmdir($file->getPathname());
            }
        }
        rmdir($directory);
    });
}
$count = 0;
function check(bool $condition, string $message): void
{
    global $count;
    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
    $count++;
}
function rejects(callable $callback, int $status, string $message): void
{
    try {
        $callback();
    } catch (ApiError $error) {
        check($error->status === $status, $message . ' (HTTP status)');
        return;
    }
    throw new RuntimeException('FAILED: ' . $message . ' did not reject');
}
function uuid(): string
{
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3) . '-8' . substr($hex, 17, 3) . '-' . substr($hex, 20, 12);
}
function answer(Service $service, string $runId, string $type, string $answerId, ?string $requestId = null): array
{
    return $service->act('answer', ['runId' => $runId, 'type' => $type, 'answerId' => $answerId, 'requestId' => $requestId ?? uuid()]);
}
function internalQuestion(Storage $storage, string $runId): array
{
    $run = json_decode($storage->query('SELECT data FROM runs WHERE id=?', [$runId])->fetchColumn(), true, 512, JSON_THROW_ON_ERROR);
    return $storage->query('SELECT q.*,b.book_id,b.chapters FROM questions q JOIN book_versions b ON q.version=b.version WHERE q.id=?', [$run['sequence'][$run['position']]])->fetch();
}
function rewrite(Storage $storage, string $id, callable $edit): void
{
    $run = json_decode($storage->query('SELECT data FROM runs WHERE id=?', [$id])->fetchColumn(), true, 512, JSON_THROW_ON_ERROR);
    $edit($run);
    $storage->query('UPDATE runs SET data=? WHERE id=?', [json_encode($run, JSON_THROW_ON_ERROR), $id]);
}

check(Parser::normalize("\u{FEFF} one\u{00A0}\n two\u{2007} ") === 'one two', 'JavaScript Unicode whitespace');
check(Parser::normalize("one\u{0085}two") === "one\u{0085}two", 'JavaScript excludes NEXT LINE');
check(Parser::eligible(str_repeat('😀', 60)), '120 UTF-16 units inclusive');
check(!Parser::eligible(str_repeat('😀', 59)), '118 UTF-16 units excluded');
check(Parser::eligible(str_repeat('😀', 1250)), '2500 UTF-16 units inclusive');
check(!Parser::eligible(str_repeat('😀', 1251)), '2502 UTF-16 units excluded');
check(Parser::resolve('OPS/nav/nav.xhtml', '../Text/one.xhtml#part') === 'OPS/Text/one.xhtml', 'relative navigation');
check(Parser::resolve('OPS/pkg.opf', '/Text/Some%20Chapter.xhtml?x') === 'Text/Some Chapter.xhtml', 'absolute and encoded navigation');
check(Parser::resolve('OPS/pkg.opf', 'Text/%ZZ.xhtml') === 'OPS/Text/%ZZ.xhtml', 'malformed URI escapes');
check(Parser::excerpt(str_repeat('A', 120)) === null, 'nonmeaningful minimum passage rejected');
$minimumText = 'Écoutez les oiseaux chanter dans les arbres pendant que nous regardons tranquillement le soleil éclairer les jardins enchantés. ' . str_repeat('word ', 50);
$excerpt = Parser::excerpt($minimumText);
check(mb_strlen($excerpt) >= 60 && mb_strlen($excerpt) <= 150, 'minimum codepoint bounds');
check(preg_match_all('/\p{L}[\p{L}\p{M}]*/u', $excerpt) >= 8, 'minimum Unicode letter words');
check(str_contains($minimumText, $excerpt), 'excerpt is a contiguous original passage');
check(Parser::excerpt($minimumText) === $excerpt, 'deterministic minimum passage');

$now = 1770000000000;
$storage = new Storage($directory . '/native.sqlite');
$service = new Service($storage, static function () use (&$now): int { return $now; });
check($service->rules === ['duration' => 60, 'contextLimit' => 150], 'shared rules');
check((int) $storage->db->query('PRAGMA user_version')->fetchColumn() === 1, 'first deployment initializes schema version1');
foreach (Service::MODES as $mode) {
    check($service->leaderboard($mode)['entries'] === [], 'new board ' . $mode);
}
foreach ([
    ['name' => 'X', 'mode' => 'normal', 'score' => 999],
    ['name' => 'X', 'mode' => 'timed', 'duration' => 999],
    ['name' => 'X', 'mode' => 'normal', 'selectedBooks' => []],
    ['name' => 'X', 'mode' => 'specific'],
    ['name' => 'X', 'mode' => 'specific', 'selectedBooks' => ['hp-0']],
    ['name' => 'X', 'mode' => 'specific', 'selectedBooks' => ['hp-1', 'hp-1']],
    ['name' => 'X', 'mode' => 'specific', 'selectedBooks' => ['a' => 'hp-1']],
    ['name' => '', 'mode' => 'normal'],
    ['name' => str_repeat('é', 21), 'mode' => 'normal'],
    ['name' => "name\n", 'mode' => 'normal'],
    ['name' => "name\u{202E}", 'mode' => 'normal'],
    ['name' => ['X'], 'mode' => 'normal'],
    ['name' => 'X', 'mode' => []],
    ['name' => 'X', 'mode' => 'unknown'],
] as $invalid) {
    rejects(fn () => $service->create($invalid), 400, 'invalid creation metadata');
}
$normalPool = $service->pool('normal', []);
$timedPool = $service->pool('timed', []);
$minimumPool = $service->pool('minimum', []);
check($normalPool === $timedPool, 'normal and timed canonical pools');
check(count($normalPool) === count(array_unique($normalPool)), 'unique canonical pool IDs');
$seen = [];
foreach ($minimumPool as $id) {
    $row = $storage->query('SELECT excerpt FROM questions WHERE id=?', [$id])->fetch();
    $text = $row['excerpt'];
    check(!isset($seen[$text]) && mb_strlen($text) >= 60 && mb_strlen($text) <= 150 && preg_match_all('/\p{L}[\p{L}\p{M}]*/u', $text) >= 8, 'minimum pool unique and meaningful');
    $seen[$text] = true;
}
$bookCounts = [];
foreach (Parser::TITLES as $bookId => $title) {
    $pool = $service->pool('specific', [$bookId]);
    $bookCounts[$bookId] = count($pool);
    foreach ($pool as $id) {
        check($storage->query('SELECT b.book_id FROM questions q JOIN book_versions b ON b.version=q.version WHERE q.id=?', [$id])->fetchColumn() === $bookId, 'specific never leaks another book');
    }
}
$run = $service->create(['name' => str_repeat('😀', 20), 'mode' => 'normal']);
$id = $run['runId'];
check(preg_match('/^[a-f0-9]{64}$/D', $id) === 1 && $run['state'] === 'ready' && $run['question'] === null && $run['elapsedMs'] === 0, 'opaque ready snapshot');
$internal = json_decode($storage->query('SELECT data FROM runs WHERE id=?', [$id])->fetchColumn(), true);
check(count($internal['sequence']) === count($normalPool) && count(array_unique($internal['sequence'])) === count($normalPool), 'shuffled no-repeat complete sequence');
check($internal['sequence'] !== $normalPool, 'server shuffles the sequence');
rejects(fn () => $service->act('next', ['runId' => $id]), 409, 'no skip before start');
rejects(fn () => $service->act('finish', ['runId' => $id]), 409, 'no premature finish');
$now += 15000;
$started = $service->act('start', ['runId' => $id]);
check($started['elapsedMs'] === 0 && $started['state'] === 'book-selection', 'clock starts after preparation');
check(array_keys($started['question']) === ['id', 'passage'] && preg_match('/^[a-f0-9]{64}$/D', $started['question']['id']), 'no answer keys or identity before answering');
$question = internalQuestion($storage, $id);
check($started['question']['id'] !== $question['stable_id'] && $started['question']['passage'] === $question['passage'], 'opaque external canonical question');
$now += 1000;
check($service->act('start', ['runId' => $id])['elapsedMs'] === 1000, 'second start never resets timer');
rejects(fn () => answer($service, $id, 'chapter', $question['chapter_id']), 409, 'cannot bypass book');
rejects(fn () => $service->act('next', ['runId' => $id]), 409, 'cannot skip book');
rejects(fn () => answer($service, $id, 'book', 'hp-8'), 400, 'invalid book cannot score');
$bookAnswer = answer($service, $id, 'book', $question['book_id']);
check($bookAnswer['state'] === 'chapter-selection' && $bookAnswer['feedback']['correct'] && $bookAnswer['score'] === 0, 'correct book does not score');
check(count($bookAnswer['question']['chapterChoices']) > 1, 'actual chapter choices');
rejects(fn () => answer($service, $id, 'chapter', 'not-a-choice'), 400, 'chapter must be offered');
$requestId = uuid();
$chapterAnswer = answer($service, $id, 'chapter', $question['chapter_id'], $requestId);
check($chapterAnswer['state'] === 'answer-feedback' && $chapterAnswer['score'] === 1 && $chapterAnswer['feedback']['correct'], 'only correct chapter scores');
check(answer($service, $id, 'chapter', $question['chapter_id'], $requestId) === $chapterAnswer, 'answer request UUID is idempotent');
$now += 100;
$freshReplay = answer($service, $id, 'chapter', $question['chapter_id'], $requestId);
check($freshReplay['elapsedMs'] === 1100 && $freshReplay['state'] === 'answer-feedback' && $freshReplay['score'] === 1, 'answer replay has fresh authoritative time');
$now -= 100;
rejects(fn () => answer($service, $id, 'chapter', $bookAnswer['question']['chapterChoices'][0]['id'] === $question['chapter_id'] ? $bookAnswer['question']['chapterChoices'][1]['id'] : $bookAnswer['question']['chapterChoices'][0]['id'], $requestId), 409, 'UUID reuse with another payload rejected');
rejects(fn () => answer($service, $id, 'chapter', $question['chapter_id']), 409, 'new UUID cannot score twice');
$next = $service->act('next', ['runId' => $id]);
check($next['question']['id'] !== $started['question']['id'] && $next['score'] === 1 && !isset($next['feedback']), 'next changes question and clears feedback');
check(answer($service, $id, 'chapter', $chapterAnswer['feedback']['correctChapterId'], $requestId) === $next, 'old successful answer replay never reopens previous question');
$question = internalQuestion($storage, $id);
$wrong = $question['book_id'] === 'hp-1' ? 'hp-2' : 'hp-1';
$ended = answer($service, $id, 'book', $wrong);
check($ended['state'] === 'completed' && $ended['result'] === [
    'score' => 1, 'reason' => 'incorrect-book', 'elapsedMs' => 1000, 'accepted' => true,
    'id' => (int) $storage->query('SELECT id FROM scores WHERE run_id=?', [$id])->fetchColumn(),
    'entryId' => (int) $storage->query('SELECT id FROM scores WHERE run_id=?', [$id])->fetchColumn(), 'rank' => 1,
], 'wrong book immediately persists result with public identity and rank');
check($ended['question'] === null && isset($ended['feedback']['bookTitle']), 'wrong book feedback and no stale question');
check($service->act('finish', ['runId' => $id]) === $ended && $service->act('state', ['runId' => $id]) === $ended, 'finalization and resync idempotent');
check((int) $storage->query('SELECT COUNT(*) FROM scores WHERE run_id=?', [$id])->fetchColumn() === 1, 'unique ranking per run');
check(answer($service, $id, 'chapter', $chapterAnswer['feedback']['correctChapterId'], $requestId) === $ended, 'cached earlier answer cannot revive a completed run');
check($service->leaderboard('normal')['entries'][0]['score'] === 1 && !isset($service->leaderboard('normal')['entries'][0]['runId']), 'board hides bearer token');

foreach (['normal', 'minimum', 'specific', 'timed'] as $mode) {
    $body = ['name' => $mode, 'mode' => $mode];
    if ($mode === 'specific') {
        $body['selectedBooks'] = ['hp-7', 'hp-1'];
    }
    $ready = $service->create($body);
    $runId = $ready['runId'];
    if ($mode === 'specific') {
        check($ready['selectedBooks'] === ['hp-1', 'hp-7'], 'specific selection canonicalized');
    }
    if ($mode === 'minimum') {
        $minimum = $service->act('start', ['runId' => $runId])['question']['passage'];
        check(mb_strlen($minimum) <= 150, 'minimum snapshot context cap');
    }
    $service->act('start', ['runId' => $runId]);
    rewrite($storage, $runId, static function (array &$run): void { $run['sequence'] = [$run['sequence'][0]]; });
    $question = internalQuestion($storage, $runId);
    answer($service, $runId, 'book', $question['book_id']);
    $ended = answer($service, $runId, 'chapter', $question['chapter_id']);
    check($ended['state'] === 'completed' && $ended['result']['reason'] === 'pool-exhausted' && $ended['result']['accepted'], 'last answer automatically finalizes ' . $mode);
    $entryIds = array_column($service->leaderboard($mode)['entries'], 'id');
    check(array_search($ended['result']['entryId'], $entryIds, true) + 1 === $ended['result']['rank'], 'accepted result rank matches board ordering ' . $mode);
    check(count($service->leaderboard($mode)['entries']) >= 1, 'separate persisted board ' . $mode);
}
$ready = $service->create(['name' => 'timer', 'mode' => 'timed']);
$id = $ready['runId'];
$now += 300000;
check($service->act('state', ['runId' => $id])['remainingMs'] === 60000, 'ready timed clock has not started');
$service->act('start', ['runId' => $id]);
$question = internalQuestion($storage, $id);
$timedBookRequest = uuid();
answer($service, $id, 'book', $question['book_id'], $timedBookRequest);
$now += 59999;
check($service->act('state', ['runId' => $id])['remainingMs'] === 1, 'timer just before boundary');
$timedReplay = answer($service, $id, 'book', $question['book_id'], $timedBookRequest);
check($timedReplay['remainingMs'] === 1 && $timedReplay['state'] === 'chapter-selection' && $timedReplay['elapsedMs'] === 59999, 'timed answer replay cannot restore earlier remaining time');
$now += 1;
$expired = answer($service, $id, 'chapter', $question['chapter_id']);
check($expired['result']['reason'] === 'time-expired' && $expired['score'] === 0 && $expired['result']['elapsedMs'] === 60000, 'expiry enforced before awarding score');
$now += 10000;
check($service->act('finish', ['runId' => $id]) === $expired && $service->act('start', ['runId' => $id]) === $expired, 'expired run never extends or submits twice');
check(answer($service, $id, 'book', $question['book_id'], $timedBookRequest) === $expired, 'cached book answer cannot revive expired run');
$conflictRun = $service->create(['name' => 'expiry conflict', 'mode' => 'timed']);
$service->act('start', ['runId' => $conflictRun['runId']]);
$conflictQuestion = internalQuestion($storage, $conflictRun['runId']);
$conflictRequestId = uuid();
answer($service, $conflictRun['runId'], 'book', $conflictQuestion['book_id'], $conflictRequestId);
$now += 60000;
rejects(fn () => answer($service, $conflictRun['runId'], 'book', $conflictQuestion['book_id'] === 'hp-1' ? 'hp-2' : 'hp-1', $conflictRequestId), 409, 'expired conflicting UUID still rejects payload reuse');
check($service->act('state', ['runId' => $conflictRun['runId']])['result']['reason'] === 'time-expired', 'UUID conflict cannot roll back expired finalization');
$ready = $service->create(['name' => 'validation boundary', 'mode' => 'timed']);
$service->act('start', ['runId' => $ready['runId']]);
$question = internalQuestion($storage, $ready['runId']);
answer($service, $ready['runId'], 'book', $question['book_id']);
$boundaryStart = $now;
$clockCalls = 0;
$boundaryService = new Service($storage, static function () use (&$clockCalls, $boundaryStart): int {
    return $boundaryStart + (++$clockCalls < 3 ? 59999 : 60000);
});
$validationExpired = answer($boundaryService, $ready['runId'], 'chapter', $question['chapter_id']);
check($validationExpired['score'] === 0 && $validationExpired['result']['reason'] === 'time-expired', 'deadline crossing during validation cannot score');
check($validationExpired['result']['elapsedMs'] === 60000, 'validation delay expiry elapsed capped exactly');
$ready = $service->create(['name' => 'resync timer', 'mode' => 'timed']);
$service->act('start', ['runId' => $ready['runId']]);
$now += 61000;
check($service->act('state', ['runId' => $ready['runId']])['result']['reason'] === 'time-expired', 'resync finalizes expired timer');
$ready = $service->create(['name' => 'finish timer', 'mode' => 'timed']);
$service->act('start', ['runId' => $ready['runId']]);
$now += 60000;
check($service->act('finish', ['runId' => $ready['runId']])['result']['reason'] === 'time-expired', 'finish finalizes expired timer');
$ready = $service->create(['name' => 'wrong chapter', 'mode' => 'normal']);
$id = $ready['runId'];
$service->act('start', ['runId' => $id]);
$question = internalQuestion($storage, $id);
$bookAnswer = answer($service, $id, 'book', $question['book_id']);
$wrongChapter = array_values(array_filter($bookAnswer['question']['chapterChoices'], fn (array $choice): bool => $choice['id'] !== $question['chapter_id']))[0]['id'];
$wrongAnswer = answer($service, $id, 'chapter', $wrongChapter);
check($wrongAnswer['score'] === 0 && !$wrongAnswer['feedback']['correct'] && $wrongAnswer['state'] === 'answer-feedback', 'wrong chapter continues without scoring');
$abandoned = $service->act('abandon', ['runId' => $id]);
check($abandoned['state'] === 'abandoned' && $abandoned['result'] === null && !$storage->query('SELECT id FROM scores WHERE run_id=?', [$id])->fetch(), 'abandon never ranks');
$ready = $service->create(['name' => 'expired abandon', 'mode' => 'timed']);
$service->act('start', ['runId' => $ready['runId']]);
$now += 61000;
check($service->act('abandon', ['runId' => $ready['runId']])['state'] === 'abandoned' && !$storage->query('SELECT id FROM scores WHERE run_id=?', [$ready['runId']])->fetch(), 'menu abandon does not score even after clock expiry');

$secondStorage = new Storage($directory . '/native.sqlite');
$secondService = new Service($secondStorage, static function () use (&$now): int { return $now; });
check($secondService->leaderboard('normal') === $service->leaderboard('normal'), 'persisted boards survive new client and connection');
check($secondService->act('state', ['runId' => $id])['state'] === 'abandoned', 'persisted run survives new connection');
rejects(fn () => $service->act('state', ['runId' => str_repeat('f', 64)]), 404, 'unknown bearer token');
rejects(fn () => $service->act('state', ['runId' => []]), 400, 'invalid bearer metadata');
rejects(fn () => $service->act('state', ['runId' => $id, 'score' => -1]), 400, 'no client score accepted');
foreach (Service::MODES as $mode) {
    foreach ([
        ['A', 8, 200, '2026-01-02T00:00:00.000Z'],
        ['B', 9, 500, '2026-01-02T00:00:00.000Z'],
        ['C', 8, 100, '2026-01-02T00:00:00.000Z'],
        ['D', 8, 100, '2026-01-01T00:00:00.000Z'],
        ['E', 8, 100, '2026-01-01T00:00:00.000Z'],
    ] as [$name, $score, $elapsed, $date]) {
        $storage->query('INSERT INTO scores(run_id,name,mode,score,achieved_at,elapsed_ms,duration,selected_books) VALUES(?,?,?,?,?,?,60,?)', [bin2hex(random_bytes(32)), $name, $mode, $score, $date, $elapsed, $mode === 'specific' ? '["hp-1"]' : '[]']);
    }
    check(array_column(array_slice($service->leaderboard($mode)['entries'], 0, 5), 'name') === ['B', 'D', 'E', 'C', 'A'], 'all four ranking tiebreakers ' . $mode);
    check(count($service->leaderboard($mode, 2)['entries']) === 2, 'board limit ' . $mode);
}
rejects(fn () => $service->leaderboard('normal', 101), 400, 'maximum board limit');
rejects(fn () => $service->leaderboard('normal', 0), 400, 'positive board limit');
$rankRun = $service->create(['name' => 'rank tiebreakers', 'mode' => 'normal']);
$service->act('start', ['runId' => $rankRun['runId']]);
$rankQuestion = internalQuestion($storage, $rankRun['runId']);
$rankResult = answer($service, $rankRun['runId'], 'book', $rankQuestion['book_id'] === 'hp-1' ? 'hp-2' : 'hp-1')['result'];
check(array_search($rankResult['entryId'], array_column($service->leaderboard('normal')['entries'], 'id'), true) + 1 === $rankResult['rank'], 'accepted rank uses global four-key ordering');
for ($i = 0; $i < 101; $i++) {
    $storage->query('INSERT INTO scores(run_id,name,mode,score,achieved_at,elapsed_ms,duration,selected_books) VALUES(?,?,?,99,?,1,60,?)', [bin2hex(random_bytes(32)), 'rank fixture', 'normal', '2026-01-01T00:00:00.000Z', '[]']);
}
$outsideTop = $service->create(['name' => 'outside top100', 'mode' => 'normal']);
$service->act('start', ['runId' => $outsideTop['runId']]);
$outsideQuestion = internalQuestion($storage, $outsideTop['runId']);
$outsideResult = answer($service, $outsideTop['runId'], 'book', $outsideQuestion['book_id'] === 'hp-1' ? 'hp-2' : 'hp-1')['result'];
check($outsideResult['rank'] > 100 && !in_array($outsideResult['entryId'], array_column($service->leaderboard('normal')['entries'], 'id'), true), 'accepted global rank is available beyond leaderboard top100');
$rateNow = $now;
for ($i = 0; $i < 240; $i++) {
    $storage->rateLimit('client-a', $rateNow);
}
rejects(fn () => $storage->rateLimit('client-a', $rateNow), 429, 'same-millisecond requests still rate limited');
$storage->rateLimit('client-b', $rateNow);
check((int) $storage->query('SELECT count FROM rate_limits WHERE address=?', ['client-b'])->fetchColumn() === 1, 'independent client rate buckets');
$storage->rateLimit('client-a', $rateNow + 60000);
check((int) $storage->query('SELECT count FROM rate_limits WHERE address=?', ['client-a'])->fetchColumn() === 1, 'rate window resets');
$oldRunId = $ready['runId'];
$storage->query('UPDATE runs SET updated_ms=? WHERE id=?', [$now - 86400001, $oldRunId]);
$storage->rateLimit('cleanup', $now + 180000);
check(!$storage->query('SELECT id FROM runs WHERE id=?', [$oldRunId])->fetch(), 'bounded abandoned run retention');
check((int) $storage->query('SELECT COUNT(*) FROM rate_limits')->fetchColumn() === 1, 'bounded rate retention');
check((int) $storage->query('SELECT COUNT(*) FROM requests WHERE run_id=?', [$oldRunId])->fetchColumn() === 0, 'request cache cascades with run retention');
$ready = $service->create(['name' => 'absolute TTL', 'mode' => 'normal']);
$storage->query('UPDATE runs SET expires_ms=? WHERE id=?', [$now, $ready['runId']]);
rejects(fn () => $service->act('state', ['runId' => $ready['runId']]), 404, 'absolute TTL cannot be extended by resync');
check(!$storage->query('SELECT id FROM scores WHERE run_id=?', [$ready['runId']])->fetch(), 'expired abandoned run never scored');
$storage->rateLimit('cleanup', $now);
check(!$storage->query('SELECT id FROM runs WHERE id=?', [$ready['runId']])->fetch(), 'absolute TTL cleanup');
$missingService = new Service($storage, null, $directory . '/missing-books');
rejects(fn () => $missingService->create(['name' => 'selected failure', 'mode' => 'specific', 'selectedBooks' => ['hp-1']]), 503, 'selected book failure never falls back');
check((int) $storage->query('SELECT COUNT(*) FROM scores WHERE name=?', ['selected failure'])->fetchColumn() === 0, 'no ranking for failed creation');
$partialDirectory = $directory . '/partial-books';
mkdir($partialDirectory, 0700);
symlink(dirname(__DIR__) . '/books/book1.epub', $partialDirectory . '/book1.epub');
$partialService = new Service($storage, static function () use (&$now): int { return $now; }, $partialDirectory);
foreach (['normal', 'timed', 'minimum'] as $mode) {
    $partial = $partialService->create(['name' => 'partial ' . $mode, 'mode' => $mode]);
    check(count($partial['failures']) === 6 && $partial['notice'] !== null, 'partial loading is explicit ' . $mode);
    check(array_column($partial['failures'], 'bookId') === ['hp-2', 'hp-3', 'hp-4', 'hp-5', 'hp-6', 'hp-7'], 'partial snapshot identifies failures ' . $mode);
    $partialStarted = $partialService->act('start', ['runId' => $partial['runId']]);
    check($partialStarted['failures'] === $partial['failures'] && $partialStarted['notice'] === $partial['notice'], 'partial warning persists through transitions ' . $mode);
    check(internalQuestion($storage, $partial['runId'])['book_id'] === 'hp-1', 'partial run uses successful book only ' . $mode);
}
rejects(fn () => $partialService->create(['name' => 'specific partial', 'mode' => 'specific', 'selectedBooks' => ['hp-1', 'hp-2']]), 503, 'specific partial load fails without fallback');
$specificSuccess = $partialService->create(['name' => 'specific success', 'mode' => 'specific', 'selectedBooks' => ['hp-1']]);
check($specificSuccess['failures'] === [] && $specificSuccess['notice'] === null, 'specific ignores unselected failed assets');
$rulesPath = $directory . '/rules.json';
foreach ([
    ['duration' => 10, 'contextLimit' => 60],
    ['duration' => 600, 'contextLimit' => 500],
    ['contextLimit' => 200, 'duration' => 30],
] as $rules) {
    file_put_contents($rulesPath, json_encode($rules, JSON_THROW_ON_ERROR));
    check((new Service($storage, null, null, $rulesPath))->rules === $rules, 'validated owner rules are configurable and order independent');
}
foreach ([
    ['duration' => 9, 'contextLimit' => 150],
    ['duration' => 601, 'contextLimit' => 150],
    ['duration' => 60, 'contextLimit' => 59],
    ['duration' => 60, 'contextLimit' => 501],
    ['duration' => '60', 'contextLimit' => 150],
    ['duration' => 60.5, 'contextLimit' => 150],
    ['duration' => 60, 'contextLimit' => 150, 'score' => 99],
    ['duration' => 60],
    ['duration' => null, 'contextLimit' => 150],
    [60, 150],
] as $rules) {
    file_put_contents($rulesPath, json_encode($rules, JSON_THROW_ON_ERROR));
    try {
        new Service($storage, null, null, $rulesPath);
        throw new LogicException('Invalid owner rules were accepted.');
    } catch (RuntimeException $error) {
        check(!$error instanceof LogicException, 'invalid configurable owner rules rejected');
    }
}
$oldTimed = $service->create(['name' => 'old config timer', 'mode' => 'timed']);
$service->act('start', ['runId' => $oldTimed['runId']]);
$oldMinimum = $service->create(['name' => 'old config minimum', 'mode' => 'minimum']);
$oldQuestion = $storage->query('SELECT * FROM questions WHERE version LIKE ? AND length(passage)>500 AND length(excerpt)>=140 LIMIT 1', ['hp-1:p1:c150:%'])->fetch();
rewrite($storage, $oldMinimum['runId'], static function (array &$run) use ($oldQuestion): void {
    $run['sequence'] = [$oldQuestion['id']];
});
$oldStarted = $service->act('start', ['runId' => $oldMinimum['runId']]);
file_put_contents($rulesPath, '{"duration":10,"contextLimit":200}');
$changedService = new Service($storage, static function () use (&$now): int { return $now; }, null, $rulesPath);
$newTimed = $changedService->create(['name' => 'new config timer', 'mode' => 'timed']);
$changedService->act('start', ['runId' => $newTimed['runId']]);
$newMinimum = $changedService->create(['name' => 'new config minimum', 'mode' => 'minimum']);
$newQuestion = $storage->query('SELECT * FROM questions WHERE stable_id=? AND version LIKE ?', [$oldQuestion['stable_id'], 'hp-1:p1:c200:%'])->fetch();
check($newQuestion['version'] !== $oldQuestion['version'] && $newQuestion['id'] !== $oldQuestion['id'], 'question cache version includes context limit and parser schema');
rewrite($storage, $newMinimum['runId'], static function (array &$run) use ($newQuestion): void {
    $run['sequence'] = [$newQuestion['id']];
});
$newStarted = $changedService->act('start', ['runId' => $newMinimum['runId']]);
check($newStarted['contextLimit'] === 200 && mb_strlen($newStarted['question']['passage']) > 150 && mb_strlen($newStarted['question']['passage']) <= 200, 'new minimum runs use newly configured cache excerpts');
check($changedService->act('state', ['runId' => $oldMinimum['runId']])['question']['passage'] === $oldStarted['question']['passage'], 'config change does not alter existing excerpt');
$now += 10000;
$oldResync = $changedService->act('state', ['runId' => $oldTimed['runId']]);
check($oldResync['duration'] === 60 && $oldResync['contextLimit'] === 150 && $oldResync['remainingMs'] === 50000, 'existing run retains creation configuration after owner edits');
$newExpired = $service->act('state', ['runId' => $newTimed['runId']]);
check($newExpired['duration'] === 10 && $newExpired['contextLimit'] === 200 && $newExpired['result']['elapsedMs'] === 10000 && $newExpired['result']['reason'] === 'time-expired', 'configured timer expiry stays authoritative across services');
$newEntry = array_values(array_filter($service->leaderboard('timed')['entries'], fn (array $entry): bool => $entry['id'] === $newExpired['result']['id']))[0];
check($newEntry['duration'] === 10 && $newEntry['contextLimit'] === 200, 'leaderboard persists run duration and context limit');
$newMinimumQuestion = internalQuestion($storage, $newMinimum['runId']);
$newMinimumEnded = answer($service, $newMinimum['runId'], 'book', $newMinimumQuestion['book_id'] === 'hp-1' ? 'hp-2' : 'hp-1');
$minimumEntry = array_values(array_filter($service->leaderboard('minimum')['entries'], fn (array $entry): bool => $entry['id'] === $newMinimumEnded['result']['id']))[0];
check($minimumEntry['contextLimit'] === 200 && $minimumEntry['duration'] === 10, 'historical minimum board uses actual recorded config rather than current service config');
$legacyPath = $directory . '/legacy.sqlite';
$legacy = new PDO('sqlite:' . $legacyPath);
$legacy->exec('CREATE TABLE scores(id INTEGER PRIMARY KEY AUTOINCREMENT,run_id TEXT NOT NULL UNIQUE,name TEXT NOT NULL,mode TEXT NOT NULL,score INTEGER NOT NULL,achieved_at TEXT NOT NULL,elapsed_ms INTEGER NOT NULL,duration INTEGER NOT NULL,selected_books TEXT NOT NULL); PRAGMA user_version=1');
$legacy->exec("INSERT INTO scores(run_id,name,mode,score,achieved_at,elapsed_ms,duration,selected_books) VALUES('legacy-run','legacy','minimum',1,'2026-01-01T00:00:00.000Z',1000,60,'[]')");
$legacy->exec('CREATE TABLE runs(id TEXT PRIMARY KEY,data TEXT NOT NULL,updated_ms INTEGER NOT NULL,expires_ms INTEGER NOT NULL)');
$legacy->exec("INSERT INTO runs(id,data,updated_ms,expires_ms) VALUES('legacy-configured-run','{\"contextLimit\":200}',0,0)");
$legacy->exec("INSERT INTO scores(run_id,name,mode,score,achieved_at,elapsed_ms,duration,selected_books) VALUES('legacy-configured-run','legacy configured','minimum',1,'2026-01-01T00:00:00.000Z',1000,60,'[]')");
$legacy = null;
$migrated = new Storage($legacyPath);
check((int) $migrated->query('SELECT context_limit FROM scores WHERE run_id=?', ['legacy-run'])->fetchColumn() === 150, 'legacy first-deploy score metadata defaults to150 during migration');
check((int) $migrated->db->query('PRAGMA user_version')->fetchColumn() === 1, 'schema1 remains versioned after additive metadata migration');
check((int) $migrated->query('SELECT context_limit FROM scores WHERE run_id=?', ['legacy-configured-run'])->fetchColumn() === 200, 'metadata migration recovers custom context limit from retained run');
for ($i = 0; $i < 20; $i++) {
    $storage->rateLimit('creator-a', $now, true);
}
rejects(fn () => $storage->rateLimit('creator-a', $now, true), 429, 'separate creation allowance20perfive minutes');
$storage->rateLimit('creator-a', $now);
check((int) $storage->query('SELECT count FROM rate_limits WHERE address=?', ['creator-a'])->fetchColumn() === 21, 'creation throttling does not prevent ordinary answer cadence');
$storage->rateLimit('creator-b', $now, true);
check((int) $storage->query('SELECT count FROM rate_limits WHERE address=?', ['create:creator-b'])->fetchColumn() === 1, 'creation allowance is independent per direct address');
$storage->rateLimit('creator-a', $now + 300000, true);
check((int) $storage->query('SELECT count FROM rate_limits WHERE address=?', ['create:creator-a'])->fetchColumn() === 1, 'creation allowance resets at five-minute boundary');
$storage->rateLimit('retention-check', $now + 900001);
check(!$storage->query("SELECT address FROM rate_limits WHERE address LIKE 'create:%'")->fetch(), 'creation rate rows expire after ten minutes');
$capacity = new Storage($directory . '/capacity.sqlite');
$capacityService = new Service($capacity, static function () use (&$now): int { return $now; });
$closedId = bin2hex(random_bytes(32));
$activeIds = [];
$capacity->transaction(function () use ($capacity, $closedId, &$activeIds, $now): void {
    for ($i = 0; $i < 1999; $i++) {
        $activeId = bin2hex(random_bytes(32));
        $activeIds[] = $activeId;
        $capacity->query('INSERT INTO runs(id,data,updated_ms,expires_ms,state) VALUES(?,?,?,?,?)', [$activeId, '{}', $now, $now + 86400000, 'ready']);
    }
    $capacity->query('INSERT INTO runs(id,data,updated_ms,expires_ms,state) VALUES(?,?,?,?,?)', [$closedId, '{}', $now - 1, $now + 86400000, 'completed']);
    $capacity->query('INSERT INTO scores(run_id,name,mode,score,achieved_at,elapsed_ms,duration,context_limit,selected_books) VALUES(?,?,?,?,?,?,?,?,?)', [$closedId, 'retained score', 'normal', 42, '2026-01-01T00:00:00.000Z', 100, 60, 150, '[]']);
    $capacity->query('INSERT INTO requests(run_id,request_id,payload,response) VALUES(?,?,?,?)', [$closedId, uuid(), '{}', '{}']);
});
$capacityService->create(['name' => 'capacity completion', 'mode' => 'specific', 'selectedBooks' => ['hp-1']]);
check((int) $capacity->query('SELECT COUNT(*) FROM runs')->fetchColumn() === 2000 && !$capacity->query('SELECT id FROM runs WHERE id=?', [$closedId])->fetch(), 'completed state evicted before capacity rejection');
check((int) $capacity->query('SELECT COUNT(*) FROM scores WHERE run_id=?', [$closedId])->fetchColumn() === 1 && !$capacity->query('SELECT run_id FROM requests WHERE run_id=?', [$closedId])->fetch(), 'capacity eviction retains scores and cascades request state');
rejects(fn () => $capacityService->create(['name' => 'all running', 'mode' => 'specific', 'selectedBooks' => ['hp-1']]), 503, 'all2000active sessions still reject excess capacity');
$capacity->query("UPDATE runs SET state='abandoned' WHERE id=?", [$activeIds[0]]);
$capacityService->create(['name' => 'capacity abandonment', 'mode' => 'specific', 'selectedBooks' => ['hp-1']]);
check(!$capacity->query('SELECT id FROM runs WHERE id=?', [$activeIds[0]])->fetch() && (int) $capacity->query('SELECT COUNT(*) FROM runs')->fetchColumn() === 2000, 'abandoned state evicted before capacity rejection');
echo json_encode(['assertions' => $count, 'normal' => count($normalPool), 'timed' => count($timedPool), 'minimum' => count($minimumPool), 'specific' => $bookCounts], JSON_THROW_ON_ERROR) . "\n";
