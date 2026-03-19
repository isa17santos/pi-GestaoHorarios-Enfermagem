<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function shiftType(): BelongsTo
    {
        return $this->belongsTo(ShiftType::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_shifts')
            ->withTimestamps();
    }

    public function swapRequests(): BelongsToMany
    {
        return $this->belongsToMany(ShiftSwapRequest::class, 'swap_shifts', 'shift_id', 'swap_id')
            ->withTimestamps();
    }
}
