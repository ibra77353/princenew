<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Sale;
use App\Services\Core\Auth;
use App\Services\Core\Database;

/**
 * عمليات البيع والإلغاء.
 * الأسعار تأتي من قاعدة البيانات، والتحقق من المخزون يحدث على الخادم،
 * وكل الحركات المترابطة تُنفَّذ داخل معاملة واحدة.
 */
final class SaleService
{
    /**
     * @param array{distributor_id:int, items:array<int,array{package_id:mixed,quantity:mixed}>, paid_amount:mixed, payment_type:string, sale_date:string, notes:string} $payload
     * @return array<string,mixed> الفاتورة المنشأة
     */
    public static function create(array $payload): array
    {
        $distributorId = (int) $payload['distributor_id'];
        $paymentType = $payload['payment_type'] === 'cash' ? 'cash' : 'credit';
        $saleDate = self::normalizeDate((string) $payload['sale_date']);

        $items = self::normalizeItems($payload['items']);
        if ($items === []) {
            throw new \RuntimeException('أضف باقة واحدة على الأقل إلى الفاتورة.');
        }

        return Database::transaction(function () use ($distributorId, $paymentType, $saleDate, $items, $payload) {
            $distributor = Database::one('SELECT * FROM distributors WHERE id = ? FOR UPDATE', [$distributorId]);
            if ($distributor === null) {
                throw new \RuntimeException('الموزع غير موجود.');
            }
            if ($distributor['status'] !== 'active') {
                throw new \RuntimeException('لا يمكن البيع لموزع غير نشط.');
            }

            $packageIds = array_keys($items);
            sort($packageIds);
            $placeholders = implode(',', array_fill(0, count($packageIds), '?'));
            $packages = Database::all(
                'SELECT * FROM packages WHERE id IN (' . $placeholders . ') ORDER BY id ASC FOR UPDATE',
                $packageIds
            );

            $byId = [];
            foreach ($packages as $package) {
                $byId[(int) $package['id']] = $package;
            }

            $total = 0.0;
            $lines = [];
            foreach ($items as $packageId => $quantity) {
                if (!isset($byId[$packageId])) {
                    throw new \RuntimeException('إحدى الباقات المختارة غير موجودة.');
                }
                $package = $byId[$packageId];
                if ($package['status'] !== 'active') {
                    throw new \RuntimeException('الباقة «' . $package['name'] . '» غير مفعّلة.');
                }

                $stock = InventoryService::currentStock($packageId);
                if ($quantity > $stock) {
                    throw new \RuntimeException(sprintf(
                        'الكمية المطلوبة من «%s» (%d) تتجاوز المخزون المتاح (%d).',
                        $package['name'],
                        $quantity,
                        $stock
                    ));
                }

                $unitPrice = round((float) $package['price'], 2);
                $lineTotal = round($unitPrice * $quantity, 2);
                $total += $lineTotal;

                $lines[] = [
                    'package_id' => $packageId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total' => $lineTotal,
                ];
            }

            $total = round($total, 2);

            $paid = $paymentType === 'cash' ? $total : round((float) $payload['paid_amount'], 2);
            if ($paid < 0) {
                throw new \RuntimeException('المبلغ المدفوع غير صالح.');
            }
            if ($paid > $total + 0.001) {
                throw new \RuntimeException('المبلغ المدفوع أكبر من إجمالي الفاتورة.');
            }

            $invoiceNumber = (new Sale())->nextInvoiceNumber();
            $saleId = Database::insert(
                'INSERT INTO sales
                    (invoice_number, distributor_id, distributor_name, sale_date, subtotal, total_amount, paid_amount,
                     payment_type, status, notes, created_at, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $invoiceNumber,
                    $distributorId,
                    $distributor['name'],
                    $saleDate,
                    $total,
                    $total,
                    $paid,
                    $paymentType,
                    'active',
                    $payload['notes'] !== '' ? $payload['notes'] : null,
                    now(),
                    Auth::id(),
                ]
            );

            foreach ($lines as $line) {
                Database::insert(
                    'INSERT INTO sale_items (sale_id, package_id, quantity, unit_price, total) VALUES (?, ?, ?, ?, ?)',
                    [$saleId, $line['package_id'], $line['quantity'], $line['unit_price'], $line['total']]
                );

                InventoryService::recordSale(
                    $line['package_id'],
                    $line['quantity'],
                    InventoryService::averageCost($line['package_id']),
                    $saleId,
                    $invoiceNumber
                );
            }

            if ($paid > 0) {
                CashService::record(
                    'in',
                    'sale',
                    $paid,
                    'فاتورة ' . $invoiceNumber . ' — ' . $distributor['name'],
                    $saleDate,
                    'sale',
                    $saleId
                );
            }

            AuditService::log('create', 'sale', $saleId, sprintf(
                'فاتورة %s للموزع %s بمبلغ %s (%s)',
                $invoiceNumber,
                $distributor['name'],
                (string) $total,
                $paymentType === 'cash' ? 'نقدي' : 'آجل'
            ));

            return [
                'id' => $saleId,
                'invoice_number' => $invoiceNumber,
                'total_amount' => $total,
                'paid_amount' => $paid,
                'remaining_amount' => round($total - $paid, 2),
                'distributor_name' => $distributor['name'],
                'sale_date' => $saleDate,
                'payment_type' => $paymentType,
                'items' => $lines,
            ];
        });
    }

