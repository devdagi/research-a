<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\LiveSession;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class MapAudioController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Device::class);

        return view('admin.map_audio.index', [
            'mapboxToken' => config('cdm.mapbox_access_token'),
            'mapboxStyle' => config('cdm.mapbox_style'),
            'devicesJson' => $this->fleetPayload(),
        ]);
    }

    public function fleetPulse(): JsonResponse
    {
        $this->authorize('viewAny', Device::class);

        return response()->json(['data' => $this->fleetPayload()]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fleetPayload(): array
    {
        $threshold = (int) config('cdm.device_online_threshold_minutes', 5);

        return Device::query()
            ->where('status', Device::STATUS_ACTIVE)
            ->with([
                'latestLocation',
                'latestStatus',
                'liveSessions' => static function ($q) {
                    $q->whereIn('status', [
                        LiveSession::STATUS_PENDING,
                        LiveSession::STATUS_APPROVED,
                        LiveSession::STATUS_ACTIVE,
                    ])->latest();
                },
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Device $device) use ($threshold) {
                $loc = $device->latestLocation;
                $session = $device->liveSessions->first();

                return [
                    'uuid' => $device->uuid,
                    'name' => $device->name,
                    'platform' => $device->platform,
                    'model' => $device->model,
                    'online' => $device->isOnline($threshold),
                    'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                    'battery_level' => $device->latestStatus?->battery_level,
                    'is_charging' => $device->latestStatus?->is_charging,
                    'location' => $loc ? [
                        'latitude' => (float) $loc->latitude,
                        'longitude' => (float) $loc->longitude,
                        'accuracy' => $loc->accuracy !== null ? (float) $loc->accuracy : null,
                        'recorded_at' => $loc->recorded_at?->toIso8601String(),
                    ] : null,
                    'session' => $session ? [
                        'uuid' => $session->uuid,
                        'status' => $session->status,
                        'request_microphone' => (bool) $session->request_microphone,
                        'device_offer_sdp' => $session->device_offer_sdp,
                        'admin_answer_sdp' => $session->admin_answer_sdp,
                        'device_ice' => $session->device_ice ?? [],
                        'admin_ice' => $session->admin_ice ?? [],
                    ] : null,
                ];
            })
            ->values()
            ->all();
    }
}
