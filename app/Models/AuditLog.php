<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Core\Database;
use App\Services\Core\Model;

final class AuditLog extends Model
{
    protected string $table = 'audit_logs';

    public function listing(int $limit = 200, int $offset = 0, ?string $entityType = null, ?string $term = null): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($entityType !== null && $entityType !== '') {
            $where[] = 'entity_type = ?';
            $params[] = $entityType;
        }
        if ($term !== null && $term !== '') {
            $where[] = '(action LIKE ? OR details LIKE ? OR entity_type LIKE ?)';
            $like = '%' . $term . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        return Database::all(
            'SELECT * FROM audit_logs WHERE ' . implode(' AND ', $where) . '
             ORDER BY id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }

    public function countFiltered(?string $entityType = null, ?string $term = null): int
    {
        $where = ['1 = 1'];
        $params = [];

        if ($entityType !== null && $entityType !== '') {
            $where[] = 'entity_type = ?';
            $params[] = $entityType;
        }
        if ($term !== null && $term !== '') {
            $where[] = '(action LIKE ? OR details LIKE ? OR entity_type LIKE ?)';
            $like = '%' . $term . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        return (int) Database::scalar('SELECT COUNT(*) FROM audit_logs WHERE ' . implode(' AND ', $where), $params);
    }
}
