<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * @OA\Tag(name="Health", description="Service health and readiness checks")
 */
class HealthCheckController extends Controller
{
    /**
     * @OA\Get(
     *     path="/health",
     *     operationId="healthCheck",
     *     tags={"Health"},
     *     summary="Service health check",
     *     description="Returns the health status of the service and its dependencies. Use for k8s liveness/readiness probes.",
     *
     *     @OA\Response(
     *         response=200,
     *         description="Service healthy",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="healthy"),
     *             @OA\Property(property="checks", type="object",
     *                 @OA\Property(property="database", type="string", example="ok"),
     *                 @OA\Property(property="cache", type="string", example="ok"),
     *                 @OA\Property(property="queue", type="string", example="ok")
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     ),
     *
     *     @OA\Response(response=503, description="Service unhealthy")
     * )
     */
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache'    => $this->checkCache(),
            'queue'    => $this->checkQueue(),
        ];

        $healthy = ! in_array('error', $checks, true);

        return response()->json([
            'status'    => $healthy ? 'healthy' : 'degraded',
            'checks'    => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }

    private function checkDatabase(): string
    {
        try {
            DB::statement('SELECT 1');

            return 'ok';
        } catch (\Throwable) {
            return 'error';
        }
    }

    private function checkCache(): string
    {
        try {
            Cache::set('_health', 1, 5);

            return Cache::get('_health') === 1 ? 'ok' : 'error';
        } catch (\Throwable) {
            return 'error';
        }
    }

   private function checkQueue(): string
    {
        try {
            // Only ping Redis if queue connection is redis
            if (config('queue.default') !== 'redis') {
                return 'ok (sync)';
            }
            Redis::ping();
            return 'ok';
        } catch (\Throwable) {
            return 'error';
        }
    }
}
