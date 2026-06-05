<?php

namespace App\Http\Controllers;

use App\Enums\ShiftSwapParticipantRole;
use App\Enums\ShiftSwapStatus;
use App\Http\Resources\SwapRequestResource;
use App\Models\ShiftSwapRequest;
use App\Notifications\SwapRejectedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftSwapRequestController extends Controller
{
    /**
     * Relations required by the swap response resource.
     */
    private const RELATIONS = [
        'creator',
        'participants.user',
        'requestShifts.shift.shiftType',
        'requestShifts.owner',
    ];

    public function reject(Request $request, int $swapRequest): JsonResponse|SwapRequestResource
    {
        $swapRequestModel = ShiftSwapRequest::query()
            ->with(['participants'])
            ->find($swapRequest);

        if (!$swapRequestModel) {
            return response()->json([
                'message' => 'Pedido de troca não encontrado.',
            ], 404);
        }

        // Restrict rejection to the target participant of this swap request.
        $isTargetParticipant = $swapRequestModel->participants
            ->contains(fn ($participant): bool => $participant->user_id === $request->user()->id
                && $participant->role->value === ShiftSwapParticipantRole::Target->value);

        if (!$isTargetParticipant) {
            return response()->json([
                'message' => 'Sem permissão para rejeitar este pedido de troca.',
            ], 403);
        }

        $swapRequestModel->update([
            'status' => ShiftSwapStatus::Rejected,
        ]);

        $swapRequestModel->load(self::RELATIONS);

        $swapRequestModel->creator?->notify(new SwapRejectedNotification($swapRequestModel));

        return new SwapRequestResource($swapRequestModel);
    }
}
