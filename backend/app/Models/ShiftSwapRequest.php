<?php

namespace App\Models;

use App\Enums\ShiftSwapStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShiftSwapRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShiftSwapStatus::class,
        ];
    }

    // Returns the users linked to this swap request.
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_swaps', 'swap_id', 'user_id')
            ->withTimestamps();
    }

    // Returns the shifts linked to this swap request.
    public function shifts(): BelongsToMany
    {
        return $this->belongsToMany(Shift::class, 'swap_shifts', 'swap_id', 'shift_id')
            ->withTimestamps();
    }
}
