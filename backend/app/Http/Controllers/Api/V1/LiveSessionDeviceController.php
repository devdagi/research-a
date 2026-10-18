<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\LiveSession;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LiveSessionDeviceController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function pending(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();

        // Pending = needs decision.
        // Approved / active without an offer = device must still publish WebRTC media.
        $sessions = $device->liveSessions()
            ->with('requester:id,name,email')
            ->whereIn('status', [
                LiveSession::STATUS_PENDING,
                LiveSession::STATUS_APPROVED,
                LiveSession::STATUS_ACTIVE,
            ])
            ->orderByDesc('created_at')
            ->get()
            ->filter(function (LiveSession $session) {
                if ($session->isExpired()) {
                    $session->forceFill(['status' => LiveSession::STATUS_EXPIRED])->save();

                    return false;
                }

                // Keep ACTIVE sessions visible so a background isolate can
                // re-attach WebRTC after Recents / process recovery.
                return true;
            })
            ->values()
            ->map(fn (LiveSession $s) => $this->serialize($s));

        return response()->json(['data' => $sessions]);
    }

    public function respond(Request $request, string $uuid): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();

        $data = $request->validate([
            'decision' => ['required', 'string', 'in:approved,denied'],
        ]);

        /** @var LiveSession|null $session */
        $session = $device->liveSessions()->where('uuid', $uuid)->first();

        if (! $session) {
            return response()->json(['message' => 'Session not found.', 'error' => 'not_found'], 404);
        }

        if ($session->status !== LiveSession::STATUS_PENDING) {
            return response()->json([
                'message' => 'Session is not awaiting a decision.',
                'error' => 'invalid_state',
            ], 409);
        }

        if ($session->isExpired()) {
            $session->forceFill(['status' => LiveSession::STATUS_EXPIRED])->save();

            return response()->json(['message' => 'Session expired.', 'error' => 'expired'], 409);
        }

        $signalingToken = null;
        $updates = [
            'status' => $data['decision'] === 'approved'
                ? LiveSession::STATUS_APPROVED
                : LiveSession::STATUS_DENIED,
            'responded_at' => now(),
        ];

        if ($data['decision'] === 'approved') {
            $signalingToken = Str::random(64);
            $updates['signaling_token_hash'] = hash('sha256', $signalingToken);
        }

        $session->forceFill($updates)->save();

        $this->audit->log('live_session.'.$data['decision'], null, $device, $session);

        return response()->json([
            'data' => array_merge($this->serialize($session->fresh('requester')), [
                'signaling_token' => $signalingToken,
                'webrtc_configured' => (bool) config('cdm.webrtc_configured'),
                'ice_servers' => $this->iceServers(),
            ]),
        ]);
    }

    public function start(Request $request, string $uuid): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();

        /** @var LiveSession|null $session */
        $session = $device->liveSessions()->where('uuid', $uuid)->first();

        if (! $session || ! in_array($session->status, [
            LiveSession::STATUS_APPROVED,
            LiveSession::STATUS_ACTIVE,
        ], true)) {
            return response()->json([
                'message' => 'Session must be approved before starting.',
                'error' => 'invalid_state',
            ], 409);
        }

        if ($session->status === LiveSession::STATUS_ACTIVE) {
            return response()->json(['data' => $this->serialize($session->fresh('requester'))]);
        }

        $session->forceFill([
            'status' => LiveSession::STATUS_ACTIVE,
            'started_at' => now(),
        ])->save();

        $this->audit->log('live_session.started', null, $device, $session);

        return response()->json(['data' => $this->serialize($session)]);
    }

    public function end(Request $request, string $uuid): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();

        /** @var LiveSession|null $session */
        $session = $device->liveSessions()->where('uuid', $uuid)->first();

        if (! $session) {
            return response()->json(['message' => 'Session not found.', 'error' => 'not_found'], 404);
        }

        if (! in_array($session->status, [LiveSession::STATUS_APPROVED, LiveSession::STATUS_ACTIVE], true)) {
            return response()->json([
                'message' => 'Session cannot be ended from its current state.',
                'error' => 'invalid_state',
            ], 409);
        }

        $session->forceFill([
            'status' => LiveSession::STATUS_ENDED,
            'ended_at' => now(),
            'ended_by' => 'device',
            'signaling_token_hash' => null,
        ])->save();

        $this->audit->log('live_session.ended', null, $device, $session, ['ended_by' => 'device']);

        return response()->json(['data' => $this->serialize($session)]);
    }

    public function postOffer(Request $request, string $uuid): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();

        $data = $request->validate([
            'offer_sdp' => ['required', 'string'],
            'ice' => ['nullable', 'array'],
        ]);

        /** @var LiveSession|null $session */
        $session = $device->liveSessions()->where('uuid', $uuid)->first();

        if (! $session || ! in_array($session->status, [LiveSession::STATUS_APPROVED, LiveSession::STATUS_ACTIVE], true)) {
            return response()->json(['message' => 'Session not active.', 'error' => 'invalid_state'], 409);
        }

        // Fresh offer (e.g. after lock-screen reconnect) invalidates the old answer.
        $session->forceFill([
            'device_offer_sdp' => $data['offer_sdp'],
            'device_ice' => array_values($data['ice'] ?? []),
            'admin_answer_sdp' => null,
            'admin_ice' => [],
        ])->save();

        return response()->json(['data' => $this->serialize($session->fresh('requester'))]);
    }

    public function postDeviceIce(Request $request, string $uuid): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();

        $data = $request->validate([
            'candidate' => ['required', 'array'],
        ]);

        /** @var LiveSession|null $session */
        $session = $device->liveSessions()->where('uuid', $uuid)->first();
        if (! $session) {
            return response()->json(['message' => 'Session not found.', 'error' => 'not_found'], 404);
        }

        $ice = $session->device_ice ?? [];
        $ice[] = $data['candidate'];
        $session->forceFill(['device_ice' => $ice])->save();

        return response()->json(['data' => ['ok' => true]]);
    }

    public function signaling(Request $request, string $uuid): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();

        /** @var LiveSession|null $session */
        $session = $device->liveSessions()->where('uuid', $uuid)->first();
        if (! $session) {
            return response()->json(['message' => 'Session not found.', 'error' => 'not_found'], 404);
        }

        return response()->json([
            'data' => [
                'status' => $session->status,
                'admin_answer_sdp' => $session->admin_answer_sdp,
                'admin_ice' => $session->admin_ice ?? [],
                'device_offer_sdp' => $session->device_offer_sdp,
                'device_ice' => $session->device_ice ?? [],
                'ice_servers' => $this->iceServers(),
            ],
        ]);
    }

    private function serialize(LiveSession $session): array
    {
        return [
            'uuid' => $session->uuid,
            'status' => $session->status,
            'purpose' => $session->purpose,
            'request_camera' => $session->request_camera,
            'request_microphone' => $session->request_microphone,
            'requester' => [
                'name' => $session->requester?->name,
                'email' => $session->requester?->email,
            ],
            'expires_at' => $session->expires_at?->toIso8601String(),
            'started_at' => $session->started_at?->toIso8601String(),
            'ended_at' => $session->ended_at?->toIso8601String(),
            'admin_answer_sdp' => $session->admin_answer_sdp,
            'admin_ice' => $session->admin_ice ?? [],
        ];
    }

    private function iceServers(): array
    {
        $servers = [['urls' => 'stun:stun.l.google.com:19302']];
        $turnUrls = config('cdm.turn_urls', []);

        if ($turnUrls) {
            $servers[] = array_filter([
                'urls' => $turnUrls,
                'username' => config('cdm.turn_username'),
                'credential' => config('cdm.turn_credential'),
            ]);
        }

        return $servers;
    }
}
