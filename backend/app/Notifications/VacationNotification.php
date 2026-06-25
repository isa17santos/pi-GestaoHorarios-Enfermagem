<?php

namespace App\Notifications;

use App\Models\Vacation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VacationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Vacation $vacation,
        private readonly string $action, // 'created', 'updated', 'deleted'
        private readonly string $nurseName,
        private readonly int $days,
        private readonly string $startDateFormatted,
        private readonly string $endDateFormatted
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', \App\Notifications\Channels\DatabaseNotificationChannel::class];
    }

    public function toCustomDatabase(object $notifiable): array
    {
        if ($this->action === 'created') {
            return [
                'subject' => "Atribuição de férias",
                'body' => "Foi-lhe atribuído um período de férias de {$this->days} dias, com início a {$this->startDateFormatted} e fim a {$this->endDateFormatted}.",
            ];
        } elseif ($this->action === 'updated') {
            return [
                'subject' => "Alteração de férias",
                'body' => "O seu período de férias foi alterado. O novo período é de {$this->days} dias, com início a {$this->startDateFormatted} e fim a {$this->endDateFormatted}.",
            ];
        } else {
            return [
                'subject' => "Cancelamento de férias",
                'body' => "O seu período de férias de {$this->days} dias (de {$this->startDateFormatted} a {$this->endDateFormatted}) foi cancelado.",
            ];
        }
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = new MailMessage();
        $mail->greeting("Olá, {$notifiable->name}!");

        if ($this->action === 'created') {
            $mail->subject("Atribuição de férias")
                ->line("Foi-lhe atribuído um período de férias de {$this->days} dias, com início a {$this->startDateFormatted} e fim a {$this->endDateFormatted}.");
        } elseif ($this->action === 'updated') {
            $mail->subject("Alteração de férias")
                ->line("O seu período de férias foi alterado.")
                ->line("O novo período é de {$this->days} dias, com início a {$this->startDateFormatted} e fim a {$this->endDateFormatted}.");
        } else {
            $mail->subject("Cancelamento de férias")
                ->line("O seu período de férias de {$this->days} dias (com início a {$this->startDateFormatted} e fim a {$this->endDateFormatted}) foi cancelado.");
        }

        return $mail->salutation('Com os melhores cumprimentos, ShiftCare');
    }
}
