<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Shift\StoreShiftRequest;
use App\Http\Requests\Shift\UpdateShiftRequest;
use App\Models\Schedule;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
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
        $shift->users()->attach($validated['user_ids']);

        return response()->json([
            'message' => 'Turno criado com sucesso.',
            'data' => [
                'id' => $shift->id,
                'schedule_id' => $shift->schedule_id,
                'shift_type_id' => $shift->shift_type_id,
                'shift_date' => $shift->shift_date?->toDateString(),
                'user_ids' => $validated['user_ids'],
            ],
        ], 201);
    }

    #[OA\Patch(
        path: '/api/shifts/{id}',
        summary: 'Atualiza um turno existente',
        security: [['bearerAuth' => []]],
        tags: ['Shifts'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
                example: 1
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['schedule_id', 'shift_type_id', 'shift_date', 'user_ids'],
                properties: [
                    new OA\Property(property: 'schedule_id', type: 'integer', example: 1),
                    new OA\Property(property: 'shift_type_id', type: 'integer', example: 2),
                    new OA\Property(property: 'shift_date', type: 'string', format: 'date', example: '2026-04-07'),
                    new OA\Property(
                        property: 'user_ids',
                        type: 'array',
                        items: new OA\Items(type: 'integer', example: 4)
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Turno atualizado com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Turno atualizado com sucesso.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'schedule_id', type: 'integer', example: 1),
                                new OA\Property(property: 'shift_type_id', type: 'integer', example: 2),
                                new OA\Property(property: 'shift_date', type: 'string', format: 'date', example: '2026-04-07'),
                                new OA\Property(
                                    property: 'user_ids',
                                    type: 'array',
                                    items: new OA\Items(type: 'integer', example: 4)
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Sem permissão para atualizar turnos'),
            new OA\Response(response: 404, description: 'Turno não encontrado'),
            new OA\Response(response: 422, description: 'Payload inválido, horário publicado, ou conflito de alocação'),
        ]
    )]
    public function update(UpdateShiftRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $role = $user->role instanceof \BackedEnum ? $user->role->value : $user->role;

        if (! in_array($role, [UserRole::Admin->value, UserRole::HeadNurse->value], true)) {
            return response()->json([
                'message' => 'Sem permissão para atualizar turnos.',
            ], 403);
        }

        $shift = Shift::query()->with('users')->find($id);

        if (! $shift) {
            return response()->json([
                'message' => 'Turno não encontrado.',
            ], 404);
        }

        $validated = $request->validated();
        $schedule = Schedule::query()->findOrFail($validated['schedule_id']);

        if ($shift->schedule_id !== (int) $validated['schedule_id']) {
            throw ValidationException::withMessages([
                'schedule_id' => ['O turno não pertence ao schedule_id enviado.'],
            ]);
        }

        if ($schedule->status !== 'draft') {
            return response()->json([
                'message' => 'Não é possível atualizar turnos de um horário publicado.',
            ], 422);
        }

        if (
            $validated['shift_date'] < $schedule->start_date->toDateString()
            || $validated['shift_date'] > $schedule->end_date->toDateString()
        ) {
            throw ValidationException::withMessages([
                'shift_date' => ['A data do turno deve estar dentro do intervalo do horário.'],
            ]);
        }

        $hasConflict = Shift::query()
            ->where('schedule_id', $validated['schedule_id'])
            ->whereDate('shift_date', $validated['shift_date'])
            ->where('id', '!=', $shift->id)
            ->whereHas('users', function ($query) use ($validated): void {
                $query->whereIn('users.id', $validated['user_ids']);
            })
            ->exists();

        if ($hasConflict) {
            throw ValidationException::withMessages([
                'user_ids' => ['Já existe um turno para este(s) enfermeiro(s) nesta data e horário.'],
            ]);
        }

        DB::transaction(function () use ($shift, $validated): void {
            $shift->update([
                'schedule_id' => $validated['schedule_id'],
                'shift_type_id' => $validated['shift_type_id'],
                'shift_date' => $validated['shift_date'],
            ]);

            $shift->users()->sync($validated['user_ids']);
        });

        $shift->refresh();

        return response()->json([
            'message' => 'Turno atualizado com sucesso.',
            'data' => [
                'id' => $shift->id,
                'schedule_id' => $shift->schedule_id,
                'shift_type_id' => $shift->shift_type_id,
                'shift_date' => $shift->shift_date?->toDateString(),
                'user_ids' => array_values(array_map('intval', $validated['user_ids'])),
            ],
        ]);
    }
}
