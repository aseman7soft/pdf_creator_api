<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GeneratedDocument;
use App\Models\User;

class GeneratedDocumentPolicy
{
    use Concerns\ChecksUserRoles;

    public function viewAny(User $user): bool
    {
        return $this->active($user);
    }

    public function view(User $user, GeneratedDocument $document): bool
    {
        return $this->active($user);
    }

    public function download(User $user, GeneratedDocument $document): bool
    {
        return $this->active($user) && $document->canDownload();
    }

    public function retry(User $user, GeneratedDocument $document): bool
    {
        return $this->manager($user);
    }

    public function delete(User $user, GeneratedDocument $document): bool
    {
        return $this->manager($user);
    }

    public function forceDelete(User $user, GeneratedDocument $document): bool
    {
        return $this->admin($user);
    }
}
