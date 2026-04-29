<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendPasswordRecoveryEmailRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ValidatePasswordRecoveryTokenRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
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
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'miguel.ferreira@example.pt'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password'),
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
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Utilizador inativo',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Este utilizador está inativo.'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Credenciais inválidas',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Credenciais inválidas'),
                    ]
                )
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
                [__('auth.invalid_credentials')],
            ]);
        }

        // Prevent inactive users from logging into the API.
        if (! $user->active) {
            return response()->json([
                'message' => __('auth.inactive_user'),
            ], 403);
        }

        // If the user is required to change their password, do not authenticate them yet.
        // Instead, generate a Laravel password reset token so the frontend can redirect
        // them to the existing reset-password flow and reuse the current reset endpoint.
        if ($user->must_change_password) {
            $resetToken = Password::createToken($user);

            return response()->json([
                'message' => 'Tem que alterar a password',
                'must_change_password' => true,
                'email' => $user->email,
                'password_reset_token' => $resetToken,
            ]);
        }

        // Create a new Sanctum personal access token only when the user is allowed
        // to complete the login flow and access the application immediately.
        $token = $user->createToken('auth-token')->plainTextToken;

        // Return the API token and the authenticated user's public data
        return response()->json([
            'message' => __('auth.login_success'),
            'token' => $token,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role instanceof \BackedEnum ? $user->role->value : $user->role,
                'must_change_password' => $user->must_change_password,
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
                description: 'Utilizador autenticado returnado com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'user',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'name', type: 'string', example: 'Miguel Ferreira'),
                                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'miguel.ferreira@example.pt'),
                                new OA\Property(property: 'role', type: 'string', example: 'admin'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Utilizador não autenticado',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Utilizador não autenticado.'
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

        // Return the authenticated user's public data, including the
        // must_change_password flag so the frontend can preserve the
        // forced-password-change flow after page reloads
        return response()->json([
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role instanceof \BackedEnum ? $user->role->value : $user->role,
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
                description: 'Logout efetuado com sucesso',
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
                description: 'Não autenticado',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Não autenticado.'
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


    #[OA\Post(
        path: '/api/password-recovery/email',
        summary: 'Envia o email de recuperação de password',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'miguel.ferreira@example.pt'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 202,
                description: 'Pedido aceite',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Se o email existir, será enviado um link de recuperação.'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Payload inválido',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'O email deve ter um formato válido.'),
                    ]
                )
            ),
        ]
    )]
    public function sendPasswordRecoveryEmail(SendPasswordRecoveryEmailRequest $request): JsonResponse
    {
        // Triggers Laravel's password broker to send the password reset link to the provided email address
        Password::sendResetLink($request->validated());

        // Returns a JSON response confirming that the password recovery email has been queued/sent
        return response()->json([
            'message' => __('auth.password_recovery_email_sent'),
        ], 202);
    }

    // Maps Laravel password broker status codes to translated user-friendly messages
    private function passwordBrokerStatusMessage(string $status): string
    {
        return match ($status) {

            // Returned when the provided password reset token is invalid
            Password::INVALID_TOKEN => __('auth.password_reset_token_invalid'),

            // Returned when no user is found for the provided credentials
            Password::INVALID_USER => __('auth.password_reset_user_not_found'),

            // Returned when the password reset link has been successfully sent
            Password::RESET_LINK_SENT => __('auth.password_reset_link_sent'),

            // Returned when the password has been successfully reset
            Password::PASSWORD_RESET => __('auth.password_reset_completed'),

            // Fallback message for any unexpected or unmapped status
            default => __('auth.password_reset_token_invalid'),
        };
    }


    #[OA\Post(
        path: '/api/password-recovery/reset',
        summary: 'Redefine a password com token de recuperação',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'token', type: 'string', example: '4f2d7d8f2f3f...'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'miguel.ferreira@example.pt'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'NovaPassword@123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'NovaPassword@123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Password redefinida com sucesso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Password redefinida com sucesso.'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Token inválido ou payload inválido',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Dados inválidos.'),
                        new OA\Property(
                            property: 'errors',
                            type: 'object',
                            example: [
                                'email' => ['Token inválido.'],
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        // Attempts to reset the password through Laravel's password broker using the validated request data
        $status = Password::reset(
            $request->validated(),
            function (User $user, string $password): void {
                // Verify if the new password is the same as the current one
                    if (Hash::check($password, $user->password)) {
                        throw ValidationException::withMessages([
                            'password' => [__('auth.new_password_must_be_different')],
                        ]);
                    }
                // If the new password is different, update the user's password
                $user->forceFill([
                    'password' => $password,
                    'must_change_password' => false,
                ])->save();    
                $user->tokens()->delete();
                event(new PasswordReset($user));
            }  
        );

        // Throws a validation error if the password broker did not complete the reset successfully
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => [$this->passwordBrokerStatusMessage($status)],
            ]);
        }

        // Returns a JSON response confirming that the password was reset successfully
        return response()->json([
            'message' => __('auth.password_reset_success'),
        ]);
    }



    #[OA\Get(
        path: '/api/password-recovery/validate-token',
        summary: 'Valida o token de recuperação de password',
        tags: ['Auth'],
        parameters: [
            new OA\Parameter(
                name: 'token',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string'),
                example: '4f2d7d8f2f3f...'
            ),
            new OA\Parameter(
                name: 'email',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'email'),
                example: 'miguel.ferreira@example.pt'
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Token válido',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Token válido.'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Token inválido ou expirado',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Dados inválidos.'),
                        new OA\Property(
                            property: 'errors',
                            type: 'object',
                            example: [
                                'token' => ['O token de recuperação é inválido ou expirou.'],
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function validatePasswordRecoveryToken(ValidatePasswordRecoveryTokenRequest $request): JsonResponse
    {
        // Retrieves the already validated token and email from the request
        $validated = $request->validated();

        // Looks up the user associated with the provided email address
        $user = User::where('email', $validated['email'])->first();

        // Throws a validation error if no user exists for the given email
        if (! $user) {
            throw ValidationException::withMessages([
                'token' => [__('auth.invalid_or_expired_reset_token')],
            ]);
        }

        // Retrieves the password reset token record for the given email from the configured password reset table
        $record = DB::table(config('auth.passwords.users.table', 'password_reset_tokens'))
            ->where('email', $validated['email'])
            ->first();

        // Throws a validation error if no reset token record exists for the given email
        if (! $record) {
            throw ValidationException::withMessages([
                'token' => [__('auth.invalid_or_expired_reset_token')],
            ]);
        }

        // Calculates the token expiration time based on the record creation time and configured expiration window
        $expiresAt = Carbon::parse($record->created_at)
            ->addMinutes((int) config('auth.passwords.users.expire', 60));

        // Throws a validation error if the token has expired or does not match the hashed token stored in the database
        if ($expiresAt->isPast() || ! Hash::check($validated['token'], $record->token)) {
            throw ValidationException::withMessages([
                'token' => [__('auth.invalid_or_expired_reset_token')],
            ]);
        }

        // Returns a JSON response confirming that the password recovery token is valid
        return response()->json([
            'message' => __('auth.valid_reset_token'),
        ]);
    }
}
