<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Core\Database;
use App\Services\Core\Model;

final class Package extends Model
{
    protected string $table = 'packages';

    /** @return array<int,array<string,mixed>> */
    public function ordered(?string $status = null): array
    {
        if ($status !== null) {
            return Database::all(
                'SELECT * FROM packages WHERE status = ? ORDER BY price ASC, id ASC',
                [$status]
            );
        }

        return $this->all('price ASC, id ASC');
    }

    /** @return array<int,array<string,mixed>> */
    public function active(): array
    {
        return $this->ordered('active');
    }

    public function lock(int $id): ?array
    {
        return Database::one('SELECT * FROM packages WHERE id = ? FOR UPDATE', [$id]);
    }
}
