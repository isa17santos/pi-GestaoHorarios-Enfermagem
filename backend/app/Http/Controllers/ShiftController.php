<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Shift\StoreShiftRequest;
use App\Http\Requests\Shift\UpdateShiftRequest;
use App\Http\Resources\ShiftResource;
use App\Models\Schedule;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class ShiftController extends Controller
{
    #[OA\Get(
        path: '/api/shifts',
        summary: 'List shifts with optional filters',
        security: [['bearerAuth' => []]],
        tags: ['Shifts'],
        parameters: [
            new OA\Parameter(
                name: 'mine',
                in: 'query',
                required: false,
                description: 'Filter shifts assigned to the authenticated user',
                schema: new OA\Schema(type: 'boolean')
            ),
            new OA\Parameter(
                name: 'user_id',
                in: 'query',
                required: false,
                description: 'Filter shifts assigned to a specific user',
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'from',
                in: 'query',
                required: false,
                description: 'Filter shifts with shift_date >= from (YYYY-MM-DD)',
                schema: new OA\Schema(type: 'string', format: 'date')
            ),
            new OA\Parameter(
                name: 'future',
                in: 'query',
                required: false,
                description: 'Filter shifts with shift_date > today',
                schema: new OA\Schema(type: 'boolean')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful response with data array of shifts',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(type: 'object')
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Shift::query()
            ->with(['shiftType', 'users']);

        // Apply assignment-based filters from query params.
        if ($request->filled('user_id')) {
            // Keep only shifts assigned to the explicit user passed in the query.
            $query->whereHas('users', function ($q) use ($request): void {
                $q->where('users.id', (int) $request->query('user_id'));
            });
        }

        if ($request->boolean('mine')) {
            // Restrict results to shifts where the authenticated user is assigned.
            $query->whereHas('users', function ($q) use ($request): void {
                $q->where('users.id', $request->user()->id);
            });
        }

        if ($request->boolean('exclude_mine')) {
            // Remove shifts that already include the authenticated user.
            $query->whereDoesntHave('users', function ($q) use ($request): void {
                $q->where('users.id', $request->user()->id);
            });
        }

        // Exclude shifts assigned exclusively to head nurses.
        $query->whereHas('users', function ($q): void {
            $q->where('users.role', '!=', UserRole::HeadNurse->value);
        });

        if ($request->filled('from')) {
            // Return only shifts on or after the provided start date.
            $query->whereDate('shift_date', '>=', (string) $request->query('from'));
        }

        if ($request->boolean('future')) {
            // Keep only upcoming shifts relative to today's date.
            $query->whereDate('shift_date', '>', Carbon::today()->toDateString());
        }

        $shifts = $query
            ->orderBy('shift_date')
            ->get();

        return ShiftResource::collection($shifts);
    }

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
            new OA\Response(response: 422, description: 'Payload inválido ou tentativa de adicionar turno a um horário já publicado'),
        ]
    )]
    public function store(StoreShiftRequest $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->role instanceof \BackedEnum ? $user->role->value : $user->role;

        // Restrict shift creation to management roles.
        if (!in_array($role, [UserRole::Admin->value, UserRole::HeadNurse->value], true)) {
            return response()->json([
                'message' => 'Sem permissão para criar turnos.',
            ], 403);
        }

        $validated = $request->validated();
        $schedule = Schedule::query()->findOrFail($validated['schedule_id']);

        if (!in_array($schedule->status, ['draft', 'revision'])) {
            return response()->json(['message' => 'Não pode adicionar turnos a um horário já publicado. Crie uma edição primeiro.'], 422);
        }

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

        if (!in_array($role, [UserRole::Admin->value, UserRole::HeadNurse->value], true)) {
            return response()->json([
                'message' => 'Sem permissão para atualizar turnos.',
            ], 403);
        }

        $shift = Shift::query()->with('users')->find($id);

        if (!$shift) {
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

        if (!in_array($schedule->status, ['draft', 'revision'])) {
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

    #[OA\Delete(
        path: '/api/shifts/{id}',
        summary: 'Elimina um turno individual ou remove um enfermeiro do mesmo',
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
            new OA\Parameter(
                name: 'nurse_id',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer'),
                description: 'ID do enfermeiro a desassociar do turno',
                example: 4
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Turno ou enfermeiro removido com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Operação concluída com sucesso.'),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Não autenticado',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Utilizador não autenticado.'),
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Sem permissão para eliminar turnos',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Sem permissão para eliminar turnos.'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Turno não encontrado',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Turno não encontrado.'),
                    ]
                )
            ),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $user = request()->user();
        $role = $user->role instanceof \BackedEnum ? $user->role->value : $user->role;

        if (!in_array($role, [UserRole::Admin->value, UserRole::HeadNurse->value], true)) {
            return response()->json([
                'message' => 'Sem permissão para eliminar turnos.',
            ], 403);
        }

        $shift = Shift::find($id);

        if (!$shift) {
            return response()->json([
                'message' => 'Turno não encontrado.',
            ], 404);
        }

        // Verify if a nurse_id was passed to remove only that nurse
        $nurseId = request()->query('nurse_id');

        if ($nurseId) {
            $shift->users()->detach($nurseId);

            // If the shift no longer has anyone associated, delete the shift completely
            if ($shift->users()->count() === 0) {
                $shift->delete();
            }

            return response()->json([
                'message' => 'Enfermeiro removido do turno com sucesso.',
            ]);
        }

        // If nurse_id is not passed, delete the shift completely
        $shift->delete();

        return response()->json([
            'message' => 'Turno removido com sucesso.',
        ]);
    }

}
