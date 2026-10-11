<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Core\Database;
use App\Services\Core\DateRange;
use App\Services\Core\Model;

final class Sale extends Model
{
    protected string $table = 'sales';

    /**
     * رقم الفاتورة التالي بصيغة PN-YYYYMM-0001.
     *
     * يُقرأ تحت **قفل تسلسلي** على صف الإعدادات داخل معاملة البيع، فلا تحصل عمليتان
     * متزامنتان على الرقم نفسه (M12). يُحسب الرقم من أعلى تسلسل مسجّل للشهر الحالي
     * لا من آخر صف بالمعرّف، فيبقى التسلسل صحيحًا حتى لو تغيّر ترتيب الإدخال.
     */
    public function nextInvoiceNumber(): string
    {
        Database::one('SELECT id FROM settings WHERE id = 1 FOR UPDATE');

        $prefix = 'PN-' . date('Ym') . '-';
        $max = Database::scalar(
            "SELECT MAX(CAST(SUBSTRING_INDEX(invoice_number, '-', -1) AS UNSIGNED))
             FROM sales WHERE invoice_number LIKE ?",
            [$prefix . '%']
        );

        $sequence = ((int) $max) + 1;

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public function lock(int $id): ?array
    {
        return Database::one('SELECT * FROM sales WHERE id = ? FOR UPDATE', [$id]);
    }

    public function withItems(int $id): ?array
    {
        $sale = Database::one(
            'SELECT s.*, d.name AS distributor_name, d.phone AS distributor_phone, d.address AS distributor_address
             FROM sales s LEFT JOIN distributors d ON d.id = s.distributor_id
             WHERE s.id = ? LIMIT 1',
            [$id]
        );

        if ($sale === null) {
            return null;
        }

        $sale['items'] = (new SaleItem())->forSale($id);
        $sale['collections'] = Database::all(
            "SELECT * FROM collections WHERE sale_id = ? ORDER BY id ASC",
            [$id]
        );

        return $sale;
    }

    /**
     * تعبير SQL موحّد للمتبقي — مصدر واحد لصفحة الفاتورة وقائمة المبيعات وبطاقاتها،
     * وتقارير المبيعات وصفحة الموزع:
     * صفر للفاتورة الملغاة (الإلغاء يُلغي الالتزام ويُعكس المدفوع والتحصيلات)،
     * وإلا: الإجمالي − المدفوع − التحصيلات النشطة.
     */
    public static function remainingSql(string $alias = 's'): string
    {
        return "CASE WHEN {$alias}.status = 'active'
                     THEN {$alias}.total_amount - {$alias}.paid_amount
                          - COALESCE((SELECT SUM(c.amount) FROM collections c
                                      WHERE c.sale_id = {$alias}.id AND c.status = 'active'), 0)
                     ELSE 0 END";
    }

    public function remaining(int $saleId): float
    {
        $remaining = Database::scalar(
            'SELECT ' . self::remainingSql('s') . ' FROM sales s WHERE s.id = ?',
            [$saleId]
        );

        return round((float) $remaining, 2);
    }

    /** فواتير موزّع نشطة عليها مبلغ متبقٍ. */
    public function outstandingForDistributor(int $distributorId): array
    {
        return Database::all(
            "SELECT s.id, s.invoice_number, s.sale_date, s.total_amount, s.paid_amount, s.payment_type,
                " . self::remainingSql('s') . " AS remaining
             FROM sales s
             WHERE s.distributor_id = ? AND s.status = 'active'
             HAVING remaining > 0
             ORDER BY s.sale_date ASC, s.id ASC",
            [$distributorId]
        );
    }

    /** @param array<string,mixed> $filters */
    public function listing(array $filters = [], int $limit = 200, int $offset = 0): array
    {
        [$where, $params] = $this->buildFilters($filters);

        return Database::all(
            'SELECT s.*, d.name AS distributor_name,
                    (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS items_count,
                    ' . self::remainingSql('s') . ' AS remaining
             FROM sales s LEFT JOIN distributors d ON d.id = s.distributor_id
             WHERE ' . $where . '
             ORDER BY s.sale_date DESC, s.id DESC
             LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }

    /** @param array<string,mixed> $filters */
    public function countFiltered(array $filters = []): int
    {
        return $this->totalsFiltered($filters)['count'];
    }

    /**
     * إجماليات مطابقة لنفس فلاتر القائمة (لا تعتمد على الصفحة المعروضة)،
     * فلا يختلف المجموع المعروض عن السجلات الفعلية المطابقة للتصفية.
     * الأرقام المالية للفواتير **النشطة** فقط: الفاتورة الملغاة لا تُحتسب في الإجمالي
     * ولا في المدفوع ولا في الآجل، كما في لوحة التحكم وصفحة الفاتورة.
     * @param array<string,mixed> $filters
     * @return array{count:int,total:float,paid:float,remaining:float}
     */
    public function totalsFiltered(array $filters = []): array
    {
        [$where, $params] = $this->buildFilters($filters);
        $row = Database::one(
            "SELECT COUNT(*) AS c,
                    COALESCE(SUM(CASE WHEN s.status = 'active' THEN s.total_amount END), 0) AS total,
                    COALESCE(SUM(CASE WHEN s.status = 'active' THEN s.paid_amount END), 0) AS paid,
                    COALESCE(SUM(" . self::remainingSql('s') . "), 0) AS remaining
             FROM sales s LEFT JOIN distributors d ON d.id = s.distributor_id
             WHERE " . $where,
            $params
        ) ?? [];

        return [
            'count' => (int) ($row['c'] ?? 0),
            'total' => round((float) ($row['total'] ?? 0), 2),
            'paid' => round((float) ($row['paid'] ?? 0), 2),
            'remaining' => round((float) ($row['remaining'] ?? 0), 2),
        ];
    }

    /** @param array<string,mixed> $filters @return array{0:string,1:array<int,mixed>} */
    private function buildFilters(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filters['term'])) {
            $where[] = '(s.invoice_number LIKE ? OR d.name LIKE ? OR s.distributor_name LIKE ?)';
            $like = '%' . $filters['term'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($filters['distributor_id'])) {
            $where[] = 's.distributor_id = ?';
            $params[] = (int) $filters['distributor_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 's.status = ?';
            $params[] = $filters['status'];
        }
        // عرض السداد: «غير مسددة» = متبقي أكبر من صفر (متبقي الملغاة صفر فلا تظهر)،
        // و«المسددة» = فاتورة نشطة اكتمل سدادها، فالملغاة لا تُعدّ مسددة.
        if (!empty($filters['payment'])) {
            $where[] = $filters['payment'] === 'unpaid'
                ? '(' . self::remainingSql('s') . ') > 0'
                : "s.status = 'active' AND (" . self::remainingSql('s') . ') <= 0';
        }
        if (!empty($filters['from']) || !empty($filters['to'])) {
            $range = DateRange::normalize((string) ($filters['from'] ?? ''), (string) ($filters['to'] ?? ''));
            DateRange::apply($where, $params, 's.sale_date', $range['from'], $range['to']);
        }

        return [implode(' AND ', $where), $params];
    }
}
