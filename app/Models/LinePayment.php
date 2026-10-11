<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Core\Database;
use App\Services\Core\DateRange;
use App\Services\Core\Model;

final class LinePayment extends Model
{
    protected string $table = 'line_payments';

    public function listing(int $limit = 200, int $offset = 0, ?int $lineId = null, ?string $from = null, ?string $to = null): array
    {
        [$where, $params] = $this->buildFilters($lineId, $from, $to);

        return Database::all(
            'SELECT lp.*, l.name AS line_name, l.provider AS line_provider
             FROM line_payments lp LEFT JOIN `lines` l ON l.id = lp.line_id
             WHERE ' . $where . '
             ORDER BY lp.payment_date DESC, lp.id DESC
             LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }

    /**
     * عدد وإجمالي الدفعات المطابقة لنفس الفلاتر (لا للصفحة المعروضة)،
     * فلا يختلف المجموع المعروض عن السجلات الفعلية المطابقة.
     * @return array{count:int,total:float}
     */
    public function sums(?int $lineId = null, ?string $from = null, ?string $to = null): array
    {
        [$where, $params] = $this->buildFilters($lineId, $from, $to);

        $row = Database::one(
            'SELECT COUNT(*) AS c, COALESCE(SUM(lp.amount), 0) AS total FROM line_payments lp WHERE ' . $where,
            $params
        ) ?? [];

        return ['count' => (int) ($row['c'] ?? 0), 'total' => round((float) ($row['total'] ?? 0), 2)];
    }

    /** @return array{0:string,1:array<int,mixed>} */
    private function buildFilters(?int $lineId, ?string $from, ?string $to): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($lineId !== null) {
            $where[] = 'lp.line_id = ?';
            $params[] = $lineId;
        }
        DateRange::apply($where, $params, 'lp.payment_date', $from !== '' ? $from : null, $to !== '' ? $to : null);

        return [implode(' AND ', $where), $params];
    }
}
