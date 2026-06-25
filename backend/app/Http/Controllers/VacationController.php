<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Vacation\StoreVacationRequest;
use App\Http\Requests\Vacation\UpdateVacationRequest;
use App\Models\Vacation;
use App\Models\VacationReplacedShift;
use App\Models\Shift;
use App\Models\ShiftType;
use App\Models\User;
use App\Notifications\VacationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class VacationController extends Controller
{
    #[OA\Get(
        path: '/api/vacations',
        summary: 'Listar todas as férias',
        tags: ['Vacations'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de férias devolvida com sucesso.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'user_id', type: 'integer', example: 4),
                                    new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2026-06-26'),
                                    new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2026-07-02'),
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(
                response: 403,
                description: 'Acesso proibido.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Apenas administradores podem realizar esta ação.'),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        if ($request->user()->role !== UserRole::Admin) {
            return response()->json(['message' => 'Apenas administradores podem aceder a esta funcionalidade.'], 403);
        }

        $vacations = Vacation::with('user')
            ->orderBy('start_date', 'desc')
            ->get();

        return response()->json([
            'data' => $vacations,
        ]);
    }

    #[OA\Post(
        path: '/api/vacations',
        summary: 'Atribuir um novo período de férias',
        tags: ['Vacations'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_id', 'start_date', 'end_date'],
                properties: [
                    new OA\Property(property: 'user_id', type: 'integer', example: 4),
                    new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2026-06-26'),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2026-07-02'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Férias criadas com sucesso.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Férias atribuídas com sucesso e horário atualizado.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'user_id', type: 'integer', example: 4),
                                new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2026-06-26'),
                                new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2026-07-02'),
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(
                response: 403,
                description: 'Acesso proibido.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Apenas administradores podem realizar esta ação.'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Erro de validação ou sobreposição.'),
        ]
    )]
    public function store(StoreVacationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // 1. Validar sobreposição de férias
        $this->checkOverlap($validated['user_id'], $validated['start_date'], $validated['end_date']);

        // 2. Garantir que o tipo de turno "ferias" existe
        $vacationType = $this->getOrCreateVacationType();

        $vacation = DB::transaction(function () use ($validated, $vacationType) {
            $vac = Vacation::create([
                'user_id' => $validated['user_id'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'reason' => $validated['reason'] ?? null,
            ]);

            // 3. Substituir os turnos existentes pelo turno de férias no horário correspondente
            $this->applyVacation($vac, $vacationType);

            return $vac;
        });

        // 4. Enviar notificações por email e painel
        $this->notifyUsers($vacation, 'created');

        return response()->json([
            'message' => 'Férias atribuídas com sucesso e horário atualizado.',
            'data' => $vacation->load('user'),
        ], 201);
    }

    #[OA\Get(
        path: '/api/vacations/{id}',
        summary: 'Obter detalhes de um período de férias específico',
        tags: ['Vacations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID das férias',
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detalhes obtidos com sucesso.'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(
                response: 403,
                description: 'Acesso proibido.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Apenas administradores podem realizar esta ação.'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Férias não encontradas.'),
        ]
    )]
    public function show(Request $request, int $id): JsonResponse
    {
        if ($request->user()->role !== UserRole::Admin) {
            return response()->json(['message' => 'Apenas administradores podem aceder a esta funcionalidade.'], 403);
        }

        $vacation = Vacation::with('user')->find($id);

        if (!$vacation) {
            return response()->json(['message' => 'Registo de férias não encontrado.'], 404);
        }

        return response()->json([
            'data' => $vacation,
        ]);
    }

    #[OA\Patch(
        path: '/api/vacations/{id}',
        summary: 'Editar um período de férias existente',
        tags: ['Vacations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_id', 'start_date', 'end_date'],
                properties: [
                    new OA\Property(property: 'user_id', type: 'integer', example: 4),
                    new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2026-06-26'),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2026-07-03'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Férias atualizadas com sucesso.'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(
                response: 403,
                description: 'Acesso proibido.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Apenas administradores podem realizar esta ação.'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Férias não encontradas.'),
            new OA\Response(response: 422, description: 'Erro de validação ou sobreposição.'),
        ]
    )]
    public function update(UpdateVacationRequest $request, int $id): JsonResponse
    {
        $vacation = Vacation::find($id);

        if (!$vacation) {
            return response()->json(['message' => 'Registo de férias não encontrado.'], 404);
        }

        $validated = $request->validated();

        // 1. Validar sobreposição excluindo a atual
        $this->checkOverlap($validated['user_id'], $validated['start_date'], $validated['end_date'], $id);

        $vacationType = $this->getOrCreateVacationType();

        DB::transaction(function () use ($vacation, $validated, $vacationType) {
            // 2. Repor os turnos originais do período anterior
            $this->restoreReplacedShifts($vacation);

            // 3. Atualizar datas
            $vacation->update([
                'user_id' => $validated['user_id'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'reason' => $validated['reason'] ?? null,
            ]);

            // 4. Aplicar o turno de férias nas novas datas
            $this->applyVacation($vacation, $vacationType);
        });

        // 5. Enviar notificações sobre alteração
        $this->notifyUsers($vacation, 'updated');

        return response()->json([
            'message' => 'Férias atualizadas com sucesso e horário ajustado.',
            'data' => $vacation->load('user'),
        ]);
    }

    #[OA\Delete(
        path: '/api/vacations/{id}',
        summary: 'Eliminar um registo de férias',
        tags: ['Vacations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Férias eliminadas com sucesso e turnos originais repostos.'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(
                response: 403,
                description: 'Acesso proibido.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Apenas administradores podem realizar esta ação.'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Férias não encontradas.'),
        ]
    )]
    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($request->user()->role !== UserRole::Admin) {
            return response()->json(['message' => 'Apenas administradores podem realizar esta ação.'], 403);
        }

        $vacation = Vacation::find($id);

        if (!$vacation) {
            return response()->json(['message' => 'Registo de férias não encontrado.'], 404);
        }

        // Notificar o cancelamento antes de apagar
        $this->notifyUsers($vacation, 'deleted');

        DB::transaction(function () use ($vacation) {
            // 1. Repor todos os turnos originais
            $this->restoreReplacedShifts($vacation);

            // 2. Apagar logicamente o registo de férias
            $vacation->delete();
        });

        return response()->json([
            'message' => 'Férias eliminadas com sucesso e turnos originais repostos no horário.',
        ]);
    }

    // --- MÉTODOS AUXILIARES ---

    private function checkOverlap(int $userId, string $startDate, string $endDate, ?int $ignoreId = null): void
    {
        $overlap = Vacation::where('user_id', $userId)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate])
                  ->orWhere(function ($sub) use ($startDate, $endDate) {
                      $sub->where('start_date', '<=', $startDate)
                          ->where('end_date', '>=', $endDate);
                  });
            })
            ->when($ignoreId, function ($q) use ($ignoreId) {
                $q->where('id', '!=', $ignoreId);
            })
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'start_date' => ['Este enfermeiro já tem férias marcadas no período selecionado.'],
            ]);
        }
    }

    private function getOrCreateVacationType(): ShiftType
    {
        $type = ShiftType::whereRaw('LOWER(name) = ?', ['ferias'])
            ->orWhereRaw('LOWER(name) = ?', ['férias'])
            ->orWhereRaw('LOWER(name) = ?', ['vacation'])
            ->first();

        if (!$type) {
            $type = ShiftType::create([
                'name' => 'ferias',
                'color' => '#fff3cd',
                'start_time' => '00:00:00',
                'end_time' => '00:00:00',
                'min_nurses' => 0,
            ]);
        }

        return $type;
    }

    private function applyVacation(Vacation $vacation, ShiftType $vacationType): void
    {
        $userId = $vacation->user_id;
        $startDate = Carbon::parse($vacation->start_date);
        $endDate = Carbon::parse($vacation->end_date);

        $currentDate = $startDate->copy();
        while ($currentDate->lessThanOrEqualTo($endDate)) {
            $dateStr = $currentDate->toDateString();

            // Buscar todos os turnos deste enfermeiro nesta data
            $shifts = Shift::where('shift_date', $dateStr)
                ->whereHas('users', function ($query) use ($userId) {
                    $query->where('users.id', $userId);
                })
                ->get();

            foreach ($shifts as $shift) {
                $userIds = $shift->users()->pluck('users.id')->toArray();
                $isShared = count($userIds) > 1;

                if ($isShared) {
                    // Turno partilhado: Desassocia o enfermeiro deste turno original
                    $shift->users()->detach($userId);

                    // Cria um novo turno exclusivo de férias para o enfermeiro
                    $newShift = Shift::create([
                        'schedule_id' => $shift->schedule_id,
                        'shift_type_id' => $vacationType->id,
                        'shift_date' => $dateStr,
                    ]);
                    $newShift->users()->attach($userId);

                    VacationReplacedShift::create([
                        'vacation_id' => $vacation->id,
                        'shift_date' => $dateStr,
                        'schedule_id' => $shift->schedule_id,
                        'original_shift_id' => $shift->id,
                        'original_shift_type_id' => $shift->shift_type_id,
                        'temp_shift_id' => $newShift->id,
                        'was_shared' => true,
                    ]);
                } else {
                    // Turno exclusivo: Atualiza o tipo do turno diretamente para "ferias"
                    $originalShiftTypeId = $shift->shift_type_id;
                    $shift->update([
                        'shift_type_id' => $vacationType->id,
                    ]);

                    VacationReplacedShift::create([
                        'vacation_id' => $vacation->id,
                        'shift_date' => $dateStr,
                        'schedule_id' => $shift->schedule_id,
                        'original_shift_id' => $shift->id,
                        'original_shift_type_id' => $originalShiftTypeId,
                        'temp_shift_id' => null,
                        'was_shared' => false,
                    ]);
                }
            }

            $currentDate->addDay();
        }
    }

    private function restoreReplacedShifts(Vacation $vacation): void
    {
        $userId = $vacation->user_id;
        $replacedShifts = VacationReplacedShift::where('vacation_id', $vacation->id)->get();

        foreach ($replacedShifts as $replaced) {
            if ($replaced->was_shared) {
                // Eliminar o turno temporário de férias criado
                if ($replaced->temp_shift_id) {
                    $tempShift = Shift::find($replaced->temp_shift_id);
                    if ($tempShift) {
                        $tempShift->users()->detach($userId);
                        $tempShift->forceDelete();
                    }
                }

                // Devolver o enfermeiro ao turno partilhado original
                if ($replaced->original_shift_id) {
                    $originalShift = Shift::find($replaced->original_shift_id);
                    if ($originalShift) {
                        if (!$originalShift->users()->where('users.id', $userId)->exists()) {
                            $originalShift->users()->attach($userId);
                        }
                    }
                }
            } else {
                // Repor o tipo de turno original
                if ($replaced->original_shift_id) {
                    $originalShift = Shift::find($replaced->original_shift_id);
                    if ($originalShift) {
                        $originalShift->update([
                            'shift_type_id' => $replaced->original_shift_type_id,
                        ]);
                    }
                }
            }

            $replaced->delete();
        }
    }

    private function notifyUsers(Vacation $vacation, string $action): void
    {
        $nurse = $vacation->user;
        if (!$nurse) {
            $nurse = User::find($vacation->user_id);
        }

        $days = Carbon::parse($vacation->start_date)->diffInDays(Carbon::parse($vacation->end_date)) + 1;
        $startDateFormatted = Carbon::parse($vacation->start_date)->format('d/m/Y');
        $endDateFormatted = Carbon::parse($vacation->end_date)->format('d/m/Y');

        // Notificar o enfermeiro
        $nurse->notify(new VacationNotification(
            $vacation,
            $action,
            $nurse->name,
            $days,
            $startDateFormatted,
            $endDateFormatted
        ));
    }
}
