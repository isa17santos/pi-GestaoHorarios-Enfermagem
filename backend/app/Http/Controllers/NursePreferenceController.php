<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\NursePreference;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class NursePreferenceController extends Controller
{
    #[OA\Get(
        path: '/api/users/{userId}/preferences',
        summary: 'Lista as preferencias de um utilizador',
        security: [['bearerAuth' => []]],
        tags: ['Nurse Preferences'],
        parameters: [
            new OA\Parameter(
                name: 'userId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
                example: 4
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Preferencias devolvidas com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'month', type: 'integer', example: 3),
                                    new OA\Property(property: 'year', type: 'integer', example: 2026),
                                    new OA\Property(property: 'prefers_morning', type: 'boolean', example: true),
                                    new OA\Property(property: 'prefers_afternoon', type: 'boolean', example: false),
                                    new OA\Property(property: 'prefers_night', type: 'boolean', example: false),
                                    new OA\Property(property: 'avoid_weekends', type: 'boolean', example: true),
                                    new OA\Property(property: 'prefers_weekends', type: 'boolean', example: false),
                                    new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Prefere turnos de manha durante a semana.'),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Utilizador não encontrado'
            ),
            new OA\Response(
                response: 403,
                description: 'Sem permissão para consultar preferencias'
            ),
            new OA\Response(
                response: 401,
                description: 'Não autenticado'
            ),
        ]
    )]
    public function index(int $userId): JsonResponse
    {
        $user = request()->user();
        $role = $user->role instanceof \BackedEnum ? $user->role->value : $user->role;

        if ($role !== UserRole::HeadNurse->value) {
            return response()->json([
                'message' => 'Sem permissão para consultar preferencias.',
            ], 403);
        }

        // Return 404 explicitly when the target user does not exist.
        if (! User::query()->whereKey($userId)->exists()) {
            return response()->json([
                'message' => 'Utilizador não encontrado.',
            ], 404);
        }

        // Preferences are organized by month and year.
        $preferences = NursePreference::query()
            ->where('user_id', $userId)
            ->orderBy('year')
            ->orderBy('month')
            ->get([
                'id',
                'month',
                'year',
                'prefers_morning',
                'prefers_afternoon',
                'prefers_night',
                'avoid_weekends',
                'prefers_weekends',
                'notes',
            ]);

        return response()->json([
            'data' => $preferences,
        ]);
    }
}
