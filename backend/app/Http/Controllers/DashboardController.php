<?php

namespace App\Http\Controllers;

use App\Enums\ShiftSwapParticipantRole;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Models\Shift;
use App\Models\ShiftSwapParticipant;
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
                                new OA\Property(property: 'monthly_hours', type: 'number', format: 'float', example: 160.5, description: 'Apenas para role nurse'),
                                new OA\Property(
                                    property: 'shift_type_breakdown',
                                    type: 'object',
                                    example: ['manhã' => 8, 'tarde' => 6, 'noite' => 4],
                                    description: 'Contagem de turnos por tipo — apenas para role nurse'
                                ),
                                new OA\Property(property: 'pending_swaps_count', type: 'integer', example: 2, description: 'Pedidos de troca pendentes onde o nurse é target — apenas para role nurse'),
                                new OA\Property(property: 'swaps_this_month', type: 'integer', example: 5, description: 'Pedidos de troca do mês (como requester ou target) — apenas para role nurse'),
                                new OA\Property(property: 'team_pending_swaps_count', type: 'integer', example: 3, description: 'Total de pedidos de troca pendentes na equipa — apenas para role head_nurse'),
                                new OA\Property(property: 'team_swaps_this_month', type: 'integer', example: 10, description: 'Total de pedidos de troca criados este mês — apenas para role head_nurse'),
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

        if ($role === UserRole::Nurse->value) {
            return $this->nurseData($user);
        }

        if ($role === UserRole::HeadNurse->value) {
            return $this->headNurseData();
        }

        return response()->json(['data' => ['role' => $role]]);
    }

    private function nurseData(\App\Models\User $user): JsonResponse
    {
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd   = Carbon::now()->endOfMonth();

        // Fetch all shifts assigned to this nurse in the current month, with their type loaded.
        $shifts = Shift::query()
            ->with('shiftType')
            ->whereHas('users', fn ($q) => $q->where('users.id', $user->id))
            ->whereDate('shift_date', '>=', $monthStart->toDateString())
            ->whereDate('shift_date', '<=', $monthEnd->toDateString())
            ->get();

        // Sum shift hours, handling overnight shifts where end_time <= start_time.
        $monthlyHours = 0;
        $shiftTypeBreakdown = [];

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

                $monthlyHours += ($endMinutes - $startMinutes) / 60;

                $typeName = $type->name;
                $shiftTypeBreakdown[$typeName] = ($shiftTypeBreakdown[$typeName] ?? 0) + 1;
            }
        }

        // Count pending swap requests where this nurse is the target.
        $pendingSwapsCount = ShiftSwapRequest::query()
            ->where('status', ShiftSwapStatus::Pending)
            ->whereHas('participants', function ($q) use ($user): void {
                $q->where('user_id', $user->id)
                    ->where('role', ShiftSwapParticipantRole::Target->value);
            })
            ->count();

        // Count all swap requests (as requester or target) created this month.
        $swapsThisMonth = ShiftSwapRequest::query()
            ->whereDate('created_at', '>=', $monthStart->toDateString())
            ->whereDate('created_at', '<=', $monthEnd->toDateString())
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->count();

        return response()->json([
            'data' => [
                'role'                  => UserRole::Nurse->value,
                'monthly_hours'         => round($monthlyHours, 2),
                'shift_type_breakdown'  => $shiftTypeBreakdown,
                'pending_swaps_count'   => $pendingSwapsCount,
                'swaps_this_month'      => $swapsThisMonth,
            ],
        ]);
    }

    private function headNurseData(): JsonResponse
    {
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd   = Carbon::now()->endOfMonth();

        // Count all pending swap requests across the entire team.
        $teamPendingSwapsCount = ShiftSwapRequest::query()
            ->where('status', ShiftSwapStatus::Pending)
            ->count();

        // Count all swap requests created this month, regardless of participant.
        $teamSwapsThisMonth = ShiftSwapRequest::query()
            ->whereDate('created_at', '>=', $monthStart->toDateString())
            ->whereDate('created_at', '<=', $monthEnd->toDateString())
            ->count();

        return response()->json([
            'data' => [
                'role'                      => UserRole::HeadNurse->value,
                'team_pending_swaps_count'  => $teamPendingSwapsCount,
                'team_swaps_this_month'     => $teamSwapsThisMonth,
            ],
        ]);
    }

}
