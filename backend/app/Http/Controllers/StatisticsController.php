<?php

namespace App\Http\Controllers;

use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Models\MedicalLeave;
use App\Models\Shift;
use App\Models\ShiftSwapRequest;
use App\Models\ShiftType;
use App\Models\User;
use App\Models\Vacation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Statistics', description: 'Dados estatísticos por role')]
class StatisticsController extends Controller
{
    #[OA\Get(
        path: '/api/statistics',
        summary: 'Devolve estatísticas do mês atual para admin ou head_nurse',
        security: [['bearerAuth' => []]],
        tags: ['Statistics'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Estatísticas devolvidas com sucesso. A estrutura varia consoante o role.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'role', type: 'string', enum: ['admin', 'head_nurse']),
                                new OA\Property(property: 'nurses_count', type: 'integer', example: 20, description: 'Apenas para role admin'),
                                new OA\Property(property: 'head_nurses_count', type: 'integer', example: 3, description: 'Apenas para role admin'),
                                new OA\Property(property: 'medical_leaves_this_month', type: 'integer', example: 2, description: 'Apenas para role admin'),
                                new OA\Property(property: 'vacations_this_month', type: 'integer', example: 4, description: 'Apenas para role admin'),
                                new OA\Property(property: 'acceptance_rate', type: 'number', format: 'float', nullable: true, example: 75.0, description: 'Percentagem de trocas aceites sobre respondidas no mês — apenas para role head_nurse. Null se não houver pedidos respondidos.'),
                                new OA\Property(property: 'avg_hours_per_nurse', type: 'array', description: 'Horas trabalhadas no mês por cada enfermeiro, ordenadas de forma decrescente — apenas para role head_nurse', items: new OA\Items(properties: [new OA\Property(property: 'user_id', type: 'integer', example: 5), new OA\Property(property: 'name', type: 'string', example: 'Ana Silva'), new OA\Property(property: 'hours', type: 'number', format: 'float', example: 168.5)])),
                                new OA\Property(property: 'quality_indicator', nullable: true, example: null, description: 'Indicador de qualidade — apenas para role head_nurse. Ainda não implementado.'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Sem permissão para aceder às estatísticas'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->role instanceof \BackedEnum ? $user->role->value : $user->role;

        if ($role === UserRole::Admin->value) {
            return $this->adminStatistics();
        }

        if ($role === UserRole::HeadNurse->value) {
            return $this->headNurseStatistics();
        }

        return response()->json(['message' => 'Sem permissão para aceder às estatísticas.'], 403);
    }

    // Thresholds para o quality_indicator — valores provisórios a validar com orientador/utilizadores reais.
    private const MAX_SWAPS_FOR_GOOD            = 5;
    private const MAX_MIN_VIOLATIONS_FOR_GOOD   = 0;
    private const MAX_SWAPS_FOR_MEDIO           = 15;
    // preference_violations: pendente de implementação — Val4 existe apenas no frontend por agora.

