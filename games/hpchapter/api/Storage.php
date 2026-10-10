<?php
declare(strict_types=1);

namespace HPChapter;

final class Storage
{
    public readonly \PDO $db;

    public function __construct(?string $path = null)
    {
        $repositoryRoot = realpath(dirname(__DIR__, 3));
        $documentRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : $repositoryRoot;
        $publicRoots = array_unique(array_filter([$repositoryRoot, $documentRoot]));
        $outerRoot = $repositoryRoot;
        foreach ($publicRoots as $root) {
            if (self::within($outerRoot, $root)) {
                $outerRoot = $root;
            }
        }
        $default = dirname($outerRoot) . '/.hpchapter-private/' . substr(hash('sha256', __DIR__), 0, 16) . '/scores.sqlite';
        $configured = $path !== null || (getenv('HPCHAPTER_DB_PATH') !== false && getenv('HPCHAPTER_DB_PATH') !== '');
        $path ??= getenv('HPCHAPTER_DB_PATH') ?: $default;
        self::rejectSymlinks(str_starts_with($path, '/') ? $path : getcwd() . '/' . $path);
        $absolute = self::normalizePath(str_starts_with($path, '/') ? $path : getcwd() . '/' . $path);
        $canonical = self::canonicalPath($path);
        foreach ($publicRoots as $root) {
            // Check both spellings: a public symlink to a private database also exposes WAL/SHM.
            if (self::within($absolute, $root) || self::within($canonical, $root)) {
                throw new \RuntimeException('The database must be outside the public repository and document root.');
            }
        }
        $path = $canonical;
        if (!is_dir(dirname($path)) && !@mkdir(dirname($path), 0700, true) && !is_dir(dirname($path))) {
            throw new \RuntimeException('Database directory unavailable.');
        }
        self::rejectSymlinks($path);
        self::privateDirectory(dirname($path));
        if (!$configured) {
            self::privateDirectory(dirname(dirname($path)));
        }
        if (is_file($path) && function_exists('posix_geteuid') && fileowner($path) !== posix_geteuid()) {
            throw new \RuntimeException('Database owner does not match the PHP service user.');
        }
        $this->db = new \PDO('sqlite:' . $path, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        @chmod($path, 0600);
        $this->db->exec('PRAGMA busy_timeout=10000; PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON');
        $schemaVersion = (int) $this->db->query('PRAGMA user_version')->fetchColumn();
        if ($schemaVersion > 1) {
            throw new \RuntimeException('Unsupported database schema version.');
        }
        $this->db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS book_versions (
 version TEXT PRIMARY KEY, book_id TEXT NOT NULL, chapters TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS questions (
 id INTEGER PRIMARY KEY AUTOINCREMENT, version TEXT NOT NULL REFERENCES book_versions(version),
 stable_id TEXT NOT NULL, chapter_id TEXT NOT NULL, chapter_title TEXT NOT NULL,
 passage TEXT NOT NULL, excerpt TEXT, position INTEGER NOT NULL, UNIQUE(version,stable_id)
);
CREATE INDEX IF NOT EXISTS questions_version ON questions(version,position);
CREATE TABLE IF NOT EXISTS runs (
 id TEXT PRIMARY KEY, data TEXT NOT NULL, updated_ms INTEGER NOT NULL, expires_ms INTEGER NOT NULL,
 state TEXT NOT NULL DEFAULT 'ready'
);
CREATE INDEX IF NOT EXISTS runs_updated ON runs(updated_ms);
CREATE INDEX IF NOT EXISTS runs_expiry ON runs(expires_ms);
CREATE TABLE IF NOT EXISTS requests (
 run_id TEXT NOT NULL REFERENCES runs(id) ON DELETE CASCADE, request_id TEXT NOT NULL,
 payload TEXT NOT NULL, response TEXT NOT NULL, PRIMARY KEY(run_id,request_id)
);
CREATE TABLE IF NOT EXISTS scores (
 id INTEGER PRIMARY KEY AUTOINCREMENT, run_id TEXT NOT NULL UNIQUE,
 name TEXT NOT NULL, mode TEXT NOT NULL, score INTEGER NOT NULL,
 achieved_at TEXT NOT NULL, elapsed_ms INTEGER NOT NULL, duration INTEGER NOT NULL,
 context_limit INTEGER NOT NULL DEFAULT 150, selected_books TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS scores_ranking ON scores(mode,score DESC,elapsed_ms ASC,achieved_at ASC,id ASC);
CREATE TABLE IF NOT EXISTS rate_limits (
 address TEXT PRIMARY KEY, window_ms INTEGER NOT NULL, count INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS rates_window ON rate_limits(window_ms);
SQL);
        $this->transaction(function (): void {
            $runColumns = $this->db->query('PRAGMA table_info(runs)')->fetchAll();
            if (!in_array('state', array_column($runColumns, 'name'), true)) {
                $this->db->exec("ALTER TABLE runs ADD COLUMN state TEXT NOT NULL DEFAULT 'ready'");
                foreach ($this->query('SELECT id,data FROM runs')->fetchAll() as $row) {
                    $run = json_decode($row['data'], true);
                    if (isset($run['state']) && is_string($run['state'])) {
                        $this->query('UPDATE runs SET state=? WHERE id=?', [$run['state'], $row['id']]);
                    }
                }
            }
            $this->db->exec('CREATE INDEX IF NOT EXISTS runs_state ON runs(state,updated_ms)');
            $columns = $this->db->query('PRAGMA table_info(scores)')->fetchAll();
            if (!in_array('context_limit', array_column($columns, 'name'), true)) {
                $this->db->exec('ALTER TABLE scores ADD COLUMN context_limit INTEGER NOT NULL DEFAULT 150');
                $existing = $this->query('SELECT s.id,r.data FROM scores s JOIN runs r ON r.id=s.run_id')->fetchAll();
                foreach ($existing as $row) {
                    $run = json_decode($row['data'], true);
                    $limit = $run['contextLimit'] ?? null;
                    if (is_int($limit) && $limit >= 60 && $limit <= 500) {
                        $this->query('UPDATE scores SET context_limit=? WHERE id=?', [$limit, $row['id']]);
                    }
                }
            }
        });
        if ($schemaVersion === 0) {
            $this->db->exec('PRAGMA user_version=1');
        }
    }

    private static function within(string $path, string $root): bool
    {
        return $path === $root || str_starts_with($path, rtrim($root, '/') . '/');
    }

    private static function rejectSymlinks(string $path): void
    {
        for ($entry = $path; $entry !== '/'; $entry = dirname($entry)) {
            if (is_link($entry)) {
                throw new \RuntimeException('Private database paths must not contain symbolic links.');
            }
        }
    }

    private static function privateDirectory(string $directory): void
    {
        $stat = @stat($directory);
        if (!$stat || ($stat['mode'] & 0077) !== 0
            || (function_exists('posix_geteuid') && $stat['uid'] !== posix_geteuid())) {
            throw new \RuntimeException('Private database directories require service ownership and permissions 0700.');
        }
    }

    private static function normalizePath(string $path): string
    {
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
            } else {
                $parts[] = $part;
            }
        }
        return '/' . implode('/', $parts);
    }

    private static function canonicalPath(string $path): string
    {
        $path = str_starts_with($path, '/') ? $path : getcwd() . '/' . $path;
        $suffix = [];
        while (!file_exists($path) && !is_link($path)) {
            array_unshift($suffix, basename($path));
            $parent = dirname($path);
            if ($parent === $path) {
                throw new \RuntimeException('Database path unavailable.');
            }
            $path = $parent;
        }
        $ancestor = realpath($path);
        if ($ancestor === false) {
            throw new \RuntimeException('Database path unavailable.');
        }
        return self::normalizePath($ancestor . '/' . implode('/', $suffix));
    }

    public function query(string $sql, array $parameters = []): \PDOStatement
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);
        return $statement;
    }

    public function transaction(callable $callback): mixed
    {
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $result = $callback();
            $this->db->exec('COMMIT');
            return $result;
        } catch (\Throwable $error) {
            $this->db->exec('ROLLBACK');
            throw $error;
        }
    }

    public function rateLimit(string $address, int $now, bool $creating = false): void
    {
        $this->transaction(function () use ($address, $now, $creating): void {
            // Expired abandoned runs are deleted, never automatically submitted.
            $this->query('DELETE FROM runs WHERE expires_ms <= ? OR updated_ms < ?', [$now, $now - 86400000]);
            $this->query("DELETE FROM rate_limits WHERE (address LIKE 'create:%' AND window_ms<=?) OR (address NOT LIKE 'create:%' AND window_ms<=?)", [$now - 600000, $now - 120000]);
            $this->bucket($address, $now, 60000, 240, 'Too many requests. Please wait a minute.');
            if ($creating) {
                $this->bucket('create:' . $address, $now, 300000, 20, 'Too many new runs. Please wait up to five minutes.');
            }
        });
    }

    private function bucket(string $key, int $now, int $duration, int $maximum, string $message): void
    {
        $row = $this->query('SELECT window_ms,count FROM rate_limits WHERE address=?', [$key])->fetch();
        $continuing = $row && $row['window_ms'] > $now - $duration;
        $window = $continuing ? (int) $row['window_ms'] : $now;
        $count = $continuing ? (int) $row['count'] + 1 : 1;
        if ($count > $maximum) {
            throw new ApiError(429, $message, intdiv($duration, 1000));
        }
        $this->query('INSERT INTO rate_limits(address,window_ms,count) VALUES(?,?,?) ON CONFLICT(address) DO UPDATE SET window_ms=excluded.window_ms,count=excluded.count', [$key, $window, $count]);
    }
}
