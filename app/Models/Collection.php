<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Core\Database;
use App\Services\Core\DateRange;
use App\Services\Core\Model;

final class Collection extends Model
{
    protected string $table = 'collections';

    public function listing(int $limit = 200, int $offset = 0, ?int $distributorId = null, ?string $from = null, ?string $to = null): array
    {
        [$where, $params] = $this->buildFilters($distributorId, $from, $to);

        return Database::all(
            'SELECT c.*, d.name AS distributor_name, s.invoice_number, s.status AS sale_status
             FROM collections c
             LEFT JOIN distributors d ON d.id = c.distributor_id
             LEFT JOIN sales s ON s.id = c.sale_id
             WHERE ' . $where . '
             ORDER BY c.collection_date DESC, c.id DESC
             LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }

    /**
     * إجماليات مطابقة لنفس فلاتر القائمة (الموزع + الفترة) مع عدد السجلات المطابقة،
     * فلا يختلف المجموع أو ترقيم الصفحات عن السجلات الفعلية (M8).
     * @return array{valid:float,reversed:float,count:int}
     */
    public function totals(?int $distributorId = null, ?string $from = null, ?string $to = null): array
    {
        [$where, $params] = $this->buildFilters($distributorId, $from, $to);

        $row = Database::one(
            "SELECT
                COALESCE(SUM(CASE WHEN c.status = 'active' THEN c.amount ELSE 0 END), 0) AS valid,
                COALESCE(SUM(CASE WHEN c.status = 'reversed' THEN c.amount ELSE 0 END), 0) AS reversed,
                COUNT(*) AS c
             FROM collections c WHERE " . $where,
            $params
        ) ?? [];

        return [
            'valid' => round((float) ($row['valid'] ?? 0), 2),
            'reversed' => round((float) ($row['reversed'] ?? 0), 2),
            'count' => (int) ($row['c'] ?? 0),
        ];
    }

    /**
     * شروط القائمة المشتركة (الموزع + الفترة الشاملة).
     * @return array{0:string,1:array<int,mixed>}
     */
    private function buildFilters(?int $distributorId, ?string $from, ?string $to): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($distributorId !== null) {
            $where[] = 'c.distributor_id = ?';
            $params[] = $distributorId;
        }
        DateRange::apply($where, $params, 'c.collection_date', $from !== '' ? $from : null, $to !== '' ? $to : null);

        return [implode(' AND ', $where), $params];
    }

    /** تحصيلات الموزع، مع إمكانية حصرها بفترة (كشف الحساب) بنفس شروط الفترة في بقية الشاشات. */
    public function forDistributor(int $distributorId, ?string $from = null, ?string $to = null): array
    {
        [$where, $params] = $this->buildFilters($distributorId, $from, $to);

        return Database::all(
            'SELECT c.*, s.invoice_number FROM collections c
             LEFT JOIN sales s ON s.id = c.sale_id
             WHERE ' . $where . ' ORDER BY c.collection_date DESC, c.id DESC',
            $params
        );
    }

    public function forSale(int $saleId): array
    {
        return Database::all('SELECT * FROM collections WHERE sale_id = ? ORDER BY id ASC', [$saleId]);
    }
}
