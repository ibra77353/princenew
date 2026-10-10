<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Distributor;
use App\Models\Expense;
use App\Models\InventoryMovement;
use App\Services\Core\Database;

/**
 * التقارير: تُحسب الإجماليات من كل السجلات المطابقة للفترة على الخادم،
 * وتُفصل المبيعات عن التدفق النقدي الفعلي.
 */
final class ReportService
{
    /**
     * @param array{from:string,to:string,distributor_id:int,package_id:int,status:string} $filters
     */
    public static function build(array $filters): array
    {
        $from = $filters['from'] ?? '';
        $to = $filters['to'] ?? '';
        $distributorId = (int) ($filters['distributor_id'] ?? 0);
        $packageId = (int) ($filters['package_id'] ?? 0);

        return [
            'range' => ['from' => $from, 'to' => $to],
            'sales' => self::sales($from, $to, $distributorId),
            'salesByPackage' => self::salesByPackage($from, $to, $packageId),
            'collections' => self::collections($from, $to, $distributorId),
            'debts' => self::debts(),
            'expenses' => self::expenses($from, $to),
            'linePayments' => self::linePayments($from, $to),
            'withdrawals' => self::withdrawals($from, $to),
            'cash' => self::cash($from, $to),
            'inventory' => self::inventory(),
        ];
    }

    public static function sales(string $from, string $to, int $distributorId = 0): array
    {
        $where = ["s.status = 'active'"];
        $params = [];
        self::applyRange($where, $params, 's.sale_date', $from, $to);
        if ($distributorId > 0) {
            $where[] = 's.distributor_id = ?';
            $params[] = $distributorId;
        }
        $sql = implode(' AND ', $where);

        $totals = Database::one(
            "SELECT COUNT(*) AS invoices,
                COALESCE(SUM(s.total_amount), 0) AS total,
                COALESCE(SUM(s.paid_amount), 0) AS paid
             FROM sales s WHERE " . $sql,
            $params
        ) ?? [];

        return [
            'totals' => [
                'invoices' => (int) ($totals['invoices'] ?? 0),
                'total' => round((float) ($totals['total'] ?? 0), 2),
                'paid' => round((float) ($totals['paid'] ?? 0), 2),
                'credit' => round((float) ($totals['total'] ?? 0) - (float) ($totals['paid'] ?? 0), 2),
            ],
            'rows' => Database::all(
                'SELECT s.invoice_number, s.sale_date, s.distributor_name, s.total_amount, s.paid_amount, s.payment_type
                 FROM sales s WHERE ' . $sql . ' ORDER BY s.sale_date DESC, s.id DESC LIMIT 500',
                $params
            ),
        ];
    }

    public static function salesByPackage(string $from, string $to, int $packageId = 0): array
    {
        $where = ["s.status = 'active'"];
        $params = [];
        self::applyRange($where, $params, 's.sale_date', $from, $to);
        if ($packageId > 0) {
            $where[] = 'si.package_id = ?';
            $params[] = $packageId;
        }

        return Database::all(
            'SELECT p.name AS package_name,
                COALESCE(SUM(si.quantity), 0) AS quantity,
                COALESCE(SUM(si.total), 0) AS total
             FROM sale_items si
             JOIN sales s ON s.id = si.sale_id
             JOIN packages p ON p.id = si.package_id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY p.id, p.name ORDER BY total DESC',
            $params
        );
    }

    public static function collections(string $from, string $to, int $distributorId = 0): array
    {
        $where = ["c.status = 'active'"];
        $params = [];
        self::applyRange($where, $params, 'c.collection_date', $from, $to);
        if ($distributorId > 0) {
            $where[] = 'c.distributor_id = ?';
            $params[] = $distributorId;
        }
        $sql = implode(' AND ', $where);

        $total = (float) Database::scalar('SELECT COALESCE(SUM(c.amount), 0) FROM collections c WHERE ' . $sql, $params);
        $reversed = (float) Database::scalar(
            "SELECT COALESCE(SUM(amount), 0) FROM collections WHERE status = 'reversed'
             " . ($from !== '' ? " AND collection_date >= '" . date('Y-m-d 00:00:00', (int) strtotime($from)) . "'" : ''),
            []
        );

        return [
            'total' => round($total, 2),
            'reversed_total' => round($reversed, 2),
            'rows' => Database::all(
                'SELECT c.collection_date, c.amount, c.status, d.name AS distributor_name, s.invoice_number
                 FROM collections c
                 LEFT JOIN distributors d ON d.id = c.distributor_id
                 LEFT JOIN sales s ON s.id = c.sale_id
                 WHERE ' . $sql . ' ORDER BY c.collection_date DESC, c.id DESC LIMIT 500',
                $params
            ),
        ];
    }

