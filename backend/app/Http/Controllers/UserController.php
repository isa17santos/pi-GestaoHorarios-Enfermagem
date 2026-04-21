<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Requests\User\DestroyUserRequest;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class UserController extends Controller
{

    #[OA\Get(
        path: '/api/users',
        summary: 'Lista todos os utilizadores',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Utilizadores devolvidos com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'name', type: 'string', example: 'Miguel Ferreira'),
                                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'miguel.ferreira@example.pt'),
                                    new OA\Property(property: 'role', type: 'string', example: 'admin'),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Não autenticado'
            ),
        ]
    )]
    public function index(): JsonResponse
    {
        // Keep response lightweight and avoid exposing sensitive columns.
        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role']);

        return response()->json([
            'data' => $users,
        ]);
    }

    #[OA\Post(
        path: '/api/users',
        summary: 'Criar um novo utilizador',
        tags: ['Users'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'role'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Marco Varela'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'marco.varela@example.pt'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'Password@123'),
                    new OA\Property(property: 'role', type: 'string', enum: ['admin', 'nurse', 'head_nurse'], example: 'nurse'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Utilizador criado com sucesso.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Utilizador criado com sucesso.'),
                    ]
                )
            ),
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
                description: 'Erro de validação.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Dados invalidos ou em falta.'),
                    ]
                )
            ),
        ]
    )]
    public function store(StoreUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Create the user with the requested data and enforce default values
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'],
            'active' => true,
            'must_change_password' => true,
        ]);

        return response()->json([
            'message' => 'Utilizador criado com sucesso.',
        ], 201);

    }


    #[OA\Patch(
        path: '/api/users/{id}',
        summary: 'Editar um utilizador existente',
        tags: ['Users'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID do utilizador a editar',
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Mario Varela'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'mario.varela@example.pt'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Utilizador atualizado com sucesso.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Utilizador atualizado com sucesso.'),
                    ]
                )
            ),
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
                description: 'Utilizador não encontrado.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Utilizador não encontrado.'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Erro de validação.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Dados invalidos ou em falta.'),
                    ]
                )
            ),
        ]
    )]
    public function update(UpdateUserRequest $request, $id): JsonResponse
    {
        // Fetch the user or automatically return 404 if the ID doesn't exist
        $user = User::findOrFail($id);

        // validated() contains only the validated fields that the frontend sent
        $validated = $request->validated();

        // update() will only change the fields present in the array
        $user->update($validated);

        return response()->json([
            'message' => 'Utilizador atualizado com sucesso.',
        ], 200);

    }


    #[OA\Delete(
        path: '/api/users/{id}',
        summary: 'Eliminar um utilizador',
        tags: ['Users'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID do utilizador a eliminar',
                schema: new OA\Schema(type: 'integer', example: 13)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Utilizador eliminado com sucesso.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Utilizador eliminado com sucesso.'),
                    ]
                )
            ),
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
                description: 'Utilizador não encontrado.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Utilizador não encontrado.'),
                    ]
                )
            ),
        ]
    )]
    public function destroy(DestroyUserRequest $request, $id): JsonResponse
    {
        // Fetch the user or return 404 if the ID doesn't exist
        $user = User::findOrFail($id);

        // Delete the user 
        $user->delete();

        return response()->json([
            'message' => 'Utilizador eliminado com sucesso.',
        ], 200);
    }


}
