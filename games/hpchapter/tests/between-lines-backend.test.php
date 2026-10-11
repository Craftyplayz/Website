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
use HPChapter\BetweenTheLines as BTL;
use HPChapter\Parser;
use HPChapter\Service;
use HPChapter\Storage;

if (($argv[1] ?? '') === '--question') {
    $storage = new Storage($argv[2]);
    $run = json_decode($storage->query('SELECT data FROM runs WHERE id=?', [$argv[3]])->fetchColumn(), true, 512, JSON_THROW_ON_ERROR);
    echo json_encode($storage->query('SELECT descriptor FROM questions WHERE id=?', [$run['sequence'][$run['position']]])->fetch(), JSON_THROW_ON_ERROR);
    exit;
}

$directory = $argv[1] ?? dirname(__DIR__, 4) . '/.hpchapter-btl-tests-' . bin2hex(random_bytes(8));
if (!is_dir($directory)) {
    mkdir($directory, 0700, true);
}
register_shutdown_function(static function () use ($directory): void {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($directory);
});
$assertions = 0;
function btlCheck(bool $condition, string $message): void
{
    global $assertions;
    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
    $assertions++;
}
function btlReject(callable $operation, int $status, string $message): void
{
    try {
        $operation();
    } catch (ApiError $error) {
        btlCheck($error->status === $status, $message);
        return;
    }
    throw new RuntimeException('FAILED: did not reject ' . $message);
}
function btlUuid(): string
{
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3) . '-8' . substr($hex, 17, 3) . '-' . substr($hex, 20);
}
function btlBook(array $paragraphs): array
{
    return ['id' => 'hp-1', 'chapters' => [[
        'id' => 'hp-1:test', 'title' => 'Synthetic', 'href' => 'chapter.xhtml', 'paragraphs' => [],
        'sourceParagraphs' => array_map(static fn (string $text, int $i): array => ['id' => 'hp-1:test:p' . ($i + 1), 'text' => $text], $paragraphs, array_keys($paragraphs)),
    ]]];
}
function btlExtract(string $target, array $preceding = ['The bell rang.', 'The room fell silent.', 'Everyone listened.'], string $following = 'The door closed.'): array
{
    return BTL::extract(btlBook([...$preceding, $target, $following, 'A bird flew away.']));
}
function btlInternal(Storage $storage, string $runId): array
{
    $run = json_decode($storage->query('SELECT data FROM runs WHERE id=?', [$runId])->fetchColumn(), true, 512, JSON_THROW_ON_ERROR);
    return $storage->query('SELECT * FROM questions WHERE id=?', [$run['sequence'][$run['position']]])->fetch();
}

