<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class ApiRequestLogPolicy
{
    use Concerns\ChecksUserRoles;

    public function viewAny(User $user): bool
    {
        return $this->active($user);
    }

    public function delete(User $user): bool
    {
        return $this->admin($user);
    }
}
