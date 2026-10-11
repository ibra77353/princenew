<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Core\Auth;
use App\Services\Core\Database;
use App\Services\Core\Idempotency;
use App\Services\Core\Setting;

/**
 * العمليات المالية الخارجة: المصروفات، دفعات الخطوط، وسحوبات المالك.
 * كل عملية تُسجّل حركة نقدية خارجة مرتبطة بها، مع منع تكرار الحركة.
 * كفاية الصندوق تُفرض مركزيًا في `CashService::record()` لكل الأنواع الثلاثة بشكل موحّد،
 * فلا تحتاج أي عملية من هنا لفحص منفصل.
 */
final class OutflowService
{
    public static function recordExpense(string $category, float $amount, string $description, string $date, string $notes = '', string $requestToken = ''): int
    {
        if (!array_key_exists($category, \App\Models\Expense::CATEGORIES)) {
            throw new \RuntimeException('تصنيف المصروف غير صالح.');
        }
        if ($amount <= 0) {
            throw new \RuntimeException('مبلغ المصروف يجب أن يكون أكبر من صفر.');
        }
        if (trim($description) === '') {
            throw new \RuntimeException('وصف المصروف مطلوب.');
        }

        $date = self::normalizeDate($date);

        return Database::transaction(function () use ($category, $amount, $description, $date, $notes, $requestToken) {
            Idempotency::claim($requestToken, 'expense');

            $id = Database::insert(
                'INSERT INTO expenses (category, amount, description, notes, expense_date, created_at, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$category, round($amount, 2), $description, $notes !== '' ? $notes : null, $date, now(), Auth::id()]
            );

            CashService::record('out', 'expense', $amount, 'مصروف — ' . $description, $date, 'expense', $id);
            AuditService::log('create', 'expense', $id, sprintf('%s بمبلغ %s', $description, (string) $amount));

            return $id;
        });
    }

    public static function recordLinePayment(int $lineId, float $amount, string $date, string $notes = '', string $requestToken = ''): int
    {
        if ($amount <= 0) {
            throw new \RuntimeException('مبلغ الدفعة يجب أن يكون أكبر من صفر.');
        }

        $line = Database::one('SELECT * FROM `lines` WHERE id = ? LIMIT 1', [$lineId]);
        if ($line === null) {
            throw new \RuntimeException('الخط غير موجود.');
        }

        $date = self::normalizeDate($date);

        return Database::transaction(function () use ($line, $lineId, $amount, $date, $notes, $requestToken) {
            Idempotency::claim($requestToken, 'line_payment');

            $id = Database::insert(
                'INSERT INTO line_payments (line_id, amount, payment_date, notes, created_at, created_by)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$lineId, round($amount, 2), $date, $notes !== '' ? $notes : null, now(), Auth::id()]
            );

            CashService::record('out', 'line_payment', $amount, 'دفعة خط — ' . $line['name'], $date, 'line_payment', $id);
            AuditService::log('create', 'line_payment', $id, sprintf('دفعة %s للخط %s', (string) $amount, $line['name']));

            return $id;
        });
    }

    public static function recordWithdrawal(float $amount, string $reason, string $date, string $notes = '', string $requestToken = ''): int
    {
        if ($amount <= 0) {
            throw new \RuntimeException('مبلغ السحب يجب أن يكون أكبر من صفر.');
        }
        if (trim($reason) === '') {
            throw new \RuntimeException('سبب السحب مطلوب.');
        }

        $date = self::normalizeDate($date);

        return Database::transaction(function () use ($amount, $reason, $date, $notes, $requestToken) {
            Idempotency::claim($requestToken, 'owner_withdrawal');

            $id = Database::insert(
                'INSERT INTO owner_withdrawals (amount, reason, notes, withdrawal_date, created_at, created_by)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [round($amount, 2), $reason, $notes !== '' ? $notes : null, $date, now(), Auth::id()]
            );

            CashService::record('out', 'owner_withdrawal', $amount, 'سحب مالك — ' . $reason, $date, 'owner_withdrawal', $id);
            AuditService::log('create', 'withdrawal', $id, sprintf('%s — %s', $reason, (string) $amount));

            return $id;
        });
    }

    private static function normalizeDate(string $date): string
    {
        if ($date === '') {
            return now();
        }
        $parts = explode('-', $date);
        if (count($parts) !== 3 || !checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
            throw new \RuntimeException('التاريخ غير صالح.');
        }

        return $date . ' ' . (strlen($date) > 10 ? date('H:i:s') : '00:00:00');
    }
}
