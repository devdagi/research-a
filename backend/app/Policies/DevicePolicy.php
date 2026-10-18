<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

class DevicePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [User::ROLE_ADMIN, User::ROLE_VIEWER], true);
    }

    public function view(User $user, Device $device): bool
    {
        return $this->viewAny($user);
    }

    public function manage(User $user, ?Device $device = null): bool
    {
        return $user->isAdmin();
    }

    public function revoke(User $user, Device $device): bool
    {
        return $user->isAdmin();
    }

    public function issueCommand(User $user, Device $device): bool
    {
        return $user->isAdmin() && $device->isActive();
    }

    public function requestLiveSession(User $user, Device $device): bool
    {
        return $user->isAdmin() && $device->isActive();
    }
}
