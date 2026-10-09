<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Core\Model;

final class User extends Model
{
    protected string $table = 'users';

    /** @return array<int,array<string,mixed>> */
    public function allOrdered(): array
    {
        return $this->all('id ASC');
    }
}
