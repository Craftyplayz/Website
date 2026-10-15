<?php
declare(strict_types=1);

namespace HPChapter;

final class ApiError extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message, public readonly ?int $retryAfter = null)
    {
        parent::__construct($message);
    }
}

final class Service
{
    public const MODES = ['normal', 'timed', 'minimum', 'specific'];
    public readonly array $rules;
    private readonly \Closure $clock;

    public function __construct(
        private readonly Storage $storage,
        ?callable $clock = null,
        private readonly ?string $booksDirectory = null,
        ?string $rulesPath = null,
    )
    {
        $this->clock = $clock ? \Closure::fromCallable($clock) : static fn (): int => (int) floor(microtime(true) * 1000);
        $source = @file_get_contents($rulesPath ?? dirname(__DIR__) . '/rules.json');
        if ($source === false) {
            throw new \RuntimeException('Server rules unavailable.');
        }
        $rules = json_decode($source, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($rules) || count($rules) !== 2 || array_diff(array_keys($rules), ['duration', 'contextLimit'])
            || !isset($rules['duration'], $rules['contextLimit'])
            || !is_int($rules['duration']) || $rules['duration'] < 10 || $rules['duration'] > 600
            || !is_int($rules['contextLimit']) || $rules['contextLimit'] < 60 || $rules['contextLimit'] > 500) {
            throw new \RuntimeException('Invalid server rules.');
        }
        $this->rules = $rules;
    }

    public function now(): int
    {
        return ($this->clock)();
    }

    private function fields(array $body, array $required, array $optional = []): void
    {
        if (array_diff(array_keys($body), [...$required, ...$optional]) || array_diff($required, array_keys($body))) {
            throw new ApiError(400, 'Missing or unknown request fields.');
        }
    }

    private function mode(mixed $mode): string
    {
        if (!is_string($mode) || !in_array($mode, self::MODES, true)) {
            throw new ApiError(400, 'Choose a valid game mode.');
        }
        return $mode;
    }

    private function version(string $bookId): string
    {
        $file = ($this->booksDirectory ?? dirname(__DIR__) . '/books') . '/book' . substr($bookId, 3) . '.epub';
        if (!is_file($file) || !is_readable($file)) {
            throw new \RuntimeException('The selected EPUB is unavailable.');
        }
        $hash = @hash_file('sha256', $file);
        if ($hash === false) {
            throw new \RuntimeException('The selected EPUB is unavailable.');
        }
        $version = $bookId . ':p' . Parser::SCHEMA_VERSION . ':c' . $this->rules['contextLimit'] . ':' . $hash;
        if ($this->storage->query('SELECT version FROM book_versions WHERE version=?', [$version])->fetch()) {
            return $version;
        }
        $book = Parser::parse($file, $bookId);
        if (!$book['chapters']) {
            throw new \RuntimeException('The selected EPUB contains no usable chapters.');
        }
        $choices = array_map(static fn (array $chapter): array => ['id' => $chapter['id'], 'title' => $chapter['title']], $book['chapters']);
        $this->storage->transaction(function () use ($book, $version, $bookId, $choices): void {
            $this->storage->query('INSERT OR IGNORE INTO book_versions(version,book_id,chapters) VALUES(?,?,?)', [$version, $bookId, json_encode($choices, JSON_THROW_ON_ERROR)]);
            $position = 0;
            foreach ($book['chapters'] as $chapter) {
                foreach ($chapter['paragraphs'] as $paragraph) {
                    $this->storage->query('INSERT OR IGNORE INTO questions(version,stable_id,chapter_id,chapter_title,passage,excerpt,position) VALUES(?,?,?,?,?,?,?)', [
                        $version, $paragraph['id'], $chapter['id'], $chapter['title'],
                        $paragraph['text'], Parser::excerpt($paragraph['text'], $this->rules['contextLimit']), $position++,
                    ]);
                }
            }
        });
        return $version;
    }

