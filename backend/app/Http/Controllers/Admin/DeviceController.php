<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\DeviceConsent;
use App\Models\LiveSession;
use App\Services\AuditLogger;
use App\Services\LiveSessionFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly LiveSessionFactory $liveSessions,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Device::class);

        $query = Device::query()
            ->with(['latestStatus', 'latestLocation', 'enrolledBy'])
            ->latest('enrolled_at');

        if ($search = $request->string('q')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('uuid', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('os_version', 'like', "%{$search}%");
            });
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return view('admin.devices.index', [
            'devices' => $query->paginate(20)->withQueryString(),
            'threshold' => (int) config('cdm.device_online_threshold_minutes', 5),
            'filters' => [
                'q' => $search ?? '',
                'status' => $status ?? '',
            ],
        ]);
    }

    public function show(Device $device): View
    {
        $this->authorize('view', $device);

        $device->load(['latestStatus', 'latestLocation', 'enrolledBy']);

        $activeSession = $device->liveSessions()
            ->with('requester')
            ->whereIn('status', [
                LiveSession::STATUS_PENDING,
                LiveSession::STATUS_APPROVED,
                LiveSession::STATUS_ACTIVE,
            ])
            ->latest()
            ->first();

        $track = $device->locations()
            ->orderBy('recorded_at')
            ->limit(5000)
            ->get(['latitude', 'longitude', 'accuracy', 'recorded_at']);

        $trackJson = $track->map(static function ($row) {
            return [
                'latitude' => (float) $row->latitude,
                'longitude' => (float) $row->longitude,
                'accuracy' => $row->accuracy !== null ? (float) $row->accuracy : null,
                'recorded_at' => $row->recorded_at?->toIso8601String(),
            ];
        })->values()->all();

        return view('admin.devices.show', [
            'device' => $device,
            'locations' => $track->sortByDesc('recorded_at')->values(),
            'track' => $track,
            'trackJson' => $trackJson,
            'statuses' => $device->statuses()->latest('reported_at')->limit(20)->get(),
            'commands' => $device->commands()->latest()->limit(20)->get(),
            'sessions' => $device->liveSessions()->with('requester')->latest()->limit(20)->get(),
            'activeSession' => $activeSession,
            'threshold' => (int) config('cdm.device_online_threshold_minutes', 5),
            'hasLocationConsent' => $device->hasActiveConsent(DeviceConsent::TYPE_LOCATION),
            'hasLiveMicConsent' => $device->hasActiveConsent(DeviceConsent::TYPE_LIVE_MICROPHONE),
            'webrtcConfigured' => (bool) config('cdm.webrtc_configured'),
        ]);
    }

    public function livePulse(Device $device): JsonResponse
    {
        $this->authorize('view', $device);

        $device->load(['latestStatus', 'latestLocation']);
        $session = $device->liveSessions()
            ->whereIn('status', [
                LiveSession::STATUS_PENDING,
                LiveSession::STATUS_APPROVED,
                LiveSession::STATUS_ACTIVE,
            ])
            ->latest()
            ->first();

        $loc = $device->latestLocation;

        $track = $device->locations()
            ->orderBy('recorded_at')
            ->limit(5000)
            ->get(['latitude', 'longitude', 'accuracy', 'recorded_at'])
            ->map(fn ($row) => [
                'latitude' => (float) $row->latitude,
                'longitude' => (float) $row->longitude,
                'accuracy' => $row->accuracy !== null ? (float) $row->accuracy : null,
                'recorded_at' => $row->recorded_at?->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'data' => [
                'uuid' => $device->uuid,
                'name' => $device->name,
                'status' => $device->status,
                'online' => $device->isOnline(),
                'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                'battery_level' => $device->latestStatus?->battery_level,
                'is_charging' => $device->latestStatus?->is_charging,
                'network_type' => $device->latestStatus?->network_type,
                'location' => $loc ? [
                    'latitude' => (float) $loc->latitude,
                    'longitude' => (float) $loc->longitude,
                    'accuracy' => $loc->accuracy !== null ? (float) $loc->accuracy : null,
                    'recorded_at' => $loc->recorded_at?->toIso8601String(),
                ] : null,
                'track' => $track,
                'track_count' => $track->count(),
                'session' => $session ? [
                    'uuid' => $session->uuid,
                    'status' => $session->status,
                    'purpose' => $session->purpose,
                    'request_camera' => $session->request_camera,
                    'request_microphone' => $session->request_microphone,
                    'device_offer_sdp' => $session->device_offer_sdp,
                    'admin_answer_sdp' => $session->admin_answer_sdp,
                    'device_ice' => $session->device_ice ?? [],
                    'admin_ice' => $session->admin_ice ?? [],
                ] : null,
            ],
        ]);
    }

    public function revoke(Request $request, Device $device): RedirectResponse
    {
        $this->authorize('revoke', $device);

        DB::transaction(function () use ($device) {
            $device->tokens()->delete();
            $device->forceFill([
                'status' => Device::STATUS_REVOKED,
                'revoked_at' => now(),
                'fcm_token' => null,
            ])->save();
        });

        $this->audit->log('device.revoked', $request->user(), $device, $device);

        return back()->with('success', 'Device revoked and tokens invalidated.');
    }

    public function issueCommand(Request $request, Device $device): RedirectResponse
    {
        $this->authorize('issueCommand', $device);

        $data = $request->validate([
            'type' => ['required', 'string', 'in:check_in,notify,request_location,request_status'],
            'message' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        $ttl = (int) config('cdm.command_ttl_minutes', 30);

        if (! empty($data['idempotency_key'])) {
            $existing = $device->commands()
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existing) {
                return back()->with('success', 'Command already issued (idempotent).');
            }
        }

        $command = DeviceCommand::query()->create([
            'device_id' => $device->id,
            'issued_by' => $request->user()->id,
            'type' => $data['type'],
            'payload' => ['message' => $data['message'] ?? null],
            'status' => DeviceCommand::STATUS_PENDING,
            'expires_at' => now()->addMinutes($ttl),
            'idempotency_key' => $data['idempotency_key'] ?? null,
        ]);

        $this->audit->log('command.issued', $request->user(), $device, $command, [
            'type' => $command->type,
        ]);

        return back()->with('success', 'Command queued for device.');
    }

    public function requestLiveSession(Request $request, Device $device): RedirectResponse
    {
        $this->authorize('requestLiveSession', $device);

        $data = $request->validate([
            'purpose' => ['required', 'string', 'max:255'],
            'request_camera' => ['nullable', 'boolean'],
            'request_microphone' => ['nullable', 'boolean'],
        ]);

        $wantCamera = $request->boolean('request_camera', true);
        $wantMic = $request->boolean('request_microphone', true);

        $result = $this->liveSessions->create(
            $device,
            $request->user()->id,
            $data['purpose'],
            $wantCamera,
            $wantMic,
        );

        $session = $result['session'];
        $this->audit->log(
            $result['auto_approved'] ? 'live_session.auto_approved' : 'live_session.requested',
            $request->user(),
            $device,
            $session,
        );

        return back()->with(
            'success',
            $result['auto_approved']
                ? 'Live session started — device already granted consent (no new approval prompt).'
                : 'Live session request sent. Awaiting one-time approval on the device.'
        );
    }

    public function requestListenAudio(Request $request, Device $device): RedirectResponse|JsonResponse
    {
        $this->authorize('requestLiveSession', $device);

        $result = $this->liveSessions->create(
            $device,
            $request->user()->id,
            'Live audio listen (admin requested)',
            false,
            true,
        );

        $session = $result['session'];
        $this->audit->log(
            $result['auto_approved'] ? 'live_session.audio_auto_approved' : 'live_session.audio_listen_requested',
            $request->user(),
            $device,
            $session,
        );

        $message = $result['auto_approved']
            ? 'Listening started — mic consent already granted on this device (no prompt each time).'
            : 'Audio listen request sent. Approve once on the device; later listens will not ask again while consent stays on.';

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'ok' => true,
                    'auto_approved' => $result['auto_approved'],
                    'message' => $message,
                    'session' => [
                        'uuid' => $session->uuid,
                        'status' => $session->status,
                        'request_microphone' => (bool) $session->request_microphone,
                        'device_offer_sdp' => $session->device_offer_sdp,
                        'admin_answer_sdp' => $session->admin_answer_sdp,
                        'device_ice' => $session->device_ice ?? [],
                        'admin_ice' => $session->admin_ice ?? [],
                    ],
                ],
            ]);
        }

        return back()->with('success', $message);
    }

    public function postAdminAnswer(Request $request, Device $device, LiveSession $liveSession): JsonResponse
    {
        $this->authorize('requestLiveSession', $device);
        abort_unless($liveSession->device_id === $device->id, 404);

        $data = $request->validate([
            'answer_sdp' => ['required', 'string'],
            'ice' => ['nullable', 'array'],
        ]);

        $liveSession->forceFill([
            'admin_answer_sdp' => $data['answer_sdp'],
            'admin_ice' => array_values(array_merge($liveSession->admin_ice ?? [], $data['ice'] ?? [])),
        ])->save();

        return response()->json(['data' => ['ok' => true]]);
    }

    public function postAdminIce(Request $request, Device $device, LiveSession $liveSession): JsonResponse
    {
        $this->authorize('requestLiveSession', $device);
        abort_unless($liveSession->device_id === $device->id, 404);

        $data = $request->validate([
            'candidate' => ['required', 'array'],
        ]);

        $ice = $liveSession->admin_ice ?? [];
        $ice[] = $data['candidate'];
        $liveSession->forceFill(['admin_ice' => $ice])->save();

        return response()->json(['data' => ['ok' => true]]);
    }
}
