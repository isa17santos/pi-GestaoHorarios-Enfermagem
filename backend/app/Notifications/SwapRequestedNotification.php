<?php

namespace App\Notifications;

use App\Enums\ShiftSwapRequestShiftType;
use App\Models\ShiftSwapRequest;
use App\Notifications\Channels\DatabaseNotificationChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SwapRequestedNotification extends Notification
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
        $requester = $this->swapRequest->participants->firstWhere('role', 'requester');

        $offeredShift = $this->swapRequest->requestShifts->firstWhere('type', ShiftSwapRequestShiftType::Offered);
        $requestedShift = $this->swapRequest->requestShifts->firstWhere('type', ShiftSwapRequestShiftType::Requested);

        $requesterName = $requester?->user?->name ?? '-';
        $offeredDate = $this->formatShiftDate($offeredShift?->shift?->shift_date?->toDateString());
        $offeredType = $offeredShift?->shift?->shiftType?->name ?? '-';
        $requestedDate = $this->formatShiftDate($requestedShift?->shift?->shift_date?->toDateString());
        $requestedType = $requestedShift?->shift?->shiftType?->name ?? '-';

        return (new MailMessage)
            ->subject('Novo Pedido de Troca de Turno')
            ->greeting('Olá,')
            ->line("{$requesterName} quer o seu turno de {$requestedType} ({$requestedDate}) em troca do turno de {$offeredType} ({$offeredDate}).");
    }

    public function toCustomDatabase(object $notifiable): array
    {
        $requester = $this->swapRequest->participants->firstWhere('role', 'requester');

        $offeredShift = $this->swapRequest->requestShifts->firstWhere('type', ShiftSwapRequestShiftType::Offered);
        $requestedShift = $this->swapRequest->requestShifts->firstWhere('type', ShiftSwapRequestShiftType::Requested);

        $requesterName = (string) ($requester?->user?->name ?? '');
        $offeredDate = $this->formatShiftDate($offeredShift?->shift?->shift_date?->toDateString());
        $offeredType = $offeredShift?->shift?->shiftType?->name ?? '-';
        $requestedDate = $this->formatShiftDate($requestedShift?->shift?->shift_date?->toDateString());
        $requestedType = $requestedShift?->shift?->shiftType?->name ?? '-';

        return [
            'subject' => 'Novo Pedido de Troca de Turno',
            'body' => "{$requesterName} quer o seu turno de {$requestedType} ({$requestedDate}) em troca do turno de {$offeredType} ({$offeredDate}).",
        ];
    }
}