btlCheck(BTL::normalize(" \t‘ＷＨＩＳＰＥＲＥＤ!’\n") === 'whispered', 'NFKC, case, punctuation and whitespace normalization');
btlCheck(BTL::normalize('whis pered') === 'whis pered', 'internal whitespace is not silently removed');
btlCheck(BTL::normalize("whis \t\n  pered") === 'whis pered', 'repeated internal whitespace collapses to one space');
btlCheck(!BTL::matches("whis \t\n  pered", 'whispered'), 'internal spaces still reject single-token descriptor');
btlCheck(BTL::SETTINGS['editDistanceThresholds'] === [
    'short' => ['maximumLength' => 4, 'distance' => 0],
    'typical' => ['maximumLength' => 8, 'distance' => 1],
    'long' => ['maximumLength' => null, 'distance' => 2],
], 'word-length limits and edit budgets configured centrally');
foreach (BTL::SETTINGS['descriptors'] as $descriptor => $forms) {
    btlCheck(BTL::matches($descriptor, $descriptor), 'canonical source answer');
    btlCheck(BTL::matches('“' . strtoupper($descriptor) . '!”', $descriptor), 'case and punctuation');
    foreach ($forms as $form) {
        btlCheck(BTL::matches($form, $descriptor), 'explicit grammatical form ' . $form);
    }
    foreach (array_keys(BTL::SETTINGS['descriptors']) as $other) {
        if ($other !== $descriptor) {
            btlCheck(!BTL::matches($other, $descriptor), 'different allowed descriptor rejected');
        }
        foreach (BTL::SETTINGS['excludedDescriptors'] as $excluded) {
            btlCheck(!BTL::matches($excluded, $descriptor), 'basic tag and trivial variant robustly excluded');
        }
    }
}
btlCheck(BTL::matches('whspered', 'whispered'), 'one edit typical descriptor');
btlCheck(BTL::matches('stammerxx', 'stammered'), 'two edits long descriptor');
btlCheck(!BTL::matches('sop', 'sobbed'), 'short answers receive no edit tolerance');
btlCheck(!BTL::matches('whspred', 'whispered'), 'two edits typical descriptor rejected');
btlCheck(!BTL::matches('shouttering', 'shouted'), 'unconfigured invented grammatical variant rejected');
btlCheck(!BTL::matches('muttling', 'muttered'), 'no stemming heuristic');
btlCheck(!BTL::matches('hisses', 'whispered'), 'different exact variant rejected');
btlCheck(!BTL::matches('growled', 'snarled'), 'synonyms not accepted');
btlCheck(!BTL::matches('', 'whispered'), 'empty answer rejected');
btlCheck(!BTL::matches('whispered', 'said'), 'ordinary descriptor not allowed');
btlCheck(!BTL::matches("\xff", 'whispered'), 'invalid UTF-8 rejected centrally');
btlCheck(!BTL::matches('stuttered', 'sputtered'), 'nearby but distinct descriptor rejected');
btlCheck(!BTL::matches('sxuttered', 'stuttered') && !BTL::matches('sxuttered', 'sputtered'), 'ambiguous tied alternatives rejected');
btlCheck(!BTL::matches('sputterex', 'stuttered'), 'closer alternative rejected despite own edit budget');
btlCheck(BTL::matches('sniggred', 'sniggered'), 'configured expressive example with typo accepted');
btlCheck(BTL::matches('snapping', 'snapped'), 'configured snapped grammar accepted');
btlCheck(BTL::matches('splutter', 'spluttered'), 'configured spluttered grammar accepted');
btlCheck(!BTL::matches('sputtered', 'spluttered') && !BTL::matches('spluttered', 'sputtered'), 'different near expressive descriptors are not interchanged');
btlCheck(!BTL::matches(' “ＡＳＫＥＤ!” ', 'snapped'), 'basic exclusion follows the same Unicode normalization');

foreach (['sniggered', 'snapped', 'spluttered', 'retorted', 'growled', 'demanded'] as $descriptor) {
    $candidates = btlExtract('“Stay here,” ' . $descriptor . ' Harry.');
    btlCheck(count($candidates) === 1 && $candidates[0]['descriptor'] === $descriptor, 'explicit newly configured expressive attribution extracted');
}

