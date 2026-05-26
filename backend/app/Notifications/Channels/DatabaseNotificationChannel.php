<?php

namespace App\Notifications\Channels;

use App\Models\SystemNotification;

class DatabaseNotificationChannel
{
    public function send(mixed $notifiable, mixed $notification): void
    {
        $payload = $notification->toCustomDatabase($notifiable);

        $systemNotification = SystemNotification::create([
            'subject' => $payload['subject'],
            'body' => $payload['body'],
        ]);

        $systemNotification->users()->attach($notifiable->id);
    }
}
