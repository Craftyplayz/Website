<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/api/Parser.php';
require_once dirname(__DIR__) . '/api/Storage.php';
require_once dirname(__DIR__) . '/api/Service.php';

use HPChapter\Parser;
use HPChapter\Storage;

// CLI-only helpers: the HTTP tests use these to inspect authoritative test data.
$action = $argv[1] ?? '';
if ($action === 'catalog') {
    $catalog = [];
    foreach (Parser::TITLES as $bookId => $title) {
        $book = Parser::parse(dirname(__DIR__) . '/books/book' . substr($bookId, 3) . '.epub', $bookId);
        $chapters = [];
        foreach ($book['chapters'] as $chapter) {
            $hash = hash_init('sha256');
            foreach ($chapter['paragraphs'] as $paragraph) {
                hash_update($hash, $paragraph['id'] . "\0" . $paragraph['text'] . "\0");
            }
            $chapters[] = [
                'id' => $chapter['id'], 'title' => $chapter['title'], 'href' => $chapter['href'],
                'count' => count($chapter['paragraphs']), 'hash' => hash_final($hash),
            ];
        }
        $catalog[] = ['id' => $bookId, 'chapters' => $chapters];
    }
    echo json_encode($catalog, JSON_THROW_ON_ERROR);
    exit;
}
$storage = new Storage($argv[2]);
$runId = $argv[3] ?? '';
if ($action === 'question') {
    $run = json_decode($storage->query('SELECT data FROM runs WHERE id=?', [$runId])->fetchColumn(), true, 512, JSON_THROW_ON_ERROR);
    echo json_encode($storage->query('SELECT q.*,b.book_id,b.chapters FROM questions q JOIN book_versions b ON b.version=q.version WHERE q.id=?', [$run['sequence'][$run['position']]])->fetch(), JSON_THROW_ON_ERROR);
} elseif ($action === 'expire') {
    $run = json_decode($storage->query('SELECT data FROM runs WHERE id=?', [$runId])->fetchColumn(), true, 512, JSON_THROW_ON_ERROR);
    $run['startedMs'] = (int) floor(microtime(true) * 1000) - 61000;
    $storage->query('UPDATE runs SET data=? WHERE id=?', [json_encode($run, JSON_THROW_ON_ERROR), $runId]);
} elseif ($action === 'deadline-lock') {
    $storage->transaction(function () use ($storage, $runId): void {
        $run = json_decode($storage->query('SELECT data FROM runs WHERE id=?', [$runId])->fetchColumn(), true, 512, JSON_THROW_ON_ERROR);
        $run['startedMs'] = (int) floor(microtime(true) * 1000) - 59900;
        $storage->query('UPDATE runs SET data=? WHERE id=?', [json_encode($run, JSON_THROW_ON_ERROR), $runId]);
        echo "locked\n";
        flush();
        usleep(250000);
    });
} elseif ($action === 'rates') {
    echo json_encode($storage->query('SELECT address,count FROM rate_limits ORDER BY address')->fetchAll(), JSON_THROW_ON_ERROR);
} elseif ($action === 'seed-rate') {
    $storage->query('INSERT INTO rate_limits(address,window_ms,count) VALUES(?,?,240) ON CONFLICT(address) DO UPDATE SET window_ms=excluded.window_ms,count=240', ['127.0.0.1', (int) floor(microtime(true) * 1000)]);
} elseif ($action === 'seed-create-rate') {
    $storage->query('INSERT INTO rate_limits(address,window_ms,count) VALUES(?,?,20) ON CONFLICT(address) DO UPDATE SET window_ms=excluded.window_ms,count=20', ['create:127.0.0.1', (int) floor(microtime(true) * 1000)]);
} elseif ($action === 'clear-rate') {
    $storage->query('DELETE FROM rate_limits');
}
