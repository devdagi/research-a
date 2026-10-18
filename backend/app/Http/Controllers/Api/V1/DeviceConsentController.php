<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceConsent;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceConsentController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();

        $consents = $device->consents()
            ->orderByDesc('decided_at')
            ->limit(50)
            ->get()
            ->map(fn (DeviceConsent $c) => [
                'id' => $c->id,
                'type' => $c->type,
                'status' => $c->status,
                'version' => $c->version,
                'decided_at' => $c->decided_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $consents]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();

        $data = $request->validate([
            'type' => ['required', 'string', 'in:location,live_camera,live_microphone,notifications'],
            'status' => ['required', 'string', 'in:granted,denied,withdrawn'],
            'version' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $consent = DeviceConsent::query()->create([
            'device_id' => $device->id,
            'type' => $data['type'],
            'status' => $data['status'],
            'version' => $data['version'] ?? '1',
            'notes' => $data['notes'] ?? null,
            'decided_at' => now(),
        ]);

        $this->audit->log('consent.'.$data['status'], null, $device, $consent, [
            'type' => $data['type'],
        ]);

        return response()->json([
            'data' => [
                'id' => $consent->id,
                'type' => $consent->type,
                'status' => $consent->status,
                'decided_at' => $consent->decided_at?->toIso8601String(),
            ],
        ], 201);
    }
}
