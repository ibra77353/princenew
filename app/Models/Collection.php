<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Core\Database;
use App\Services\Core\Model;

final class Collection extends Model
{
    protected string $table = 'collections';

    public function listing(int $limit = 200, int $offset = 0, ?int $distributorId = null, ?string $from = null, ?string $to = null): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($distributorId !== null) {
            $where[] = 'c.distributor_id = ?';
            $params[] = $distributorId;
        }
        if ($from !== null && $from !== '') {
            $where[] = 'c.collection_date >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== null && $to !== '') {
            $where[] = 'c.collection_date <= ?';
            $params[] = $to . ' 23:59:59';
        }

        return Database::all(
            'SELECT c.*, d.name AS distributor_name, s.invoice_number, s.status AS sale_status
             FROM collections c
             LEFT JOIN distributors d ON d.id = c.distributor_id
             LEFT JOIN sales s ON s.id = c.sale_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY c.collection_date DESC, c.id DESC
             LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }

    public function forDistributor(int $distributorId): array
    {
        return Database::all(
            'SELECT c.*, s.invoice_number FROM collections c
             LEFT JOIN sales s ON s.id = c.sale_id
             WHERE c.distributor_id = ? ORDER BY c.collection_date DESC, c.id DESC',
            [$distributorId]
        );
    }

    public function forSale(int $saleId): array
    {
        return Database::all('SELECT * FROM collections WHERE sale_id = ? ORDER BY id ASC', [$saleId]);
    }
}
