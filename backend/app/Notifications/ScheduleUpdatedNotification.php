<?php

namespace App\Notifications;

use App\Models\Schedule;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ScheduleUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(protected Schedule $schedule)
    {
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $mes = $this->schedule->start_date->translatedFormat('F');
        return (new MailMessage)
            ->subject("Alteração no Horário de {$mes}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Informamos que o horário referente ao mês de {$mes} sofreu alterações.")
            ->line("Por favor, aceda à aplicação para consultar a sua nova escala.")
            ->action('Ver Horário', url('/schedules/' . $this->schedule->id))
            ->line('Obrigado por utilizar o nosso sistema!');
    }

    public function toArray($notifiable): array
    {
        return [
            'schedule_id' => $this->schedule->id,
            'message' => "O horário de {$this->schedule->start_date->format('M/Y')} foi atualizado.",
        ];
    }
}