    public function pool(string $mode, array $selected, ?array &$failures = null): array
    {
        $failures = [];
        $seen = [];
        $ids = [];
        // Canonical book order preserves the browser pool's first attribution.
        foreach (array_keys(Parser::TITLES) as $bookId) {
            if ($mode === 'specific' && !in_array($bookId, $selected, true)) {
                continue;
            }
            try {
                $version = $this->version($bookId);
            } catch (\RuntimeException $error) {
                if ($error instanceof \PDOException) {
                    throw $error;
                }
                if ($mode === 'specific') {
                    throw new ApiError(503, 'Selected book ' . $bookId . ' is unavailable or has no usable passages.');
                }
                $failures[] = [
                    'bookId' => $bookId, 'title' => 'Harry Potter and the ' . Parser::TITLES[$bookId],
                    'error' => 'The book could not be loaded or contained no eligible passages.',
                ];
                continue;
            }
            $rows = $this->storage->query('SELECT id,passage,excerpt FROM questions WHERE version=? ORDER BY position', [$version]);
            while ($row = $rows->fetch()) {
                $passage = $mode === 'minimum' ? $row['excerpt'] : $row['passage'];
                if ($passage === null || isset($seen[$passage])) {
                    continue;
                }
                $seen[$passage] = true;
                $ids[] = $row['id'];
            }
        }
        if (!$ids) {
            throw new ApiError(503, 'No usable passages are available for this selection.');
        }
        return $ids;
    }

