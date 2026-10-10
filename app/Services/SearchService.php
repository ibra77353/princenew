<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Core\Database;

/**
 * البحث الشامل في كل أقسام النظام.
 */
final class SearchService
{
    public static function search(string $term): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 1) {
            return [];
        }

        $like = '%' . $term . '%';

        return [
            'packages' => Database::all(
                'SELECT id, name, price, status FROM packages WHERE name LIKE ? OR description LIKE ? ORDER BY name ASC LIMIT 20',
                [$like, $like]
            ),
            'distributors' => Database::all(
                'SELECT id, name, phone, status FROM distributors
                 WHERE name LIKE ? OR phone LIKE ? OR address LIKE ? ORDER BY name ASC LIMIT 20',
                [$like, $like, $like]
            ),
            'sales' => Database::all(
                'SELECT id, invoice_number, distributor_name, total_amount, sale_date, status FROM sales
                 WHERE invoice_number LIKE ? OR distributor_name LIKE ? ORDER BY id DESC LIMIT 20',
                [$like, $like]
            ),
            'collections' => Database::all(
                'SELECT c.id, c.amount, c.collection_date, c.status, d.name AS distributor_name
                 FROM collections c LEFT JOIN distributors d ON d.id = c.distributor_id
                 WHERE d.name LIKE ? OR c.notes LIKE ? ORDER BY c.id DESC LIMIT 20',
                [$like, $like]
            ),
            'expenses' => Database::all(
                'SELECT id, amount, category, description, expense_date FROM expenses
                 WHERE description LIKE ? OR notes LIKE ? OR category LIKE ? ORDER BY id DESC LIMIT 20',
                [$like, $like, $like]
            ),
            'lines' => Database::all(
                'SELECT id, name, provider, identifier, status FROM `lines`
                 WHERE name LIKE ? OR provider LIKE ? OR identifier LIKE ? ORDER BY name ASC LIMIT 20',
                [$like, $like, $like]
            ),
            'line_payments' => Database::all(
                'SELECT lp.id, lp.amount, lp.payment_date, l.name AS line_name
                 FROM line_payments lp LEFT JOIN `lines` l ON l.id = lp.line_id
                 WHERE l.name LIKE ? OR lp.notes LIKE ? ORDER BY lp.id DESC LIMIT 20',
                [$like, $like]
            ),
            'cash' => Database::all(
                'SELECT id, type, source, amount, description, transaction_date FROM cash_transactions
                 WHERE description LIKE ? ORDER BY id DESC LIMIT 20',
                [$like]
            ),
            'withdrawals' => Database::all(
                'SELECT id, amount, reason, withdrawal_date FROM owner_withdrawals
                 WHERE reason LIKE ? OR notes LIKE ? ORDER BY id DESC LIMIT 20',
                [$like, $like]
            ),
        ];
    }
}
