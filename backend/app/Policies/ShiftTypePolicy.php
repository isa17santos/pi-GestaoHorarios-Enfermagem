<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ShiftType;
use App\Models\User;

class ShiftTypePolicy
{
    // Only administrators can manage shift types.
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function view(User $user, ShiftType $shiftType): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, ShiftType $shiftType): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, ShiftType $shiftType): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function restore(User $user, ShiftType $shiftType): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function forceDelete(User $user, ShiftType $shiftType): bool
    {
        return $user->role === UserRole::Admin;
    }
}