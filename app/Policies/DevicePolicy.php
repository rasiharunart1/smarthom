<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

class DevicePolicy
{
    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Create a new policy instance.
     */

    public function view(User $user, Device $device)
    {
        return $user->id === $device->user_id;
    }

    public function update(User $user, Device $device)
    {
        return $user->id === $device->user_id;
    }

    public function delete(User $user, Device $device)
    {
        return $user->id === $device->user_id;
    }

}