foreach ([
    '“Be careful,” whispered Harry.',
    '“Be careful!” Harry whispered.',
    'Harry whispered, “Be careful.”',
    '‘Be careful,’ whispered Harry.',
    '‘Don’t move!’ Harry whispered.',
    'Harry whispered, ‘Don’t move.’',
    '"Be careful!" Harry whispered.',
    '“Be careful. Stay here!” Harry whispered.',
] as $target) {
    $candidates = btlExtract($target);
    btlCheck(count($candidates) === 1, 'clear post/pre quote, apostrophe and speech unit: ' . $target);
    $q = $candidates[0];
    btlCheck($q['descriptor'] === 'whispered', 'source original preserved');
    btlCheck($q['provenance']['targetSentence'] === $target, 'whole quoted utterance and attribution intact');
    btlCheck($q['provenance']['precedingCount'] === 3 && $q['provenance']['followingCount'] === 1, 'target centered three preceding exactly one following');
    btlCheck(str_ends_with($q['passage'], 'The door closed.') && !str_contains($q['passage'], 'A bird flew away.'), 'no second following sentence');
    btlCheck(!str_contains($q['passage'], 'whispered') && substr_count($q['passage'], '______') === 1, 'exactly one six-underscore missing descriptor');
}
foreach ([
    '“Be careful,” said Harry.', '“Be careful,” asked Harry.', '“Be careful,” replied Harry.',
    '“Be careful,” Harry smiled.', '“Be careful,” Harry walked.', 'Harry muttered something.',
    'Harry whispered that he knew.', '“Be careful,” a voice whispered.',
    '“Be careful,” Harry probably whispered.', '“Be careful,” whispered someone.',
    '“Be careful,” Harry shouted and laughed.', 'Harry whispered, “Be careful,” he muttered.',
    'Harry whispered, “Be careful,” he said.', 'Harry whispered, “Be careful,” Ron answered.',
    '“Be careful,” Harry snapped back.', 'Harry snapped back, “Be careful.”',
    '“Be careful!” Harry quietly whispered.',
    '“She wrote ‘Be careful,’ whispered Harry. That was the story.”',
    'If Harry whispered, “Be careful.”',
] as $target) {
    btlCheck(!btlExtract($target), 'ordinary action, basic tag or ambiguous attribution skipped: ' . $target);
}
btlCheck(BTL::sentences('“Be careful!” Harry quietly whispered.') === [
    ['text' => '“Be careful!” Harry quietly whispered.', 'offset' => 0],
], 'skipped adverbial attribution remains attached to its speech in context');
btlCheck(BTL::sentences('“Be careful!” Harry answered.') === [
    ['text' => '“Be careful!” Harry answered.', 'offset' => 0],
], 'excluded neutral speech tag remains attached in surrounding context');
btlCheck(!btlExtract('“Be careful,” whispered Harry.', ['Harry whispered earlier.', 'The bell rang.', 'Everyone listened.']), 'answer appearing in preceding context skipped');
btlCheck(!btlExtract('“Be careful,” whispered Harry.', following: 'Harry began whispering again.'), 'accepted variant in following context skipped');
$boundary = BTL::extract(btlBook(['“Be careful,” whispered Harry.']));
btlCheck(count($boundary) === 1 && $boundary[0]['provenance']['followingCount'] === 0, 'chapter last target works without invented following sentence');
btlCheck(!btlExtract('“' . str_repeat('word ', 600) . ',” whispered Harry.'), 'oversized target skipped, never sliced');
btlCheck(!btlExtract('“Be careful,” whispered Harry.', [str_repeat('word ', 600) . '.', 'The bell rang.', 'Everyone listened.']), 'oversized context skipped');
$boundaryBook = btlBook(['“Be careful,” whispered Harry.']);
$nextChapter = btlBook(['The door closed.'])['chapters'][0];
$nextChapter['id'] = 'hp-1:other';
$boundaryBook['chapters'][] = $nextChapter;
$boundary = BTL::extract($boundaryBook);
btlCheck(count($boundary) === 1 && $boundary[0]['provenance']['sourceContext'] === '“Be careful,” whispered Harry.'
    && $boundary[0]['provenance']['followingCount'] === 0, 'context never crosses chapter boundaries');

$counts = [];
$total = 0;
foreach (Parser::TITLES as $bookId => $title) {
    $book = Parser::parse(dirname(__DIR__) . '/books/book' . substr($bookId, 3) . '.epub', $bookId, true);
    $questions = BTL::extract($book);
    btlCheck($questions === BTL::extract($book), 'deterministic extraction ' . $bookId);
    btlCheck(count($questions) > 20, 'real book has useful conservative candidates ' . $bookId);
    $counts[$bookId] = count($questions);
    $total += count($questions);
    $paragraphs = [];
    foreach ($book['chapters'] as $chapter) {
        foreach ($chapter['sourceParagraphs'] as $paragraph) {
            $paragraphs[$paragraph['id']] = $paragraph['text'];
        }
        btlCheck(array_filter($chapter['paragraphs'], static fn (array $p): bool => !Parser::eligible($p['text'])) === [], 'legacy eligible pool unchanged');
    }
    $ids = [];
    foreach ($questions as $q) {
        $p = $q['provenance'];
        btlCheck(!isset($ids[$q['id']]), 'stable candidate unique per book');
        $ids[$q['id']] = true;
        btlCheck(substr($paragraphs[$p['paragraphId']], $p['offset'], strlen($q['descriptor'])) === $q['descriptor'], 'target byte offset is real source');
        btlCheck($p['bookId'] === $bookId && str_starts_with($p['paragraphId'], $p['chapterId'] . ':'), 'book chapter paragraph attribution');
        btlCheck($p['followingCount'] >= 0 && $p['followingCount'] <= 1 && $p['precedingCount'] <= 3, 'bounded sentence context with graceful chapter boundary');
        btlCheck($p['sourceParagraph'] === $paragraphs[$p['paragraphId']], 'full original source paragraph retained privately');
        btlCheck(count($p['context']) === $p['precedingCount'] + 1 + $p['followingCount'], 'context provenance accounts for every unit');
        btlCheck(implode(' ', array_column($p['context'], 'text')) === $p['sourceContext'], 'complete source context recoverable');
        foreach ($p['context'] as $context) {
            btlCheck(str_starts_with($context['paragraphId'], $p['chapterId'] . ':'), 'each context sentence is in same chapter');
            btlCheck(substr($paragraphs[$context['paragraphId']], $context['offset'], strlen($context['text'])) === $context['text'], 'each context sentence exact source');
        }
        btlCheck(substr_replace($p['sourceContext'], '______', $p['passageOffset'], strlen($q['descriptor'])) === $q['passage'], 'only source descriptor is masked with six underscores');
        btlCheck(mb_strlen($q['passage'], 'UTF-8') <= 2500 && !preg_match('/\b' . $q['descriptor'] . '\b/iu', $q['passage']), 'length and no answer appearance');
        btlCheck(BTL::matches($q['descriptor'], $q['descriptor']), 'canonical source is always accepted');
    }
}
btlCheck(BTL::version('hp-1', 'a') !== BTL::version('hp-1', 'b'), 'source hash versions private dataset');
btlCheck(str_contains(BTL::version('hp-1', 'a'), ':p' . Parser::SCHEMA_VERSION . ':'), 'parser schema participates in private dataset version');
btlCheck(str_contains(BTL::version('hp-1', 'a'), hash('sha256', json_encode(BTL::SETTINGS, JSON_THROW_ON_ERROR))), 'settings version private dataset');

