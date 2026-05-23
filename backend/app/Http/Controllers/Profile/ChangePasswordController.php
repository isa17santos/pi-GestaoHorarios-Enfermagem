<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\ChangePasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class ChangePasswordController extends Controller
{
    #[OA\Post(
        path: '/api/profile/change-password',
        summary: 'Altera a password do utilizador autenticado',
        tags: ['Profile'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string', format: 'password', example: 'PasswordAtual@123'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'NovaPassword@123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'NovaPassword@123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Password alterada com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Password alterada com sucesso.'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Password atual inválida ou payload inválido',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Dados inválidos.'),
                        new OA\Property(
                            property: 'errors',
                            type: 'object',
                            example: [
                                'current_password' => ['A password atual está incorreta.'],
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function __invoke(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => [__('auth.new_password_must_be_different')],
            ]);
        }

        $user->forceFill([
            'password' => $request->password,
        ])->save();

        return response()->json([
            'message' => __('auth.password_changed_success'),
        ], 200);
    }
}