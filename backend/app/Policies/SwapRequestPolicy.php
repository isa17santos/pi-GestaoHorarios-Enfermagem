<?php

namespace App\Policies;

use App\Enums\ShiftSwapParticipantRole;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Models\ShiftSwapRequest;
use App\Models\User;

class SwapRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->id;
    }

    public function view(User $user, ShiftSwapRequest $swapRequest): bool
    {
        return $swapRequest->participants()
            ->where('user_id', $user->id)
            ->exists();
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Nurse, UserRole::HeadNurse], true);
    }

    public function accept(User $user, ShiftSwapRequest $swapRequest): bool
    {
        return $this->isPending($swapRequest)
            && $this->hasParticipantRole($swapRequest, $user->id, ShiftSwapParticipantRole::Target->value);
    }

    public function reject(User $user, ShiftSwapRequest $swapRequest): bool
    {
        return $this->isPending($swapRequest)
            && $this->hasParticipantRole($swapRequest, $user->id, ShiftSwapParticipantRole::Target->value);
    }

    public function cancel(User $user, ShiftSwapRequest $swapRequest): bool
    {
        return $this->isPending($swapRequest)
            && $this->hasParticipantRole($swapRequest, $user->id, ShiftSwapParticipantRole::Requester->value);
    }

    // Keep participant and status checks centralized across transition actions.
    private function hasParticipantRole(ShiftSwapRequest $swapRequest, int $userId, string $role): bool
    {
        return $swapRequest->participants()
            ->where('user_id', $userId)
            ->where('role', $role)
            ->exists();
    }

    private function isPending(ShiftSwapRequest $swapRequest): bool
    {
        return $swapRequest->status === ShiftSwapStatus::Pending
            || $swapRequest->status === ShiftSwapStatus::Pending->value;
    }
}
