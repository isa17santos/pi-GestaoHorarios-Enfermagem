<?php

namespace App\Http\Controllers;

use App\Enums\ShiftSwapParticipantRole;
use App\Enums\ShiftSwapRequestShiftType;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Http\Requests\StoreSwapRequest;
use App\Http\Resources\SwapRequestResource;
use App\Models\ShiftSwapParticipant;
use App\Models\ShiftSwapRequest;
use App\Models\ShiftSwapRequestShift;
use App\Models\User;
use App\Notifications\SwapAcceptedNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'SwapRequests', description: 'Gestão de pedidos de troca de turnos')]
class SwapRequestController extends Controller
{
    use AuthorizesRequests;
    /**
     * Relations required by the swap response resource.
     */
    private const RELATIONS = [
        'creator',
        'participants.user',
        'requestShifts.shift.shiftType',
        'requestShifts.owner',
    ];

    #[OA\Get(
        path: '/api/swaps',
        summary: 'Listar pedidos de troca',
        security: [['bearerAuth' => []]],
        tags: ['SwapRequests'],
        parameters: [
            new OA\Parameter(
                name: 'direction',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['sent', 'received'], nullable: true)
            ),
            new OA\Parameter(
                name: 'status',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['pending', 'accepted', 'rejected', 'cancelled'], nullable: true)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listagem paginada de SwapRequests'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Não autorizado'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ShiftSwapRequest::class);

        $user = $request->user();
        $direction = $request->query('direction');
        $status = $request->query('status');

        $swapRequests = ShiftSwapRequest::query()
            ->whereHas('participants', function ($query) use ($user, $direction): void {
                $query->where('user_id', $user->id);

                if ($direction === 'sent') {
                    $query->where('role', ShiftSwapParticipantRole::Requester->value);
                }

                if ($direction === 'received') {
                    $query->where('role', ShiftSwapParticipantRole::Target->value);
                }
            })
            ->when($status, fn ($query, $status) => $query->where('status', $status))
            ->with(self::RELATIONS)
            ->orderByDesc('id')
            ->get();

        return SwapRequestResource::collection($swapRequests);
    }

    #[OA\Get(
        path: '/api/swaps/{swapRequest}',
        summary: 'Ver detalhe de um pedido de troca',
        security: [['bearerAuth' => []]],
        tags: ['SwapRequests'],
        parameters: [
            new OA\Parameter(
                name: 'swapRequest',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'SwapRequest detalhado'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Não autorizado'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ]
    )]
    public function show(ShiftSwapRequest $swapRequest): SwapRequestResource
    {
        $this->authorize('view', $swapRequest);

        $swapRequest->load(self::RELATIONS);

        return new SwapRequestResource($swapRequest);
    }

    #[OA\Post(
        path: '/api/swaps',
        summary: 'Criar pedido de troca',
        security: [['bearerAuth' => []]],
        tags: ['SwapRequests'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['offered_shift_ids', 'requested_shift_ids', 'target_user_id'],
                properties: [
                    new OA\Property(
                        property: 'offered_shift_ids',
                        type: 'array',
                        items: new OA\Items(type: 'integer')
                    ),
                    new OA\Property(
                        property: 'requested_shift_ids',
                        type: 'array',
                        items: new OA\Items(type: 'integer')
                    ),
                    new OA\Property(property: 'target_user_id', type: 'integer'),
                    new OA\Property(property: 'notes', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Pedido de troca criado com sucesso'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Não autorizado'),
            new OA\Response(response: 422, description: 'Dados inválidos'),
        ]
    )]
    public function store(StoreSwapRequest $request)
    {
        $this->authorize('create', ShiftSwapRequest::class);

        $validated = $request->validated();
        $user = $request->user();

        // Keep swap request creation and related rows consistent.
        $swapRequest = DB::transaction(function () use ($validated, $user): ShiftSwapRequest {
            $swapRequest = ShiftSwapRequest::query()->create([
                'created_by' => $user->id,
                'notes' => $validated['notes'] ?? null,
                'status' => ShiftSwapStatus::Pending,
            ]);

            ShiftSwapParticipant::query()->insert([
                [
                    'swap_request_id' => $swapRequest->id,
                    'user_id' => $user->id,
                    'role' => ShiftSwapParticipantRole::Requester->value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'swap_request_id' => $swapRequest->id,
                    'user_id' => (int) $validated['target_user_id'],
                    'role' => ShiftSwapParticipantRole::Target->value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            $offeredShifts = collect($validated['offered_shift_ids'])->map(function ($shiftId) use ($swapRequest, $user): array {
                return [
                    'swap_request_id' => $swapRequest->id,
                    'shift_id' => (int) $shiftId,
                    'type' => ShiftSwapRequestShiftType::Offered->value,
                    'owner_user_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->all();

            $requestedShifts = collect($validated['requested_shift_ids'])->map(function ($shiftId) use ($swapRequest, $validated): array {
                return [
                    'swap_request_id' => $swapRequest->id,
                    'shift_id' => (int) $shiftId,
                    'type' => ShiftSwapRequestShiftType::Requested->value,
                    'owner_user_id' => (int) $validated['target_user_id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->all();

            ShiftSwapRequestShift::query()->insert([...$offeredShifts, ...$requestedShifts]);

            return $swapRequest;
        });

        $swapRequest->load(self::RELATIONS);

        return (new SwapRequestResource($swapRequest))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Post(
        path: '/api/swaps/{swapRequest}/accept',
        summary: 'Aceitar pedido de troca',
        security: [['bearerAuth' => []]],
        tags: ['SwapRequests'],
        parameters: [
            new OA\Parameter(
                name: 'swapRequest',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pedido de troca aceite com sucesso'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Não autorizado'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ]
    )]
    public function accept(ShiftSwapRequest $swapRequest): SwapRequestResource
    {
        $this->authorize('accept', $swapRequest);

        DB::transaction(function () use ($swapRequest): void {
            $swapRequest->update([
                'status' => ShiftSwapStatus::Accepted,
            ]);

            $acceptedShiftId = $swapRequest->requestShifts()
                ->where('type', ShiftSwapRequestShiftType::Offered->value)
                ->value('shift_id');

            ShiftSwapRequest::query()
                ->where('id', '!=', $swapRequest->id)
                ->where('status', ShiftSwapStatus::Pending)
                ->whereHas('requestShifts', function ($q) use ($acceptedShiftId): void {
                    $q->where('shift_id', $acceptedShiftId)
                        ->where('type', ShiftSwapRequestShiftType::Offered->value);
                })
                ->update(['status' => ShiftSwapStatus::Cancelled]);
        });

        $headNurses = User::query()
            ->where('role', UserRole::HeadNurse->value)
            ->get();

        $swapRequest->load(self::RELATIONS);

        Notification::send($headNurses, new SwapAcceptedNotification($swapRequest));

        return new SwapRequestResource($swapRequest);
    }

    #[OA\Post(
        path: '/api/swaps/{swapRequest}/reject',
        summary: 'Rejeitar pedido de troca',
        security: [['bearerAuth' => []]],
        tags: ['SwapRequests'],
        parameters: [
            new OA\Parameter(
                name: 'swapRequest',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pedido de troca rejeitado com sucesso'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Não autorizado'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ]
    )]
    public function reject(ShiftSwapRequest $swapRequest): SwapRequestResource
    {
        $this->authorize('reject', $swapRequest);

        $swapRequest->update([
            'status' => ShiftSwapStatus::Rejected,
        ]);

        $swapRequest->load(self::RELATIONS);

        return new SwapRequestResource($swapRequest);
    }

    #[OA\Post(
        path: '/api/swaps/{swapRequest}/cancel',
        summary: 'Cancelar pedido de troca',
        security: [['bearerAuth' => []]],
        tags: ['SwapRequests'],
        parameters: [
            new OA\Parameter(
                name: 'swapRequest',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pedido de troca cancelado com sucesso'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 403, description: 'Não autorizado'),
            new OA\Response(response: 404, description: 'Não encontrado'),
        ]
    )]
    public function cancel(ShiftSwapRequest $swapRequest): SwapRequestResource
    {
        $this->authorize('cancel', $swapRequest);

        $swapRequest->update([
            'status' => ShiftSwapStatus::Cancelled,
        ]);

        $swapRequest->load(self::RELATIONS);

        return new SwapRequestResource($swapRequest);
    }
}
