<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfilePreferencesRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\NursePreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
    #[OA\Get(
        path: '/api/profile',
        summary: 'Obter perfil do utilizador autenticado',
        security: [['bearerAuth' => []]],
        tags: ['Profile'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Perfil obtido com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'Ana Antunes'),
                                new OA\Property(property: 'email', type: 'string', example: 'ana.antunes@example.pt'),
                                new OA\Property(property: 'role', type: 'string', example: 'head_nurse'),
                                new OA\Property(property: 'active', type: 'boolean', example: true),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => $user->only(['id', 'name', 'email', 'role', 'active']),
        ]);
    }

    #[OA\Get(
        path: '/api/profile/preferences',
        summary: 'Lista as preferencias do utilizador autenticado',
        security: [['bearerAuth' => []]],
        tags: ['Profile'],
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
                                    new OA\Property(property: 'schedule_id', type: 'integer', example: 1),
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
            new OA\Response(response: 401, description: 'Não autenticado'),
        ]
    )]
    public function showPreferences(Request $request): JsonResponse
    {
        $user = $request->user();

        $preferences = NursePreference::query()
            ->where('user_id', $user->id)
            ->orderBy('schedule_id')
            ->get([
                'id',
                'schedule_id',
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

    #[OA\Patch(
        path: '/api/profile/preferences',
        summary: 'Atualizar preferencias do utilizador autenticado',
        security: [['bearerAuth' => []]],
        tags: ['Profile'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['schedule_id', 'prefers_morning', 'prefers_afternoon', 'prefers_night', 'avoid_weekends', 'prefers_weekends'],
                properties: [
                    new OA\Property(property: 'schedule_id', type: 'integer', example: 1),
                    new OA\Property(property: 'prefers_morning', type: 'boolean', example: true),
                    new OA\Property(property: 'prefers_afternoon', type: 'boolean', example: false),
                    new OA\Property(property: 'prefers_night', type: 'boolean', example: false),
                    new OA\Property(property: 'avoid_weekends', type: 'boolean', example: true),
                    new OA\Property(property: 'prefers_weekends', type: 'boolean', example: false),
                    new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Prefere turnos de manha durante a semana.'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Preferencias atualizadas com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Preferencias atualizadas com sucesso.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'schedule_id', type: 'integer', example: 1),
                                new OA\Property(property: 'prefers_morning', type: 'boolean', example: true),
                                new OA\Property(property: 'prefers_afternoon', type: 'boolean', example: false),
                                new OA\Property(property: 'prefers_night', type: 'boolean', example: false),
                                new OA\Property(property: 'avoid_weekends', type: 'boolean', example: true),
                                new OA\Property(property: 'prefers_weekends', type: 'boolean', example: false),
                                new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Prefere turnos de manha durante a semana.'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 422, description: 'Dados inválidos ou em falta'),
        ]
    )]
    public function updatePreferences(UpdateProfilePreferencesRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $preference = NursePreference::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'schedule_id' => $validated['schedule_id'],
            ],
            [
                'prefers_morning' => $validated['prefers_morning'],
                'prefers_afternoon' => $validated['prefers_afternoon'],
                'prefers_night' => $validated['prefers_night'],
                'avoid_weekends' => $validated['avoid_weekends'],
                'prefers_weekends' => $validated['prefers_weekends'],
                'notes' => $validated['notes'] ?? null,
            ]
        );

        return response()->json([
            'message' => __('auth.profile_preferences_updated_success'),
            'data' => $preference->only([
                'id',
                'schedule_id',
                'prefers_morning',
                'prefers_afternoon',
                'prefers_night',
                'avoid_weekends',
                'prefers_weekends',
                'notes',
            ]),
        ]);
    }

    #[OA\Patch(
        path: '/api/profile',
        summary: 'Atualizar perfil do utilizador autenticado',
        security: [['bearerAuth' => []]],
        tags: ['Profile'],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Ana Antunes'),
                    new OA\Property(property: 'email', type: 'string', example: 'ana.antunes@example.pt'),
                    new OA\Property(property: 'password', type: 'string', example: 'Password@123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', example: 'Password@123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Perfil atualizado com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Perfil atualizado com sucesso.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'Ana Antunes'),
                                new OA\Property(property: 'email', type: 'string', example: 'ana.antunes@example.pt'),
                                new OA\Property(property: 'role', type: 'string', example: 'head_nurse'),
                                new OA\Property(property: 'active', type: 'boolean', example: true),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 422, description: 'Dados inválidos ou em falta'),
        ]
    )]
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($request->filled('name')) {
            $user->name = $request->input('name');
        }

        if ($request->filled('email')) {
            $user->email = $request->input('email');
        }

        if ($request->filled('password')) {
            $user->password = bcrypt($request->input('password'));
        }

        $user->save();

        return response()->json([
            'message' => __('auth.profile_updated_success'),
            'data' => $user->only(['id', 'name', 'email', 'role', 'active']),
        ]);
    }
}
