<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $input = $request->all();
        $uri = $request->getRequestUri();

        // Common malicious patterns
        $patterns = [
            '/<script\b/i',
            '/union\s+select/i',
            '/select\s+.+\s+from/i',
            '/insert\s+into/i',
            '/update\s+.+\s+set/i',
            '/delete\s+from/i',
            '/\.env\b/i',
            '/wp-admin/i',
            '/phpmyadmin/i',
            '/\.\.\//', // Directory traversal
        ];

        // [SECURITY FIX Q-5] Recursively flatten nested arrays to prevent bypass
        $flatValues = $this->flattenInput($input);

        foreach ($patterns as $pattern) {
            foreach ($flatValues as $value) {
                if (is_string($value) && preg_match($pattern, $value)) {
                    abort(404);
                }
            }

            if (preg_match($pattern, $uri)) {
                abort(404);
            }
        }

        $response = $next($request);

        // [SECURITY FIX Q-1] Standard security response headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        return $response;
    }

    /**
     * Recursively flatten nested input arrays into a flat list of scalar values.
     */
    private function flattenInput(array $data): array
    {
        $result = [];
        array_walk_recursive($data, function ($value) use (&$result) {
            $result[] = $value;
        });
        return $result;
    }
}
