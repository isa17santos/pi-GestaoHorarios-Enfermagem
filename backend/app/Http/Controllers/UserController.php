<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    // Returns the full list of users.
    #[OA\Get(
        path: '/api/users',
        operationId: 'listUsers',
        tags: ['Users'],
        summary: 'Lista todos os utilizadores',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de utilizadores',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(type: 'object')
                )
            ),
        ]
    )]
    public function index(): JsonResponse
    {
        return response()->json(
            User::query()->get()
        );
    }

    // Returns a single user by id.
    #[OA\Get(
        path: '/api/users/{id}',
        operationId: 'showUser',
        tags: ['Users'],
        summary: 'Obtém um utilizador pelo ID',
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID do utilizador',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Utilizador encontrado',
                content: new OA\JsonContent(type: 'object')
            ),
            new OA\Response(response: 404, description: 'Utilizador nao encontrado'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        return response()->json(
            User::query()->findOrFail($id)
        );
    }
}
