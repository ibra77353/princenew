<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Core\Model;

final class Setting extends Model
{
    protected string $table = 'settings';

    public function current(): ?array
    {
        return $this->findBy([]) ?? \App\Services\Core\Database::one('SELECT * FROM settings ORDER BY id ASC LIMIT 1');
    }
}
