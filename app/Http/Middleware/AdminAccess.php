<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return $this->unauthorizedResponse('Authentication required');
        }

        if (!$user->isAdmin()) {
            return $this->forbiddenResponse('Admin access required');
        }

        if (!$user->is_active) {
            return $this->forbiddenResponse('Account is inactive');
        }

        return $next($request);
    }

    private function unauthorizedResponse(string $message): Response
    {
        return response()->json([
            'error' => 'Unauthorized',
            'message' => $message,
        ], 401);
    }

    private function forbiddenResponse(string $message): Response
    {
        return response()->json([
            'error' => 'Forbidden',
            'message' => $message,
        ], 403);
    }
}