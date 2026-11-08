<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class SettingPolicy
{
    use Concerns\ChecksUserRoles;

    public function viewAny(User $user): bool
    {
        return $this->admin($user);
    }

    public function update(User $user): bool
    {
        return $this->admin($user);
    }
}
