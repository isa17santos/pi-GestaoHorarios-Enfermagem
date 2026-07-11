<?php

namespace App\Http\Controllers;

use App\Enums\ShiftSwapStatus;
use App\Enums\ShiftTypeName;
use App\Enums\UserRole;
use App\Models\MedicalLeave;
use App\Models\NursePreference;
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
                                new OA\Property(property: 'inactive_users_count', type: 'integer', example: 3, description: 'Apenas para role admin — total de utilizadores com active = false, sem filtro de role nem de mês'),
                                new OA\Property(property: 'pending_password_change_count', type: 'integer', example: 1, description: 'Apenas para role admin — total de utilizadores com must_change_password = true, sem filtro de role nem de mês'),
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

    // Thresholds usados para calcular o quality_score — valores provisórios a validar com
    // orientador/utilizadores reais.
    private const MAX_SWAPS_FOR_GOOD  = 5;
    private const MAX_SWAPS_FOR_MEDIO = 15;
    // Percentagem (0-1) de turnos fora das preferências de período marcadas a partir da qual
    // um enfermeiro é considerado ter um padrão sistemático de violação — não conta por turno,
    // conta o enfermeiro uma única vez. Fixado acima do ruído estatístico esperado: com 3 tipos
    // de turno possíveis, um horário aleatório sem qualquer consideração por preferências já
    // produz ~67% de mismatch por puro acaso (2 em cada 3 tipos não batem com a preferência
    // marcada). O threshold precisa de ficar acima disso para só sinalizar desvio genuíno,
    // não essa taxa de base esperada mesmo quando a escala é bem feita.
    private const PREFERENCE_MISMATCH_THRESHOLD = 0.75;
    // Número mínimo de turnos de trabalho no mês para um enfermeiro entrar na verificação de
    // tipo de período — evita falsos positivos com amostras demasiado pequenas.
    private const MIN_SHIFTS_FOR_PREFERENCE_CHECK = 4;

    // Pesos de penalização do quality_score (0-100) — valores provisórios a validar com dados
    // reais. Cada fator penaliza proporcionalmente à gravidade que representa:
    // - Trocas: sintoma de instabilidade, não um problema em si — penalização suave e gradual.
    // - Mínimos de enfermeiros: risco operacional direto (cobertura insuficiente do serviço),
    //   por isso pesa muito mais que os restantes fatores.
    // - Preferências de tipo: já é um sinal agregado por enfermeiro (padrão sistemático), por
    //   isso cada ocorrência pesa mais que uma violação de fim de semana isolada.
    // - Preferências de fim de semana: evento pontual por turno, não um padrão — peso mínimo.
    private const SWAPS_PENALTY_MAX               = 40;
    private const MIN_NURSES_PENALTY_PER_VIOLATION = 25;
    private const MIN_NURSES_PENALTY_MAX           = 50;
    private const PREFERENCE_TYPE_PENALTY_PER_NURSE = 5;
    private const PREFERENCE_WEEKEND_PENALTY_PER_SHIFT = 1;

    // Thresholds do quality_indicator, derivados diretamente do quality_score — valores
    // provisórios a validar com dados reais.
    private const QUALITY_SCORE_GOOD_THRESHOLD  = 70;
    private const QUALITY_SCORE_MEDIO_THRESHOLD = 40;

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

                    // Zero-duration type (e.g. day off, holidays, sick leave): contributes no hours.
                    if ($endMinutes !== $startMinutes) {
                        // Overnight shift: end crosses midnight.
                        if ($endMinutes < $startMinutes) {
                            $endMinutes += 24 * 60;
                        }

                        $hours += ($endMinutes - $startMinutes) / 60;
                    }
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

        // Preference violations: para o mês atual, cruza os shifts de trabalho (manhã/tarde/noite)
        // com a NursePreference de cada enfermeiro para esse mesmo mês/ano. Shifts sem tipo de
        // trabalho (folga, férias, baixa, licença) são irrelevantes aqui — já não entram no filtro.
        //
        // Violação de tipo de período: NÃO é contada por turno individual — enfermeiros trabalham
        // naturalmente em vários períodos por necessidade de escala, mesmo tendo uma preferência.
        // Contar por turno saturava o indicador de forma irrealista (ex: 200 violações com 14
        // enfermeiros). Em vez disso, agrega-se por enfermeiro: calcula-se a percentagem dos seus
        // turnos do mês que caem fora das preferências marcadas, e só conta 1 violação (o
        // enfermeiro, não os turnos) quando essa percentagem atinge PREFERENCE_MISMATCH_THRESHOLD —
        // ou seja, quando há um padrão sistemático de desalinhamento, não uma variação pontual.
        $currentMonth = Carbon::now()->month;
        $currentYear  = Carbon::now()->year;

        $workingTypeNames = [
            ShiftTypeName::Morning->value,
            ShiftTypeName::Afternoon->value,
            ShiftTypeName::Night->value,
        ];

        $preferenceShifts = Shift::query()
            ->with(['shiftType', 'users'])
            ->whereHas('shiftType', fn ($q) => $q->whereIn('name', $workingTypeNames))
            ->whereDate('shift_date', '>=', $monthStart)
            ->whereDate('shift_date', '<=', $monthEnd)
            ->get();

        // Preferências indexadas por user_id para o mês atual — evita 1 query por shift.
        $preferencesByUser = NursePreference::query()
            ->where('month', $currentMonth)
            ->where('year', $currentYear)
            ->get()
            ->keyBy('user_id');

        // Agrega turnos por enfermeiro: total de turnos de trabalho e quantos não correspondem
        // a nenhuma das preferências de período marcadas.
        $shiftStatsByUser = [];
        $weekendViolations = 0;

        foreach ($preferenceShifts as $shift) {
            $shiftTypeName = $shift->shiftType->name;
            $isWeekend     = $shift->shift_date->isWeekend();

            foreach ($shift->users as $nurse) {
                $preference = $preferencesByUser->get($nurse->id);

                // Sem preferência definida para este user/mês/ano: não há como violar nada.
                if (!$preference) {
                    continue;
                }

                // Violação de fim de semana: mantém-se por turno — não sofre do mesmo problema
                // de escala, já que só conta quando avoid_weekends está marcado.
                if ($preference->avoid_weekends && $isWeekend) {
                    $weekendViolations++;
                }

                $hasPeriodPreference = $preference->prefers_morning
                    || $preference->prefers_afternoon
                    || $preference->prefers_night;

                // Enfermeiros sem preferência de período marcada não entram nesta verificação.
                if (!$hasPeriodPreference) {
                    continue;
                }

                $shiftStatsByUser[$nurse->id] ??= ['total' => 0, 'mismatched' => 0];
                $shiftStatsByUser[$nurse->id]['total']++;

                $matchesPreference = match ($shiftTypeName) {
                    ShiftTypeName::Morning->value   => $preference->prefers_morning,
                    ShiftTypeName::Afternoon->value => $preference->prefers_afternoon,
                    ShiftTypeName::Night->value     => $preference->prefers_night,
                    default => true,
                };

                if (!$matchesPreference) {
                    $shiftStatsByUser[$nurse->id]['mismatched']++;
                }
            }
        }

        // Conta 1 violação de tipo por enfermeiro (não por turno) quando a percentagem de
        // turnos fora da preferência atinge o threshold, e há amostra suficiente para ser fiável.
        $typeViolations = 0;

        foreach ($shiftStatsByUser as $stats) {
            if ($stats['total'] < self::MIN_SHIFTS_FOR_PREFERENCE_CHECK) {
                continue;
            }

            $mismatchRatio = $stats['mismatched'] / $stats['total'];

            if ($mismatchRatio >= self::PREFERENCE_MISMATCH_THRESHOLD) {
                $typeViolations++;
            }
        }

        // O breakdown expõe as duas unidades em separado (violações por enfermeiro vs. violações
        // por turno) em vez de as somar num só "preference_violations" — misturar as duas unidades
        // produzia contagens que podiam exceder o total de enfermeiros da equipa e tornava o texto
        // "X enfermeiros com..." incorreto no frontend.
        $preferenceTypeViolations    = $typeViolations;
        $preferenceWeekendViolations = $weekendViolations;

        // Quality score (0-100): parte de 100 e subtrai penalizações por cada fator, em vez de
        // classificar diretamente em 3 níveis — dá uma escala contínua mais granular, da qual o
        // quality_indicator passa a ser apenas uma leitura em faixas.
        //
        // Penalização de trocas: sobe linearmente entre MAX_SWAPS_FOR_GOOD e MAX_SWAPS_FOR_MEDIO,
        // até saturar em SWAPS_PENALTY_MAX. Volume de trocas é sintoma de instabilidade, não um
        // problema em si — por isso a penalização é gradual, não um corte abrupto.
        if ($swapsThisMonth <= self::MAX_SWAPS_FOR_GOOD) {
            $swapsPenalty = 0;
        } elseif ($swapsThisMonth >= self::MAX_SWAPS_FOR_MEDIO) {
            $swapsPenalty = self::SWAPS_PENALTY_MAX;
        } else {
            $swapsRange   = self::MAX_SWAPS_FOR_MEDIO - self::MAX_SWAPS_FOR_GOOD;
            $swapsPenalty = (($swapsThisMonth - self::MAX_SWAPS_FOR_GOOD) / $swapsRange) * self::SWAPS_PENALTY_MAX;
        }

        // Penalização de mínimos de enfermeiros: pesa muito mais que os restantes fatores porque
        // representa risco operacional direto — cobertura insuficiente do serviço, não apenas uma
        // preferência não respeitada.
        $minNursesPenalty = min(
            $minNursesViolations * self::MIN_NURSES_PENALTY_PER_VIOLATION,
            self::MIN_NURSES_PENALTY_MAX
        );

        // Penalização de preferências de tipo: cada ocorrência já é um enfermeiro inteiro com
        // padrão sistemático (agregado acima), por isso pesa mais que uma violação de fim de
        // semana isolada.
        $preferenceTypePenalty = $preferenceTypeViolations * self::PREFERENCE_TYPE_PENALTY_PER_NURSE;

        // Penalização de fim de semana: evento pontual por turno, não um padrão — peso mínimo.
        $preferenceWeekendPenalty = $preferenceWeekendViolations * self::PREFERENCE_WEEKEND_PENALTY_PER_SHIFT;

        $qualityScore = max(0, 100 - ($swapsPenalty + $minNursesPenalty + $preferenceTypePenalty + $preferenceWeekendPenalty));
        $qualityScore = (int) round($qualityScore);

        // Quality indicator — leitura em 3 faixas diretamente sobre o quality_score.
        $qualityIndicator = match (true) {
            $qualityScore >= self::QUALITY_SCORE_GOOD_THRESHOLD  => 'bom',
            $qualityScore >= self::QUALITY_SCORE_MEDIO_THRESHOLD => 'medio',
            default => 'mau',
        };

        return response()->json([
            'data' => [
                'role'                => UserRole::HeadNurse->value,
                'acceptance_rate'     => $acceptanceRate,
                'avg_hours_per_nurse' => $nurseHours,
                'quality_indicator'   => $qualityIndicator,
                'quality_score'       => $qualityScore,
                'quality_breakdown'   => [
                    'swaps_this_month'            => $swapsThisMonth,
                    'min_nurses_violations'       => $minNursesViolations,
                    'preference_type_violations'  => $preferenceTypeViolations,
                    'preference_weekend_violations' => $preferenceWeekendViolations,
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

        // Count inactive users and users pending a password change — snapshot of current
        // system state, sem filtro de role nem de mês (não são estatísticas mensais).
        $inactiveUsersCount        = User::query()->where('active', false)->count();
        $pendingPasswordChangeCount = User::query()->where('must_change_password', true)->count();

        return response()->json([
            'data' => [
                'role'                           => UserRole::Admin->value,
                'nurses_count'                   => $nursesCount,
                'head_nurses_count'              => $headNursesCount,
                'medical_leaves_this_month'      => $medicalLeavesThisMonth,
                'vacations_this_month'           => $vacationsThisMonth,
                'inactive_users_count'           => $inactiveUsersCount,
                'pending_password_change_count'  => $pendingPasswordChangeCount,
            ],
        ]);
    }
}
