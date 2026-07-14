<?php

namespace App\Http\Controllers;

use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\ShiftSwapRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Dashboard', description: 'Dados de resumo por role para o painel principal')]
class DashboardController extends Controller
{
    #[OA\Get(
        path: '/api/dashboard',
        summary: 'Devolve dados de resumo do mês atual para o utilizador autenticado',
        security: [['bearerAuth' => []]],
        tags: ['Dashboard'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Dados de resumo devolvidos com sucesso. A estrutura varia consoante o role.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'role', type: 'string', example: 'nurse', enum: ['nurse', 'head_nurse', 'admin']),
                                new OA\Property(property: 'monthly_hours', type: 'number', format: 'float', example: 160.5, description: 'Horas trabalhadas este mês até hoje (exclui turnos futuros) — apenas para role nurse e head_nurse'),
                                new OA\Property(
                                    property: 'shift_type_breakdown',
                                    type: 'object',
                                    example: ['manhã' => 8, 'tarde' => 6, 'noite' => 4],
                                    description: 'Contagem de turnos por tipo — apenas para role nurse e head_nurse'
                                ),
                                new OA\Property(property: 'swaps_this_month', type: 'integer', example: 5, description: 'Pedidos de troca do mês do próprio utilizador (como requester ou target) — apenas para role nurse e head_nurse'),
                                new OA\Property(
                                    property: 'today_shift',
                                    type: 'object',
                                    nullable: true,
                                    example: ['shift_type' => ['name' => 'morning', 'color' => '#7C5CFC', 'start_time' => '08:00:00', 'end_time' => '16:00:00']],
                                    description: 'Turno do próprio utilizador atribuído a hoje, ou null se não houver — apenas para role nurse e head_nurse'
                                ),
                                new OA\Property(
                                    property: 'tomorrow_shift',
                                    type: 'object',
                                    nullable: true,
                                    example: ['shift_type' => ['name' => 'night', 'color' => '#4C3A8C', 'start_time' => '23:00:00', 'end_time' => '08:00:00']],
                                    description: 'Turno do próprio utilizador atribuído a amanhã, ou null se não houver — apenas para role nurse e head_nurse'
                                ),
                                new OA\Property(property: 'team_pending_swaps_count', type: 'integer', example: 3, description: 'Total de pedidos de troca pendentes na equipa — apenas para role head_nurse'),
                                new OA\Property(property: 'team_swaps_this_month', type: 'integer', example: 10, description: 'Total de pedidos de troca criados este mês por toda a equipa — apenas para role head_nurse'),
                                new OA\Property(property: 'nurses_count', type: 'integer', example: 20, description: 'Número de enfermeiros — apenas para role admin'),
                                new OA\Property(property: 'head_nurses_count', type: 'integer', example: 3, description: 'Número de enfermeiros chefes — apenas para role admin'),
                                new OA\Property(property: 'medical_leaves_this_month', type: 'integer', example: 2, description: 'Baixas médicas com sobreposição no mês atual — apenas para role admin'),
                                new OA\Property(property: 'vacations_this_month', type: 'integer', example: 4, description: 'Férias com sobreposição no mês atual — apenas para role admin'),
                            ]
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

        [$year, $month] = $this->resolveRequestedMonth($request);

        if (! $this->isMonthAvailable($year, $month)) {
            return response()->json(['message' => __('statistics.future_month_not_available')], 422);
        }

        if ($role === UserRole::Nurse->value) {
            return response()->json([
                'data' => array_merge(
                    [
                        'role' => UserRole::Nurse->value,
                        'month' => $month,
                        'year' => $year,
                        'next_month_available' => $this->isNextMonthAvailable($year, $month),
                    ],
                    $this->personalShiftData($user, $year, $month)
                ),
            ]);
        }

        if ($role === UserRole::HeadNurse->value) {
            return response()->json([
                'data' => array_merge(
                    [
                        'role' => UserRole::HeadNurse->value,
                        'month' => $month,
                        'year' => $year,
                        'next_month_available' => $this->isNextMonthAvailable($year, $month),
                    ],
                    $this->personalShiftData($user, $year, $month),
                    $this->teamSwapData($year, $month)
                ),
            ]);
        }

        return response()->json(['data' => ['role' => $role]]);
    }

    /**
     * Resolve the requested month/year from the query string, defaulting to the current month.
     * Falls back to today's month/year when the input is missing or out of range.
     */
    private function resolveRequestedMonth(Request $request): array
    {
        $year  = $request->integer('year');
        $month = $request->integer('month');

        if (! $year || ! $month || $month < 1 || $month > 12) {
            return [Carbon::now()->year, Carbon::now()->month];
        }

        return [$year, $month];
    }

    /**
     * A month is available when it isn't in the future, or when a published schedule already
     * covers it (e.g. next month's schedule was published ahead of time).
     */
    private function isMonthAvailable(int $year, int $month): bool
    {
        $requested = Carbon::createFromDate($year, $month, 1)->startOfMonth();

        if ($requested->lessThanOrEqualTo(Carbon::now()->startOfMonth())) {
            return true;
        }

        return Schedule::query()
            ->where('status', 'published')
            ->whereDate('start_date', '<=', $requested->copy()->endOfMonth())
            ->whereDate('end_date', '>=', $requested)
            ->exists();
    }

    /**
     * Whether the month right after the given one is available for navigation — lets the
     * frontend enable/disable the "next month" control without an extra request.
     */
    private function isNextMonthAvailable(int $year, int $month): bool
    {
        $next = Carbon::createFromDate($year, $month, 1)->addMonthNoOverflow();

        return $this->isMonthAvailable($next->year, $next->month);
    }

    /**
     * Build the authenticated user's own shift/hours summary for the current month.
     * Shared between nurse and head_nurse — both are staff members with assigned shifts.
     */
    private function personalShiftData(\App\Models\User $user, int $year, int $month): array
    {
        $monthStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $monthEnd   = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        // Fetch all shifts assigned to this user in the current month, with their type loaded.
        $shifts = Shift::query()
            ->with('shiftType')
            ->whereHas('users', fn ($q) => $q->where('users.id', $user->id))
            ->whereHas('schedule', fn ($q) => $q->where('status', 'published'))
            ->whereDate('shift_date', '>=', $monthStart->toDateString())
            ->whereDate('shift_date', '<=', $monthEnd->toDateString())
            ->get();

        // Sum shift hours worked up to and including today (excludes future shifts still to happen).
        $today = Carbon::today();
        $monthlyHours = 0;
        $shiftTypeBreakdown = [];

        foreach ($shifts as $shift) {
            $type = $shift->shiftType;

            if ($type) {
                if ($shift->shift_date->lessThanOrEqualTo($today)) {
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

                        $monthlyHours += ($endMinutes - $startMinutes) / 60;
                    }
                }

                $typeName = strtolower($type->name);
                $shiftTypeBreakdown[$typeName] = ($shiftTypeBreakdown[$typeName] ?? 0) + 1;
            }
        }

        // Count accepted swap requests (as requester or target) created this month.
        $swapsThisMonth = ShiftSwapRequest::query()
            ->where('status', ShiftSwapStatus::Accepted)
            ->whereDate('created_at', '>=', $monthStart->toDateString())
            ->whereDate('created_at', '<=', $monthEnd->toDateString())
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->count();

        return [
            'monthly_hours'         => round($monthlyHours, 2),
            'shift_type_breakdown'  => $shiftTypeBreakdown,
            'swaps_this_month'      => $swapsThisMonth,
            'today_shift'           => $this->formatShiftForDate($user, Carbon::today()),
            'tomorrow_shift'        => $this->formatShiftForDate($user, Carbon::tomorrow()),
        ];
    }

    /**
     * Fetch and format the nurse's shift for a specific date, or null if none assigned.
     */
    private function formatShiftForDate(\App\Models\User $user, Carbon $date): ?array
    {
        $shift = Shift::query()
            ->with('shiftType')
            ->whereHas('users', fn ($q) => $q->where('users.id', $user->id))
            ->whereHas('schedule', fn ($q) => $q->where('status', 'published'))
            ->whereDate('shift_date', $date->toDateString())
            ->first();

        if (! $shift || ! $shift->shiftType) {
            return null;
        }

        $type = $shift->shiftType;

        return [
            'shift_type' => [
                'name'       => $type->name instanceof \BackedEnum ? $type->name->value : $type->name,
                'color'      => $type->color,
                'start_time' => $type->start_time,
                'end_time'   => $type->end_time,
            ],
        ];
    }

    /**
     * Build team-wide swap counters, shown alongside the head_nurse's own shift summary.
     */
    private function teamSwapData(int $year, int $month): array
    {
        $monthStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $monthEnd   = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        // Count all pending swap requests across the entire team.
        $teamPendingSwapsCount = ShiftSwapRequest::query()
            ->where('status', ShiftSwapStatus::Pending)
            ->count();

        // Count accepted swap requests created this month, regardless of participant.
        $teamSwapsThisMonth = ShiftSwapRequest::query()
            ->where('status', ShiftSwapStatus::Accepted)
            ->whereDate('created_at', '>=', $monthStart->toDateString())
            ->whereDate('created_at', '<=', $monthEnd->toDateString())
            ->count();

        return [
            'team_pending_swaps_count'  => $teamPendingSwapsCount,
            'team_swaps_this_month'     => $teamSwapsThisMonth,
        ];
    }

}
