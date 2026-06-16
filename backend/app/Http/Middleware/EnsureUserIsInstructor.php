<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsInstructor
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->canInstruct()) {
            return response()->json([
                'message' => 'Instructor role required.',
            ], 403);
        }

        return $next($request);
    }
}
