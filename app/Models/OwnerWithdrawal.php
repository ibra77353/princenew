<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Core\Database;
use App\Services\Core\DateRange;
use App\Services\Core\Model;

final class OwnerWithdrawal extends Model
{
    protected string $table = 'owner_withdrawals';

    public function listing(int $limit = 200, int $offset = 0, ?string $from = null, ?string $to = null): array
    {
        [$where, $params] = $this->buildFilters($from, $to);

        return Database::all(
            'SELECT * FROM owner_withdrawals WHERE ' . $where . '
             ORDER BY withdrawal_date DESC, id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }

    /** إجمالي السحوبات المطابقة للفترة المعروضة. */
    public function total(?string $from = null, ?string $to = null): float
    {
        [$where, $params] = $this->buildFilters($from, $to);

        return round((float) Database::scalar(
            'SELECT COALESCE(SUM(amount), 0) FROM owner_withdrawals WHERE ' . $where,
            $params
        ), 2);
    }

    /** عدد السحوبات المطابقة للفترة (لترقيم الصفحات المطابق للتصفية). */
    public function countFiltered(?string $from = null, ?string $to = null): int
    {
        [$where, $params] = $this->buildFilters($from, $to);

        return (int) Database::scalar(
            'SELECT COUNT(*) FROM owner_withdrawals WHERE ' . $where,
            $params
        );
    }

    /** @return array{0:string,1:array<int,mixed>} */
    private function buildFilters(?string $from, ?string $to): array
    {
        $where = ['1 = 1'];
        $params = [];
        DateRange::apply($where, $params, 'withdrawal_date', $from !== '' ? $from : null, $to !== '' ? $to : null);

        return [implode(' AND ', $where), $params];
    }
}
