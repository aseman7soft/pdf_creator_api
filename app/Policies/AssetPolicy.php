<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

class AssetPolicy
{
    use Concerns\ChecksUserRoles;

    public function viewAny(User $user): bool
    {
        return $this->active($user);
    }

    public function view(User $user, Asset $asset): bool
    {
        return $this->active($user);
    }

    public function create(User $user): bool
    {
        return $this->manager($user);
    }

    public function update(User $user, Asset $asset): bool
    {
        return $this->manager($user);
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $this->manager($user);
    }
}
