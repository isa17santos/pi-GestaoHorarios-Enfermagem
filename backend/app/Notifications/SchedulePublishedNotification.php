<?php

namespace App\Notifications;

use App\Models\Schedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class SchedulePublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Schedule $schedule)
    {
        //
    }

    public function via(object $notifiable): array
    {
        return ['mail', \App\Notifications\Channels\DatabaseNotificationChannel::class];
    }

    public function toCustomDatabase(object $notifiable): array
    {
        Carbon::setLocale('pt');
        $month = Carbon::parse($this->schedule->start_date)->translatedFormat('F \d\e Y');

        return [
            'subject' => "Horário de {$month} publicado",
            'body' => "O horário do mês de {$month} foi publicado e já está disponível.",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        Carbon::setLocale('pt');

        $month = Carbon::parse($this->schedule->start_date)->translatedFormat('F \d\e Y');
        $scheduleUrl = config('app.frontend_url') . '/schedules/' . $this->schedule->id;

        return (new MailMessage)
            ->subject("Horário de {$month} publicado")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("O horário do mês de {$month} foi publicado e já está disponível.")
            ->action('Ver horário', $scheduleUrl)
            ->line('Se tiveres alguma dúvida, contacta o teu enfermeiro chefe.')
            ->salutation('Obrigado, ShiftCare');

    }
}
