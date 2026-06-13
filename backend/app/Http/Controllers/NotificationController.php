<?php

namespace App\Http\Controllers;

use App\Models\SystemNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class NotificationController extends Controller
{
    #[OA\Get(
        path: '/api/notifications',
        summary: 'Lista as notificações do utilizador autenticado',
        security: [['bearerAuth' => []]],
        tags: ['Notifications'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de notificações obtida com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'subject', type: 'string', example: 'Horário de Junho publicado'),
                                    new OA\Property(property: 'body', type: 'string', example: 'O horário do mês de Junho foi publicado...'),
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Não autenticado')
        ]
    )]
    // shows all the notifications of the authenticated user, ordered by creation date (most recent first)
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($notif) {
                return [
                    'id' => $notif->id,
                    'subject' => $notif->subject,
                    'body' => $notif->body,
                    'read' => (bool) $notif->read,
                    'created_at' => $notif->created_at,
                ];
            });
        return response()->json([
            'data' => $notifications,
        ]);
    }

    #[OA\Get(
        path: '/api/notifications/unread-count',
        summary: 'Obtém a quantidade de notificações por ler',
        security: [['bearerAuth' => []]],
        tags: ['Notifications'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Contagem obtida com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'unread_count', type: 'integer', example: 3)
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Não autenticado')
        ]
    )]
    // number of unread notifications for the authenticated user
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $request->user()->notifications()
            ->where('read', false)
            ->count();

        return response()->json([
            'unread_count' => $count,
        ]);
    }

    #[OA\Patch(
        path: '/api/notifications/{id}/read',
        summary: 'Marca uma notificação específica como lida',
        security: [['bearerAuth' => []]],
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
                example: 1
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Notificação marcada como lida com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Notificação marcada como lida.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'subject', type: 'string', example: 'Horário de Junho publicado'),
                                new OA\Property(property: 'body', type: 'string', example: 'O horário do mês de Junho foi publicado...'),
                                new OA\Property(property: 'read', type: 'boolean', example: true),
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 404, description: 'Notificação não encontrada')
        ]
    )]
    // set a certain notification as read for the authenticated user
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $notification = $request->user()->notifications()->find($id);

        if (!$notification) {
            return response()->json([
                'message' => 'Notificação não encontrada.',
            ], 404);
        }

        $notification->update([
            'read' => true,
        ]);
        return response()->json([
            'message' => 'Notificação marcada como lida.',
            'data' => [
                'id' => $notification->id,
                'subject' => $notification->subject,
                'body' => $notification->body,
                'read' => (bool) $notification->read,
                'created_at' => $notification->created_at,
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/notifications/read-all',
        summary: 'Marca todas as notificações do utilizador como lidas',
        security: [['bearerAuth' => []]],
        tags: ['Notifications'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Todas as notificações marcadas como lidas com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Todas as notificações foram marcadas como lidas.')
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Não autenticado')
        ]
    )]
    // set all notifications as read for the authenticated user
    public function markAllAsRead(Request $request): JsonResponse
    {
        $notificationIds = $request->user()->notifications()
            ->where('read', false)
            ->pluck('notifications.id');

        SystemNotification::whereIn('id', $notificationIds)->update([
            'read' => true,
        ]);

        return response()->json([
            'message' => 'Todas as notificações foram marcadas como lidas.',
        ]);
    }
}