$legacy = new PDO('sqlite:' . $directory . '/legacy.sqlite');
$legacy->exec(<<<'SQL'
CREATE TABLE book_versions(version TEXT PRIMARY KEY,book_id TEXT NOT NULL,chapters TEXT NOT NULL);
CREATE TABLE questions(id INTEGER PRIMARY KEY AUTOINCREMENT,version TEXT NOT NULL,stable_id TEXT NOT NULL,chapter_id TEXT NOT NULL,chapter_title TEXT NOT NULL,passage TEXT NOT NULL,excerpt TEXT,position INTEGER NOT NULL,UNIQUE(version,stable_id));
CREATE TABLE scores(id INTEGER PRIMARY KEY AUTOINCREMENT,run_id TEXT NOT NULL UNIQUE,name TEXT NOT NULL,mode TEXT NOT NULL,score INTEGER NOT NULL,achieved_at TEXT NOT NULL,elapsed_ms INTEGER NOT NULL,duration INTEGER NOT NULL,selected_books TEXT NOT NULL);
INSERT INTO book_versions VALUES('old','hp-1','[]');
INSERT INTO questions VALUES(1,'old','old:p1','old:chapter','Old','Existing passage.',NULL,0);
INSERT INTO scores VALUES(1,'old-run','Existing player','normal',5,'2026-01-01T00:00:00.000Z',500,60,'[]');
PRAGMA user_version=1;
SQL);
$legacy = null;
$migrated = new Storage($directory . '/legacy.sqlite');
$oldQuestion = $migrated->query('SELECT * FROM questions WHERE id=1')->fetch();
$oldScore = $migrated->query('SELECT * FROM scores WHERE id=1')->fetch();
btlCheck($oldQuestion['passage'] === 'Existing passage.' && $oldQuestion['descriptor'] === null && $oldQuestion['provenance'] === null, 'additive question migration preserves existing passage');
btlCheck($oldScore['score'] === 5 && $oldScore['answered'] === null && $oldScore['question_count'] === null, 'additive score migration does not invent historical denominators');

