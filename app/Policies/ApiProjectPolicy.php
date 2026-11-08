<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ApiProject;
use App\Models\User;

class ApiProjectPolicy
{
    use Concerns\ChecksUserRoles;

    public function viewAny(User $user): bool
    {
        return $this->active($user);
    }

    public function view(User $user, ApiProject $project): bool
    {
        return $this->active($user);
    }

    public function create(User $user): bool
    {
        return $this->manager($user);
    }

    public function update(User $user, ApiProject $project): bool
    {
        return $this->manager($user);
    }

    public function delete(User $user, ApiProject $project): bool
    {
        return $this->manager($user);
    }

    public function manageKeys(User $user, ApiProject $project): bool
    {
        return $this->manager($user);
    }
}
