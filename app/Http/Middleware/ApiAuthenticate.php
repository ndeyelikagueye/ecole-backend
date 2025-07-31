<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;

class ApiAuthenticate
{
    public function handle(Request $request, Closure $next, ...$guards)
    {
        if (! auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié. Token requis.',
                'error' => 'Unauthenticated'
            ], 401);
        }

        return $next($request);
    }
}