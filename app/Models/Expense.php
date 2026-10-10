<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Core\Database;
use App\Services\Core\Model;

final class Expense extends Model
{
    protected string $table = 'expenses';

    public const CATEGORIES = [
        'internet' => 'إنترنت',
        'electricity' => 'كهرباء',
        'maintenance' => 'صيانة',
        'transport' => 'مواصلات',
        'salaries' => 'رواتب',
        'rent' => 'إيجار',
        'tools' => 'أدوات',
        'other' => 'أخرى',
    ];

    public function listing(int $limit = 200, int $offset = 0, ?string $category = null, ?string $from = null, ?string $to = null): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($category !== null && $category !== '') {
            $where[] = 'category = ?';
            $params[] = $category;
        }
        if ($from !== null && $from !== '') {
            $where[] = 'expense_date >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== null && $to !== '') {
            $where[] = 'expense_date <= ?';
            $params[] = $to . ' 23:59:59';
        }

        return Database::all(
            'SELECT * FROM expenses WHERE ' . implode(' AND ', $where) . '
             ORDER BY expense_date DESC, id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }

    public function total(?string $from = null, ?string $to = null): float
    {
        $sql = 'SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE 1 = 1';
        $params = [];
        if ($from !== null && $from !== '') {
            $sql .= ' AND expense_date >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== null && $to !== '') {
            $sql .= ' AND expense_date <= ?';
            $params[] = $to . ' 23:59:59';
        }

        return round((float) Database::scalar($sql, $params), 2);
    }
}
