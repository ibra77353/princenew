<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CashTransaction;
use App\Models\Distributor;
use App\Models\InventoryMovement;
use App\Models\Sale;
use App\Services\Core\Database;
use App\Services\Core\DateRange;

/**
 * التقارير: تُحسب الإجماليات من **كل** السجلات المطابقة للفترة على الخادم،
 * وتُفصل المبيعات عن التدفق النقدي الفعلي.
 *
 * ضمانات إصلاح M7–M11:
 * - الحدود الزمنية تُطبَّق عبر DateRange: تحقق من صحة التاريخ وحدود شاملة بلا فقد/تكرار.
 * - نفس الفلاتر (الفترة + الموزع + الباقة + الحالة) تُطبَّق على الإجماليات وعلى الجداول
 *   التفصيلية معًا، فلا يختلف المجموع المعروض عن السجلات الفعلية المطابقة.
 * - إجمالي المصروفات وإجماليات الصندوق تُحسب من **نفس شروط** الصفوف والتصنيفات المعروضة.
 * - الحركات العكسية تُقرأ من `cash_transactions` مرة واحدة حسب تاريخها، فلا تُحتسب مرتين.
 */
final class ReportService
{
    /**
     * @param array{from?:string,to?:string,distributor_id?:int,package_id?:int,status?:string} $filters
     */
    public static function build(array $filters): array
    {
        $filters = self::normalizeFilters($filters);

        return [
            'range' => ['from' => $filters['from'] ?? '', 'to' => $filters['to'] ?? ''],
            'sales' => self::sales($filters),
            'salesByPackage' => self::salesByPackage($filters),
            'collections' => self::collections($filters),
            'debts' => self::debts(),
            'expenses' => self::expenses($filters),
            'linePayments' => self::linePayments($filters),
            'withdrawals' => self::withdrawals($filters),
            'cash' => self::cash($filters),
            'inventory' => self::inventory(),
        ];
    }

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    private static function normalizeFilters(array $filters): array
    {
        $range = DateRange::normalize((string) ($filters['from'] ?? ''), (string) ($filters['to'] ?? ''));
        $status = (string) ($filters['status'] ?? 'active');
        if (!in_array($status, ['active', 'cancelled', 'all'], true)) {
            $status = 'active';
        }

        return [
            'from' => $range['from'],
            'to' => $range['to'],
            'distributor_id' => (int) ($filters['distributor_id'] ?? 0),
            'package_id' => (int) ($filters['package_id'] ?? 0),
            'status' => $status,
        ];
    }

    /** @param array<string,mixed> $filters */
    private static function sales(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];
        self::applySalesScope($where, $params, $filters);
        if ($filters['package_id'] > 0) {
            // الباقة المفلترة تُطبَّق على الفاتورة نفسها، فيتوافق إجمالي المبيعات مع قسم المبيعات حسب الباقة.
            $where[] = 'EXISTS (SELECT 1 FROM sale_items si WHERE si.sale_id = s.id AND si.package_id = ?)';
            $params[] = $filters['package_id'];
        }
        $sql = implode(' AND ', $where);

        // الأرقام المالية للفواتير النشطة فقط (مطابقة لقائمة المبيعات وصفحة الفاتورة):
        // الفاتورة الملغاة لا تدخل في الإجمالي ولا في المدفوع، والآجل غير المحصّل يخصم التحصيلات.
        $totals = Database::one(
            "SELECT COUNT(*) AS invoices,
                COALESCE(SUM(CASE WHEN s.status = 'active' THEN s.total_amount END), 0) AS total,
                COALESCE(SUM(CASE WHEN s.status = 'active' THEN s.paid_amount END), 0) AS paid,
                COALESCE(SUM(" . Sale::remainingSql('s') . "), 0) AS credit
             FROM sales s WHERE " . $sql,
            $params
        ) ?? [];

