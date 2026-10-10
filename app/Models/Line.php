<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Core\Database;
use App\Services\Core\Model;

final class Line extends Model
{
    // «lines» كلمة محجوزة في MySQL، لذا تُقتبس دائمًا بعلامات خلفية
    protected string $table = '`lines`';

    public function listing(?string $status = null): array
    {
        if ($status !== null) {
            return Database::all('SELECT * FROM `lines` WHERE status = ? ORDER BY name ASC', [$status]);
        }

        return $this->all('name ASC');
    }

    public function withPayments(int $id): ?array
    {
        $line = $this->find($id);
        if ($line === null) {
            return null;
        }

        $line['payments'] = Database::all(
            'SELECT * FROM line_payments WHERE line_id = ? ORDER BY payment_date DESC, id DESC',
            [$id]
        );
        $line['paid_total'] = round((float) Database::scalar(
            'SELECT COALESCE(SUM(amount), 0) FROM line_payments WHERE line_id = ?',
            [$id]
        ), 2);

        return $line;
    }
}
