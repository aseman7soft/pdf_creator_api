<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Models\User;

trait ChecksUserRoles
{
    protected function active(User $user): bool
    {
        return $user->is_active === true;
    }

    protected function manager(User $user): bool
    {
        return $this->active($user) && $user->canManage();
    }

    protected function admin(User $user): bool
    {
        return $this->active($user) && $user->isAdmin();
    }
}
