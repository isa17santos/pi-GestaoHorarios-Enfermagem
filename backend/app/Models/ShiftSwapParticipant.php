<?php

namespace App\Models;

use App\Enums\ShiftSwapParticipantRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftSwapParticipant extends Model
{
    protected $fillable = [
        'swap_id',
        'user_id',
        'role',
    ];

    protected function casts(): array
    {
        return [
            'role' => ShiftSwapParticipantRole::class,
        ];
    }

    // Returns the swap request that this participant belongs to.
    public function swapRequest(): BelongsTo
    {
        return $this->belongsTo(ShiftSwapRequest::class, 'swap_id');
    }

    // Returns the user linked to this participant record.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
