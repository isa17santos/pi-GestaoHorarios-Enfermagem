<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NursePreference extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'prefers_morning',
        'prefers_afternoon',
        'prefers_night',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'prefers_morning' => 'boolean',
            'prefers_afternoon' => 'boolean',
            'prefers_night' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }
}
