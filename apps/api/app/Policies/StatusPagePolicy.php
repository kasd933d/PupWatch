<?php

namespace App\Policies;

use App\Models\StatusPage;
use App\Models\User;

/**
 * Every status page belongs to exactly one user (PRD §5, multi-tenancy).
 */
class StatusPagePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, StatusPage $statusPage): bool
    {
        return $statusPage->user_id === $user->id;
    }

    public function update(User $user, StatusPage $statusPage): bool
    {
        return $statusPage->user_id === $user->id;
    }

    public function delete(User $user, StatusPage $statusPage): bool
    {
        return $statusPage->user_id === $user->id;
    }
}
