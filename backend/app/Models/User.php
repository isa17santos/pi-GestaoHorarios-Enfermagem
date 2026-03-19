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

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
            'active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function nursePreferences(): HasMany
    {
        return $this->hasMany(NursePreference::class);
    }

    public function createdSchedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'created_by');
    }

    public function schedules(): BelongsToMany
    {
        return $this->belongsToMany(Schedule::class, 'user_schedules')
            ->withTimestamps();
    }

    public function shifts(): BelongsToMany
    {
        return $this->belongsToMany(Shift::class, 'user_shifts')
            ->withTimestamps();
    }

    public function shiftSwaps(): BelongsToMany
    {
        return $this->belongsToMany(ShiftSwapRequest::class, 'user_swaps', 'user_id', 'swap_id')
            ->withTimestamps();
    }

    public function notifications(): BelongsToMany
    {
        return $this->belongsToMany(SystemNotification::class, 'user_notifications')
            ->withTimestamps();
    }
}
