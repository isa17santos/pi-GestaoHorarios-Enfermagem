<?php

namespace App\Notifications;

use App\Enums\ShiftSwapRequestShiftType;
use App\Models\ShiftSwapRequest;
use App\Notifications\Channels\DatabaseNotificationChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SwapAcceptedNotification extends Notification
{
    use Queueable;

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
        $requester = $this->swapRequest->participants->firstWhere('role', 'requester');
        $target = $this->swapRequest->participants->firstWhere('role', 'target');

        $offeredShift = $this->swapRequest->requestShifts->firstWhere('type', ShiftSwapRequestShiftType::Offered);
        $requestedShift = $this->swapRequest->requestShifts->firstWhere('type', ShiftSwapRequestShiftType::Requested);

        $offeredDate = $offeredShift?->shift?->shift_date?->toDateString() ?? '-';
        $offeredType = $offeredShift?->shift?->shiftType?->name ?? '-';
        $requestedDate = $requestedShift?->shift?->shift_date?->toDateString() ?? '-';
        $requestedType = $requestedShift?->shift?->shiftType?->name ?? '-';

        return (new MailMessage)
            ->subject('Troca de Turno Aceite')
            ->greeting('Ola,')
            ->line('Uma troca de turno foi aceite.')
            ->line('Requerente: '.($requester?->user?->name ?? '-'))
            ->line('Destino: '.($target?->user?->name ?? '-'))
            ->line('Turno oferecido: '.$offeredDate.' - '.$offeredType)
            ->line('Turno solicitado: '.$requestedDate.' - '.$requestedType);
    }

    public function toCustomDatabase(object $notifiable): array
    {
        $requester = $this->swapRequest->participants->firstWhere('role', 'requester');
        $target = $this->swapRequest->participants->firstWhere('role', 'target');

        $offeredShift = $this->swapRequest->requestShifts->firstWhere('type', ShiftSwapRequestShiftType::Offered);
        $requestedShift = $this->swapRequest->requestShifts->firstWhere('type', ShiftSwapRequestShiftType::Requested);

        $requesterName = (string) ($requester?->user?->name ?? '');
        $targetName = (string) ($target?->user?->name ?? '');
        $offeredDate = $offeredShift?->shift?->shift_date?->toDateString() ?? '-';
        $offeredType = $offeredShift?->shift?->shiftType?->name ?? '-';
        $requestedDate = $requestedShift?->shift?->shift_date?->toDateString() ?? '-';
        $requestedType = $requestedShift?->shift?->shiftType?->name ?? '-';

        return [
            'subject' => 'Troca de Turno Aceite',
            'body' => "Troca aceite entre {$requesterName} e {$targetName}. Turno oferecido: {$offeredDate} ({$offeredType}). Turno solicitado: {$requestedDate} ({$requestedType}).",
        ];
    }
}
