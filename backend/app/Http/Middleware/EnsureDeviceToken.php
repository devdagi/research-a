<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeviceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Device) {
            return response()->json([
                'message' => 'Device authentication required.',
                'error' => 'unauthenticated_device',
            ], 401);
        }

        if (! $user->isActive()) {
            return response()->json([
                'message' => 'Device has been revoked or unenrolled.',
                'error' => 'device_revoked',
            ], 403);
        }

        if (! $user->tokenCan('device')) {
            return response()->json([
                'message' => 'Invalid device token abilities.',
                'error' => 'forbidden',
            ], 403);
        }

        $request->attributes->set('device', $user);

        return $next($request);
    }
}
