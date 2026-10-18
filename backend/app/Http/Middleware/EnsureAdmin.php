<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! in_array($user->role, [User::ROLE_ADMIN, User::ROLE_VIEWER], true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Staff access required.',
                    'error' => 'forbidden',
                ], 403);
            }

            abort(403, 'Staff access required.');
        }

        return $next($request);
    }
}
