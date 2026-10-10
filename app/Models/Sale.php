<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Core\Database;
use App\Services\Core\Model;

final class Sale extends Model
{
    protected string $table = 'sales';

    /** رقم الفاتورة التالي بصيغة PN-YYYYMM-0001. */
    public function nextInvoiceNumber(): string
    {
        $prefix = 'PN-' . date('Ym') . '-';
        $last = Database::scalar(
            'SELECT invoice_number FROM sales WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1',
            [$prefix . '%']
        );

        $sequence = 1;
        if (is_string($last) && preg_match('/(\d+)$/', $last, $m)) {
            $sequence = (int) $m[1] + 1;
        }

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

    public function remaining(int $saleId): float
    {
        $row = Database::one(
            "SELECT s.total_amount, s.paid_amount, s.status,
                COALESCE((SELECT SUM(amount) FROM collections c WHERE c.sale_id = s.id AND c.status = 'active'), 0) AS collected
             FROM sales s WHERE s.id = ?",
            [$saleId]
        );

        if ($row === null) {
            return 0.0;
        }

        return round((float) $row['total_amount'] - (float) $row['paid_amount'] - (float) $row['collected'], 2);
    }

    /** فواتير موزّع نشطة عليها مبلغ متبقٍ. */
    public function outstandingForDistributor(int $distributorId): array
    {
        return Database::all(
            "SELECT s.id, s.invoice_number, s.sale_date, s.total_amount, s.paid_amount, s.payment_type,
                (s.total_amount - s.paid_amount
                 - COALESCE((SELECT SUM(c.amount) FROM collections c WHERE c.sale_id = s.id AND c.status = 'active'), 0)) AS remaining
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
                    (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS items_count
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
        [$where, $params] = $this->buildFilters($filters);
        $row = Database::one(
            'SELECT COUNT(*) AS c, COALESCE(SUM(s.total_amount), 0) AS total, COALESCE(SUM(s.paid_amount), 0) AS paid
             FROM sales s LEFT JOIN distributors d ON d.id = s.distributor_id
             WHERE ' . $where,
            $params
        );

        return (int) ($row['c'] ?? 0);
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
        if (!empty($filters['from'])) {
            $where[] = 's.sale_date >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where[] = 's.sale_date <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }

        return [implode(' AND ', $where), $params];
    }
}
