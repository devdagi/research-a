<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceStatusController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();

        $data = $request->validate([
            'battery_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'is_charging' => ['nullable', 'boolean'],
            'network_type' => ['nullable', 'string', 'max:32'],
            'is_online' => ['nullable', 'boolean'],
            'extras' => ['nullable', 'array'],
            'reported_at' => ['nullable', 'date'],
        ]);

        $status = DeviceStatus::query()->create([
            'device_id' => $device->id,
            'battery_level' => $data['battery_level'] ?? null,
            'is_charging' => $data['is_charging'] ?? null,
            'network_type' => $data['network_type'] ?? null,
            'is_online' => $data['is_online'] ?? true,
            'extras' => $data['extras'] ?? null,
            'reported_at' => isset($data['reported_at']) ? $data['reported_at'] : now(),
        ]);

        $device->touchLastSeen();

        return response()->json([
            'data' => [
                'id' => $status->id,
                'battery_level' => $status->battery_level,
                'is_charging' => $status->is_charging,
                'network_type' => $status->network_type,
                'reported_at' => $status->reported_at?->toIso8601String(),
            ],
        ], 201);
    }
}
