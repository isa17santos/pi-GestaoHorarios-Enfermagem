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
                                    new OA\Property(property: 'type', type: 'string', enum: ['rest_hours', 'days_off']),
                                    new OA\Property(property: 'message', type: 'string', example: 'A troca pode violar o descanso mínimo de 11 horas entre turnos consecutivos.'),
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
                'message' => 'O turno oferecido deve pertencer ao utilizador autenticado.',
            ], 422);
        }

        $requestedOwner = $requestedShift->users->first(fn (User $shiftUser): bool => $shiftUser->id !== $user->id);

        if (!$requestedOwner) {
            return response()->json([
                'message' => 'Não foi possível identificar o enfermeiro dono do turno solicitado.',
            ], 422);
        }

        $warnings = [];

        $warnings = [
            ...$warnings,
            ...$this->buildWarningsForNurse($user, $offeredShift, $requestedShift),
            ...$this->buildWarningsForNurse($requestedOwner, $requestedShift, $offeredShift),
        ];

        return response()->json([
            'warnings' => $warnings,
        ]);
    }

    private function buildWarningsForNurse(User $nurse, Shift $givenShift, Shift $receivedShift): array
    {
        $weekStart = $givenShift->shift_date->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $givenShift->shift_date->copy()->endOfWeek(Carbon::SUNDAY);

        $weekShifts = Shift::query()
            ->with('shiftType')
            ->whereHas('users', function ($query) use ($nurse): void {
                $query->where('users.id', $nurse->id);
            })
            ->whereDate('shift_date', '>=', $weekStart->toDateString())
            ->whereDate('shift_date', '<=', $weekEnd->toDateString())
            ->where('id', '!=', $givenShift->id)
            ->get();

        // Add the incoming shift only when it falls inside the evaluated week window.
        if ($receivedShift->shift_date->betweenIncluded($weekStart, $weekEnd)) {
            $weekShifts->push($receivedShift);
        }

        $weekShifts = $weekShifts
            ->filter(fn (Shift $shift): bool => $shift->shiftType !== null)
            ->unique('id')
            ->values();

        $warnings = [];

        if ($this->hasRestHoursViolation($weekShifts)) {
            $warnings[] = [
                'nurse_id' => $nurse->id,
                'nurse_name' => $nurse->name,
                'type' => 'rest_hours',
                'message' => 'A troca pode violar o descanso mínimo de 11 horas entre turnos consecutivos.',
            ];
        }

        if ($this->countDaysOff($weekShifts, $weekStart, $weekEnd) < 2) {
            $warnings[] = [
                'nurse_id' => $nurse->id,
                'nurse_name' => $nurse->name,
                'type' => 'days_off',
                'message' => 'A troca pode deixar a enfermeira com menos de 2 dias de folga nessa semana.',
            ];
        }

        return $warnings;
    }

    private function hasRestHoursViolation($shifts): bool
    {
        $sortedShifts = $shifts
            ->sortBy(function (Shift $shift): string {
                $startTime = $this->normalizeTime((string) $shift->shiftType->start_time);

                return $shift->shift_date->toDateString().' '.$startTime;
            })
            ->values();

        for ($index = 1; $index < $sortedShifts->count(); $index++) {
            $previousInterval = $this->resolveShiftInterval($sortedShifts[$index - 1]);
            $currentInterval = $this->resolveShiftInterval($sortedShifts[$index]);

            $restMinutes = $previousInterval['end']->diffInMinutes($currentInterval['start'], false);

            if ($restMinutes < 11 * 60) {
                return true;
            }
        }

        return false;
    }

    private function countDaysOff($shifts, Carbon $weekStart, Carbon $weekEnd): int
    {
        $workedDays = $shifts
            ->filter(function (Shift $shift) use ($weekStart, $weekEnd): bool {
                return $shift->shift_date->betweenIncluded($weekStart, $weekEnd);
            })
            ->map(fn (Shift $shift): string => $shift->shift_date->toDateString())
            ->unique()
            ->count();

        return 7 - $workedDays;
    }

    private function resolveShiftInterval(Shift $shift): array
    {
        $shiftDate = $shift->shift_date->toDateString();
        $startTime = $this->normalizeTime((string) $shift->shiftType->start_time);
        $endTime = $this->normalizeTime((string) $shift->shiftType->end_time);

        $start = Carbon::parse($shiftDate.' '.$startTime);
        $end = Carbon::parse($shiftDate.' '.$endTime);

        // When end time is earlier/equal, the shift spans to the next day.
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
