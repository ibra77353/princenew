<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Core\Database;
use App\Services\Core\Model;

final class OwnerWithdrawal extends Model
{
    protected string $table = 'owner_withdrawals';

    public function listing(int $limit = 200, int $offset = 0, ?string $from = null, ?string $to = null): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($from !== null && $from !== '') {
            $where[] = 'withdrawal_date >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== null && $to !== '') {
            $where[] = 'withdrawal_date <= ?';
            $params[] = $to . ' 23:59:59';
        }

        return Database::all(
            'SELECT * FROM owner_withdrawals WHERE ' . implode(' AND ', $where) . '
             ORDER BY withdrawal_date DESC, id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }

    public function total(?string $from = null, ?string $to = null): float
    {
        $sql = 'SELECT COALESCE(SUM(amount), 0) FROM owner_withdrawals WHERE 1 = 1';
        $params = [];
        if ($from !== null && $from !== '') {
            $sql .= ' AND withdrawal_date >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== null && $to !== '') {
            $sql .= ' AND withdrawal_date <= ?';
            $params[] = $to . ' 23:59:59';
        }

        return round((float) Database::scalar($sql, $params), 2);
    }
}
