<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    use Concerns\ChecksUserRoles;

    public function viewAny(User $user): bool
    {
        return $this->admin($user);
    }

    public function view(User $user, User $target): bool
    {
        return $this->admin($user);
    }

    public function create(User $user): bool
    {
        return $this->admin($user);
    }

    public function update(User $user, User $target): bool
    {
        return $this->admin($user);
    }

    public function delete(User $user, User $target): bool
    {
        return $this->admin($user) && $user->getKey() !== $target->getKey();
    }
}
