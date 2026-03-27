<?php

namespace App\Models;

use App\Enums\ShiftSwapRequestShiftKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftSwapRequestShift extends Model
{
    protected $fillable = [
        'swap_id',
        'shift_id',
        'owner_user_id',
        'kind',
    ];

    protected function casts(): array
    {
        return [
            'kind' => ShiftSwapRequestShiftKind::class,
        ];
    }

    // Returns the swap request that this shift entry belongs to.
    public function swapRequest(): BelongsTo
    {
        return $this->belongsTo(ShiftSwapRequest::class, 'swap_id');
    }

    // Returns the shift linked to this entry.
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    // Returns the user that owns the shift in the context of the request.
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
