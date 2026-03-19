<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SystemNotification extends Model
{
    use SoftDeletes;

    protected $table = 'notifications';

    protected $fillable = [
        'subject',
        'body',
        'read',
    ];

    protected function casts(): array
    {
        return [
            'read' => 'boolean',
        ];
    }

    // Returns the users who received this notification.
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_notifications', 'notification_id', 'user_id')
            ->withTimestamps();
    }
}
