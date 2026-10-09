<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Core\Model;

final class Backup extends Model
{
    protected string $table = 'backups';

    public function listing(): array
    {
        return $this->all('id DESC');
    }
}
