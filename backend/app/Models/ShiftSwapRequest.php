<?php

namespace App\Models;

use App\Enums\ShiftSwapStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShiftSwapRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'created_by',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShiftSwapStatus::class,
        ];
    }

    // Returns the user who created this swap request.
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Returns the participant entries linked to this swap request.
    public function participants(): HasMany
    {
        return $this->hasMany(ShiftSwapParticipant::class, 'swap_id');
    }

    // Returns the users linked to this swap request.
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shift_swap_participants', 'swap_id', 'user_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    // Returns the shift entries linked to this swap request.
    public function requestShifts(): HasMany
    {
        return $this->hasMany(ShiftSwapRequestShift::class, 'swap_id');
    }

    // Returns the shifts linked to this swap request.
    public function shifts(): BelongsToMany
    {
        return $this->belongsToMany(Shift::class, 'shift_swap_request_shifts', 'swap_id', 'shift_id')
            ->withPivot(['kind', 'owner_user_id'])
            ->withTimestamps();
    }
}
