<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceProfileController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function show(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();

        return response()->json([
            'data' => [
                'uuid' => $device->uuid,
                'name' => $device->name,
                'platform' => $device->platform,
                'model' => $device->model,
                'os_version' => $device->os_version,
                'status' => $device->status,
                'enrolled_at' => $device->enrolled_at?->toIso8601String(),
                'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            ],
        ]);
    }

    public function unenroll(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();

        $device->tokens()->delete();
        $device->forceFill([
            'status' => Device::STATUS_UNENROLLED,
            'revoked_at' => now(),
            'fcm_token' => null,
        ])->save();

        $this->audit->log('device.unenrolled', null, $device, $device);

        return response()->json([
            'data' => [
                'uuid' => $device->uuid,
                'status' => $device->status,
            ],
        ]);
    }
}
