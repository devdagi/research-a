<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceHeartbeatController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();
        $device->touchLastSeen();

        return response()->json([
            'data' => [
                'device_uuid' => $device->uuid,
                'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }
}
