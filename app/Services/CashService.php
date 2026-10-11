<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Core\Auth;
use App\Services\Core\Database;
use App\Services\Core\DateRange;
use App\Services\Core\Setting;

/**
 * سجل الصندوق الموحّد: كل حركة نقدية مرتبطة بالعملية التي أنشأتها.
 * الرصيد = الرصيد الافتتاحي + إجمالي المقبوضات − إجمالي المدفوعات.
 *
 * سياسة كفاية الصندوق الموحّدة: كل حركة خارجة **تشغيلية** (مصروف، دفعة خط، سحب مالك)
 * تُرفض إذا تجاوزت الرصيد المتاح، والفحص يُنفَّذ هنا في نقطة واحدة عند تسجيل الحركة،
 * داخل معاملة العملية نفسها، فلا يمكن لأي مسار خروج أن يتجاوز الرصيد.
 * الحركات العكسية (`reversal`) مستثناة لأنها تُلغي أثر حركة سابقة ولا يجوز رفضها.
 */
final class CashService
{
    /** المصادر الخارجة الخاضعة لكفاية الصندوق. */
    private const GUARDED_OUT_SOURCES = ['expense', 'line_payment', 'owner_withdrawal'];

    /** هامش تسامح للفروق العشرية. */
    private const SUFFICIENCY_TOLERANCE = 0.001;

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

        if ($type === 'out') {
            self::assertSufficient($amount, $source);
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

    /** إجماليات فترة محددة (حدود شاملة بلا فقد أو تكرار عند حدّ اليوم). */
    public static function totals(?string $from = null, ?string $to = null): array
    {
        $where = ['1 = 1'];
        $params = [];
        DateRange::apply($where, $params, 'transaction_date', $from !== '' ? $from : null, $to !== '' ? $to : null);

        $row = Database::one(
            "SELECT
                COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END), 0) AS cash_in,
                COALESCE(SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END), 0) AS cash_out
             FROM cash_transactions WHERE " . implode(' AND ', $where),
            $params
        ) ?? [];

        return [
            'in' => round((float) ($row['cash_in'] ?? 0), 2),
            'out' => round((float) ($row['cash_out'] ?? 0), 2),
        ];
    }

    /**
     * التحقق الموحّد من كفاية الصندوق لمبلغ خارج.
     * المصادر غير الخاضعة للفحص (مثل الحركات العكسية) تُتجاوز بلا رفض.
     */
    public static function assertSufficient(float $amount, string $source): void
    {
        if (!in_array($source, self::GUARDED_OUT_SOURCES, true)) {
            return;
        }

        $available = self::lockedBalance();
        if ($amount > $available + self::SUFFICIENCY_TOLERANCE) {
            throw new \RuntimeException(sprintf(
                'رصيد الصندوق غير كافٍ: المتاح %s والمطلوب %s.',
                number_format($available, 2),
                number_format($amount, 2)
            ));
        }
    }

    /**
     * رصيد الصندوق مقروءًا تحت قفل تسلسلي على صف الإعدادات، فيتسلسل الفحص مع أي حركة
     * خارجة متزامنة ولا يمكن لعمليتين معًا تجاوز الرصيد (منع الرصيد السالب).
     */
    private static function lockedBalance(): float
    {
        Database::one('SELECT id FROM settings WHERE id = 1 FOR UPDATE');

        return self::balance();
    }
}
