<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ApiKey;
use App\Models\User;

class ApiKeyPolicy
{
    use Concerns\ChecksUserRoles;

    public function viewAny(User $user): bool
    {
        return $this->active($user);
    }

    public function view(User $user, ApiKey $apiKey): bool
    {
        return $this->active($user);
    }

    public function create(User $user): bool
    {
        return $this->manager($user);
    }

    public function update(User $user, ApiKey $apiKey): bool
    {
        return $this->manager($user);
    }

    public function delete(User $user, ApiKey $apiKey): bool
    {
        return $this->manager($user);
    }

    public function rotate(User $user, ApiKey $apiKey): bool
    {
        return $this->manager($user);
    }
}
