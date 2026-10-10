<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Core\Database;
use App\Services\Core\Model;

final class Distributor extends Model
{
    protected string $table = 'distributors';

    /** أرصدة الموزعين = مجموع المتبقي على الفواتير النشطة − التحصيلات الصالحة. */
    public function balances(): array
    {
        $rows = Database::all(
            "SELECT d.*,
                COALESCE(s.total, 0) AS sales_total,
                COALESCE(s.paid, 0) AS sales_paid,
                COALESCE(c.collected, 0) AS collected,
                (COALESCE(s.total, 0) - COALESCE(s.paid, 0) - COALESCE(c.collected, 0)) AS balance
             FROM distributors d
             LEFT JOIN (
                SELECT distributor_id, SUM(total_amount) AS total, SUM(paid_amount) AS paid
                FROM sales WHERE status = 'active' GROUP BY distributor_id
             ) s ON s.distributor_id = d.id
             LEFT JOIN (
                SELECT distributor_id, SUM(amount) AS collected
                FROM collections WHERE status = 'active' GROUP BY distributor_id
             ) c ON c.distributor_id = d.id
             ORDER BY d.name ASC"
        );

        foreach ($rows as &$row) {
            $row['balance'] = round((float) $row['balance'], 2);
            $row['sales_total'] = round((float) $row['sales_total'], 2);
            $row['sales_paid'] = round((float) $row['sales_paid'], 2);
            $row['collected'] = round((float) $row['collected'], 2);
        }
        unset($row);

        return $rows;
    }

    public function balance(int $distributorId): float
    {
        $value = Database::scalar(
            "SELECT
                (SELECT COALESCE(SUM(total_amount - paid_amount), 0) FROM sales
                    WHERE distributor_id = ? AND status = 'active')
              - (SELECT COALESCE(SUM(amount), 0) FROM collections
                    WHERE distributor_id = ? AND status = 'active')",
            [$distributorId, $distributorId]
        );

        return round((float) $value, 2);
    }

    public function active(): array
    {
        return Database::all("SELECT * FROM distributors WHERE status = 'active' ORDER BY name ASC");
    }

    public function search(string $term): array
    {
        $like = '%' . $term . '%';

        return Database::all(
            'SELECT * FROM distributors WHERE name LIKE ? OR phone LIKE ? ORDER BY name ASC LIMIT 200',
            [$like, $like]
        );
    }
}
