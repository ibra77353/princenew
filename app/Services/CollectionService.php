<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Distributor;
use App\Models\Sale;
use App\Services\Core\Auth;
use App\Services\Core\Database;
use App\Services\Core\Idempotency;

/**
 * تحصيل الأموال من الموزعين مع التحقق من الدين المستحق على الخادم.
 */
final class CollectionService
{
    public static function create(array $payload): array
    {
        $distributorId = (int) $payload['distributor_id'];
        $saleId = (int) ($payload['sale_id'] ?? 0);
        $amount = round((float) $payload['amount'], 2);
        $date = self::normalizeDate((string) ($payload['collection_date'] ?? ''));

        if ($amount <= 0) {
            throw new \RuntimeException('مبلغ التحصيل يجب أن يكون أكبر من صفر.');
        }

        return Database::transaction(function () use ($distributorId, $saleId, $amount, $date, $payload) {
            // حجز رمز الطلب داخل المعاملة: إعادة إرسال التحصيل لا تُنشئ حركة صندوق مكررة (M13).
            Idempotency::claim((string) ($payload['request_id'] ?? ''), 'collection');

            $distributor = Database::one('SELECT * FROM distributors WHERE id = ? FOR UPDATE', [$distributorId]);
            if ($distributor === null) {
                throw new \RuntimeException('الموزع غير موجود.');
            }

            $sale = null;
            if ($saleId > 0) {
                $sale = Database::one('SELECT * FROM sales WHERE id = ? FOR UPDATE', [$saleId]);
                if ($sale === null) {
                    throw new \RuntimeException('الفاتورة غير موجودة.');
                }
                if ($sale['status'] !== 'active') {
                    throw new \RuntimeException('لا يمكن التحصيل على فاتورة ملغاة.');
                }
                if ((int) $sale['distributor_id'] !== $distributorId) {
                    throw new \RuntimeException('الفاتورة لا تتبع الموزع المحدد.');
                }

                $remaining = (new Sale())->remaining($saleId);
                if ($amount > $remaining + 0.001) {
                    throw new \RuntimeException(sprintf(
                        'المبلغ يتجاوز المتبقي على الفاتورة (%s).',
                        number_format($remaining, 2)
                    ));
                }
            } else {
                $balance = (new Distributor())->balance($distributorId);
                if ($amount > $balance + 0.001) {
                    throw new \RuntimeException(sprintf(
                        'المبلغ يتجاوز الرصيد المستحق على الموزع (%s).',
                        number_format($balance, 2)
                    ));
                }
            }

            $collectionId = Database::insert(
                'INSERT INTO collections
                    (distributor_id, sale_id, amount, collection_date, notes, status, created_at, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $distributorId,
                    $saleId > 0 ? $saleId : null,
                    $amount,
                    $date,
                    ($payload['notes'] ?? '') !== '' ? $payload['notes'] : null,
                    'active',
                    now(),
                    Auth::id(),
                ]
            );

            CashService::record(
                'in',
                'collection',
                $amount,
                'تحصيل من ' . $distributor['name'] . ($sale !== null ? ' — فاتورة ' . $sale['invoice_number'] : ''),
                $date,
                'collection',
                $collectionId
            );

            AuditService::log('create', 'collection', $collectionId, sprintf(
                'تحصيل %s من %s%s',
                (string) $amount,
                $distributor['name'],
                $sale !== null ? ' على الفاتورة ' . $sale['invoice_number'] : ''
            ));

            return [
                'id' => $collectionId,
                'amount' => $amount,
                'distributor_name' => $distributor['name'],
            ];
        });
    }

    private static function normalizeDate(string $date): string
    {
        if ($date === '') {
            return now();
        }
        $parts = explode('-', $date);
        if (count($parts) !== 3 || !checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
            throw new \RuntimeException('تاريخ التحصيل غير صالح.');
        }

        return $date . ' ' . date('H:i:s');
    }
}
