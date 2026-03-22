<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Centralised exception → JSON response mapping for API consumers.
 *
 * Laravel 12 bootstrap/app.php handles most cases inline, but this class
 * provides a single place to add new mappings as the API grows.
 */
class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     */
    protected $levels = [];

    /**
     * A list of the exception types that are not reported.
     */
    protected $dontReport = [];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Hook for Sentry / Bugsnag:
            // if (app()->bound('sentry')) {
            //     app('sentry')->captureException($e);
            // }
        });

        // Domain-level InvalidArgumentException (e.g. reserved slug, duplicate slug)
        $this->renderable(function (\InvalidArgumentException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return new JsonResponse(['message' => $e->getMessage()], 422);
            }
        });

        // Runtime errors that are our fault — don't expose internals
        $this->renderable(function (\RuntimeException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                report($e); // Still logged/tracked

                return new JsonResponse([
                    'message' => 'An unexpected error occurred. Please try again.',
                ], 500);
            }
        });
    }
}
