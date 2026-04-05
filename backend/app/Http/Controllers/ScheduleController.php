<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Schedule\StoreScheduleRequest;
use App\Models\Schedule;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class ScheduleController extends Controller
{
    #[OA\Post(
        path: '/api/schedules',
        summary: 'Cria um novo horario',
        security: [['bearerAuth' => []]],
        tags: ['Schedules'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['start_date', 'end_date'],
                properties: [
                    new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2026-04-06'),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2026-04-12'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Horario criado com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Horario criado com sucesso.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 4),
                                new OA\Property(property: 'created_by', type: 'integer', example: 2),
                                new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2026-04-06'),
                                new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2026-04-12'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Sem permissão para criar horarios'),
            new OA\Response(response: 422, description: 'Payload inválido'),
        ]
    )]
    public function store(StoreScheduleRequest $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->role instanceof \BackedEnum ? $user->role->value : $user->role;

        // Only admin/head nurse can create planning periods.
        if (! in_array($role, [UserRole::Admin->value, UserRole::HeadNurse->value], true)) {
            return response()->json([
                'message' => 'Sem permissão para criar horários.',
            ], 403);
        }

        $validated = $request->validated();

        // Persist schedule and stamp the creator from the current token.
        $schedule = new Schedule();
        $schedule->created_by = $user->id;
        $schedule->start_date = $validated['start_date'];
        $schedule->end_date = $validated['end_date'];
        $schedule->save();

        return response()->json([
            'message' => 'Horario criado com sucesso.',
            'data' => [
                'id' => $schedule->id,
                'created_by' => $schedule->created_by,
                'start_date' => $schedule->start_date?->toDateString(),
                'end_date' => $schedule->end_date?->toDateString(),
            ],
        ], 201);
    }
}
