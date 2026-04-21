<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Schedule extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'start_date',
        'end_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => 'string',
        ];
    }

    // Returns the user who created this schedule.
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Returns all nurse preferences linked to this schedule.
    public function nursePreferences(): HasMany
    {
        return $this->hasMany(NursePreference::class);
    }

    // Returns all shifts included in this schedule.
    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    // Returns all users assigned to this schedule.
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_schedules')
            ->withTimestamps();
    }
}