    /** ديون الموزعين الحالية (من الحركات الصالحة فقط). */
    public static function debts(): array
    {
        $rows = [];
        $total = 0.0;
        foreach ((new Distributor())->balances() as $row) {
            if ((float) $row['balance'] > 0) {
                $total += (float) $row['balance'];
                $rows[] = [
                    'name' => $row['name'],
                    'phone' => $row['phone'],
                    'balance' => (float) $row['balance'],
                ];
            }
        }

        return ['total' => round($total, 2), 'rows' => $rows];
    }

    public static function expenses(string $from, string $to): array
    {
        $where = ['1 = 1'];
        $params = [];
        self::applyRange($where, $params, 'expense_date', $from, $to);
        $sql = implode(' AND ', $where);

        return [
            'total' => (new Expense())->total($from, $to),
            'byCategory' => Database::all(
                'SELECT category, COALESCE(SUM(amount), 0) AS total, COUNT(*) AS count
                 FROM expenses WHERE ' . $sql . ' GROUP BY category ORDER BY total DESC',
                $params
            ),
            'rows' => Database::all(
                'SELECT * FROM expenses WHERE ' . $sql . ' ORDER BY expense_date DESC, id DESC LIMIT 500',
                $params
            ),
        ];
    }

    public static function linePayments(string $from, string $to): array
    {
        $where = ['1 = 1'];
        $params = [];
        self::applyRange($where, $params, 'lp.payment_date', $from, $to);
        $sql = implode(' AND ', $where);

        $total = (float) Database::scalar('SELECT COALESCE(SUM(lp.amount), 0) FROM line_payments lp WHERE ' . $sql, $params);

        return [
            'total' => round($total, 2),
            'rows' => Database::all(
                'SELECT lp.payment_date, lp.amount, lp.notes, l.name AS line_name
                 FROM line_payments lp LEFT JOIN `lines` l ON l.id = lp.line_id
                 WHERE ' . $sql . ' ORDER BY lp.payment_date DESC, lp.id DESC LIMIT 500',
                $params
            ),
        ];
    }

    public static function withdrawals(string $from, string $to): array
    {
        $where = ['1 = 1'];
        $params = [];
        self::applyRange($where, $params, 'withdrawal_date', $from, $to);
        $sql = implode(' AND ', $where);

        $total = (float) Database::scalar('SELECT COALESCE(SUM(amount), 0) FROM owner_withdrawals WHERE ' . $sql, $params);

        return [
            'total' => round($total, 2),
            'rows' => Database::all(
                'SELECT * FROM owner_withdrawals WHERE ' . $sql . ' ORDER BY withdrawal_date DESC, id DESC LIMIT 500',
                $params
            ),
        ];
    }

    /** التدفق النقدي من حركات الصندوق الفعلية مع تفصيل المصادر. */
    public static function cash(string $from, string $to): array
    {
        $where = ['1 = 1'];
        $params = [];
        self::applyRange($where, $params, 'transaction_date', $from, $to);
        $sql = implode(' AND ', $where);

        $totals = CashService::totals($from, $to);

        return [
            'in' => $totals['in'],
            'out' => $totals['out'],
            'net' => round($totals['in'] - $totals['out'], 2),
            'balance' => CashService::balance(),
            'bySource' => Database::all(
                "SELECT source,
                    COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END), 0) AS cash_in,
                    COALESCE(SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END), 0) AS cash_out
                 FROM cash_transactions WHERE " . $sql . ' GROUP BY source ORDER BY source ASC',
                $params
            ),
            'rows' => Database::all(
                'SELECT * FROM cash_transactions WHERE ' . $sql . ' ORDER BY transaction_date DESC, id DESC LIMIT 500',
                $params
            ),
        ];
    }

    public static function inventory(): array
    {
        $rows = (new InventoryMovement())->stockSummary();
        $value = 0.0;
        $units = 0;
        foreach ($rows as $row) {
            $value += (float) $row['value'];
            $units += (int) $row['current'];
        }

        return ['total_value' => round($value, 2), 'total_units' => $units, 'rows' => $rows];
    }

    /** @param array<int,string> $where @param array<int,mixed> $params */
    private static function applyRange(array &$where, array &$params, string $column, string $from, string $to): void
    {
        if ($from !== '') {
            $where[] = $column . ' >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== '') {
            $where[] = $column . ' <= ?';
            $params[] = $to . ' 23:59:59';
        }
    }
}
