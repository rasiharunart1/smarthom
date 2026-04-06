<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

class DevicePolicy
{
    /**
     * Admins bypass all policy checks.
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Owner OR any shared user (view or control) can view.
     */
    public function view(User $user, Device $device): bool
    {
        if ($user->id === $device->user_id) {
            return true;
        }

        return $device->shares()->where('shared_with_user_id', $user->id)->exists();
    }

    /**
     * Only the owner can edit the device name / settings.
     */
    public function update(User $user, Device $device): bool
    {
        return $user->id === $device->user_id;
    }

    /**
     * Only the owner can delete the device.
     */
    public function delete(User $user, Device $device): bool
    {
        return $user->id === $device->user_id;
    }

    /**
     * Owner OR shared users with 'control' permission can send commands / toggle widgets.
     */
    public function control(User $user, Device $device): bool
    {
        if ($user->id === $device->user_id) {
            return true;
        }

        return $device->shares()
            ->where('shared_with_user_id', $user->id)
            ->where('permission', 'control')
            ->exists();
    }

    /**
     * Only the owner can manage share settings.
     */
    public function share(User $user, Device $device): bool
    {
        return $user->id === $device->user_id;
    }
}
