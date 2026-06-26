<?php

namespace App\Notifications;

use App\Enums\ShiftSwapRequestShiftType;
use App\Enums\UserRole;
use App\Models\ShiftSwapRequest;
use App\Notifications\Channels\DatabaseNotificationChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SwapAcceptedNotification extends Notification
{
    use Queueable, FormatsDates;

    public function __construct(
        public ShiftSwapRequest $swapRequest,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail', DatabaseNotificationChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payload = $this->toCustomDatabase($notifiable);

        return (new MailMessage)
            ->subject($payload['subject'])
            ->greeting('Olá,')
            ->line($payload['body']);
    }

    public function toCustomDatabase(object $notifiable): array
    {
        $requester = $this->swapRequest->participants->firstWhere('role', 'requester');
        $target    = $this->swapRequest->participants->firstWhere('role', 'target');

        $offeredShifts   = $this->swapRequest->requestShifts->where('type', ShiftSwapRequestShiftType::Offered);
        $requestedShifts = $this->swapRequest->requestShifts->where('type', ShiftSwapRequestShiftType::Requested);

        $requesterName = (string) ($requester?->user?->name ?? '-');
        $targetName    = (string) ($target?->user?->name ?? '-');

        $isHeadNurse = $notifiable->role === UserRole::HeadNurse
            || $notifiable->role === UserRole::HeadNurse->value;

        if ($isHeadNurse) {
            $requesterShifts = $requestedShifts->map(fn ($s) =>
                ($s->shift?->shiftType?->name ?? '-').' ('.$this->formatShiftDate($s->shift?->shift_date?->toDateString()).')'
            )->implode(', ');

            $targetShifts = $offeredShifts->map(fn ($s) =>
                ($s->shift?->shiftType?->name ?? '-').' ('.$this->formatShiftDate($s->shift?->shift_date?->toDateString()).')'
            )->implode(', ');

            return [
                'subject' => 'Troca de Turno Concluída',
                'body'    => "Troca concluída entre {$requesterName} e {$targetName}. {$requesterName} ficou com o turno {$requesterShifts}. {$targetName} ficou com o turno {$targetShifts}.",
            ];
        }

        // Requerente: o target aceitou o pedido
        $acceptedShifts = $offeredShifts->map(fn ($s) =>
            ($s->shift?->shiftType?->name ?? '-').' ('.$this->formatShiftDate($s->shift?->shift_date?->toDateString()).')'
        )->implode(', ');

        $receivedShifts = $requestedShifts->map(fn ($s) =>
            ($s->shift?->shiftType?->name ?? '-').' ('.$this->formatShiftDate($s->shift?->shift_date?->toDateString()).')'
        )->implode(', ');

        return [
            'subject' => 'Pedido de Troca Aceite',
            'body'    => "{$targetName} aceitou o seu pedido de troca. Deu o turno {$acceptedShifts} e recebeu o turno {$receivedShifts}.",
        ];
    }
}
