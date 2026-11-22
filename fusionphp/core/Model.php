<?php

namespace Fusion\Core;

abstract class Model
{
    // Override in each model
    protected static string $table = '';
    protected static string $primary = 'id';

    // Only these fields can be mass-assigned
    protected static array $fillable = [];

    // These fields are hidden from toArray() / toJson()
    protected static array $hidden = [];

    // Holds the row data
    private array $attributes = [];

    // Tracks changed fields for UPDATE
    private array $dirty = [];
    private int|string|null $persistedKey = null;

    // ── Magic access ──────────────────────────────────────────

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
        $this->dirty[$key] = $value;
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    // ── Hydration — fill from DB row ──────────────────────────

    private static function hydrate(array $row): static
    {
        $model = new static();
        $model->attributes = $row;
        $model->persistedKey = $row[static::$primary] ?? null;
        return $model;
    }

    // ── Finders ───────────────────────────────────────────────

    public static function find(int|string $id): ?static
    {
        $table = static::$table;
        $primary = static::$primary;

        $row = static::db()
            ->query("SELECT * FROM {$table} WHERE {$primary} = ? LIMIT 1", [$id])
            ->fetch();

        return $row ? static::hydrate($row) : null;
    }

    public static function findOrFail(int|string $id): static
    {
        $model = static::find($id);

        if (!$model) {
            throw new \RuntimeException(static::class . " with id {$id} not found");
        }

        return $model;
    }

    public static function all(): array
    {
        $table = static::$table;
        $rows = static::db()->query("SELECT * FROM {$table}")->fetchAll();
        return array_map(fn($row) => static::hydrate($row), $rows);
    }

    public static function where(string $column, mixed $value, string $operator = '='): array
    {
        $table = static::$table;
        $rows = static::db()
            ->query("SELECT * FROM {$table} WHERE {$column} {$operator} ?", [$value])
            ->fetchAll();

        return array_map(fn($row) => static::hydrate($row), $rows);
    }

    public static function first(string $column, mixed $value): ?static
    {
        $table = static::$table;
        $row = static::db()
            ->query("SELECT * FROM {$table} WHERE {$column} = ? LIMIT 1", [$value])
            ->fetch();

        return $row ? static::hydrate($row) : null;
    }

    public static function count(): int
    {
        $table = static::$table;
        return (int) static::db()
            ->query("SELECT COUNT(*) FROM {$table}")
            ->fetchColumn();
    }

    // ── Create ────────────────────────────────────────────────

    public static function create(array $data): static
    {
        $data = static::filterFillable($data);
        $table = static::$table;

        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        static::db()->query(
            "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})",
            array_values($data)
        );

        return static::findOrFail($data[static::$primary] ?? static::db()->lastInsertId());
    }

    // ── Save — INSERT or UPDATE ───────────────────────────────

    public function save(): bool
    {
        $table = static::$table;
        $primary = static::$primary;

        // UPDATE — only dirty fields
        if ($this->persistedKey !== null) {
            if (empty($this->dirty))
                return true;

            $set = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($this->dirty)));
            $values = array_values($this->dirty);
            $values[] = $this->persistedKey;

            static::db()->query(
                "UPDATE {$table} SET {$set} WHERE {$primary} = ?",
                $values
            );

            $this->persistedKey = $this->attributes[$primary];
            $this->dirty = [];
            return true;
        }

        // INSERT
        $data = static::filterFillable($this->attributes);
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        static::db()->query(
            "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})",
            array_values($data)
        );

        $this->persistedKey = $data[$primary] ?? static::db()->lastInsertId();
        $this->attributes = $data;
        $this->attributes[$primary] = $this->persistedKey;
        $this->dirty = [];

        return true;
    }

    // ── Delete ────────────────────────────────────────────────

    public function delete(): bool
    {
        $table = static::$table;
        $primary = static::$primary;

        if ($this->persistedKey === null) {
            return false;
        }

        static::db()->query(
            "DELETE FROM {$table} WHERE {$primary} = ?",
            [$this->persistedKey]
        );

        $this->persistedKey = null;
        return true;
    }

    public static function deleteWhere(string $column, mixed $value): int
    {
        $table = static::$table;
        $stmt = static::db()->query(
            "DELETE FROM {$table} WHERE {$column} = ?",
            [$value]
        );

        return $stmt->rowCount();
    }

    // ── Output ────────────────────────────────────────────────

    public function toArray(): array
    {
        return array_diff_key(
            $this->attributes,
            array_flip(static::$hidden)
        );
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    // ── Helpers ───────────────────────────────────────────────

    private static function db(): Database
    {
        return Database::getInstance();
    }

    private static function filterFillable(array $data): array
    {
        if (empty(static::$fillable))
            return $data;
        return array_intersect_key($data, array_flip(static::$fillable));
    }
}