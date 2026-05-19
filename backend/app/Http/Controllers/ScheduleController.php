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

        if (!$schedule) {
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
        if (
            Schedule::whereYear('start_date', $startDate->year)
                ->whereMonth('start_date', $startDate->month)
                ->exists()
        ) {
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

        if (!$schedule) {
            return response()->json([
                'message' => 'Horário não encontrado.',
            ], 404);
        }

        if (!in_array($schedule->status, ['draft', 'revision'])) {
            return response()->json([
                'message' => 'Apenas rascunhos ou revisões podem ser removidos.',
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

        if (!$schedule) {
            return response()->json([
                'message' => 'Horário não encontrado.',
            ], 404);
        }

        if ($schedule->status !== 'draft') {
            return response()->json([
                'message' => 'O horário já foi publicado.',
            ], 422);
        }

        if (!Shift::where('schedule_id', $schedule->id)->exists()) {
            return response()->json([
                'message' => 'O horário não pode ser publicado sem turnos atribuídos.',
            ], 422);
        }

        \Carbon\Carbon::setLocale('pt');

        $validationError = $this->validateScheduleIntegrity($schedule);
        if ($validationError) {
            return $validationError;
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

        if (!$schedule) {
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

    // ------------------- EDIT SCHEDULE--------------------

    #[OA\Post(
        path: '/api/schedules/{id}/edit',
        summary: 'Inicia a edição de um horário publicado (Cria rascunho)',
        security: [['bearerAuth' => []]],
        tags: ['Schedules'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                description: 'ID do horário publicado original',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Rascunho de edição criado com sucesso.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Rascunho de edição criado com sucesso.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 5)
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 200,
                description: 'Já existe uma edição deste horário em curso.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Já existe uma edição em curso para este horário.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 5)
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Acesso proibido.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Sem permissão para editar horários.')
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'O horário não está publicado ou dados inválidos.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Apenas horários publicados podem ser editados.')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Não autenticado.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Utilizador não autenticado.')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Horário não encontrado.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Horário não encontrado.')
                    ]
                )
            ),


        ]
    )]
    public function startEdit(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $role = $user->role instanceof \BackedEnum ? $user->role->value : $user->role;
        if ($role !== UserRole::HeadNurse->value) {
            return response()->json([
                'message' => 'Sem permissão para editar horários.',
            ], 403);
        }

        return DB::transaction(function () use ($id) {
            $original = Schedule::with('shifts.users')->find($id);

            if (!$original) {
                return response()->json(['message' => 'Horário não encontrado.'], 404);
            }

            if ($original->status !== 'published') {
                return response()->json(['message' => 'Apenas horários publicados podem ser editados.'], 422);
            }

            // Verifies if there is already an editing in progress
            $existingRevision = Schedule::withTrashed()
                ->where('parent_id', $id)
                ->where('status', 'revision')
                ->first();
            if ($existingRevision) {
                if (!$existingRevision->trashed()) {
                    return response()->json(['message' => 'Já existe uma edição em curso para este horário.', 'data' => ['id' => $existingRevision->id]], 200);
                }

                //if it is an old edit (soft-deleted), we permanently delete it 
                // to not conflict with the "unique" rule of the database and allow us to create a new one
                $existingRevision->shifts()->forceDelete();
                $existingRevision->forceDelete();
            }


            // 1. Clone the Schedule
            $revision = $original->replicate();
            $revision->status = 'revision';
            $revision->parent_id = $original->id;
            $revision->save();

            // 2. Clone shifts and assignments
            foreach ($original->shifts as $shift) {
                $newShift = $shift->replicate();
                $newShift->schedule_id = $revision->id;
                $newShift->save();

                // Clone the many-to-many relationship of nurses
                $newShift->users()->attach($shift->users->pluck('id'));
            }

            return response()->json([
                'message' => 'Rascunho de edição criado com sucesso.',
                'data' => ['id' => $revision->id]
            ], 201);
        });
    }


    #[OA\Post(
        path: '/api/schedules/{id}/publish-edit',
        summary: 'Publica as alterações feitas no rascunho de edição',
        security: [['bearerAuth' => []]],
        tags: ['Schedules'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                description: 'ID do rascunho',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Alterações publicadas e fundidas com sucesso.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Alterações publicadas com sucesso.'),
                        new OA\Property(property: 'changes_detected', type: 'integer', example: 3)
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Acesso proibido.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Sem permissão para publicar edições.')
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Erro de validação.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'O horário com erros de validação.')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Rascunho não encontrado.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Horário não encontrado.')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Não autenticado.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Utilizador não autenticado.')
                    ]
                )
            )
        ]
    )]
    public function publishEdit(Request $request, int $revisionId): JsonResponse
    {
        $user = $request->user();
        $role = $user->role instanceof \BackedEnum ? $user->role->value : $user->role;

        if ($role !== UserRole::HeadNurse->value) {
            return response()->json(['message' => 'Sem permissão para publicar edições.'], 403);
        }


        try {
            return DB::transaction(function () use ($revisionId) {
                $revision = Schedule::with('shifts.users')->find($revisionId);

                if (!$revision) {
                    return response()->json(['message' => 'Rascunho não encontrado.'], 404);
                }

                if ($revision->status !== 'revision' || $revision->parent_id === null) {
                    return response()->json(['message' => 'O ID fornecido não é um rascunho de edição válido.'], 422);
                }

                $original = Schedule::findOrFail($revision->parent_id);

                $validationError = $this->validateScheduleIntegrity($revision);
                if ($validationError)
                    return $validationError;

                $changesCount = $this->calculateChanges($original, $revision);

                // Clean the old shifts from the original schedule (permanent to avoid filling the DB)
                $original->shifts()->forceDelete();

                // Move the shifts from the revision to the original schedule in an atomic way
                Shift::where('schedule_id', $revision->id)->update(['schedule_id' => $original->id]);

                // Update the edit counter
                $original->edit_count += 1;
                $original->save();


                // Notification (wrapped in try-catch so that the publication doesn't fail if the email fails)
                try {
                    $original->refresh();

                    // Get the IDs of all users who have shifts in this schedule
                    $notifiableUserIds = \DB::table('user_shifts')
                        ->whereIn('shift_id', function($query) use ($original) {
                            $query->select('id')
                                  ->from('shifts')
                                  ->where('schedule_id', $original->id);
                        })
                        ->pluck('user_id')
                        ->unique()
                        ->toArray();

                    // Get the users (Nurses and Head Nurses) who belong to these IDs
                    $recipients = User::whereIn('id', $notifiableUserIds)
                        ->where(function($query) {
                            $query->where('role', 'nurse')
                                  ->orWhere('role', 'head_nurse');
                        })
                        ->get();

                    // Delete the revision draft
                    $revision->forceDelete();

                    if ($recipients->isNotEmpty()) {
                        // Send the update notification to all nurses
                        Notification::send(
                            $recipients, 
                            new \App\Notifications\ScheduleUpdatedNotification($original)
                        );
                    }
                } catch (\Exception $e) {
                    // Only log the warning if the email fails, so that the user doesn't receive a 500 error
                    \Log::warning("Failed to send update emails: " . $e->getMessage());
                }

                return response()->json([
                    'message' => 'Alterações publicadas com sucesso.',
                    'changes_detected' => $changesCount
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao publicar alterações: ' . $e->getMessage()
            ], 500);
        }
    }


    private function calculateChanges($original, $revision): int
    {
        // count how many shifts in the revision are different from the original
        return abs($revision->shifts()->count() - $original->shifts()->count()) + 1;
    }

    private function validateScheduleIntegrity(Schedule $schedule): ?JsonResponse
    {
        \Carbon\Carbon::setLocale('pt');
        if (!Shift::where('schedule_id', $schedule->id)->exists()) {
            return response()->json(['message' => 'O horário não pode ser publicado sem turnos atribuídos.'], 422);
        }

        $scheduleShifts = Shift::with(['users:id,name', 'shiftType'])->where('schedule_id', $schedule->id)->get();


        $nurseNames = User::whereIn('role', [UserRole::Nurse->value, UserRole::HeadNurse->value])
            ->where('active', true)
            ->pluck('name', 'id');

        $nurseIds = $nurseNames->keys();

        $assignedByDateAndNurse = $scheduleShifts->flatMap(function ($shift) {
            $date = $shift->shift_date->toDateString();
            return $shift->users->map(fn($nurse) => $date . '_' . $nurse->id);
        })->unique()->flip();

        $startDate = $schedule->start_date->startOfDay();
        $endDate = $schedule->end_date->startOfDay();
        
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateString = $date->toDateString();
            foreach ($nurseIds as $nurseId) {
                if (!$assignedByDateAndNurse->has($dateString . '_' . $nurseId)) {
                    return response()->json([
                        'message' => "Existem dias no horário sem turnos atribuídos a enfermeiros."
                    ], 422);
                }
            }
        }

        // Validates the minimum number of nurses per shift
        $blockingTypeIds = ShiftType::where(function ($q) {
            $q->whereRaw('LOWER(name) LIKE ?', ['%off%'])
                ->orWhereRaw('LOWER(name) LIKE ?', ['%folga%'])
                ->orWhereRaw('LOWER(name) LIKE ?', ['%holiday%'])
                ->orWhereRaw('LOWER(name) LIKE ?', ['%férias%'])
                ->orWhereRaw('LOWER(name) LIKE ?', ['%leave%'])
                ->orWhereRaw('LOWER(name) LIKE ?', ['%baixa%'])
                ->orWhereRaw('LOWER(name) LIKE ?', ['%licença%']);
        })->pluck('id')->all();

        $shifts = Shift::with('shiftType')->where('schedule_id', $schedule->id)
            ->whereNotIn('shift_type_id', $blockingTypeIds)->get()
            ->groupBy(fn($s) => $s->shift_date->toDateString() . '_' . $s->shift_type_id);

        foreach ($shifts as $group) {
            $shiftType = $group->first()->shiftType;
            if ($group->count() < ($shiftType->min_nurses ?? 0)) {
                return response()->json([
                    'message' => "O número mínimo de enfermeiros exigido para cada turno não está a ser cumprido."
                ], 422);
            }
        }

        //Dayoff validation
        $dayOffTypeIds = ShiftType::whereRaw('LOWER(name) LIKE ?', ['%off%'])
            ->orWhereRaw('LOWER(name) LIKE ?', ['%folga%'])
            ->pluck('id')
            ->all();

        // Group shifts by nurse and date 
        $nurseShifts = [];
        foreach ($scheduleShifts as $shift) {
            $dateStr = $shift->shift_date->toDateString();
            foreach ($shift->users as $user) {
                $nurseShifts[$user->id][$dateStr] = $shift;
            }
        }

        $toMinutes = function (string $time): ?int {
            $parts = explode(':', $time);
            if (count($parts) < 2) return null;
            return ((int)$parts[0] * 60) + (int)$parts[1];
        };
        $restGapAfterPreviousShift = function ($previousShiftType, $nextShiftType) use ($toMinutes): ?int {
            $prevStart = $toMinutes($previousShiftType->start_time);
            $prevEnd = $toMinutes($previousShiftType->end_time);
            $nextStart = $toMinutes($nextShiftType->start_time);
            if ($prevStart === null || $prevEnd === null || $nextStart === null) {
                return null;
            }
            $crossesMidnight = ($prevEnd <= $prevStart);
            $previousEndAbsolute = $crossesMidnight ? ($prevEnd + 24 * 60) : $prevEnd;
            return ($nextStart + 24 * 60) - $previousEndAbsolute;
        };

        // Filter only the shift IDs corresponding to morning, afternoon and night
        $activeTypeIds = ShiftType::whereIn('name', ['morning', 'afternoon', 'night'])->pluck('id')->all();

        foreach ($nurseIds as $nurseId) {
            $shiftsOfNurse = $nurseShifts[$nurseId] ?? [];
            for ($date = $startDate->copy(); $date->lt($endDate); $date->addDay()) {
                $d1Str = $date->toDateString();
                $d2Str = $date->copy()->addDay()->toDateString();
                $shift1 = $shiftsOfNurse[$d1Str] ?? null;
                $shift2 = $shiftsOfNurse[$d2Str] ?? null;
                if ($shift1 && $shift2) {
                    if (!in_array($shift1->shift_type_id, $activeTypeIds) || !in_array($shift2->shift_type_id, $activeTypeIds)) {
                        continue;
                    }
                    $gap = $restGapAfterPreviousShift($shift1->shiftType, $shift2->shiftType);
                    if ($gap !== null && $gap < 11 * 60) {
                        return response()->json([
                            'message' => "Existem turnos com menos de 11h de diferença."
                        ], 422);
                    }
                }
            }
        }

        // Defines the start of the first full week and the end of the last full week
        $extendedStart = $startDate->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
        $extendedEnd = $endDate->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);

        // Loads all shifts of active nurses that fall within that extended interval
        $allShifts = Shift::with(['users:id,name', 'shiftType'])
            ->whereBetween('shift_date', [$extendedStart->toDateString(), $extendedEnd->toDateString()])
            ->where(function ($query) use ($schedule) {
                // Loads the shifts belonging to the current schedule/revision
                $query->where('schedule_id', $schedule->id)
                      // Loads shifts from other already PUBLISHED schedules, 
                      // but strictly outside the date range of the current schedule
                      ->orWhere(function ($q) use ($schedule) {
                          $q->whereHas('schedule', function ($sq) {
                              $sq->where('status', 'published');
                          })
                          ->where(function ($dq) use ($schedule) {
                              $dq->where('shift_date', '<', $schedule->start_date->toDateString())
                                 ->orWhere('shift_date', '>', $schedule->end_date->toDateString());
                          });
                      });
            })
            ->get();
        $extendedNurseShifts = [];
        foreach ($allShifts as $shift) {
            $dateStr = $shift->shift_date->toDateString();
            foreach ($shift->users as $user) {
                $extendedNurseShifts[$user->id][$dateStr] = $shift;
            }
        }

        // Divides the extended interval into actual weeks from Monday to Sunday
        $weeks = [];
        for ($date = $extendedStart->copy(); $date->lte($extendedEnd); $date->addWeek()) {
            $weeks[] = [
                'start' => $date->copy(),
                'end' => $date->copy()->endOfWeek(\Carbon\Carbon::SUNDAY),
            ];
        }
        foreach ($nurseIds as $nurseId) {
            $shiftsOfNurse = $extendedNurseShifts[$nurseId] ?? [];

            // Validation of not having more than 2 days off in a row
            $dayOffDates = [];
            foreach ($shiftsOfNurse as $dStr => $shift) {
                if (in_array($shift->shift_type_id, $dayOffTypeIds)) {
                    $dayOffDates[] = $dStr;
                }
            }
            sort($dayOffDates);
            $consecutive = 1;
            for ($i = 1; $i < count($dayOffDates); $i++) {
                $prevDate = \Carbon\Carbon::parse($dayOffDates[$i - 1]);
                $currDate = \Carbon\Carbon::parse($dayOffDates[$i]);
                
                // If the current date is exactly 1 day after the previous one
                if ($prevDate->copy()->addDay()->isSameDay($currDate)) {
                    $consecutive++;
                    if ($consecutive > 2) {
                        return response()->json([
                            'message' => "Não pode ter mais de 2 dias de folga seguidos",
                            'nurse_id' => $nurseId,
                            'invalid_dates' => array_slice($dayOffDates, $i - ($consecutive - 1), $consecutive)
                        ], 422);
                    }
                } else {
                    $consecutive = 1; // Resets the counter if there is a break
                }
            }
            
            // Validation of exactly 2 days off per week
            foreach ($weeks as $week) {
                $dayOffCount = 0;
                $hasAllDays = true;
                $hasSpecialAbsence = false;
                $weekDayOffDates = []; // Save the dates of the days off of this week

                for ($d = $week['start']->copy(); $d->lte($week['end']); $d->addDay()) {
                    $dStr = $d->toDateString();
                    $shift = $shiftsOfNurse[$dStr] ?? null;
                    
                    if (!$shift) {
                        // If we don't have the day mapped in the database (ex: days of the previous month not seeded),
                        // we cannot validate this week rigorously.
                        $hasAllDays = false;
                        break;
                    }
                    
                    if (in_array($shift->shift_type_id, $dayOffTypeIds)) {
                        $dayOffCount++;
                        $weekDayOffDates[] = $dStr;
                    } elseif (in_array($shift->shift_type_id, $blockingTypeIds)) {
                        // If the nurse has Vacations, Sick Leave or Parental Leave this week,
                        // it is a special absence week. We do not apply the rule of 2 days off scale.
                        $hasSpecialAbsence = true;
                    }
                }
                // Only validates if we have the 7 days mapped and no special absence (vacations, sick leave, etc)
                if ($hasAllDays && !$hasSpecialAbsence && $dayOffCount !== 2) {
                    return response()->json([
                        'message' => "Obrigatorio 2 dias de folga por semana",
                        'nurse_id' => $nurseId,
                        'invalid_dates' => $weekDayOffDates
                    ], 422);
                }
            }
        }

        return null; // Everything is ok
    }


        #[OA\Get(
        path: '/api/schedules/weekly',
        summary: 'Obtém o horário semanal ativo (pessoal ou global) de enfermeiros',
        security: [['bearerAuth' => []]],
        tags: ['Schedules'],
        parameters: [
            new OA\Parameter(
                name: 'date',
                in: 'query',
                description: 'Qualquer data dentro da semana que se quer visualizar.',
                required: false,
                schema: new OA\Schema(type: 'string', format: 'date'),
                example: '2026-05-18'
            ),
            new OA\Parameter(
                name: 'view',
                in: 'query',
                description: 'Tipo de visualização: "pessoal" (apenas o próprio horário) ou "global" (horário de toda a equipa).',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['personal', 'global']),
                example: 'personal'
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Dados do horário semanal obtidos com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2026-05-18'),
                                new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2026-05-24'),
                                new OA\Property(property: 'view', type: 'string', example: 'personal'),
                                new OA\Property(
                                    property: 'shifts',
                                    type: 'array',
                                    items: new OA\Items(
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'date', type: 'string', format: 'date', example: '2026-05-20'),
                                            new OA\Property(
                                                property: 'shift_type',
                                                type: 'object',
                                                properties: [
                                                    new OA\Property(property: 'name', type: 'string', example: 'Manhã'),
                                                    new OA\Property(property: 'start_time', type: 'string', example: '08:00:00'),
                                                    new OA\Property(property: 'end_time', type: 'string', example: '16:00:00'),
                                                ]
                                            ),
                                            new OA\Property(
                                                property: 'users',
                                                type: 'array',
                                                items: new OA\Items(
                                                    type: 'object',
                                                    properties: [
                                                        new OA\Property(property: 'name', type: 'string', example: 'Helena Coelho'),
                                                        new OA\Property(property: 'role', type: 'string', example: 'nurse'),
                                                    ]
                                                )
                                            ),
                                        ]
                                    )
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 401, 
                description: 'Não autenticado',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Utilizador não autenticado.')
                    ]
                )
            ),
            new OA\Response(
                response: 403, 
                description: 'Sem permissão para esta função',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Sem permissão para aceder a esta funcionalidade.')
                    ]
                )
            ),
            new OA\Response(
                response: 422, 
                description: 'Formato de data ou visualização inválidos',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Erro de validação nas informações fornecidas.')
                    ]
                )
            )
        ]
    )]
    public function weekly(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->role instanceof \BackedEnum ? $user->role->value : $user->role;

        // Allows access to nurses and head nurses
        if (!in_array($role, [UserRole::Nurse->value, UserRole::HeadNurse->value])) {
            return response()->json([
                'message' => 'Sem permissão para visualizar o horário semanal.',
            ], 403);
        }

        // Basic validation of query parameters
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'view' => ['nullable', 'string', 'in:personal,global'],
        ], [
            'date.date_format' => 'O campo date deve estar no formato YYYY-MM-DD.',
            'view.in' => 'O campo view deve ser "personal" ou "global".',
        ]);

        $view = $validated['view'] ?? 'personal';
        
        // Defines the reference date (default: today)
        $baseDate = isset($validated['date']) ? Carbon::parse($validated['date']) : Carbon::now();
        
        // Defines the week limits (Monday to Sunday, aligned with the European calendar)
        $startDate = $baseDate->copy()->startOfWeek(Carbon::MONDAY);
        $endDate = $baseDate->copy()->endOfWeek(Carbon::SUNDAY);

        // Build the query to search for shifts in the desired week
        $shiftsQuery = Shift::with(['shiftType', 'users:id,name,role'])
            ->whereBetween('shift_date', [
                $startDate->toDateString(), 
                $endDate->toDateString()
            ])
            ->whereHas('schedule', function ($q) {
                // Only published and active schedules are visible
                $q->where('status', 'published');
            });

        // If it's a personal view, filter only shifts where the authenticated user is scheduled
        if ($view === 'personal') {
            $shiftsQuery->whereHas('users', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }

        // Retrieves and formats data for the frontend
        $shifts = $shiftsQuery->get()->map(function (Shift $shift): array {
            return [
                'id' => $shift->id,
                'date' => $shift->shift_date?->toDateString(),
                'shift_type' => $shift->shiftType ? [
                    'id' => $shift->shiftType->id,
                    'name' => $shift->shiftType->name,
                    'color' => $shift->shiftType->color,
                    'start_time' => $shift->shiftType->start_time,
                    'end_time' => $shift->shiftType->end_time,
                ] : null,
                'users' => $shift->users->map(fn(User $u): array => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'role' => $u->role instanceof \BackedEnum ? $u->role->value : $u->role,
                ])->values()->all(),
            ];
        });

        return response()->json([
            'data' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'view' => $view,
                'shifts' => $shifts,
            ],
        ]);
    }



}



