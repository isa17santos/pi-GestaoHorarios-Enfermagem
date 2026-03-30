<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;


    public function __construct(private readonly string $token)
    {
        //
    }


    public function via(object $notifiable): array
    {
        return ['mail'];
    }


    public function toMail(object $notifiable): MailMessage
    {
        $resetUrl = rtrim(config('app.frontend_url'), '/')
            . '/reset-password?token=' . urlencode($this->token)
            . '&email=' . urlencode($notifiable->getEmailForPasswordReset());

        return (new MailMessage)
            ->subject(__('auth.reset_password_subject'))
            ->line(__('auth.reset_password_line'))
            ->action(__('auth.reset_password_action'), $resetUrl)
            ->line(__('auth.reset_password_expire', [
                'minutes' => config('auth.passwords.users.expire'),
            ]))
            ->line(__('auth.reset_password_ignore'));
    }

}
