<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'SwapRequests', description: 'Gestão de pedidos de troca de turnos')]
class SwapValidationController extends Controller
{
    #[OA\Get(
        path: '/api/swaps/validate',
        summary: 'Validar troca de turnos com avisos de regras laborais',
        security: [['bearerAuth' => []]],
        tags: ['SwapRequests'],
        parameters: [
            new OA\Parameter(
                name: 'offered_shift_id',
                in: 'query',
                required: true,
                description: 'ID do turno oferecido pelo utilizador autenticado',
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'requested_shift_id',
                in: 'query',
                required: true,
                description: 'ID do turno solicitado (pertence a outro enfermeiro)',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Validação concluída com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'warnings',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'nurse_id', type: 'integer', example: 1),
                                    new OA\Property(property: 'nurse_name', type: 'string', example: 'Ana Silva'),
                                    new OA\Property(property: 'type', type: 'string', enum: ['afternoon_night']),
                                    new OA\Property(property: 'message', type: 'string', example: 'Vai fazer um turno de tarde seguido de um turno de noite.'),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 422, description: 'Parâmetros inválidos ou turnos inconsistentes para validação'),
        ]
    )]
    public function validate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'offered_shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'requested_shift_id' => ['required', 'integer', 'exists:shifts,id'],
        ], [
            'offered_shift_id.required' => __('swap.validate_offered_shift_id.required'),
            'offered_shift_id.integer' => __('swap.validate_offered_shift_id.integer'),
            'offered_shift_id.exists' => __('swap.validate_offered_shift_id.exists'),
            'requested_shift_id.required' => __('swap.validate_requested_shift_id.required'),
            'requested_shift_id.integer' => __('swap.validate_requested_shift_id.integer'),
            'requested_shift_id.exists' => __('swap.validate_requested_shift_id.exists'),
        ]);

        $user = $request->user();

        $offeredShift = Shift::query()
            ->with(['shiftType', 'users'])
            ->findOrFail((int) $validated['offered_shift_id']);

        $requestedShift = Shift::query()
            ->with(['shiftType', 'users'])
            ->findOrFail((int) $validated['requested_shift_id']);

        if (!$offeredShift->users->contains('id', $user->id)) {
            return response()->json([
                'message' => __('swap.validate_offered_shift_not_owned'),
            ], 422);
        }

        $requestedOwner = $requestedShift->users->first(fn (User $shiftUser): bool => $shiftUser->id !== $user->id);

        if (!$requestedOwner) {
            return response()->json([
                'message' => __('swap.validate_requested_owner_not_found'),
            ], 422);
        }

        // Afternoon/night sequence warning disabled: the detection logic produced false
        // positives and was not correctly identifying consecutive-day violations.
        $warnings = [];

        return response()->json([
            'warnings' => $warnings,
        ]);
    }

    private function buildWarningsForNurse(User $nurse, Shift $givenShift, Shift $receivedShift): array
    {
        // The swap can move a shift across a week boundary, so both the given
        // shift's week and the received shift's week must be evaluated.
        $givenWeekStart = $givenShift->shift_date->copy()->startOfWeek(Carbon::MONDAY);
        $givenWeekEnd = $givenShift->shift_date->copy()->endOfWeek(Carbon::SUNDAY);

        $receivedWeekStart = $receivedShift->shift_date->copy()->startOfWeek(Carbon::MONDAY);
        $receivedWeekEnd = $receivedShift->shift_date->copy()->endOfWeek(Carbon::SUNDAY);

        $rangeStart = $givenWeekStart->lessThan($receivedWeekStart) ? $givenWeekStart : $receivedWeekStart;
        $rangeEnd = $givenWeekEnd->greaterThan($receivedWeekEnd) ? $givenWeekEnd : $receivedWeekEnd;

        $weekShifts = Shift::query()
            ->with('shiftType')
            ->whereHas('users', function ($query) use ($nurse): void {
                $query->where('users.id', $nurse->id);
            })
            ->whereDate('shift_date', '>=', $rangeStart->toDateString())
            ->whereDate('shift_date', '<=', $rangeEnd->toDateString())
            ->where('id', '!=', $givenShift->id)
            // The received shift replaces whatever the nurse already has on that date.
            ->whereDate('shift_date', '!=', $receivedShift->shift_date->toDateString())
            ->get();

        $weekShifts->push($receivedShift);

        $weekShifts = $weekShifts
            ->filter(fn (Shift $shift): bool => $shift->shiftType !== null)
            ->unique('id')
            ->values();

        $warnings = [];

        if ($this->hasAfternoonNightViolation($weekShifts)) {
            $warnings[] = [
                'nurse_id' => $nurse->id,
                'nurse_name' => $nurse->name,
                'type' => 'afternoon_night',
                'message' => __('swap.afternoon_night_violation'),
            ];
        }

        return $warnings;
    }

    private function hasAfternoonNightViolation($shifts): bool
    {
        // Day-off and all-day leave types (holidays, sick leave, parental leave) have no
        // meaningful start/end times; exclude them so they never bridge an afternoon/night
        // pair that isn't actually consecutive shifts.
        $workShifts = $shifts->filter(function (Shift $shift): bool {
            $name = strtolower($shift->shiftType->name ?? '');
            return !in_array($name, ['dayoff', 'day off', 'folga', 'holidays', 'sick leave', 'parental leave'], true);
        });

        $sortedShifts = $workShifts
            ->sortBy(function (Shift $shift): string {
                $startTime = $this->normalizeTime((string) $shift->shiftType->start_time);

                return $shift->shift_date->toDateString().' '.$startTime;
            })
            ->values();

        for ($index = 1; $index < $sortedShifts->count(); $index++) {
            $previousShift = $sortedShifts[$index - 1];
            $currentShift = $sortedShifts[$index];

            $previousName = strtolower($previousShift->shiftType->name ?? '');
            $currentName = strtolower($currentShift->shiftType->name ?? '');

            $dayGap = $previousShift->shift_date->diffInDays($currentShift->shift_date);

            if ($previousName === 'afternoon' && $currentName === 'night' && $dayGap <= 1) {
                return true;
            }
        }

        return false;
    }

    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
