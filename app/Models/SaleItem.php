<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Core\Database;
use App\Services\Core\Model;

final class SaleItem extends Model
{
    protected string $table = 'sale_items';

    public function forSale(int $saleId): array
    {
        return Database::all(
            'SELECT si.*, p.name AS package_name, p.color AS package_color
             FROM sale_items si LEFT JOIN packages p ON p.id = si.package_id
             WHERE si.sale_id = ? ORDER BY si.id ASC',
            [$saleId]
        );
    }
}