    public function create(array $body): array
    {
        $this->fields($body, ['name', 'mode'], ['selectedBooks']);
        $mode = $this->mode($body['mode']);
        $name = $body['name'];
        if (!is_string($name) || !mb_check_encoding($name, 'UTF-8') || preg_match('/[\p{C}\p{Zl}\p{Zp}]/u', $name)) {
            throw new ApiError(400, 'Use a public name without control characters.');
        }
        $name = Parser::normalize($name);
        if (mb_strlen($name, 'UTF-8') < 1 || mb_strlen($name, 'UTF-8') > 20) {
            throw new ApiError(400, 'Public names must contain 1 to 20 Unicode characters.');
        }
        $selected = $body['selectedBooks'] ?? [];
        if ($mode !== 'specific' && array_key_exists('selectedBooks', $body)) {
            throw new ApiError(400, 'selectedBooks is only valid in specific mode.');
        }
        if ($mode === 'specific' && (!is_array($selected) || !array_is_list($selected) || !$selected || count($selected) > 7)) {
            throw new ApiError(400, 'Select at least one valid book.');
        }
        foreach ($selected as $id) {
            if (!is_string($id) || !isset(Parser::TITLES[$id])) {
                throw new ApiError(400, 'Select valid book IDs.');
            }
        }
        if (count(array_unique($selected)) !== count($selected)) {
            throw new ApiError(400, 'Selected books must not be repeated.');
        }
        $selected = array_values(array_intersect(array_keys(Parser::TITLES), $selected));
        $sequence = $this->pool($mode, $selected, $failures);
        for ($i = count($sequence) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$sequence[$i], $sequence[$j]] = [$sequence[$j], $sequence[$i]];
        }
        $now = $this->now();
        $run = [
            'runId' => bin2hex(random_bytes(32)), 'mode' => $mode, 'name' => $name, 'selectedBooks' => $selected,
            'duration' => $this->rules['duration'], 'contextLimit' => $this->rules['contextLimit'],
            'state' => 'ready', 'score' => 0, 'sequence' => $sequence, 'position' => 0,
            'questionIdKey' => bin2hex(random_bytes(32)),
            'startedMs' => null, 'createdMs' => $now, 'finishedMs' => null, 'result' => null, 'feedback' => null,
            'failures' => $failures,
            'notice' => $failures ? 'Some books could not be loaded. This run uses the available books only. Return to the menu to retry.' : null,
        ];
        return $this->storage->transaction(function () use ($run, $now): array {
            $count = (int) $this->storage->query('SELECT COUNT(*) FROM runs')->fetchColumn();
            if ($count >= 2000) {
                $this->storage->query("DELETE FROM runs WHERE id IN (SELECT id FROM runs WHERE state IN ('completed','abandoned') ORDER BY updated_ms,id LIMIT ?)", [$count - 1999]);
            }
            if ((int) $this->storage->query('SELECT COUNT(*) FROM runs')->fetchColumn() >= 2000) {
                throw new ApiError(503, 'The game is busy. Please try again later.');
            }
            $this->storage->query('INSERT INTO runs(id,data,updated_ms,expires_ms,state) VALUES(?,?,?,?,?)', [$run['runId'], json_encode($run, JSON_THROW_ON_ERROR), $now, $now + 86400000, $run['state']]);
            return $this->snapshot($run, $now);
        });
    }

    private function question(array $run): array
    {
        $id = $run['sequence'][$run['position']] ?? null;
        $question = $id ? $this->storage->query('SELECT q.*,b.book_id,b.chapters FROM questions q JOIN book_versions b ON q.version=b.version WHERE q.id=?', [$id])->fetch() : false;
        if (!$question) {
            throw new ApiError(503, 'This run’s question is unavailable.');
        }
        return $question;
    }

    private function elapsed(array $run, int $now): int
    {
        return $run['startedMs'] === null ? 0 : max(0, ($run['finishedMs'] ?? $now) - $run['startedMs']);
    }

    private function snapshot(array $run, int $now): array
    {
        $elapsed = $this->elapsed($run, $now);
        $snapshot = [
            'runId' => $run['runId'], 'mode' => $run['mode'], 'name' => $run['name'], 'selectedBooks' => $run['selectedBooks'],
            'duration' => $run['duration'], 'contextLimit' => $run['contextLimit'], 'state' => $run['state'],
            'score' => $run['score'], 'elapsedMs' => $elapsed,
            'remainingMs' => $run['mode'] === 'timed' ? max(0, $run['duration'] * 1000 - $elapsed) : null,
            'question' => null, 'result' => $run['result'],
            'failures' => $run['failures'] ?? [], 'notice' => $run['notice'] ?? null,
        ];
        if (in_array($run['state'], ['book-selection', 'chapter-selection', 'answer-feedback'], true)) {
            $question = $this->question($run);
            // Stable IDs expose answers; use a private key, not the client-known bearer token.
            $snapshot['question'] = [
                'id' => hash_hmac('sha256', $question['stable_id'], hex2bin($run['questionIdKey'])),
                'passage' => $run['mode'] === 'minimum' ? $question['excerpt'] : $question['passage'],
            ];
            if ($run['state'] !== 'book-selection') {
                $snapshot['question']['chapterChoices'] = json_decode($question['chapters'], true, 512, JSON_THROW_ON_ERROR);
            }
        }
        if ($run['feedback'] !== null) {
            $snapshot['feedback'] = $run['feedback'];
        }
        return $snapshot;
    }

    private function finalize(array &$run, string $reason, int $now): void
    {
        $run['finishedMs'] = $reason === 'time-expired' ? $run['startedMs'] + $run['duration'] * 1000 : $now;
        $elapsed = $this->elapsed($run, $now);
        $this->storage->query('INSERT INTO scores(run_id,name,mode,score,achieved_at,elapsed_ms,duration,context_limit,selected_books) VALUES(?,?,?,?,?,?,?,?,?) ON CONFLICT(run_id) DO NOTHING', [
            $run['runId'], $run['name'], $run['mode'], $run['score'], self::utc($now), $elapsed, $run['duration'],
            $run['contextLimit'],
            json_encode($run['selectedBooks'], JSON_THROW_ON_ERROR),
        ]);
        $entry = $this->storage->query('SELECT id,score,elapsed_ms,achieved_at FROM scores WHERE run_id=?', [$run['runId']])->fetch();
        $rank = (int) $this->storage->query(<<<'SQL'
SELECT COUNT(*)+1 FROM scores WHERE mode=? AND (
 score>? OR (score=? AND elapsed_ms<?)
 OR (score=? AND elapsed_ms=? AND achieved_at<?)
 OR (score=? AND elapsed_ms=? AND achieved_at=? AND id<?)
)
SQL, [
            $run['mode'], $entry['score'], $entry['score'], $entry['elapsed_ms'],
            $entry['score'], $entry['elapsed_ms'], $entry['achieved_at'],
            $entry['score'], $entry['elapsed_ms'], $entry['achieved_at'], $entry['id'],
        ])->fetchColumn();
        $run['state'] = 'completed';
        $run['result'] = [
            'score' => $run['score'], 'reason' => $reason, 'elapsedMs' => $elapsed, 'accepted' => true,
            'id' => (int) $entry['id'], 'entryId' => (int) $entry['id'], 'rank' => $rank,
        ];
    }

    private static function utc(int $milliseconds): string
    {
        return gmdate('Y-m-d\TH:i:s', intdiv($milliseconds, 1000)) . sprintf('.%03dZ', $milliseconds % 1000);
    }

    private function expire(array &$run, int $now): bool
    {
        if ($run['mode'] !== 'timed' || $run['startedMs'] === null
            || in_array($run['state'], ['completed', 'abandoned'], true)
            || $now < $run['startedMs'] + $run['duration'] * 1000) {
            return false;
        }
        $run['feedback'] = null;
        $this->finalize($run, 'time-expired', $now);
        return true;
    }

    public function act(string $action, array $body): array
    {
        $this->fields($body, $action === 'answer' ? ['runId', 'type', 'answerId', 'requestId'] : ['runId']);
        if (!is_string($body['runId']) || !preg_match('/^[a-f0-9]{64}$/D', $body['runId'])) {
            throw new ApiError(400, 'Invalid run ID.');
        }
        if ($action === 'answer' && (!is_string($body['type']) || !in_array($body['type'], ['book', 'chapter'], true)
            || !is_string($body['answerId']) || strlen($body['answerId']) > 200
            || !is_string($body['requestId']) || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $body['requestId']))) {
            throw new ApiError(400, 'Invalid answer metadata or request UUID.');
        }
        $response = $this->storage->transaction(function () use ($action, $body): array|ApiError {
            $row = $this->storage->query('SELECT data,updated_ms,expires_ms FROM runs WHERE id=?', [$body['runId']])->fetch();
            $now = $this->now();
            if (!$row || $row['expires_ms'] <= $now || $row['updated_ms'] < $now - 86400000) {
                throw new ApiError(404, 'This run is unavailable or has expired.');
            }
            $run = json_decode($row['data'], true, 512, JSON_THROW_ON_ERROR);
            if (!isset($run['questionIdKey'])) {
                $run['questionIdKey'] = bin2hex(random_bytes(32));
            }
            $initialState = $run['state'];
            $payload = $action === 'answer' ? json_encode([$body['type'], $body['answerId']], JSON_THROW_ON_ERROR) : null;
            $cached = $action === 'answer' ? $this->storage->query('SELECT payload,response FROM requests WHERE run_id=? AND request_id=?', [$run['runId'], $body['requestId']])->fetch() : false;
            $conflict = $cached && $cached['payload'] !== $payload;
            $active = !in_array($run['state'], ['completed', 'abandoned'], true);
            $now = $this->now();
            $expired = $this->expire($run, $now);
            if (!$expired && $active && !$cached) {
                $this->transition($run, $action, $body, $now);
            }
            $response = $this->snapshot($run, $now);
            $this->storage->query('UPDATE runs SET data=?,updated_ms=?,state=? WHERE id=?', [json_encode($run, JSON_THROW_ON_ERROR), $now, $run['state'], $run['runId']]);
            if ($conflict) {
                // Commit any expiry first; a conflicting UUID must not roll back finalization.
                return new ApiError(409, 'This request ID was already used for another answer.');
            }
            if ($action === 'answer' && !$cached && !in_array($initialState, ['completed', 'abandoned'], true)) {
                $this->storage->query('INSERT INTO requests(run_id,request_id,payload,response) VALUES(?,?,?,?)', [$run['runId'], $body['requestId'], $payload, json_encode($response, JSON_THROW_ON_ERROR)]);
            }
            return $response;
        });
        if ($response instanceof ApiError) {
            throw $response;
        }
        return $response;
    }

    private function transition(array &$run, string $action, array $body, int &$now): void
    {
        if ($action === 'state') {
            return;
        }
        if ($action === 'abandon') {
            $now = $this->now();
            if ($this->expire($run, $now)) {
                return;
            }
            $run['state'] = 'abandoned';
            $run['finishedMs'] = $now;
            $run['feedback'] = null;
            return;
        }
        if ($action === 'start') {
            if ($run['state'] === 'ready') {
                $run['startedMs'] = $now;
                $run['state'] = 'book-selection';
            }
            return;
        }
        if ($action === 'finish') {
            throw new ApiError(409, 'This run cannot be finished before it ends.');
        }
        if ($action === 'next') {
            if ($run['state'] !== 'answer-feedback') {
                throw new ApiError(409, 'Answer the current book and chapter before continuing.');
            }
            $run['position']++;
            $run['feedback'] = null;
            if ($run['position'] >= count($run['sequence'])) {
                $this->finalize($run, 'pool-exhausted', $now);
            } else {
                $run['state'] = 'book-selection';
            }
            return;
        }
        $expected = $body['type'] === 'book' ? 'book-selection' : 'chapter-selection';
        if ($run['state'] !== $expected) {
            throw new ApiError(409, 'This answer is not allowed at the current stage.');
        }
        $question = $this->question($run);
        if ($body['type'] === 'book') {
            if (!isset(Parser::TITLES[$body['answerId']])) {
                throw new ApiError(400, 'Choose a valid book.');
            }
            $now = $this->now();
            if ($this->expire($run, $now)) {
                return;
            }
            $correct = $body['answerId'] === $question['book_id'];
            $run['feedback'] = [
                'type' => 'book', 'correct' => $correct, 'correctBookId' => $question['book_id'],
                'bookTitle' => 'Harry Potter and the ' . Parser::TITLES[$question['book_id']],
            ];
            if ($correct) {
                $run['state'] = 'chapter-selection';
            } else {
                $run['feedback']['chapterTitle'] = $question['chapter_title'];
                $this->finalize($run, 'incorrect-book', $now);
            }
        } else {
            $choices = json_decode($question['chapters'], true, 512, JSON_THROW_ON_ERROR);
            if (!in_array($body['answerId'], array_column($choices, 'id'), true)) {
                throw new ApiError(400, 'Choose a chapter offered for this question.');
            }
            $correct = $body['answerId'] === $question['chapter_id'];
            // Recheck after canonical question lookup/validation, immediately before scoring.
            $now = $this->now();
            if ($this->expire($run, $now)) {
                return;
            }
            if ($correct) {
                $run['score']++;
            }
            $run['feedback'] = [
                'type' => 'chapter', 'correct' => $correct,
                'correctChapterId' => $question['chapter_id'], 'chapterTitle' => $question['chapter_title'],
            ];
            $run['state'] = 'answer-feedback';
            if ($run['position'] === count($run['sequence']) - 1) {
                $this->finalize($run, 'pool-exhausted', $now);
            }
        }
    }

    public function leaderboard(mixed $mode, int $limit = 100): array
    {
        $mode = $this->mode($mode);
        if ($limit < 1 || $limit > 100) {
            throw new ApiError(400, 'Leaderboard limit must be between 1 and 100.');
        }
        $entries = $this->storage->query('SELECT id,name,mode,score,achieved_at,elapsed_ms,duration,context_limit,selected_books FROM scores WHERE mode=? ORDER BY score DESC,elapsed_ms ASC,achieved_at ASC,id ASC LIMIT ?', [$mode, $limit])->fetchAll();
        return ['mode' => $mode, 'entries' => array_map(static fn (array $entry): array => [
            'id' => (int) $entry['id'], 'name' => $entry['name'], 'score' => (int) $entry['score'],
            'achievedAt' => $entry['achieved_at'], 'elapsedMs' => (int) $entry['elapsed_ms'],
            'duration' => (int) $entry['duration'], 'selectedBooks' => json_decode($entry['selected_books'], true, 8, JSON_THROW_ON_ERROR),
            'mode' => $entry['mode'], 'contextLimit' => (int) $entry['context_limit'],
        ], $entries)];
    }
}
