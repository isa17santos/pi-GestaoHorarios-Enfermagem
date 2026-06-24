<?php

namespace App\Notifications;

use App\Models\MedicalLeave;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MedicalLeaveNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly MedicalLeave $medicalLeave,
        private readonly string $action, // 'created', 'updated', 'deleted'
        private readonly string $recipientType, // 'nurse', 'head_nurse'
        private readonly string $nurseName,
        private readonly int $days,
        private readonly string $startDateFormatted,
        private readonly string $endDateFormatted
    ) {}

    public function via(object $notifiable): array
    {
        // Send an email and create also a notification in the system panel
        return ['mail', \App\Notifications\Channels\DatabaseNotificationChannel::class];
    }

    public function toCustomDatabase(object $notifiable): array
    {
        if ($this->recipientType === 'nurse') {
            if ($this->action === 'created') {
                return [
                    'subject' => "Atribuição de baixa médica",
                    'body' => "Foi-lhe atribuída uma baixa médica de {$this->days} dias, com início a {$this->startDateFormatted} e fim a {$this->endDateFormatted}.",
                ];
            } elseif ($this->action === 'updated') {
                return [
                    'subject' => "Alteração de baixa médica",
                    'body' => "A sua baixa médica foi alterada. Foi-lhe atribuída uma baixa médica de {$this->days} dias, com início a {$this->startDateFormatted} e fim a {$this->endDateFormatted}.",
                ];
            } else {
                return [
                    'subject' => "Cancelamento de baixa médica",
                    'body' => "A sua baixa médica de {$this->days} dias (de {$this->startDateFormatted} a {$this->endDateFormatted}) foi eliminada.",
                ];
            }
        } else {
            if ($this->action === 'created') {
                return [
                    'subject' => "Baixa médica atribuída - {$this->nurseName}",
                    'body' => "Foi atribuída uma baixa médica ao enfermeiro {$this->nurseName} de {$this->days} dias, com início a {$this->startDateFormatted} e fim a {$this->endDateFormatted}.",
                ];
            } elseif ($this->action === 'updated') {
                return [
                    'subject' => "Baixa médica alterada - {$this->nurseName}",
                    'body' => "A baixa médica do enfermeiro {$this->nurseName} foi alterada. Foi-lhe atribuída uma baixa médica de {$this->days} dias, com início a {$this->startDateFormatted} e fim a {$this->endDateFormatted}.",
                ];
            } else {
                return [
                    'subject' => "Baixa médica cancelada - {$this->nurseName}",
                    'body' => "A baixa médica do enfermeiro {$this->nurseName} de {$this->days} dias (de {$this->startDateFormatted} a {$this->endDateFormatted}) foi eliminada.",
                ];
            }
        }
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = new MailMessage();

        if ($this->recipientType === 'nurse') {
            $mail->greeting("Olá, {$notifiable->name}!");

            if ($this->action === 'created') {
                $mail->subject("Atribuição de baixa médica")
                    ->line("Foi-lhe atribuída uma baixa médica de {$this->days} dias, com início a {$this->startDateFormatted} e fim a {$this->endDateFormatted}.");
            } elseif ($this->action === 'updated') {
                $mail->subject("Alteração de baixa médica")
                    ->line("A sua baixa médica foi alterada.")
                    ->line("Foi-lhe atribuída uma baixa médica de {$this->days} dias, com início a {$this->startDateFormatted} e fim a {$this->endDateFormatted}.");
            } else {
                $mail->subject("Cancelamento de baixa médica")
                    ->line("A sua baixa médica de {$this->days} dias (com início a {$this->startDateFormatted} e fim a {$this->endDateFormatted}) foi eliminada.");
            }
        } else {
            $mail->greeting("Olá, {$notifiable->name}!");

            if ($this->action === 'created') {
                $mail->subject("Baixa médica atribuída - {$this->nurseName}")
                    ->line("Foi atribuída uma baixa médica ao enfermeiro {$this->nurseName} de {$this->days} dias, com início a {$this->startDateFormatted} e fim a {$this->endDateFormatted}.");
            } elseif ($this->action === 'updated') {
                $mail->subject("Baixa médica alterada - {$this->nurseName}")
                    ->line("A baixa médica do enfermeiro {$this->nurseName} foi alterada.")
                    ->line("Foi-lhe atribuída uma baixa médica de {$this->days} dias, com início a {$this->startDateFormatted} e fim a {$this->endDateFormatted}.");
            } else {
                $mail->subject("Baixa médica cancelada - {$this->nurseName}")
                    ->line("A baixa médica do enfermeiro {$this->nurseName} de {$this->days} dias (com início a {$this->startDateFormatted} e fim a {$this->endDateFormatted}) foi eliminada.");
            }
        }

        return $mail->salutation('Com os melhores cumprimentos, ShiftCare');
    }
}
