<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Core\Database;
use App\Services\Core\DateRange;
use App\Services\Core\Model;

final class CashTransaction extends Model
{
    protected string $table = 'cash_transactions';

    public const SOURCES = [
        'sale' => 'مبيعات',
        'collection' => 'تحصيلات',
        'expense' => 'مصروفات',
        'line_payment' => 'دفعات خطوط',
        'owner_withdrawal' => 'سحب مالك',
        'opening' => 'رصيد افتتاحي',
        'reversal' => 'حركة عكسية',
    ];

    public function listing(int $limit = 200, int $offset = 0, ?string $source = null, ?string $from = null, ?string $to = null): array
    {
        [$where, $params] = $this->buildFilters($source, $from, $to);

        return Database::all(
            'SELECT * FROM cash_transactions WHERE ' . $where . '
             ORDER BY transaction_date DESC, id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }

    /**
     * شرط SQL لاستبعاد أثر العمليات الملغاة من **الأرقام** بلا حذفها من سجل الحركات:
     * الحركة العكسية (لا تُنشأ إلا عند الإلغاء)، ومقبوضات فاتورة أصبحت ملغاة، وتحصيل أصبح معكوسًا —
     * فتطابق أرقام الصندوق أرقام المبيعات والتحصيلات الفعلية. الرصيد الفعلي لا يتغيّر
     * لأن كل حركة مُستبعدة تقابلها حركة عكسية مُستبعدة بالمثل.
     */
    public static function excludeCancelledSql(string $alias = ''): string
    {
        $prefix = $alias !== '' ? $alias . '.' : '';

        return 'NOT (' . $prefix . "source = 'reversal'"
            . ' OR (' . $prefix . "reference_type = 'sale' AND " . $prefix . 'reference_id IN (SELECT id FROM sales WHERE status = \'cancelled\'))'
            . ' OR (' . $prefix . "reference_type = 'collection' AND " . $prefix . 'reference_id IN (SELECT id FROM collections WHERE status = \'reversed\')))';
    }

    /** إجماليات الفترة (حدود شاملة: من بداية «من» إلى ما قبل بداية اليوم التالي لـ«إلى»). */
    public function totals(?string $from = null, ?string $to = null): array
    {
        $summary = $this->summary(null, $from, $to);

        return ['in' => $summary['in'], 'out' => $summary['out']];
    }

    /**
     * ملخّص مطابق لنفس الفلاتر (المصدر + الفترة): العدد والإجماليات والتفصيل حسب المصدر،
     * فلا يختلف إجمالي الفترة أو ترقيم الصفحات عن السجلات الفعلية المطابقة.
     * **الإجماليات والتفصيل حسب المصدر يستبعدان أثر العمليات الملغاة** (مطابقةً لقائمة المبيعات
     * والتقارير)، أما العدد فيبقى لكل الحركات لأن السجل يعرضها كاملة.
     * @return array{count:int,in:float,out:float,bySource:array<int,array<string,mixed>>}
     */
    public function summary(?string $source = null, ?string $from = null, ?string $to = null): array
    {
        [$where, $params] = $this->buildFilters($source, $from, $to);
        $figuresWhere = $where . ' AND ' . self::excludeCancelledSql();

        $count = (int) Database::scalar('SELECT COUNT(*) FROM cash_transactions WHERE ' . $where, $params);

        $row = Database::one(
            "SELECT
                COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END), 0) AS cash_in,
                COALESCE(SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END), 0) AS cash_out
             FROM cash_transactions WHERE " . $figuresWhere,
            $params
        ) ?? [];

        $bySource = Database::all(
            "SELECT source,
                COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END), 0) AS cash_in,
                COALESCE(SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END), 0) AS cash_out
             FROM cash_transactions WHERE " . $figuresWhere . ' GROUP BY source ORDER BY source ASC',
            $params
        );

        return [
            'count' => $count,
            'in' => round((float) ($row['cash_in'] ?? 0), 2),
            'out' => round((float) ($row['cash_out'] ?? 0), 2),
            'bySource' => $bySource,
        ];
    }

    public function recent(int $limit = 10): array
    {
        return Database::all('SELECT * FROM cash_transactions ORDER BY id DESC LIMIT ' . (int) $limit);
    }

    /** البحث عن حركة مرتبطة بعملية معيّنة (لمنع تكرار الحركات). */
    public function forReference(string $referenceType, int $referenceId): array
    {
        return Database::all(
            'SELECT * FROM cash_transactions WHERE reference_type = ? AND reference_id = ? ORDER BY id ASC',
            [$referenceType, $referenceId]
        );
    }

    /** @return array{0:string,1:array<int,mixed>} */
    private function buildFilters(?string $source, ?string $from, ?string $to): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($source !== null && $source !== '') {
            $where[] = 'source = ?';
            $params[] = $source;
        }
        DateRange::apply($where, $params, 'transaction_date', $from !== '' ? $from : null, $to !== '' ? $to : null);

        return [implode(' AND ', $where), $params];
    }
}
