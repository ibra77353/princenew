<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Core\Database;
use App\Services\Core\Model;

final class CashTransaction extends Model
{
    protected string $table = 'cash_transactions';

    public const SOURCES = [
        'sale' => 'مبيعات',
        'collection' => 'تحصيلات',
        'expense' => 'مصروفات',
        'line_payment' => 'دفعات خطوط',
        'owner_withdrawal' => 'سحب مالك',
        'opening' => 'رصيد افتتاحي',
        'reversal' => 'حركة عكسية',
    ];

    public function listing(int $limit = 200, int $offset = 0, ?string $source = null, ?string $from = null, ?string $to = null): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($source !== null && $source !== '') {
            $where[] = 'source = ?';
            $params[] = $source;
        }
        if ($from !== null && $from !== '') {
            $where[] = 'transaction_date >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== null && $to !== '') {
            $where[] = 'transaction_date <= ?';
            $params[] = $to . ' 23:59:59';
        }

        return Database::all(
            'SELECT * FROM cash_transactions WHERE ' . implode(' AND ', $where) . '
             ORDER BY transaction_date DESC, id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }

    public function totals(?string $from = null, ?string $to = null): array
    {
        $sql = "SELECT
                COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END), 0) AS cash_in,
                COALESCE(SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END), 0) AS cash_out
             FROM cash_transactions WHERE 1 = 1";
        $params = [];
        if ($from !== null && $from !== '') {
            $sql .= ' AND transaction_date >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== null && $to !== '') {
            $sql .= ' AND transaction_date <= ?';
            $params[] = $to . ' 23:59:59';
        }

        $row = Database::one($sql, $params) ?? [];

        return [
            'in' => round((float) ($row['cash_in'] ?? 0), 2),
            'out' => round((float) ($row['cash_out'] ?? 0), 2),
        ];
    }

    public function recent(int $limit = 10): array
    {
        return Database::all('SELECT * FROM cash_transactions ORDER BY id DESC LIMIT ' . (int) $limit);
    }

    /** البحث عن حركة مرتبطة بعملية معيّنة (لمنع تكرار الحركات). */
    public function forReference(string $referenceType, int $referenceId): array
    {
        return Database::all(
            'SELECT * FROM cash_transactions WHERE reference_type = ? AND reference_id = ? ORDER BY id ASC',
            [$referenceType, $referenceId]
        );
    }
}
