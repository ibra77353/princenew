<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Distributor;
use App\Models\InventoryMovement;
use App\Services\Core\Database;
use App\Services\Core\Setting;

/**
 * بيانات لوحة التحكم وتنبيهاتها — كل الأرقام مستخرجة من قاعدة البيانات.
 */
final class DashboardService
{
    public static function data(): array
    {
        $todayStart = date('Y-m-d') . ' 00:00:00';
        $monthStart = date('Y-m-01') . ' 00:00:00';

        $todaySales = (float) Database::scalar(
            "SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE status = 'active' AND sale_date >= ?",
            [$todayStart]
        );
        $monthSales = (float) Database::scalar(
            "SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE status = 'active' AND sale_date >= ?",
            [$monthStart]
        );
        $todayCollections = (float) Database::scalar(
            "SELECT COALESCE(SUM(amount), 0) FROM collections WHERE status = 'active' AND collection_date >= ?",
            [$todayStart]
        );

        $alerts = self::alerts();
        $debt = 0.0;
        foreach ($alerts['indebted'] as $row) {
            $debt += $row['debt'];
        }

        $stock = (new InventoryMovement())->stockSummary();
        $inventoryValue = 0.0;
        foreach ($stock as $row) {
            $inventoryValue += (float) $row['value'];
        }

        $totals = CashService::totals();

        return [
            'stats' => [
                'todaySalesTotal' => round($todaySales, 2),
                'monthSalesTotal' => round($monthSales, 2),
                'todayCollectionsTotal' => round($todayCollections, 2),
                'totalDistributorDebt' => round($debt, 2),
                'cashBalance' => CashService::balance(),
                'inventoryValue' => round($inventoryValue, 2),
                'packagesCount' => (int) Database::scalar('SELECT COUNT(*) FROM packages'),
                'activePackagesCount' => (int) Database::scalar("SELECT COUNT(*) FROM packages WHERE status = 'active'"),
                'distributorsCount' => (int) Database::scalar("SELECT COUNT(*) FROM distributors WHERE status = 'active'"),
                'totalExpenses' => round((float) Database::scalar('SELECT COALESCE(SUM(amount), 0) FROM expenses'), 2),
                'totalWithdrawals' => round((float) Database::scalar('SELECT COALESCE(SUM(amount), 0) FROM owner_withdrawals'), 2),
                'cashIn' => $totals['in'],
                'cashOut' => $totals['out'],
            ],
            'alerts' => [
                'lowStock' => $alerts['lowStock'],
                'indebtedDistributors' => array_slice($alerts['indebted'], 0, 6),
            ],
            'recent' => self::recentOperations(8),
            'stockByPackage' => $stock,
        ];
    }

    /** @return array{lowStock:array<int,array<string,mixed>>,indebted:array<int,array<string,mixed>>} */
    public static function alerts(): array
    {
        $threshold = (int) Setting::get('low_stock_threshold', 10);

        $lowStock = [];
        foreach ((new InventoryMovement())->stockSummary() as $row) {
            if ((int) $row['current'] <= $threshold) {
                $lowStock[] = [
                    'id' => (int) $row['package_id'],
                    'name' => $row['name'],
                    'current' => (int) $row['current'],
                ];
            }
        }

        $indebted = [];
        foreach ((new Distributor())->balances() as $row) {
            if ((float) $row['balance'] > 0) {
                $indebted[] = [
                    'id' => (int) $row['id'],
                    'name' => $row['name'],
                    'debt' => (float) $row['balance'],
                ];
            }
        }

        usort($indebted, static fn (array $a, array $b): int => $b['debt'] <=> $a['debt']);

        return ['lowStock' => $lowStock, 'indebted' => $indebted];
    }

    /** أحدث العمليات: مبيعات، تحصيلات، مصروفات، سحوبات، دفعات خطوط. */
    public static function recentOperations(int $limit = 8): array
    {
        $rows = [];

        foreach (Database::all(
            "SELECT id, invoice_number, distributor_name, total_amount AS amount, sale_date AS at
             FROM sales WHERE status = 'active' ORDER BY id DESC LIMIT 20"
        ) as $row) {
            $rows[] = [
                'type' => 'sale',
                'label' => 'فاتورة ' . $row['invoice_number'],
                'distributor' => $row['distributor_name'],
                'amount' => (float) $row['amount'],
                'date' => $row['at'],
            ];
        }

        foreach (Database::all(
            "SELECT c.id, c.amount, c.collection_date AS at, d.name AS distributor_name
             FROM collections c LEFT JOIN distributors d ON d.id = c.distributor_id
             WHERE c.status = 'active' ORDER BY c.id DESC LIMIT 20"
        ) as $row) {
            $rows[] = [
                'type' => 'collection',
                'label' => 'تحصيل',
                'distributor' => $row['distributor_name'],
                'amount' => (float) $row['amount'],
                'date' => $row['at'],
            ];
        }

        foreach (Database::all('SELECT id, amount, expense_date AS at, category FROM expenses ORDER BY id DESC LIMIT 20') as $row) {
            $rows[] = [
                'type' => 'expense',
                'label' => 'مصروف — ' . (\App\Models\Expense::CATEGORIES[$row['category']] ?? $row['category']),
                'distributor' => null,
                'amount' => (float) $row['amount'],
                'date' => $row['at'],
            ];
        }

        foreach (Database::all('SELECT id, amount, withdrawal_date AS at, reason FROM owner_withdrawals ORDER BY id DESC LIMIT 20') as $row) {
            $rows[] = [
                'type' => 'withdrawal',
                'label' => 'سحب مالك — ' . $row['reason'],
                'distributor' => null,
                'amount' => (float) $row['amount'],
                'date' => $row['at'],
            ];
        }

        foreach (Database::all(
            'SELECT lp.id, lp.amount, lp.payment_date AS at, l.name AS line_name
             FROM line_payments lp LEFT JOIN `lines` l ON l.id = lp.line_id ORDER BY lp.id DESC LIMIT 20'
        ) as $row) {
            $rows[] = [
                'type' => 'line_payment',
                'label' => 'دفعة خط — ' . ($row['line_name'] ?? ''),
                'distributor' => null,
                'amount' => (float) $row['amount'],
                'date' => $row['at'],
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp((string) $b['date'], (string) $a['date']));

        return array_slice($rows, 0, $limit);
    }
}
