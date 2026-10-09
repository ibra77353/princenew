<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Core\Database;
use App\Services\Core\Model;

final class LinePayment extends Model
{
    protected string $table = 'line_payments';

    public function listing(int $limit = 200, int $offset = 0, ?int $lineId = null, ?string $from = null, ?string $to = null): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($lineId !== null) {
            $where[] = 'lp.line_id = ?';
            $params[] = $lineId;
        }
        if ($from !== null && $from !== '') {
            $where[] = 'lp.payment_date >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== null && $to !== '') {
            $where[] = 'lp.payment_date <= ?';
            $params[] = $to . ' 23:59:59';
        }

        return Database::all(
            'SELECT lp.*, l.name AS line_name, l.provider AS line_provider
             FROM line_payments lp LEFT JOIN `lines` l ON l.id = lp.line_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY lp.payment_date DESC, lp.id DESC
             LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }
}
