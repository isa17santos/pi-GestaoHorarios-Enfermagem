<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Shift\StoreShiftRequest;
use App\Models\Schedule;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class ShiftController extends Controller
{
    #[OA\Post(
        path: '/api/shifts',
        summary: 'Cria um turno individual',
        security: [['bearerAuth' => []]],
        tags: ['Shifts'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['schedule_id', 'shift_type_id', 'shift_date'],
                properties: [
                    new OA\Property(property: 'schedule_id', type: 'integer', example: 1),
                    new OA\Property(property: 'shift_type_id', type: 'integer', example: 1),
                    new OA\Property(property: 'shift_date', type: 'string', format: 'date', example: '2026-04-07'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Turno criado com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Turno criado com sucesso.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'schedule_id', type: 'integer', example: 1),
                                new OA\Property(property: 'shift_type_id', type: 'integer', example: 1),
                                new OA\Property(property: 'shift_date', type: 'string', format: 'date', example: '2026-04-07'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Sem permissão para criar turnos'),
            new OA\Response(response: 422, description: 'Payload inválido'),
        ]
    )]
    public function store(StoreShiftRequest $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->role instanceof \BackedEnum ? $user->role->value : $user->role;

        // Restrict shift creation to management roles.
        if (! in_array($role, [UserRole::Admin->value, UserRole::HeadNurse->value], true)) {
            return response()->json([
                'message' => 'Sem permissão para criar turnos.',
            ], 403);
        }

        $validated = $request->validated();
        $schedule = Schedule::query()->findOrFail($validated['schedule_id']);

        // A shift must belong to a date inside the selected schedule window.
        if (
            $validated['shift_date'] < $schedule->start_date->toDateString()
            || $validated['shift_date'] > $schedule->end_date->toDateString()
        ) {
            throw ValidationException::withMessages([
                'shift_date' => ['A data do turno deve estar dentro do intervalo do horário.'],
            ]);
        }

        $shift = Shift::query()->create($validated);

        return response()->json([
            'message' => 'Turno criado com sucesso.',
            'data' => [
                'id' => $shift->id,
                'schedule_id' => $shift->schedule_id,
                'shift_type_id' => $shift->shift_type_id,
                'shift_date' => $shift->shift_date?->toDateString(),
            ],
        ], 201);
    }
}
