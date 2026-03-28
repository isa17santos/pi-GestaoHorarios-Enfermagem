<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;


class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/login',
        summary: 'Autentica um utilizador e devolve um token',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login efetuado com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Login efetuado com sucesso.'),
                        new OA\Property(property: 'token', type: 'string', example: '1|abcdef123456'),
                        new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Utilizador inativo',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Este utilizador esta inativo.'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Credenciais inválidas'
            ),
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        // Validate and retrieve the incoming login credentials.
        $credentials = $request->validated();

        // Tries to find the user by email.
        $user = User::where('email', $credentials['email'])->first();

        // Reject the request if the user does not exist or the password is incorrect.
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.invalid_credentials')],
            ]);
        }

        // Prevent inactive users from logging into the API.
        if (! $user->active) {
            return response()->json([
                'message' => __('auth.inactive_user'),
            ], 403);
        }

        // Create a new Sanctum personal access token for this authenticated user.
        $token = $user->createToken('auth-token')->plainTextToken;

        // Return the token and the authenticated user data.
        return response()->json([
            'message' => __('auth.login_success'),
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role instanceof \BackedEnum ? $user->role->value : $user->role,
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/me',
        summary: 'Devolve o utilizador autenticado',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authenticated user returned successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'user',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'Administrador'),
                                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@example.com'),
                                new OA\Property(property: 'role', type: 'string', example: 'admin'),
                                new OA\Property(property: 'active', type: 'boolean', example: true),
                                new OA\Property(property: 'must_change_password', type: 'boolean', example: false),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Unauthenticated.'
                        ),
                    ]
                )
            ),
        ]
    )]
    public function me(Request $request): JsonResponse
    {
        // Get the currently authenticated user from the bearer token.
        $user = $request->user();

        // Return the authenticated user's public data.
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role instanceof \BackedEnum ? $user->role->value : $user->role,
                'active' => $user->active,
                'must_change_password' => $user->must_change_password,
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/logout',
        summary: 'Revoga o token atual',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Logout successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Logout efetuado com sucesso.'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Unauthenticated.'
                        ),
                    ]
                )
            ),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        // Revoke only the token used in the current request.
        $request->user()->currentAccessToken()->delete();

        // Return a translated success message.
        return response()->json([
            'message' => __('auth.logout_success'),
        ]);
    }
}
