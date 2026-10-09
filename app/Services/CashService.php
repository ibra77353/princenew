<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Core\Auth;
use App\Services\Core\Database;
use App\Services\Core\Setting;

/**
 * سجل الصندوق الموحّد: كل حركة نقدية مرتبطة بالعملية التي أنشأتها.
 * الرصيد = الرصيد الافتتاحي + إجمالي المقبوضات − إجمالي المدفوعات.
 */
final class CashService
{
    public static function record(
        string $type,
        string $source,
        float $amount,
        string $description,
        ?string $date = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): int {
        if (!in_array($type, ['in', 'out'], true)) {
            throw new \RuntimeException('نوع الحركة النقدية غير صالح.');
        }
        if ($amount <= 0) {
            throw new \RuntimeException('مبلغ الحركة النقدية يجب أن يكون أكبر من صفر.');
        }

        return Database::insert(
            'INSERT INTO cash_transactions
                (type, source, amount, reference_type, reference_id, description, transaction_date, created_at, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $type,
                $source,
                round($amount, 2),
                $referenceType,
                $referenceId,
                $description,
                $date !== null && $date !== '' ? $date : now(),
                now(),
                Auth::id(),
            ]
        );
    }

    /** تسجيل حركة مرة واحدة فقط لكل مصدر وعملية، لمنع التكرار عند إعادة الإرسال. */
    public static function recordOnce(
        string $type,
        string $source,
        float $amount,
        string $description,
        ?string $date,
        string $referenceType,
        int $referenceId
    ): ?int {
        $existing = Database::scalar(
            'SELECT id FROM cash_transactions WHERE reference_type = ? AND reference_id = ? AND source = ? LIMIT 1',
            [$referenceType, $referenceId, $source]
        );

        if ($existing !== null) {
            return (int) $existing;
        }

        return self::record($type, $source, $amount, $description, $date, $referenceType, $referenceId);
    }

    public static function openingBalance(): float
    {
        return round((float) Setting::get('opening_balance', 0), 2);
    }

    public static function balance(): float
    {
        $row = Database::one(
            "SELECT
                COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END), 0) AS cash_in,
                COALESCE(SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END), 0) AS cash_out
             FROM cash_transactions"
        ) ?? [];

        return round(
            self::openingBalance() + (float) ($row['cash_in'] ?? 0) - (float) ($row['cash_out'] ?? 0),
            2
        );
    }

    /** إجماليات فترة محددة. */
    public static function totals(?string $from = null, ?string $to = null): array
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

    public static function ensureSufficient(float $amount): void
    {
        if ($amount > self::balance() + 0.001) {
            throw new \RuntimeException('رصيد الصندوق غير كافٍ لهذه العملية.');
        }
    }
}
