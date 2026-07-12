<?php

namespace App\Notifications;

use App\Enums\ShiftSwapRequestShiftType;
use App\Models\ShiftSwapRequest;
use App\Notifications\Channels\DatabaseNotificationChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SwapCancelledNotification extends Notification
{
    use Queueable, FormatsDates;

    public function __construct(
        public ShiftSwapRequest $swapRequest,
        public bool $expired = false,
        public bool $byRequester = false,
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
        $offeredShift   = $this->swapRequest->requestShifts->firstWhere('type', ShiftSwapRequestShiftType::Offered);
        $requestedShift = $this->swapRequest->requestShifts->firstWhere('type', ShiftSwapRequestShiftType::Requested);

        $offeredDate   = $this->formatShiftDate($offeredShift?->shift?->shift_date?->toDateString());
        $offeredType   = $offeredShift?->shift?->shiftType?->name ?? '-';
        $requestedDate = $this->formatShiftDate($requestedShift?->shift?->shift_date?->toDateString());
        $requestedType = $requestedShift?->shift?->shiftType?->name ?? '-';

        $requester = $this->swapRequest->participants->firstWhere('role', 'requester');
        $requesterName = (string) ($requester?->user?->name ?? '-');

        $body = match (true) {
            $this->expired => "O seu pedido de troca do turno de {$offeredType} ({$offeredDate}) pelo turno de {$requestedType} ({$requestedDate}) foi cancelado automaticamente porque a data do turno já passou.",
            $this->byRequester => "{$requesterName} cancelou o pedido de troca do turno de {$requestedType} ({$requestedDate}) pelo turno de {$offeredType} ({$offeredDate}).",
            default => "O seu pedido de troca do turno de {$offeredType} ({$offeredDate}) pelo turno de {$requestedType} ({$requestedDate}) foi cancelado porque o turno já foi trocado por outro enfermeiro.",
        };

        return [
            'subject' => 'Pedido de Troca Cancelado',
            'body'    => $body,
        ];
    }
}
