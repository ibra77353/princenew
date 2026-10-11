<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Core\Database;
use App\Services\Core\DateRange;
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
        [$where, $params] = $this->buildFilters($category, $from, $to);

        return Database::all(
            'SELECT * FROM expenses WHERE ' . $where . '
             ORDER BY expense_date DESC, id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }

    /** إجمالي المصروفات المطابقة (نفس الفترة ونفس التصنيف المعروضين). */
    public function total(?string $from = null, ?string $to = null, ?string $category = null): float
    {
        [$where, $params] = $this->buildFilters($category, $from, $to);

        return round((float) Database::scalar(
            'SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE ' . $where,
            $params
        ), 2);
    }

    /** عدد المصروفات المطابقة للفلاتر (لترقيم صفحات مطابق للتصفية). */
    public function countFiltered(?string $category = null, ?string $from = null, ?string $to = null): int
    {
        [$where, $params] = $this->buildFilters($category, $from, $to);

        return (int) Database::scalar('SELECT COUNT(*) FROM expenses WHERE ' . $where, $params);
    }

    /** @return array{0:string,1:array<int,mixed>} */
    private function buildFilters(?string $category, ?string $from, ?string $to): array
    {
        $where = ['1 = 1'];
        $params = [];

        if ($category !== null && $category !== '') {
            $where[] = 'category = ?';
            $params[] = $category;
        }
        DateRange::apply($where, $params, 'expense_date', $from !== '' ? $from : null, $to !== '' ? $to : null);

        return [implode(' AND ', $where), $params];
    }
}