        return [
            'totals' => [
                'invoices' => (int) ($totals['invoices'] ?? 0),
                'total' => round((float) ($totals['total'] ?? 0), 2),
                'paid' => round((float) ($totals['paid'] ?? 0), 2),
                'credit' => round((float) ($totals['credit'] ?? 0), 2),
            ],
            'rows' => Database::all(
                'SELECT s.invoice_number, s.sale_date, s.distributor_name, s.total_amount, s.paid_amount, s.payment_type
                 FROM sales s WHERE ' . $sql . ' ORDER BY s.sale_date DESC, s.id DESC LIMIT 500',
                $params
            ),
        ];
    }

    /** @param array<string,mixed> $filters */
    private static function salesByPackage(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];
        // القسم يخص مبيعات فعلية: نشطة فقط مثل إجمالي المبيعات، فباقة الفاتورة الملغاة أُرجعت للمخزون.
        $scope = $filters;
        $scope['status'] = 'active';
        self::applySalesScope($where, $params, $scope);
        if ($filters['package_id'] > 0) {
            $where[] = 'si.package_id = ?';
            $params[] = $filters['package_id'];
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

    /**
     * نطاق المبيعات المشترك (الفترة + الموزع + الحالة) عبر الجدولين `sales`/`sale_items`.
     * @param array<int,string> $where @param array<int,mixed> $params @param array<string,mixed> $filters
     */
    private static function applySalesScope(array &$where, array &$params, array $filters, string $alias = 's'): void
    {
        DateRange::apply($where, $params, $alias . '.sale_date', $filters['from'], $filters['to']);
        if ($filters['distributor_id'] > 0) {
            $where[] = $alias . '.distributor_id = ?';
            $params[] = $filters['distributor_id'];
        }
        if ($filters['status'] !== 'all') {
            $where[] = $alias . '.status = ?';
            $params[] = $filters['status'];
        }
    }

    /** @param array<string,mixed> $filters */
    private static function collections(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];
        DateRange::apply($where, $params, 'c.collection_date', $filters['from'], $filters['to']);
        if ($filters['distributor_id'] > 0) {
            $where[] = 'c.distributor_id = ?';
            $params[] = $filters['distributor_id'];
        }
        $base = implode(' AND ', $where);

        // التحصيلات الصالحة والمعكوسة تُحسبان من **نفس** الفترة والموزع، فيطابق الإجمالي الصفوف المعروضة.
        $total = (float) Database::scalar(
            'SELECT COALESCE(SUM(c.amount), 0) FROM collections c WHERE ' . $base . " AND c.status = 'active'",
            $params
        );
        $reversed = (float) Database::scalar(
            'SELECT COALESCE(SUM(c.amount), 0) FROM collections c WHERE ' . $base . " AND c.status = 'reversed'",
            $params
        );

        return [
            'total' => round($total, 2),
            'reversed_total' => round($reversed, 2),
            'rows' => Database::all(
                'SELECT c.collection_date, c.amount, c.status, d.name AS distributor_name, s.invoice_number
                 FROM collections c
                 LEFT JOIN distributors d ON d.id = c.distributor_id
                 LEFT JOIN sales s ON s.id = c.sale_id
                 WHERE ' . $base . " AND c.status = 'active'"
                 . ' ORDER BY c.collection_date DESC, c.id DESC LIMIT 500',
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

    /** @param array<string,mixed> $filters */
    private static function expenses(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];
        DateRange::apply($where, $params, 'expense_date', $filters['from'], $filters['to']);
        $sql = implode(' AND ', $where);

        // الإجمالي من نفس شروط التصنيفات والصفوف المعروضة (لا مصدر حساب ثانٍ يختلف عنه).
        $total = (float) Database::scalar('SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE ' . $sql, $params);

        return [
            'total' => round($total, 2),
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

    /** @param array<string,mixed> $filters */
    private static function linePayments(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];
        DateRange::apply($where, $params, 'lp.payment_date', $filters['from'], $filters['to']);
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

    /** @param array<string,mixed> $filters */
    private static function withdrawals(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];
        DateRange::apply($where, $params, 'withdrawal_date', $filters['from'], $filters['to']);
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

    /**
     * التدفق النقدي من حركات الصندوق الفعلية مع تفصيل المصادر.
     * الإجماليات تُجمع من تفصيل المصادر نفسه (نفس الشروط)، والحركات العكسية تظهر
     * مرة واحدة بحسب تاريخها ولا تُحتسب مرتين. الرصيد الكلي منفصل عن تدفق الفترة.
     * **الأرقام تستبعد أثر الفواتير والتحصيلات الملغاة/المعكوسة** بنفس شرط شاشة الصندوق
     * ولوحة التحكم (`CashTransaction::excludeCancelledSql()`) عبر `$figuresSql`؛ والحركة الصحيحة
     * غير المرتبطة بمرجع لا تُستبعد. أما `rows` فتبقى كاملة بلا استبعاد ولا حذف (سجل الحركات).
     * @param array<string,mixed> $filters
     */
    private static function cash(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];
        DateRange::apply($where, $params, 'transaction_date', $filters['from'], $filters['to']);
        $sql = implode(' AND ', $where);
        $figuresSql = $sql . ' AND ' . CashTransaction::excludeCancelledSql();

        $bySource = Database::all(
            "SELECT source,
                COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END), 0) AS cash_in,
                COALESCE(SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END), 0) AS cash_out
             FROM cash_transactions WHERE " . $figuresSql . ' GROUP BY source ORDER BY source ASC',
            $params
        );

        $in = 0.0;
        $out = 0.0;
        foreach ($bySource as $row) {
            $in += (float) $row['cash_in'];
            $out += (float) $row['cash_out'];
        }

        return [
            'in' => round($in, 2),
            'out' => round($out, 2),
            'net' => round($in - $out, 2),
            'balance' => CashService::balance(),
            'bySource' => $bySource,
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
}
