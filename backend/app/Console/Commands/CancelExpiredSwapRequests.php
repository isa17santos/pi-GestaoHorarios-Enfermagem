<?php

namespace App\Console\Commands;

use App\Enums\ShiftSwapParticipantRole;
use App\Enums\ShiftSwapStatus;
use App\Models\ShiftSwapRequest;
use App\Notifications\SwapCancelledNotification;
use Illuminate\Console\Command;

class CancelExpiredSwapRequests extends Command
{
    protected $signature = 'swaps:cancel-expired';
    protected $description = 'Cancela pedidos de troca pendentes cujo turno mais antigo já passou';

    public function handle(): void
    {
        $expired = ShiftSwapRequest::where('status', ShiftSwapStatus::Pending)
            ->whereHas('requestShifts', function ($q) {
                $q->whereHas('shift', function ($q2) {
                    $q2->where('shift_date', '<', today());
                });
            })
            ->with(['requestShifts.shift', 'participants.user'])
            ->get();

        foreach ($expired as $swapRequest) {
            $swapRequest->update(['status' => ShiftSwapStatus::Cancelled]);

            foreach ($swapRequest->participants as $participant) {
                $participant->user?->notify(new SwapCancelledNotification($swapRequest, expired: true));
            }
        }

        $this->info("Cancelados {$expired->count()} pedido(s) de troca expirado(s).");
    }
}
