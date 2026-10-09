<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Core\Database;
use App\Services\Core\Model;

final class InventoryMovement extends Model
{
    protected string $table = 'inventory_movements';

    /** سطر المخزون المجمّع لكل باقة (مضاف/مباع/مرتجع/تسوية/الحالي). */
    public function stockSummary(): array
    {
        $rows = Database::all(
            "SELECT
                p.id AS package_id,
                p.name,
                p.color,
                p.status,
                COALESCE(SUM(CASE WHEN m.type IN ('add','return') THEN m.quantity ELSE 0 END), 0) AS added,
                COALESCE(SUM(CASE WHEN m.type = 'sell' THEN m.quantity ELSE 0 END), 0) AS sold,
                COALESCE(SUM(CASE WHEN m.type = 'return' THEN m.quantity ELSE 0 END), 0) AS returned,
                COALESCE(SUM(CASE WHEN m.type = 'adjust' AND m.direction = 'up' THEN m.quantity
                                  WHEN m.type = 'adjust' AND m.direction = 'down' THEN -m.quantity ELSE 0 END), 0) AS adjust_net,
                COALESCE(SUM(CASE WHEN m.type = 'add' THEN m.quantity * m.unit_cost ELSE 0 END), 0) AS added_cost,
                COALESCE(SUM(CASE WHEN m.type = 'add' THEN m.quantity ELSE 0 END), 0) AS added_qty,
                COALESCE(SUM(CASE WHEN m.type = 'add' THEN m.quantity
                                  WHEN m.type = 'sell' THEN -m.quantity
                                  WHEN m.type = 'return' THEN m.quantity
                                  WHEN m.type = 'adjust' AND m.direction = 'up' THEN m.quantity
                                  WHEN m.type = 'adjust' AND m.direction = 'down' THEN -m.quantity
                                  ELSE 0 END), 0) AS current
             FROM packages p
             LEFT JOIN inventory_movements m ON m.package_id = p.id
             GROUP BY p.id, p.name, p.color, p.status
             ORDER BY p.price ASC, p.id ASC"
        );

        foreach ($rows as &$row) {
            $row['current'] = (int) $row['current'];
            $row['added'] = (int) $row['added'];
            $row['sold'] = (int) $row['sold'];
            $row['returned'] = (int) $row['returned'];
            $row['adjust_net'] = (int) $row['adjust_net'];
            $averageCost = (int) $row['added_qty'] > 0 ? (float) $row['added_cost'] / (int) $row['added_qty'] : 0.0;
            $row['average_cost'] = round($averageCost, 2);
            $row['value'] = round(max(0, (int) $row['current']) * $averageCost, 2);
        }
        unset($row);

        return $rows;
    }

    public function currentStock(int $packageId): int
    {
        $value = Database::scalar(
            "SELECT COALESCE(SUM(CASE
                WHEN type IN ('add','return') THEN quantity
                WHEN type = 'sell' THEN -quantity
                WHEN type = 'adjust' AND direction = 'up' THEN quantity
                WHEN type = 'adjust' AND direction = 'down' THEN -quantity
                ELSE 0 END), 0)
             FROM inventory_movements WHERE package_id = ?",
            [$packageId]
        );

        return (int) $value;
    }

    public function recent(int $limit = 200): array
    {
        return Database::all(
            'SELECT m.*, p.name AS package_name, p.color AS package_color
             FROM inventory_movements m
             LEFT JOIN packages p ON p.id = m.package_id
             ORDER BY m.id DESC LIMIT ' . (int) $limit
        );
    }

    public function forPackage(int $packageId): array
    {
        return Database::all(
            'SELECT * FROM inventory_movements WHERE package_id = ? ORDER BY id DESC',
            [$packageId]
        );
    }
}
