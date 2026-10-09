<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\InventoryMovement;
use App\Services\Core\Controller;
use App\Services\InventoryService;

final class InventoryController extends Controller
{
    public function index(): void
    {
        $movements = (new InventoryMovement())->recent(200);
        $summary = InventoryService::summary();
        $threshold = (int) settings()['low_stock_threshold'];

        $totalValue = 0.0;
        $lowStock = [];
        foreach ($summary as $row) {
            $totalValue += (float) $row['value'];
            if ((int) $row['current'] <= $threshold) {
                $lowStock[] = $row;
            }
        }

        $this->view('inventory/index', [
            'title' => 'إدارة المخزون',
            'summary' => $summary,
            'movements' => $movements,
            'totalValue' => round($totalValue, 2),
            'lowStock' => $lowStock,
            'threshold' => $threshold,
        ]);
    }

    public function add(): void
    {
        $this->verifyCsrf();

        $this->action(function (): void {
            $packageId = $this->int('package_id');
            $quantity = $this->input('quantity');
            $unitCost = $this->moneyInput('unit_price');

            if ($packageId <= 0) {
                throw new \RuntimeException('اختر الباقة.');
            }
            if ($quantity === null || !is_numeric($quantity) || (float) $quantity != (int) $quantity) {
                throw new \RuntimeException('كمية الشدات يجب أن تكون عددًا صحيحًا.');
            }
            if ((int) $quantity <= 0) {
                throw new \RuntimeException('كمية الشدات يجب أن تكون أكبر من صفر.');
            }
            if ($unitCost < 0) {
                throw new \RuntimeException('تكلفة الشدة غير صالحة.');
            }

            InventoryService::add($packageId, (int) $quantity, $unitCost, $this->str('notes'));
        }, '/inventory', 'تمت إضافة المخزون بنجاح.');
    }

    public function adjust(): void
    {
        $this->verifyCsrf();

        $this->action(function (): void {
            $packageId = $this->int('package_id');
            $target = $this->input('target_quantity');
            $reason = $this->str('reason');

            if ($packageId <= 0) {
                throw new \RuntimeException('اختر الباقة.');
            }
            if ($target === null || !is_numeric($target) || (float) $target != (int) $target || (int) $target < 0) {
                throw new \RuntimeException('الكمية المستهدفة يجب أن تكون عددًا صحيحًا غير سالب.');
            }
            if ($reason === '') {
                throw new \RuntimeException('سبب التسوية مطلوب.');
            }

            InventoryService::adjust($packageId, (int) $target, $reason);
        }, '/inventory', 'تم تسجيل تسوية المخزون.');
    }
}
