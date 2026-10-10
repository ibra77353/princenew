<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InventoryMovement;
use App\Services\Core\Auth;
use App\Services\Core\Database;

/**
 * حركات المخزون بالشدات: إضافة، بيع، إرجاع، وتسوية بالزيادة أو النقصان.
 * تكلفة الشدة تُحفظ وقت الحركة فلا تتأثر بتغيّر الأسعار لاحقًا.
 */
final class InventoryService
{
    public const TYPES = [
        'add' => 'إضافة',
        'sell' => 'بيع',
        'return' => 'إرجاع',
        'adjust' => 'تسوية',
    ];

    /**
     * إضافة كمية إلى مخزون الباقة: تُجمع على الرصيد الحالي في صف واحد (لا سجل جديد لكل إضافة).
     * سعر الشدة يُعتمد من الباقة نفسها، وكل عملية تُنفَّذ داخل معاملة واحدة.
     */
    public static function add(int $packageId, int $quantity, string $notes = ''): int
    {
        if ($quantity <= 0) {
            throw new \RuntimeException('كمية الشدات يجب أن تكون عددًا صحيحًا أكبر من صفر.');
        }

        return Database::transaction(function () use ($packageId, $quantity, $notes): int {
            $package = Database::one('SELECT * FROM packages WHERE id = ? LIMIT 1', [$packageId]);
            if ($package === null) {
                throw new \RuntimeException('الباقة غير موجودة.');
            }

            $unitPrice = round((float) $package['price'], 2);

            $id = self::movement([
                'package_id' => $packageId,
                'type' => 'add',
                'direction' => null,
                'quantity' => $quantity,
                'unit_cost' => $unitPrice,
                'reason' => $notes !== '' ? $notes : 'إضافة مخزون',
                'related_operation_type' => 'add',
            ]);

            AuditService::log('add', 'inventory', $id, sprintf(
                'إضافة %d شدة إلى %s بسعر %s',
                $quantity,
                $package['name'],
                (string) $unitPrice
            ));

            return $id;
        });
    }

    /** تسوية المخزون إلى كمية مستهدفة مع تسجيل السبب. */
    public static function adjust(int $packageId, int $targetQuantity, string $reason): int
    {
        if ($targetQuantity < 0) {
            throw new \RuntimeException('الكمية المستهدفة غير صالحة.');
        }
        if (trim($reason) === '') {
            throw new \RuntimeException('سبب التسوية مطلوب.');
        }

        return Database::transaction(function () use ($packageId, $targetQuantity, $reason): int {
            $package = Database::one('SELECT * FROM packages WHERE id = ? LIMIT 1', [$packageId]);
            if ($package === null) {
                throw new \RuntimeException('الباقة غير موجودة.');
            }

            $current = (new InventoryMovement())->currentStock($packageId);
            $delta = $targetQuantity - $current;
            if ($delta === 0) {
                throw new \RuntimeException('الكمية المستهدفة مطابقة للمخزون الحالي.');
            }

            $id = self::movement([
                'package_id' => $packageId,
                'type' => 'adjust',
                'direction' => $delta > 0 ? 'up' : 'down',
                'quantity' => abs($delta),
                'unit_cost' => 0,
                'reason' => $reason,
                'related_operation_type' => $delta > 0 ? 'adjust_up' : 'adjust_down',
            ]);

            AuditService::log('adjust', 'inventory', $id, sprintf(
                'تسوية %s من %d إلى %d — %s',
                $package['name'],
                $current,
                $targetQuantity,
                $reason
            ));

            return $id;
        });
    }

    /** سعر الشدة الموحّد المعتمد من الباقة (المصدر الوحيد للسعر). */
    public static function packagePrice(int $packageId): float
    {
        $price = Database::scalar('SELECT price FROM packages WHERE id = ?', [$packageId]);

        return round((float) $price, 2);
    }

    public static function recordSale(int $packageId, int $quantity, float $unitCost, ?int $saleId, string $invoiceNumber): void
    {
        self::movement([
            'package_id' => $packageId,
            'type' => 'sell',
            'direction' => null,
            'quantity' => $quantity,
            'unit_cost' => round($unitCost, 2),
            'reason' => 'بيع — فاتورة ' . $invoiceNumber,
            'related_operation_type' => 'sale',
            'related_operation_id' => $saleId,
        ]);
    }

    /** إرجاع كميات فاتورة ملغاة إلى المخزون (دون حذف التاريخ). */
    public static function recordReturn(int $packageId, int $quantity, ?int $saleId, string $invoiceNumber): void
    {
        self::movement([
            'package_id' => $packageId,
            'type' => 'return',
            'direction' => null,
            'quantity' => $quantity,
            'unit_cost' => 0,
            'reason' => 'إلغاء فاتورة ' . $invoiceNumber,
            'related_operation_type' => 'sale_cancel',
            'related_operation_id' => $saleId,
        ]);
    }

    public static function currentStock(int $packageId): int
    {
        return (new InventoryMovement())->currentStock($packageId);
    }

    public static function summary(): array
    {
        return (new InventoryMovement())->stockSummary();
    }

    public static function averageCost(int $packageId): float
    {
        $row = Database::one(
            "SELECT COALESCE(SUM(quantity), 0) AS qty, COALESCE(SUM(quantity * unit_cost), 0) AS cost
             FROM inventory_movements WHERE package_id = ? AND type = 'add'",
            [$packageId]
        );

        $qty = (int) ($row['qty'] ?? 0);

        return $qty > 0 ? round((float) $row['cost'] / $qty, 2) : 0.0;
    }

    /** @param array<string,mixed> $data */
    private static function movement(array $data): int
    {
        $id = Database::insert(
            'INSERT INTO inventory_movements
                (package_id, type, direction, quantity, unit_cost, reason, related_operation_type, related_operation_id, created_at, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['package_id'],
                $data['type'],
                $data['direction'] ?? null,
                $data['quantity'],
                $data['unit_cost'] ?? 0,
                $data['reason'] ?? '',
                $data['related_operation_type'] ?? null,
                $data['related_operation_id'] ?? null,
                now(),
                Auth::id(),
            ]
        );

        // تحديث الرصيد النشط (صف واحد لكل باقة) بشكل ذرّي مع كل حركة مخزون.
        $delta = self::deltaFor($data);
        if ($delta !== 0) {
            (new InventoryMovement())->applyDelta((int) $data['package_id'], $delta);
        }

        return $id;
    }

    /** @param array<string,mixed> $data */
    private static function deltaFor(array $data): int
    {
        $quantity = (int) ($data['quantity'] ?? 0);

        return match ($data['type']) {
            'add', 'return' => $quantity,
            'sell' => -$quantity,
            'adjust' => ($data['direction'] ?? null) === 'down' ? -$quantity : $quantity,
            default => 0,
        };
    }
}
