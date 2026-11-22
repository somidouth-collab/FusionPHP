<?php

namespace Fusion\Core;

final class Migrations
{
    public function __construct(private Database $database, private string $root)
    {
        if (!in_array(config('database.driver'), ['sqlite', 'pgsql'], true)) {
            throw new \InvalidArgumentException('The migration runner supports SQLite and PostgreSQL.');
        }
    }

    public function status(): array
    {
        $files = $this->files();
        $applied = $this->database->transaction(function (): array {
            $this->prepareLedger();
            return $this->applied();
        });
        $states = [];
        foreach ($files as $id => $file) {
            $states[$id] = !isset($applied[$id]) ? 'pending'
                : ($applied[$id] === $this->checksum($file) ? 'applied' : 'changed');
        }
        foreach (array_keys(array_diff_key($applied, $files)) as $id) {
            $states[$id] = 'missing';
        }
        return $states;
    }

    public function apply(): array
    {
        $files = $this->files();
        return $this->database->transaction(function () use ($files): array {
            $this->prepareLedger();
            $applied = $this->applied();
            $this->verifyAppliedFiles($files, $applied);
            $position = (int) $this->database->query(
                'SELECT COALESCE(MAX(sequence_number), 0) FROM fusion_migrations'
            )->fetchColumn();
            $completed = [];
            foreach (array_diff_key($files, $applied) as $id => $file) {
                $hash = $this->checksum($file);
                $migration = $this->load($file);
                $migration['up']($this->database);
                $this->database->query(
                    'INSERT INTO fusion_migrations (migration_id, checksum, sequence_number) VALUES (?, ?, ?)',
                    [$id, $hash, ++$position]
                );
                $completed[] = $id;
            }
            return $completed;
        });
    }

    public function rollback(): ?string
    {
        $files = $this->files();
        return $this->database->transaction(function () use ($files): ?string {
            $this->prepareLedger();
            $applied = $this->applied();
            $this->verifyAppliedFiles($files, $applied);
            $id = array_key_last($applied);
            if ($id === null) {
                return null;
            }

            $migration = $this->load($files[$id]);
            $migration['down']($this->database);
            $this->database->query('DELETE FROM fusion_migrations WHERE migration_id = ?', [$id]);
            return $id;
        });
    }

    private function prepareLedger(): void
    {
        $postgres = config('database.driver') === 'pgsql';
        if ($postgres) {
            // Serialize migration operations, including creation of the ledger.
            $this->database->query('SELECT pg_advisory_xact_lock(1179997005, 1)');
        }
        $this->database->query(
            'CREATE TABLE IF NOT EXISTS fusion_migrations (
                migration_id VARCHAR(255) PRIMARY KEY,
                checksum CHAR(64) NOT NULL,
                sequence_number BIGINT NOT NULL UNIQUE
            )'
        );
        if (!$postgres) {
            // Acquire SQLite’s write lock before reading ledger state.
            $this->database->query('UPDATE fusion_migrations SET sequence_number = sequence_number WHERE 1 = 0');
        }
    }

    private function applied(): array
    {
        return $this->database->query(
            'SELECT migration_id, checksum FROM fusion_migrations ORDER BY sequence_number'
        )->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    private function files(): array
    {
        $sources = ['app' => $this->root . '/migrations'];
        foreach (glob($this->root . '/app/*/.fusionphp-plugin') ?: [] as $marker) {
            $directory = dirname($marker);
            $module = basename($directory);
            $installed = file_get_contents($marker);
            if (!is_string($installed) || !str_starts_with($installed, $module . ':')) {
                continue;
            }
            $name = trim(substr($installed, strlen($module) + 1));
            if (!preg_match('/\A[a-z][a-z0-9-]*\z/', $name) || isset($sources['plugin:' . $name])) {
                throw new \RuntimeException("Invalid or duplicate plugin migration source for {$module}.");
            }
            $sources['plugin:' . $name] = $directory . '/migrations';
        }

        $files = [];
        foreach ($sources as $source => $directory) {
            foreach (glob($directory . '/*.php') ?: [] as $file) {
                $name = basename($file, '.php');
                if (!preg_match('/\A[0-9]{14}_[a-z][a-z0-9_]{0,79}\z/', $name)) {
                    throw new \RuntimeException("Invalid migration filename: {$name}.php");
                }
                $files[$source . '/' . $name] = $file;
            }
        }
        uksort($files, static fn (string $a, string $b): int =>
            strcmp(basename($a), basename($b)) ?: strcmp($a, $b)
        );
        return $files;
    }

    private function verifyAppliedFiles(array $files, array $applied): void
    {
        foreach ($applied as $id => $hash) {
            if (!isset($files[$id]) || $hash !== $this->checksum($files[$id])) {
                throw new \RuntimeException("Applied migration is missing or changed: {$id}. Restore its original file.");
            }
        }
    }

    private function checksum(string $file): string
    {
        $hash = hash_file('sha256', $file);
        if ($hash === false) {
            throw new \RuntimeException("Could not read migration: {$file}");
        }
        return $hash;
    }

    private function load(string $file): array
    {
        $migration = require $file;
        if (!is_array($migration) || !($migration['up'] ?? null) instanceof \Closure
            || !($migration['down'] ?? null) instanceof \Closure) {
            throw new \RuntimeException('Migration must return up and down closures: ' . basename($file));
        }
        return $migration;
    }
}
