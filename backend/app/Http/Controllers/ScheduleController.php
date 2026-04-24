<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\ShiftType;
use App\Models\User;
use App\Notifications\SchedulePublishedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
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

    #[OA\Delete(
        path: '/api/schedules/{id}',
        summary: 'Remove um horário em draft',
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
                description: 'Horário removido com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Horário removido com sucesso.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Sem permissão'),
            new OA\Response(response: 404, description: 'Horário não encontrado'),
            new OA\Response(response: 422, description: 'Apenas horários em draft podem ser removidos'),
        ]
    )]
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $role = $user->role instanceof \BackedEnum ? $user->role->value : $user->role;

        if ($role !== UserRole::HeadNurse->value) {
            return response()->json([
                'message' => 'Sem permissão para remover horários.',
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
                'message' => 'Apenas horários em draft podem ser removidos.',
            ], 422);
        }

        DB::transaction(function () use ($schedule): void {
            $schedule->shifts()->delete();
            $schedule->delete();
        });

        return response()->json([
            'message' => 'Horário removido com sucesso.',
        ]);
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

        \Carbon\Carbon::setLocale('pt');

        $scheduleShifts = Shift::with('users:id,name')
            ->where('schedule_id', $schedule->id)
            ->get();

        $nurseIds = $scheduleShifts
            ->flatMap(fn($shift) => $shift->users->pluck('id'))
            ->unique()
            ->values();

        $nurseNames = User::whereIn('id', $nurseIds)
            ->where('role', UserRole::Nurse->value)
            ->pluck('name', 'id');

        $nurseIds = $nurseNames->keys()->values();

        $assignedByDateAndNurse = $scheduleShifts
            ->flatMap(function ($shift) {
                $date = $shift->shift_date->toDateString();
                return $shift->users->map(fn($nurse) => $date . '_' . $nurse->id);
            })
            ->unique()
            ->flip();

        $startDate = $schedule->start_date?->copy()->startOfDay();
        $endDate = $schedule->end_date?->copy()->startOfDay();

        if (! $startDate || ! $endDate) {
            return response()->json([
                'message' => 'O horário tem um intervalo de datas inválido.',
            ], 422);
        }

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateString = $date->toDateString();

            foreach ($nurseIds as $nurseId) {
                $assignmentKey = $dateString . '_' . $nurseId;

                if (! $assignedByDateAndNurse->has($assignmentKey)) {
                    $formatted = $date->translatedFormat('d \\d\\e F \\d\\e Y');
                    $nurseName = $nurseNames->get($nurseId, 'Enfermeiro');

                    return response()->json([
                        'message' => "O enfermeiro {$nurseName} não tem turno atribuído no dia {$formatted}.",
                    ], 422);
                }
            }
        }

        $blockingTypeIds = ShiftType::whereIn('name', ['dayOff', 'holidays', 'sick leave', 'parental leave'])
            ->pluck('id')
            ->all();

        $shifts = Shift::with('shiftType')
            ->where('schedule_id', $schedule->id)
            ->whereNotIn('shift_type_id', $blockingTypeIds)
            ->get()
            ->groupBy(fn($s) => $s->shift_date->toDateString() . '_' . $s->shift_type_id);

        $shiftTypeNames = [
            'morning'        => 'Manhã',
            'afternoon'      => 'Tarde',
            'night'          => 'Noite',
            'dayOff'         => 'Folga',
            'holidays'       => 'Férias',
            'sick leave'     => 'Baixa Médica',
            'parental leave' => 'Licença Parental',
        ];

        foreach ($shifts as $group) {
            $shiftType = $group->first()->shiftType;
            $minNurses = (int) ($shiftType?->min_nurses ?? 0);

            if ($group->count() < $minNurses) {
                $shiftDate = $group->first()->shift_date->translatedFormat('d \d\e F \d\e Y');
                $rawName = $shiftType?->name;
                $rawName = $rawName instanceof \BackedEnum ? $rawName->value : (string) $rawName;
                $translatedName = $shiftTypeNames[$rawName] ?? $rawName;

                return response()->json([
                    'message' => "O dia {$shiftDate} ({$translatedName}) tem menos do que {$minNurses} enfermeiros atribuídos.",
                ], 422);
            }
        }

        $schedule->status = 'published';
        $schedule->save();

        $nurseIds = Shift::where('schedule_id', $schedule->id)
            ->join('user_shifts', 'shifts.id', '=', 'user_shifts.shift_id')
            ->pluck('user_shifts.user_id')
            ->unique();

        $nurses = User::whereIn('id', $nurseIds)->get();

        Notification::send($nurses, new SchedulePublishedNotification($schedule));

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
