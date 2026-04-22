<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Schedule;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use OpenApi\Attributes as OA;

class ScheduleController extends Controller
{
    #[OA\Get(
        path: '/api/schedules',
        summary: 'Lista horários',
        security: [['bearerAuth' => []]],
        tags: ['Schedules'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Horários obtidos com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'created_by_name', type: 'string', example: 'Ana Silva'),
                                    new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2026-04-06'),
                                    new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2026-04-12'),
                                    new OA\Property(property: 'status', type: 'string', example: 'published'),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->role instanceof \BackedEnum ? $user->role->value : $user->role;

        $query = Schedule::with('creator');

        if ($role === UserRole::Nurse->value) {
            $query->where('status', 'published');
        }

        $schedules = $query->get()->map(function (Schedule $schedule): array {
            return [
                'id' => $schedule->id,
                'created_by_name' => $schedule->creator?->name,
                'start_date' => $schedule->start_date?->toDateString(),
                'end_date' => $schedule->end_date?->toDateString(),
                'status' => $schedule->status,
            ];
        });

        return response()->json([
            'data' => $schedules,
        ]);
    }

    #[OA\Get(
        path: '/api/schedules/{id}',
        summary: 'Obtém um horário por ID',
        security: [['bearerAuth' => []]],
        tags: ['Schedules'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
                example: 1
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Horário obtido com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'created_by_name', type: 'string', example: 'Ana Silva'),
                                new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2026-04-06'),
                                new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2026-04-12'),
                                new OA\Property(property: 'status', type: 'string', example: 'published'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Sem permissão'),
            new OA\Response(response: 404, description: 'Horário não encontrado'),
        ]
    )]
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $role = $user->role instanceof \BackedEnum ? $user->role->value : $user->role;

        $schedule = Schedule::with('creator')->find($id);

        if (! $schedule) {
            return response()->json([
                'message' => 'Horário não encontrado.',
            ], 404);
        }

        if ($role === UserRole::Nurse->value && $schedule->status === 'draft') {
            return response()->json([
                'message' => 'Sem permissão para visualizar este horário.',
            ], 403);
        }

        return response()->json([
            'data' => [
                'id' => $schedule->id,
                'created_by_name' => $schedule->creator?->name,
                'start_date' => $schedule->start_date?->toDateString(),
                'end_date' => $schedule->end_date?->toDateString(),
                'status' => $schedule->status,
            ],
        ]);
    }

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
                description: 'Horário criado com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Horário criado com sucesso.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 4),
                                new OA\Property(property: 'created_by_name', type: 'string', example: 'Ana Silva'),
                                new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2026-04-06'),
                                new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2026-04-12'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Sem permissão para criar horários'),
            new OA\Response(response: 422, description: 'Payload inválido ou já existe um horário para este mês'),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->role instanceof \BackedEnum ? $user->role->value : $user->role;

        // Only head nurse can create planning periods.
        if ($role !== UserRole::HeadNurse->value) {
            return response()->json([
                'message' => 'Sem permissão para criar horários.',
            ], 403);
        }

        $validated = $request->validate(
            [
                'start_date' => ['required', 'date'],
                'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            ],
            [
                'start_date.required' => 'O campo start_date é obrigatório.',
                'start_date.date' => 'O campo start_date deve ser uma data válida.',
                'end_date.required' => 'O campo end_date é obrigatório.',
                'end_date.date' => 'O campo end_date deve ser uma data válida.',
                'end_date.after_or_equal' => 'O campo end_date deve ser uma data posterior ou igual a start_date.',
            ]
        );

        // Prevent creating two schedules that start within the same calendar month.
        $startDate = Carbon::parse($validated['start_date']);
        if (Schedule::whereYear('start_date', $startDate->year)
            ->whereMonth('start_date', $startDate->month)
            ->exists()) {
            return response()->json([
                'message' => 'Já existe um horário para este mês.',
            ], 422);
        }

        // Persist schedule and stamp the creator from the current token.
        $schedule = new Schedule();
        $schedule->created_by = $user->id;
        $schedule->start_date = $validated['start_date'];
        $schedule->end_date = $validated['end_date'];
        $schedule->save();

        return response()->json([
            'message' => 'Horário criado com sucesso.',
            'data' => [
                'id' => $schedule->id,
                'created_by_name' => $schedule->creator?->name,
                'start_date' => $schedule->start_date?->toDateString(),
                'end_date' => $schedule->end_date?->toDateString(),
            ],
        ], 201);
    }

    #[OA\Patch(
        path: '/api/schedules/{id}/publish',
        summary: 'Publica um horário',
        security: [['bearerAuth' => []]],
        tags: ['Schedules'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
                example: 1
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Horário publicado com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Horário publicado com sucesso.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'status', type: 'string', example: 'published'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Sem permissão'),
            new OA\Response(response: 404, description: 'Horário não encontrado'),
            new OA\Response(response: 422, description: 'Horário já publicado, sem turnos atribuídos, ou turnos com enfermeiros insuficientes'),
        ]
    )]

    public function publish(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $role = $user->role instanceof \BackedEnum ? $user->role->value : $user->role;

        // Only head nurse can publish schedules.
        if ($role !== UserRole::HeadNurse->value) {
            return response()->json([
                'message' => 'Sem permissão para publicar horários.',
            ], 403);
        }

        $schedule = Schedule::find($id);

        if (! $schedule) {
            return response()->json([
                'message' => 'Horário não encontrado.',
            ], 404);
        }

        if ($schedule->status !== 'draft') {
            return response()->json([
                'message' => 'O horário já foi publicado.',
            ], 422);
        }

        if (! Shift::where('schedule_id', $schedule->id)->exists()) {
            return response()->json([
                'message' => 'O horário não pode ser publicado sem turnos atribuídos.',
            ], 422);
        }

        $shifts = Shift::with(['users', 'shiftType'])
            ->where('schedule_id', $schedule->id)
            ->get();

        foreach ($shifts as $shift) {
            $minNurses = (int) ($shift->shiftType?->min_nurses ?? 0);

            if ($shift->users->count() < $minNurses) {
                $shiftDate = $shift->shift_date?->toDateString() ?? (string) $shift->shift_date;
                $shiftTypeName = $shift->shiftType?->name;
                $shiftTypeName = $shiftTypeName instanceof \BackedEnum ? $shiftTypeName->value : (string) $shiftTypeName;

                return response()->json([
                    'message' => "O turno de {$shiftDate} ({$shiftTypeName}) tem menos do que {$minNurses} enfermeiros atribuídos.",
                ], 422);
            }
        }

        $schedule->status = 'published';
        $schedule->save();

        return response()->json([
            'message' => 'Horário publicado com sucesso.',
            'data' => [
                'id' => $schedule->id,
                'status' => $schedule->status,
            ],
        ], 200);
    }

    #[OA\Get(
        path: '/api/schedules/{id}/shifts',
        summary: 'Lista turnos de um horário',
        security: [['bearerAuth' => []]],
        tags: ['Schedules'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
                example: 1
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Turnos obtidos com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'shift_type_id', type: 'integer', example: 1),
                                    new OA\Property(property: 'shift_date', type: 'string', format: 'date', example: '2026-05-10'),
                                    new OA\Property(
                                        property: 'user_ids',
                                        type: 'array',
                                        items: new OA\Items(type: 'integer', example: 1)
                                    ),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 404, description: 'Horário não encontrado'),
        ]
    )]
    public function shifts(int $id): JsonResponse
    {
        $schedule = Schedule::find($id);

        if (! $schedule) {
            return response()->json([
                'message' => 'Horário não encontrado.',
            ], 404);
        }

        $shifts = Shift::with('users')->where('schedule_id', $id)->get()->map(function (Shift $shift): array {
            return [
                'id' => $shift->id,
                'shift_type_id' => $shift->shift_type_id,
                'shift_date' => $shift->shift_date?->toDateString(),
                'user_ids' => $shift->users->pluck('id')->values()->all(),
            ];
        });

        return response()->json([
            'data' => $shifts,
        ]);
    }
}
