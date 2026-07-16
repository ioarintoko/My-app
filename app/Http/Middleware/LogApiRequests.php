<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequests
{
    private const ERROR_THRESHOLD = 3;
    private const ERROR_WINDOW_MINUTES = 5;
    private const SLOW_THRESHOLD_MS = 2000;

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $durationMs = round((microtime(true) - $start) * 1000, 2);

        $logData = [
            'method' => $request->method(),
            'endpoint' => $request->path(),
            'status' => $response->getStatusCode(),
            'response_time_ms' => $durationMs,
            'user_id' => Auth::guard('api')->id(),
            'ip' => $request->ip(),
        ];

        Log::channel('api')->info('API Request', $logData);

        if ($durationMs > self::SLOW_THRESHOLD_MS) {
            Log::channel('alerts')->warning('Slow response detected', $logData);
        }

        if ($response->getStatusCode() >= 500) {
            $this->trackError($request, $logData);
        }

        return $response;
    }

    private function trackError(Request $request, array $logData): void
    {
        $key = 'error_count:' . $request->path();
        $count = Cache::get($key, 0) + 1;

        Cache::put($key, $count, now()->addMinutes(self::ERROR_WINDOW_MINUTES));

        Log::channel('alerts')->error('Server error detected', [
            ...$logData,
            'error_count_in_window' => $count,
        ]);

        if ($count >= self::ERROR_THRESHOLD) {
            Log::channel('alerts')->critical('ALERT: High error rate threshold exceeded', [
                'endpoint' => $request->path(),
                'error_count' => $count,
                'window_minutes' => self::ERROR_WINDOW_MINUTES,
                'message' => "Endpoint {$request->path()} mengalami {$count}x error dalam " . self::ERROR_WINDOW_MINUTES . ' menit terakhir.',
            ]);

            // Reset counter setelah alert terpicu, biar tidak spam alert terus-menerus
            Cache::forget($key);
        }
    }
}