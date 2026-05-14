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


    // Specifies that this notification should be delivered through the mail channel
    public function via(object $notifiable): array
    {
        return ['mail'];
    }


    // Builds the email message that will be sent to the user
    public function toMail(object $notifiable): MailMessage
    {
        // Builds the frontend password reset URL with the reset token and the user's email as query parameters
        $resetUrl = rtrim(config('app.frontend_url'), '/')
            . '/reset-password?token=' . urlencode($this->token)
            . '&email=' . urlencode($notifiable->getEmailForPasswordReset());

        // Creates the reset password email message with translated subject, content, action button, expiration info, and ignore notice
        return (new MailMessage)
            ->subject(__('Recuperação de password'))
            ->greeting("Olá!")
            ->line('Se solicitaste a recuperação da tua palavra-passe, clica no botão abaixo.')
            ->action('Redefinir palavra-passe', $resetUrl)
            ->line('Se não solicitaste esta alteração, podes ignorar este email.')
            ->salutation('Obrigado, ShiftCare');
    }

}
