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
            '/<script>/i',
            '/union select/i',
            '/select(.+)from/i',
            '/insert(.+)into/i',
            '/update(.+)set/i',
            '/delete(.+)from/i',
            '/\.env/i',
            '/wp-admin/i',
            '/phpmyadmin/i',
            '/\.\.\//', // Directory traversal
        ];

        foreach ($patterns as $pattern) {
            foreach ($input as $value) {
                if (is_string($value) && preg_match($pattern, $value)) {
                    abort(404);
                }
            }

            if (preg_match($pattern, $uri)) {
                abort(404);
            }
        }

        return $next($request);
    }
}