    /** إلغاء فاتورة: إرجاع المخزون، عكس المدفوع، وعكس التحصيلات المرتبطة. */
    public static function cancel(int $saleId, string $reason = ''): void
    {
        Database::transaction(function () use ($saleId, $reason) {
            $sale = Database::one('SELECT * FROM sales WHERE id = ? FOR UPDATE', [$saleId]);
            if ($sale === null) {
                throw new \RuntimeException('الفاتورة غير موجودة.');
            }
            if ($sale['status'] !== 'active') {
                throw new \RuntimeException('الفاتورة ملغاة مسبقًا.');
            }

            $items = Database::all('SELECT * FROM sale_items WHERE sale_id = ?', [$saleId]);
            foreach ($items as $item) {
                InventoryService::recordReturn(
                    (int) $item['package_id'],
                    (int) $item['quantity'],
                    $saleId,
                    (string) $sale['invoice_number']
                );
            }

            $paid = round((float) $sale['paid_amount'], 2);
            if ($paid > 0) {
                CashService::record(
                    'out',
                    'reversal',
                    $paid,
                    'عكس مدفوع فاتورة ' . $sale['invoice_number'],
                    now(),
                    'sale_cancel',
                    $saleId
                );
            }

            $collections = Database::all(
                "SELECT * FROM collections WHERE sale_id = ? AND status = 'active' FOR UPDATE",
                [$saleId]
            );
            foreach ($collections as $collection) {
                Database::affect("UPDATE collections SET status = 'reversed' WHERE id = ?", [$collection['id']]);
                CashService::record(
                    'out',
                    'reversal',
                    (float) $collection['amount'],
                    'عكس تحصيل رقم ' . $collection['id'] . ' — إلغاء فاتورة ' . $sale['invoice_number'],
                    now(),
                    'collection_reversal',
                    (int) $collection['id']
                );
            }

            Database::affect(
                "UPDATE sales SET status = 'cancelled', cancelled_at = ?, cancel_reason = ? WHERE id = ?",
                [now(), $reason !== '' ? $reason : null, $saleId]
            );

            AuditService::log('cancel', 'sale', $saleId, sprintf(
                'إلغاء فاتورة %s%s',
                $sale['invoice_number'],
                $reason !== '' ? ' — ' . $reason : ''
            ));
        });
    }

    /**
     * تجميع كميات الباقة نفسها قبل التحقق من المخزون.
     * @param array<int,array{package_id:mixed,quantity:mixed}> $rawItems
     * @return array<int,int> package_id => quantity
     */
    private static function normalizeItems(array $rawItems): array
    {
        $items = [];
        foreach ($rawItems as $row) {
            if (!is_array($row)) {
                continue;
            }
            $packageId = (int) ($row['package_id'] ?? 0);
            $quantity = $row['quantity'] ?? null;

            if ($packageId <= 0 || $quantity === null || $quantity === '') {
                continue;
            }
            if (!is_numeric($quantity) || (float) $quantity != (int) $quantity) {
                throw new \RuntimeException('كمية الشدات يجب أن تكون عددًا صحيحًا.');
            }
            $quantity = (int) $quantity;
            if ($quantity <= 0) {
                throw new \RuntimeException('كمية الشدات يجب أن تكون أكبر من صفر.');
            }

            $items[$packageId] = ($items[$packageId] ?? 0) + $quantity;
        }

        return $items;
    }

    private static function normalizeDate(string $date): string
    {
        if ($date === '') {
            return now();
        }
        $parts = explode('-', $date);
        if (count($parts) !== 3 || !checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
            throw new \RuntimeException('تاريخ البيع غير صالح.');
        }

        return $date . ' ' . date('H:i:s');
    }
}
