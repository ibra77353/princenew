<?php

declare(strict_types=1);

namespace App\Services\Core;

/**
 * أبسط نموذج: تعامل مباشر مع جدول واحد عبر PDO مع استعلامات محضّرة.
 */
abstract class Model
{
    protected string $table = '';

    protected string $primaryKey = 'id';

    /** @return array<int,array<string,mixed>> */
    public function all(string $orderBy = 'id DESC', ?int $limit = null): array
    {
        $sql = 'SELECT * FROM ' . $this->table . ' ORDER BY ' . $orderBy;
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        return Database::all($sql);
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return Database::one(
            'SELECT * FROM ' . $this->table . ' WHERE ' . $this->primaryKey . ' = ? LIMIT 1',
            [$id]
        );
    }

    /** @param array<string,mixed> $conditions @return array<string,mixed>|null */
    public function findBy(array $conditions): ?array
    {
        $where = [];
        $params = [];
        foreach ($conditions as $column => $value) {
            $where[] = $column . ' = ?';
            $params[] = $value;
        }

        return Database::one(
            'SELECT * FROM ' . $this->table . ' WHERE ' . implode(' AND ', $where) . ' LIMIT 1',
            $params
        );
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        return Database::insert(
            'INSERT INTO ' . $this->table . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')',
            array_values($data)
        );
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): int
    {
        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = $column . ' = ?';
        }
        $params = array_values($data);
        $params[] = $id;

        return Database::affect(
            'UPDATE ' . $this->table . ' SET ' . implode(', ', $sets) . ' WHERE ' . $this->primaryKey . ' = ?',
            $params
        );
    }

    public function delete(int $id): int
    {
        return Database::affect('DELETE FROM ' . $this->table . ' WHERE ' . $this->primaryKey . ' = ?', [$id]);
    }

    public function count(string $where = '1', array $params = []): int
    {
        return (int) Database::scalar('SELECT COUNT(*) FROM ' . $this->table . ' WHERE ' . $where, $params);
    }

    public function table(): string
    {
        return $this->table;
    }
}
