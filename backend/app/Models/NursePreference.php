<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NursePreference extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'month',
        'year',
        'prefers_morning',
        'prefers_afternoon',
        'prefers_night',
        'avoid_weekends',
        'prefers_weekends',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'prefers_morning' => 'boolean',
            'prefers_afternoon' => 'boolean',
            'prefers_night' => 'boolean',
            'avoid_weekends' => 'boolean',
            'prefers_weekends' => 'boolean',
        ];
    }

    // Returns the user who owns this preference entry.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Returns the schedule linked to this preference entry.
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }
}