    private function headNurseStatistics(): JsonResponse
    {
        $monthStart = Carbon::now()->startOfMonth()->toDateString();
        $monthEnd   = Carbon::now()->endOfMonth()->toDateString();

        // Acceptance rate: accepted / (accepted + rejected) for the current month.
        // Pending and cancelled requests are excluded — they haven't been decided yet.
        $accepted = ShiftSwapRequest::query()
            ->where('status', ShiftSwapStatus::Accepted)
            ->whereDate('updated_at', '>=', $monthStart)
            ->whereDate('updated_at', '<=', $monthEnd)
            ->count();

        $rejected = ShiftSwapRequest::query()
            ->where('status', ShiftSwapStatus::Rejected)
            ->whereDate('updated_at', '>=', $monthStart)
            ->whereDate('updated_at', '<=', $monthEnd)
            ->count();

        $responded      = $accepted + $rejected;
        $acceptanceRate = $responded > 0
            ? round(($accepted / $responded) * 100, 2)
            : null;

        // Hours worked this month per nurse, sorted descending.
        $nurses = User::query()
            ->where('role', UserRole::Nurse->value)
            ->get();

        $nurseHours = [];

        foreach ($nurses as $nurse) {
            $shifts = Shift::query()
                ->with('shiftType')
                ->whereHas('users', fn ($q) => $q->where('users.id', $nurse->id))
                ->whereDate('shift_date', '>=', $monthStart)
                ->whereDate('shift_date', '<=', $monthEnd)
                ->get();

            $hours = 0;

            foreach ($shifts as $shift) {
                $type = $shift->shiftType;

                if ($type) {
                    [$startH, $startM] = array_map('intval', explode(':', $type->start_time));
                    [$endH,   $endM  ] = array_map('intval', explode(':', $type->end_time));

                    $startMinutes = $startH * 60 + $startM;
                    $endMinutes   = $endH * 60 + $endM;

                    // Overnight shift: end crosses midnight.
                    if ($endMinutes <= $startMinutes) {
                        $endMinutes += 24 * 60;
                    }

                    $hours += ($endMinutes - $startMinutes) / 60;
                }
            }

            $nurseHours[] = [
                'user_id' => $nurse->id,
                'name'    => $nurse->name,
                'hours'   => round($hours, 2),
            ];
        }

        usort($nurseHours, fn ($a, $b) => $b['hours'] <=> $a['hours']);

        // Swaps created this month — high volume is a proxy for instability.
        $swapsThisMonth = ShiftSwapRequest::query()
            ->whereDate('created_at', '>=', $monthStart)
            ->whereDate('created_at', '<=', $monthEnd)
            ->count();

        // Min-nurses violations: shifts in the current month where the assigned nurse count
        // is below the shift type's min_nurses. Non-working shift types (folga, baixa, etc.)
        // are excluded, following the same filter used in ScheduleController::validateBeforePublish().
        $nonWorkingTypeIds = ShiftType::where(function ($q): void {
            $q->whereRaw('LOWER(name) LIKE ?', ['%off%'])
                ->orWhereRaw('LOWER(name) LIKE ?', ['%folga%'])
                ->orWhereRaw('LOWER(name) LIKE ?', ['%holiday%'])
                ->orWhereRaw('LOWER(name) LIKE ?', ['%férias%'])
                ->orWhereRaw('LOWER(name) LIKE ?', ['%leave%'])
                ->orWhereRaw('LOWER(name) LIKE ?', ['%baixa%'])
                ->orWhereRaw('LOWER(name) LIKE ?', ['%licença%']);
        })->pluck('id')->all();

        $workingShifts = Shift::query()
            ->with(['shiftType', 'users'])
            ->whereNotIn('shift_type_id', $nonWorkingTypeIds)
            ->whereDate('shift_date', '>=', $monthStart)
            ->whereDate('shift_date', '<=', $monthEnd)
            ->get()
            ->groupBy(fn ($s) => $s->shift_date->toDateString() . '_' . $s->shift_type_id);

        $minNursesViolations = 0;

        foreach ($workingShifts as $group) {
            $shiftType = $group->first()->shiftType;
            $minRequired = $shiftType->min_nurses ?? 0;
            // Count distinct nurses assigned across all shifts in this group.
            $assignedCount = $group->flatMap(fn ($s) => $s->users->pluck('id'))->unique()->count();

            if ($assignedCount < $minRequired) {
                $minNursesViolations++;
            }
        }

        // Quality indicator — provisório, baseado em 2 dos 3 fatores planeados.
        // Fator em falta: preference_violations (Val4 existe só no frontend; pendente de implementação no backend).
        $qualityIndicator = match (true) {
            $swapsThisMonth <= self::MAX_SWAPS_FOR_GOOD && $minNursesViolations <= self::MAX_MIN_VIOLATIONS_FOR_GOOD => 'bom',
            $swapsThisMonth > self::MAX_SWAPS_FOR_MEDIO || $minNursesViolations > self::MAX_MIN_VIOLATIONS_FOR_GOOD  => 'mau',
            default => 'medio',
        };

        return response()->json([
            'data' => [
                'role'                => UserRole::HeadNurse->value,
                'acceptance_rate'     => $acceptanceRate,
                'avg_hours_per_nurse' => $nurseHours,
                'quality_indicator'   => $qualityIndicator,
                'quality_breakdown'   => [
                    'swaps_this_month'      => $swapsThisMonth,
                    'min_nurses_violations' => $minNursesViolations,
                    'preference_violations' => null,
                ],
            ],
        ]);
    }

    private function adminStatistics(): JsonResponse
    {
        $monthStart = Carbon::now()->startOfMonth()->toDateString();
        $monthEnd   = Carbon::now()->endOfMonth()->toDateString();

        // Count nurses and head nurses by role.
        $nursesCount     = User::query()->where('role', UserRole::Nurse->value)->count();
        $headNursesCount = User::query()->where('role', UserRole::HeadNurse->value)->count();

        // Count medical leaves that overlap the current month.
        // A record overlaps when its start_date <= monthEnd AND end_date >= monthStart.
        $medicalLeavesThisMonth = MedicalLeave::query()
            ->whereDate('start_date', '<=', $monthEnd)
            ->whereDate('end_date', '>=', $monthStart)
            ->count();

        // Count vacations that overlap the current month using the same interval logic.
        $vacationsThisMonth = Vacation::query()
            ->whereDate('start_date', '<=', $monthEnd)
            ->whereDate('end_date', '>=', $monthStart)
            ->count();

        return response()->json([
            'data' => [
                'role'                      => UserRole::Admin->value,
                'nurses_count'              => $nursesCount,
                'head_nurses_count'         => $headNursesCount,
                'medical_leaves_this_month' => $medicalLeavesThisMonth,
                'vacations_this_month'      => $vacationsThisMonth,
            ],
        ]);
    }
}
