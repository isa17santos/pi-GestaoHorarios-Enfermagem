<?php

namespace App\Models;

use App\Enums\ShiftTypeName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShiftType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'color',
        'start_time',
        'end_time',
        'min_nurses',
    ];

    protected function casts(): array
    {
        return [
            'name' => ShiftTypeName::class,
        ];
    }

    // Returns all shifts that belong to this shift type.
    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }
}
