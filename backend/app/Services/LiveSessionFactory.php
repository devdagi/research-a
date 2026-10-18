<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceConsent;
use App\Models\LiveSession;
use Illuminate\Support\Str;

class LiveSessionFactory
{
    /**
     * Create a live session. If the device already granted the needed consents,
     * auto-approve so the owner is not prompted on every admin request.
     */
    public function create(
        Device $device,
        int $requestedBy,
        string $purpose,
        bool $requestCamera,
        bool $requestMicrophone,
    ): array {
        // Close any older open sessions so the phone is free to join this one.
        $this->closeOpenSessions($device);

        $ttl = (int) config('cdm.live_session_ttl_minutes', 15);
        $auto = $this->deviceHasConsent($device, $requestCamera, $requestMicrophone);

        $signalingToken = null;
        $attrs = [
            'device_id' => $device->id,
            'requested_by' => $requestedBy,
            'purpose' => $purpose,
            'request_camera' => $requestCamera,
            'request_microphone' => $requestMicrophone,
            'status' => $auto ? LiveSession::STATUS_APPROVED : LiveSession::STATUS_PENDING,
            'expires_at' => now()->addMinutes($ttl),
        ];

        if ($auto) {
            $signalingToken = Str::random(64);
            $attrs['responded_at'] = now();
            $attrs['signaling_token_hash'] = hash('sha256', $signalingToken);
        }

        $session = LiveSession::query()->create($attrs);

        return [
            'session' => $session,
            'auto_approved' => $auto,
            'signaling_token' => $signalingToken,
        ];
    }

    public function closeOpenSessions(Device $device): void
    {
        $device->liveSessions()
            ->whereIn('status', [
                LiveSession::STATUS_PENDING,
                LiveSession::STATUS_APPROVED,
                LiveSession::STATUS_ACTIVE,
            ])
            ->update([
                'status' => LiveSession::STATUS_CANCELLED,
                'ended_at' => now(),
                'ended_by' => 'admin',
                'device_offer_sdp' => null,
                'admin_answer_sdp' => null,
                'device_ice' => null,
                'admin_ice' => null,
                'signaling_token_hash' => null,
            ]);
    }

    public function deviceHasConsent(Device $device, bool $requestCamera, bool $requestMicrophone): bool
    {
        if ($requestCamera && ! $device->hasActiveConsent(DeviceConsent::TYPE_LIVE_CAMERA)) {
            return false;
        }

        if ($requestMicrophone && ! $device->hasActiveConsent(DeviceConsent::TYPE_LIVE_MICROPHONE)) {
            return false;
        }

        if (! $requestCamera && ! $requestMicrophone) {
            return false;
        }

        return true;
    }
}
