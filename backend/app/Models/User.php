<?php

namespace App\Models;

use App\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'active',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
            'active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    // Returns all preference records linked to this user.
    public function nursePreferences(): HasMany
    {
        return $this->hasMany(NursePreference::class);
    }

    // Returns all schedules created by this user.
    public function createdSchedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'created_by');
    }

    // Returns the schedules assigned to this user.
    public function schedules(): BelongsToMany
    {
        return $this->belongsToMany(Schedule::class, 'user_schedules')
            ->withTimestamps();
    }

    // Returns the shifts assigned to this user.
    public function shifts(): BelongsToMany
    {
        return $this->belongsToMany(Shift::class, 'user_shifts')
            ->withTimestamps();
    }

    // Returns the shift swap requests linked to this user.
    public function shiftSwaps(): BelongsToMany
    {
        return $this->belongsToMany(ShiftSwapRequest::class, 'shift_swap_participants', 'user_id', 'swap_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    // Returns the shift swap requests created by this user.
    public function createdShiftSwaps(): HasMany
    {
        return $this->hasMany(ShiftSwapRequest::class, 'created_by');
    }

    // Returns the participant entries linked to this user.
    public function shiftSwapParticipants(): HasMany
    {
        return $this->hasMany(ShiftSwapParticipant::class);
    }

    // Returns the shift entries this user owns inside swap requests.
    public function shiftSwapRequestShifts(): HasMany
    {
        return $this->hasMany(ShiftSwapRequestShift::class, 'owner_user_id');
    }

    // Returns the notifications linked to this user.
    public function notifications(): BelongsToMany
    {
        return $this->belongsToMany(SystemNotification::class, 'user_notifications', 'user_id', 'notification_id')
            ->withTimestamps();
    }
}
