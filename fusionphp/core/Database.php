<?php

namespace Fusion\Core;

class Database
{
    private static ?Database $instance = null;
    private \PDO $pdo;

    private function __construct()
    {
        $driver = config('database.driver','mysql');

        $this->pdo = match ($driver) {
            'mysql' => $this->connectMysql(),
            'pgsql' => $this->connectPgsql(),
            'sqlite' => $this->connectSqlite(),
            default => throw new \RuntimeException("Unsupported driver: {$driver}")
        };

        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
    }

    private function connectMysql(): \PDO
    {
        $host = config('database.host','127.0.0.1');
        $port = config('database.port','3306');
        $dbname = config('database.name','');
        $charset = config('database.charset','utf8mb4');

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        return new \PDO($dsn, config('database.user',''), config('database.password',''));
    }

    private function connectPgsql(): \PDO
    {
        $host = config('database.host','127.0.0.1');
        $port = config('database.port', 5432);
        $dbname = config('database.name','');

        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";

        return new \PDO($dsn, config('database.user',''), config('database.password',''));
    }

    private function connectSqlite(): \PDO
    {
        $path = config('database.path',BASE_PATH . '/storage/database.sqlite');
        return new \PDO("sqlite:{$path}");
    }

    // ── Singleton ─────────────────────────────────────────────

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ── Query Execution ───────────────────────────────────────

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $start = microtime(true);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $time = microtime(true) - $start;

        Debugger::logQuery($sql, $params, $time);

        return $stmt;
    }

    public function lastInsertId(): string|false
    {
        return $this->pdo->lastInsertId();
    }

    // ── Transactions ──────────────────────────────────────────

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        $this->pdo->rollBack();
    }

    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }
}