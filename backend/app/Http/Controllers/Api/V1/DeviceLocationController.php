<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceConsent;
use App\Models\DeviceLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceLocationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();

        if (! $device->hasActiveConsent(DeviceConsent::TYPE_LOCATION)) {
            return response()->json([
                'message' => 'Location consent is required before reporting location.',
                'error' => 'consent_required',
            ], 403);
        }

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'altitude' => ['nullable', 'numeric'],
            'speed' => ['nullable', 'numeric', 'min:0'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $location = DeviceLocation::query()->create([
            'device_id' => $device->id,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'accuracy' => $data['accuracy'] ?? null,
            'altitude' => $data['altitude'] ?? null,
            'speed' => $data['speed'] ?? null,
            'recorded_at' => $data['recorded_at'] ?? now(),
        ]);

        $device->touchLastSeen();

        return response()->json([
            'data' => [
                'id' => $location->id,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'accuracy' => $location->accuracy,
                'recorded_at' => $location->recorded_at?->toIso8601String(),
            ],
        ], 201);
    }
}