$now = 1790000000000;
$storage = new Storage($directory . '/btl.sqlite');
$service = new Service($storage, static function () use (&$now): int { return $now; });
$ready = $service->create(['name' => 'Descriptor tester', 'mode' => 'between-the-lines']);
$id = $ready['runId'];
btlCheck($ready['state'] === 'ready' && $ready['question'] === null && $ready['answered'] === 0 && $ready['questionCount'] === 20, 'server creates finite twenty question run');
$run = json_decode($storage->query('SELECT data FROM runs WHERE id=?', [$id])->fetchColumn(), true);
btlCheck(count(array_unique($run['sequence'])) === 20, 'finite sequence contains no repeated IDs');
$questionCount = (int) $storage->query('SELECT COUNT(*) FROM questions WHERE descriptor IS NOT NULL')->fetchColumn();
$second = $service->create(['name' => 'Cache tester', 'mode' => 'between-the-lines']);
btlCheck((int) $storage->query('SELECT COUNT(*) FROM questions WHERE descriptor IS NOT NULL')->fetchColumn() === $questionCount, 'private cached dataset reused');
$snapshot = $service->act('start', ['runId' => $id]);
btlCheck($snapshot['state'] === 'descriptor-selection' && array_keys($snapshot['question']) === ['id', 'passage'], 'no choices answers or provenance before answer');
btlCheck(preg_match('/^[a-f0-9]{64}$/D', $snapshot['question']['id']) === 1, 'opaque current question id');
$valid = ['runId' => $id, 'type' => 'descriptor', 'answerId' => 'whispered', 'questionId' => $snapshot['question']['id'], 'requestId' => btlUuid()];
foreach ([
    ['answerId' => ''], ['answerId' => '  !  '], ['answerId' => []], ['answerId' => str_repeat('é', 81)],
    ['answerId' => "\xff"], ['answerId' => "whis\0pered"], ['questionId' => null], ['questionId' => []],
    ['type' => 'unknown'], ['score' => 100], ['requestId' => 'not-a-uuid'],
] as $invalid) {
    btlReject(fn () => $service->act('answer', array_replace($valid, $invalid)), 400, 'invalid descriptor metadata rejected');
}
$missing = $valid;
unset($missing['questionId']);
btlReject(fn () => $service->act('answer', $missing), 400, 'missing question binding rejected');
btlReject(fn () => $service->act('answer', array_replace($valid, ['questionId' => str_repeat('f', 64)])), 409, 'wrong current question id rejected');
btlReject(fn () => $service->act('answer', ['runId' => $id, 'type' => 'book', 'answerId' => 'hp-1', 'requestId' => btlUuid()]), 409, 'book answers cannot score descriptor mode');
btlReject(fn () => $service->act('next', ['runId' => $id]), 409, 'cannot skip unanswered question');
btlReject(fn () => $service->act('finish', ['runId' => $id]), 409, 'cannot finish early');
$seen = [];
$expectedScore = 0;
$oldBody = null;
for ($i = 0; $i < 20; $i++) {
    $q = btlInternal($storage, $id);
    btlCheck(!isset($seen[$snapshot['question']['id']]), 'no repeated opaque questions');
    $seen[$snapshot['question']['id']] = true;
    if ($oldBody !== null) {
        btlReject(fn () => $service->act('answer', array_replace($oldBody, ['requestId' => btlUuid()])), 409, 'delayed old answer cannot apply to next question');
    }
    $correct = $i % 2 === 0;
    $answer = $correct ? ' ‘' . strtoupper($q['descriptor']) . '!‘ ' : ($q['descriptor'] === 'shouted' ? 'whispered' : 'shouted');
    $body = ['runId' => $id, 'type' => 'descriptor', 'answerId' => $answer, 'questionId' => $snapshot['question']['id'], 'requestId' => btlUuid()];
    $feedback = $service->act('answer', $body);
    $expectedScore += $correct ? 1 : 0;
    btlCheck($feedback['state'] === 'answer-feedback' && $feedback['result'] === null, 'feedback kept until explicit next, including last');
    btlCheck($feedback['answered'] === $i + 1 && $feedback['score'] === $expectedScore, 'each answer scores plus one or zero once');
    btlCheck($feedback['feedback'] === ['type' => 'descriptor', 'correct' => $correct, 'descriptor' => $q['descriptor']], 'only feedback reveals source original');
    btlCheck($service->act('answer', $body) === $feedback, 'same UUID is idempotent');
    btlReject(fn () => $service->act('answer', array_replace($body, ['requestId' => btlUuid()])), 409, 'new UUID cannot double answer');
    btlReject(fn () => $service->act('answer', array_replace($body, ['answerId' => 'different'])), 409, 'same UUID changed payload conflicts');
    $now += 500;
    $snapshot = $service->act('next', ['runId' => $id]);
    btlCheck($service->act('answer', $body)['score'] === $expectedScore, 'old exact retry after next cannot score again');
    $oldBody = $body;
}
btlCheck($snapshot['state'] === 'completed' && $snapshot['result']['score'] === 10 && $snapshot['result']['accuracy'] === 0.5, 'finite run finalizes with fractional accuracy');
btlCheck($snapshot['result']['answered'] === 20 && $snapshot['result']['questionCount'] === 20, 'final result includes answer denominator');
$scores = (int) $storage->query('SELECT COUNT(*) FROM scores WHERE run_id=?', [$id])->fetchColumn();
btlCheck($scores === 1, 'one persisted score');
$afterEnd = $service->act('answer', array_replace($oldBody, ['requestId' => btlUuid()]));
btlCheck($afterEnd['answered'] === 20 && $afterEnd['score'] === 10 && (int) $storage->query('SELECT COUNT(*) FROM scores WHERE run_id=?', [$id])->fetchColumn() === 1, 'after end does not mutate score or create duplicates');
$entry = $service->leaderboard('between-the-lines')['entries'][0];
btlCheck($entry['score'] === 10 && $entry['answered'] === 20 && $entry['questionCount'] === 20 && $entry['accuracy'] === 0.5, 'shared leaderboard persists score metadata');
$reopened = new Service(new Storage($directory . '/btl.sqlite'), static function () use (&$now): int { return $now; });
btlCheck($reopened->leaderboard('between-the-lines') === $service->leaderboard('between-the-lines'), 'metadata survives reopened storage');
$service->act('abandon', ['runId' => $second['runId']]);
btlCheck($service->act('state', ['runId' => $second['runId']])['state'] === 'abandoned', 'abandon remains supported');

