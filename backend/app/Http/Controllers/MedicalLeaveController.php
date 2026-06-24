<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\MedicalLeave\StoreMedicalLeaveRequest;
use App\Http\Requests\MedicalLeave\UpdateMedicalLeaveRequest;
use App\Models\MedicalLeave;
use App\Models\MedicalLeaveReplacedShift;
use App\Models\Shift;
use App\Models\ShiftType;
use App\Models\User;
use App\Notifications\MedicalLeaveNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class MedicalLeaveController extends Controller
{
    #[OA\Get(
        path: '/api/medical-leaves',
        summary: 'Listar todas as baixas médicas',
        tags: ['Medical Leaves'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de baixas médicas devolvida com sucesso.',
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
                                    new OA\Property(property: 'reason', type: 'string', example: 'Cirurgia planeada'),
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

        $leaves = MedicalLeave::with('user')
            ->orderBy('start_date', 'desc')
            ->get();

        return response()->json([
            'data' => $leaves,
        ]);
    }

    #[OA\Post(
        path: '/api/medical-leaves',
        summary: 'Atribuir uma nova baixa médica',
        tags: ['Medical Leaves'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_id', 'start_date', 'end_date'],
                properties: [
                    new OA\Property(property: 'user_id', type: 'integer', example: 4),
                    new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2026-06-26'),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2026-07-02'),
                    new OA\Property(property: 'reason', type: 'string', example: 'Cirurgia planeada'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Baixa médica criada com sucesso.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Baixa médica atribuída com sucesso e horário atualizado.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'user_id', type: 'integer', example: 4),
                                new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2026-06-26'),
                                new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2026-07-02'),
                                new OA\Property(property: 'reason', type: 'string', example: 'Cirurgia planeada'),
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
            new OA\Response(
                response: 422,
                description: 'Erro de validação ou sobreposição.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Dados inválidos ou em falta.'),
                    ]
                )
            ),
        ]
    )]
    public function store(StoreMedicalLeaveRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // 1. Validar sobreposição de baixas
        $this->checkOverlap($validated['user_id'], $validated['start_date'], $validated['end_date']);

        // 2. Garantir que o tipo de turno "baixa medica" ou "sick leave" existe
        $sickLeaveType = $this->getOrCreateSickLeaveType();

        // Guardamos o resultado da transação diretamente na variável, garantindo o tipo correto para o analisador estático
        $medicalLeave = DB::transaction(function () use ($validated, $sickLeaveType) {
            $leave = MedicalLeave::create([
                'user_id' => $validated['user_id'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'reason' => $validated['reason'] ?? null,
            ]);

            // 3. Substituir os turnos existentes pela baixa médica no horário correspondente
            $this->applyMedicalLeave($leave, $sickLeaveType);

            return $leave;
        });

        // 4. Enviar notificações por email
        $this->notifyUsers($medicalLeave, 'created');

        return response()->json([
            'message' => 'Baixa médica atribuída com sucesso e horário atualizado.',
            'data' => $medicalLeave->load('user'),
        ], 201);
    }

    #[OA\Get(
        path: '/api/medical-leaves/{id}',
        summary: 'Obter detalhes de uma baixa médica específica',
        tags: ['Medical Leaves'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID da baixa médica',
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Detalhes da baixa médica obtidos com sucesso.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'user_id', type: 'integer', example: 4),
                                new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2026-06-25'),
                                new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2026-07-02'),
                                new OA\Property(property: 'reason', type: 'string', example: 'Cirurgia planeada'),
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
            new OA\Response(
                response: 404,
                description: 'Baixa médica não encontrada.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Baixa médica não encontrada.'),
                    ]
                )
            ),
        ]
    )]
    public function show(Request $request, int $id): JsonResponse
    {
        if ($request->user()->role !== UserRole::Admin) {
            return response()->json(['message' => 'Apenas administradores podem aceder a esta funcionalidade.'], 403);
        }

        $medicalLeave = MedicalLeave::with('user')->find($id);

        if (!$medicalLeave) {
            return response()->json(['message' => 'Baixa médica não encontrada.'], 404);
        }

        return response()->json([
            'data' => $medicalLeave,
        ]);
    }

    #[OA\Patch(
        path: '/api/medical-leaves/{id}',
        summary: 'Editar uma baixa médica existente',
        tags: ['Medical Leaves'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID da baixa médica a editar',
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
                    new OA\Property(property: 'reason', type: 'string', example: 'Período prolongado de recuperação'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Baixa médica atualizada com sucesso.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Baixa médica atualizada com sucesso e horário ajustado.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'user_id', type: 'integer', example: 4),
                                new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2026-06-26'),
                                new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2026-07-03'),
                                new OA\Property(property: 'reason', type: 'string', example: 'Período prolongado de recuperação'),
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
            new OA\Response(
                response: 404,
                description: 'Baixa médica não encontrada.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Baixa médica não encontrada.'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Erro de validação ou sobreposição.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Dados inválidos ou em falta.'),
                    ]
                )
            ),
        ]
    )]
    public function update(UpdateMedicalLeaveRequest $request, int $id): JsonResponse
    {
        $medicalLeave = MedicalLeave::find($id);

        if (!$medicalLeave) {
            return response()->json(['message' => 'Baixa médica não encontrada.'], 404);
        }

        $validated = $request->validated();

        // 1. Validar sobreposição excluindo a baixa atual
        $this->checkOverlap($validated['user_id'], $validated['start_date'], $validated['end_date'], $id);

        $sickLeaveType = $this->getOrCreateSickLeaveType();

        DB::transaction(function () use ($medicalLeave, $validated, $sickLeaveType) {
            // 2. Repor todos os turnos originais da data anterior desta baixa
            $this->restoreReplacedShifts($medicalLeave);

            // 3. Atualizar as datas e o enfermeiro associados à baixa
            $medicalLeave->update([
                'user_id' => $validated['user_id'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'reason' => $validated['reason'] ?? null,
            ]);

            // 4. Aplicar a baixa médica nas novas datas
            $this->applyMedicalLeave($medicalLeave, $sickLeaveType);
        });

        // 5. Enviar notificações sobre a alteração
        $this->notifyUsers($medicalLeave, 'updated');

        return response()->json([
            'message' => 'Baixa médica atualizada com sucesso e horário ajustado.',
            'data' => $medicalLeave->load('user'),
        ]);
    }

    #[OA\Delete(
        path: '/api/medical-leaves/{id}',
        summary: 'Eliminar uma baixa médica',
        tags: ['Medical Leaves'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID da baixa médica a eliminar',
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Baixa médica eliminada com sucesso.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Baixa médica eliminada com sucesso e turnos originais repostos no horário.'),
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
            new OA\Response(
                response: 404,
                description: 'Baixa médica não encontrada.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Baixa médica não encontrada.'),
                    ]
                )
            ),
        ]
    )]
    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($request->user()->role !== UserRole::Admin) {
            return response()->json(['message' => 'Apenas administradores podem realizar esta ação.'], 403);
        }

        $medicalLeave = MedicalLeave::find($id);

        if (!$medicalLeave) {
            return response()->json(['message' => 'Baixa médica não encontrada.'], 404);
        }

        // Guardamos os dados em memória antes da reposição para uso nas notificações
        $this->notifyUsers($medicalLeave, 'deleted');

        DB::transaction(function () use ($medicalLeave) {
            // 1. Repor todos os turnos originais
            $this->restoreReplacedShifts($medicalLeave);

            // 2. Apagar o registo de baixa médica
            $medicalLeave->delete();
        });

        return response()->json([
            'message' => 'Baixa médica eliminada com sucesso e turnos originais repostos no horário.',
        ]);
    }

    // --- MÉTODOS AUXILIARES ---

    private function checkOverlap(int $userId, string $startDate, string $endDate, ?int $ignoreId = null): void
    {
        $overlap = MedicalLeave::where('user_id', $userId)
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
                'start_date' => ['Este enfermeiro já se encontra de baixa médica no período selecionado.'],
            ]);
        }
    }

    private function getOrCreateSickLeaveType(): ShiftType
    {
        $type = ShiftType::whereRaw('LOWER(name) = ?', ['baixa medica'])
            ->orWhereRaw('LOWER(name) = ?', ['sick leave'])
            ->first();

        if (!$type) {
            $type = ShiftType::create([
                'name' => 'baixa medica',
                'color' => '#e3efff',
                'start_time' => '00:00:00',
                'end_time' => '00:00:00',
                'min_nurses' => 0,
            ]);
        }

        return $type;
    }

    private function applyMedicalLeave(MedicalLeave $medicalLeave, ShiftType $sickLeaveType): void
    {
        $userId = $medicalLeave->user_id;
        $startDate = Carbon::parse($medicalLeave->start_date);
        $endDate = Carbon::parse($medicalLeave->end_date);

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

                    // Cria um novo turno exclusivo de baixa médica para o enfermeiro de baixa
                    $newShift = Shift::create([
                        'schedule_id' => $shift->schedule_id,
                        'shift_type_id' => $sickLeaveType->id,
                        'shift_date' => $dateStr,
                    ]);
                    $newShift->users()->attach($userId);

                    MedicalLeaveReplacedShift::create([
                        'medical_leave_id' => $medicalLeave->id,
                        'shift_date' => $dateStr,
                        'schedule_id' => $shift->schedule_id,
                        'original_shift_id' => $shift->id,
                        'original_shift_type_id' => $shift->shift_type_id,
                        'temp_shift_id' => $newShift->id,
                        'was_shared' => true,
                    ]);
                } else {
                    // Turno exclusivo: Atualiza o tipo do turno diretamente para "baixa medica"
                    $originalShiftTypeId = $shift->shift_type_id;
                    $shift->update([
                        'shift_type_id' => $sickLeaveType->id,
                    ]);

                    MedicalLeaveReplacedShift::create([
                        'medical_leave_id' => $medicalLeave->id,
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

    private function restoreReplacedShifts(MedicalLeave $medicalLeave): void
    {
        $userId = $medicalLeave->user_id;
        $replacedShifts = MedicalLeaveReplacedShift::where('medical_leave_id', $medicalLeave->id)->get();

        foreach ($replacedShifts as $replaced) {
            if ($replaced->was_shared) {
                // Eliminar o turno temporário de baixa médica criado
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

    private function notifyUsers(MedicalLeave $medicalLeave, string $action): void
    {
        $nurse = $medicalLeave->user;
        if (!$nurse) {
            $nurse = User::find($medicalLeave->user_id);
        }

        $days = Carbon::parse($medicalLeave->start_date)->diffInDays(Carbon::parse($medicalLeave->end_date)) + 1;
        $startDateFormatted = Carbon::parse($medicalLeave->start_date)->format('d/m/Y');
        $endDateFormatted = Carbon::parse($medicalLeave->end_date)->format('d/m/Y');

        // 1. Notificar o enfermeiro na segunda pessoa do singular
        $nurse->notify(new MedicalLeaveNotification(
            $medicalLeave,
            $action,
            'nurse',
            $nurse->name,
            $days,
            $startDateFormatted,
            $endDateFormatted
        ));

        // 2. Notificar o enfermeiro chefe na terceira pessoa identificando o enfermeiro em questão
        $isHeadNurse = $nurse->role === UserRole::HeadNurse || $nurse->role === 'head_nurse';

        // Só notifica os enfermeiros chefes se o enfermeiro de baixa NÃO for o próprio enfermeiro chefe
        if (!$isHeadNurse) {
            $headNurses = User::where('role', UserRole::HeadNurse)
                ->where('active', true)
                ->where('id', '!=', $nurse->id)
                ->get();

            foreach ($headNurses as $headNurse) {
                $headNurse->notify(new MedicalLeaveNotification(
                    $medicalLeave,
                    $action,
                    'head_nurse',
                    $nurse->name,
                    $days,
                    $startDateFormatted,
                    $endDateFormatted
                ));
            }
        }
    }
}
