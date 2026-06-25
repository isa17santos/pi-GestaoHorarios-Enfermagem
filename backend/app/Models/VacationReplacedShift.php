<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VacationReplacedShift extends Model
{
    protected $fillable = [
        'vacation_id',
        'shift_date',
        'schedule_id',
        'original_shift_id',
        'original_shift_type_id',
        'temp_shift_id',
        'was_shared',
    ];

    protected function casts(): array
    {
        return [
            'shift_date' => 'date',
            'was_shared' => 'boolean',
        ];
    }

    public function vacation(): BelongsTo
    {
        return $this->belongsTo(Vacation::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function originalShift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'original_shift_id');
    }

    public function originalShiftType(): BelongsTo
    {
        return $this->belongsTo(ShiftType::class, 'original_shift_type_id');
    }

    public function tempShift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'temp_shift_id');
    }
}
