<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Http\Middleware\ForceJsonResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // Register the application's route files
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Apply the locale middleware globally so each request uses the correct language.
        $middleware->append(SetLocale::class);


        // Prepend the ForceJsonResponse middleware to the API middleware group
        // so it runs before the other API middleware and ensures requests to
        // API routes are treated as expecting JSON responses.
        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Register a global exception renderer for JSON/API requests.
        // This lets us fully control the response payload instead of
        // exposing Laravel's default exception structure.
        $exceptions->render(function (Throwable $e, Request $request) {

            // Only apply the custom API error response to JSON requests or routes
            // under /api. For all other requests, keep Laravel's default behavior.
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }


            // Handle validation failures and return only one "message"
            // instead of Laravel's default "message" + "errors" payload.
            // We extract the first validation message defined in our
            // FormRequest classes or validation rules.
            if ($e instanceof ValidationException) {
                return response()->json([
                    'message' => collect($e->errors())->flatten()->first()
                        ?? __('validation.invalid'),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }


            // Handle unauthenticated requests, such as missing or invalid tokens.
            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'message' => __('auth.unauthenticated'),
                ], Response::HTTP_UNAUTHORIZED);
            }


            // Handle authenticated users trying to access a resource
            // or action they are not allowed to use. $this->authorize()
            // wraps this in an AccessDeniedHttpException before it
            // reaches here, so both need to be checked.
            if ($e instanceof AuthorizationException
                || ($e instanceof AccessDeniedHttpException && $e->getPrevious() instanceof AuthorizationException)) {
                $message = $request->is('api/swaps/*')
                    ? __('swap.unauthorized_action')
                    : __('auth.unauthorized');

                return response()->json([
                    'message' => $message,
                ], Response::HTTP_FORBIDDEN);
            }


            // Handle cases where a model lookup fails, such as
            // trying to access a record that does not exist. Route-model
            // binding wraps this in a NotFoundHttpException before it
            // reaches here, so both need to be checked.
            if ($e instanceof ModelNotFoundException
                || ($e instanceof NotFoundHttpException && $e->getPrevious() instanceof ModelNotFoundException)) {
                return response()->json([
                    'message' => __('messages.not_found'),
                ], Response::HTTP_NOT_FOUND);
            }


            // Handle generic HTTP exceptions like 404, 405, etc.
            // If the exception has a custom message, use it
            if ($e instanceof HttpExceptionInterface) {
                return response()->json([
                    'message' => $e->getMessage() ?: Response::$statusTexts[$e->getStatusCode()],
                ], $e->getStatusCode());
            }

            // Catch any unexpected exception and return a generic internal server error response
            return response()->json([
                'message' => __('messages.server_error'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        });
    })->create();
