<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Template;
use App\Models\User;

class TemplatePolicy
{
    use Concerns\ChecksUserRoles;

    public function viewAny(User $user): bool
    {
        return $this->active($user);
    }

    public function view(User $user, Template $template): bool
    {
        return $this->active($user);
    }

    public function preview(User $user, Template $template): bool
    {
        return $this->active($user);
    }

    public function create(User $user): bool
    {
        return $this->manager($user);
    }

    public function update(User $user, Template $template): bool
    {
        return $this->manager($user);
    }

    public function delete(User $user, Template $template): bool
    {
        return $this->manager($user);
    }

    public function restore(User $user, Template $template): bool
    {
        return $this->manager($user);
    }

    public function forceDelete(User $user, Template $template): bool
    {
        return $this->admin($user);
    }
}