mkdir($directory . '/books', 0700);
$zip = new ZipArchive();
$zip->open($directory . '/books/book1.epub', ZipArchive::CREATE);
$zip->addFromString('META-INF/container.xml', '<container><rootfiles><rootfile full-path="package.opf"/></rootfiles></container>');
$zip->addFromString('package.opf', '<package><manifest><item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/><item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/></manifest><spine><itemref idref="chapter"/></spine></package>');
$zip->addFromString('nav.xhtml', '<html><body><nav><a href="chapter.xhtml">Synthetic</a></nav></body></html>');
$zip->addFromString('chapter.xhtml', '<html><body><p>The bell rang.</p><p>Everyone listened.</p><p>“Stay here,” <em>whispered</em> Harry.</p><p>The door closed.</p></body></html>');
$zip->close();
btlCheck(Parser::parse($directory . '/books/book1.epub', 'hp-1')['chapters'] === [], 'short dialogue does not change old eligible chapter pool');
$complete = Parser::parse($directory . '/books/book1.epub', 'hp-1', true);
btlCheck(count($complete['chapters'][0]['sourceParagraphs']) === 4 && $complete['chapters'][0]['paragraphs'] === [], 'short complete source paragraphs and inline markup preserved');
$smallStorage = new Storage($directory . '/small.sqlite');
$small = new Service($smallStorage, static function () use (&$now): int { return $now; }, $directory . '/books');
$smallRun = $small->create(['name' => 'Small pool', 'mode' => 'between-the-lines']);
btlCheck($smallRun['questionCount'] === 1 && count($smallRun['failures']) === 6, 'smaller available pool is graceful and finite');
$smallSnapshot = $small->act('start', ['runId' => $smallRun['runId']]);
$smallSnapshot = $small->act('answer', ['runId' => $smallRun['runId'], 'type' => 'descriptor', 'answerId' => 'whisper', 'questionId' => $smallSnapshot['question']['id'], 'requestId' => btlUuid()]);
btlCheck($smallSnapshot['state'] === 'answer-feedback' && $smallSnapshot['score'] === 1, 'last small-pool passage feedback remains visible');
$smallSnapshot = $small->act('next', ['runId' => $smallRun['runId']]);
btlCheck($smallSnapshot['result']['answered'] === 1 && (float) $smallSnapshot['result']['accuracy'] === 1.0, 'small pool denominator is actual count');
btlReject(fn () => (new Service($smallStorage, null, $directory . '/missing-books'))->create(['name' => 'No pool', 'mode' => 'between-the-lines']), 503, 'empty pool never starts an unplayable run');

echo json_encode(['assertions' => $assertions, 'counts' => $counts, 'totalCandidates' => $total, 'runQuestions' => 20, 'score' => 10], JSON_THROW_ON_ERROR) . "\n";
