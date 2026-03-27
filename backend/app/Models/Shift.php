<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shift extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'schedule_id',
        'shift_type_id',
        'shift_date',
    ];

    protected function casts(): array
    {
        return [
            'shift_date' => 'date',
        ];
    }

    // Returns the schedule that contains this shift.
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    // Returns the shift type assigned to this shift.
    public function shiftType(): BelongsTo
    {
        return $this->belongsTo(ShiftType::class);
    }

    // Returns the users assigned to this shift.
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_shifts')
            ->withTimestamps();
    }

    // Returns the swap requests that include this shift.
    public function swapRequests(): BelongsToMany
    {
        return $this->belongsToMany(ShiftSwapRequest::class, 'shift_swap_request_shifts', 'shift_id', 'swap_id')
            ->withPivot(['kind', 'owner_user_id'])
            ->withTimestamps();
    }

    // Returns the swap-request entries that reference this shift.
    public function swapRequestEntries(): HasMany
    {
        return $this->hasMany(ShiftSwapRequestShift::class);
    }
}
