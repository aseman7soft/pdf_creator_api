<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DocumentJob;
use App\Models\User;

class DocumentJobPolicy
{
    use Concerns\ChecksUserRoles;

    public function viewAny(User $user): bool
    {
        return $this->active($user);
    }

    public function view(User $user, DocumentJob $job): bool
    {
        return $this->active($user);
    }

    public function retry(User $user, DocumentJob $job): bool
    {
        return $this->manager($user);
    }

    public function delete(User $user, DocumentJob $job): bool
    {
        return $this->manager($user);
    }
}
