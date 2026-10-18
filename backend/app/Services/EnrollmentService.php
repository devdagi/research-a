<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceConsent;
use App\Models\DeviceEnrollmentCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EnrollmentService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /**
     * @return array{code: DeviceEnrollmentCode, plaintext: string}
     */
    public function issueCode(User $admin, ?string $label = null): array
    {
        $plaintext = DeviceEnrollmentCode::generatePlaintext();
        $ttl = (int) config('cdm.enrollment_code_ttl_minutes', 60);

        $code = DeviceEnrollmentCode::query()->create([
            'code_hash' => DeviceEnrollmentCode::hashCode($plaintext),
            'code_hint' => substr($plaintext, -4),
            'label' => $label,
            'created_by' => $admin->id,
            'expires_at' => now()->addMinutes($ttl),
        ]);

        $this->audit->log('enrollment_code.issued', $admin, null, $code, [
            'label' => $label,
            'expires_at' => $code->expires_at?->toIso8601String(),
        ]);

        return ['code' => $code, 'plaintext' => $plaintext];
    }

    /**
     * Register a device immediately (no enrollment code).
     *
     * @param  array<string, mixed>  $deviceData
     * @return array{device: Device, token: string}
     */
    public function enrollOpen(array $deviceData): array
    {
        return DB::transaction(function () use ($deviceData) {
            $device = Device::query()->create([
                'name' => $deviceData['device_name'] ?? 'Android Device',
                'platform' => $deviceData['platform'] ?? 'android',
                'model' => $deviceData['model'] ?? null,
                'os_version' => $deviceData['os_version'] ?? null,
                'app_version' => $deviceData['app_version'] ?? null,
                'fcm_token' => $deviceData['fcm_token'] ?? null,
                'status' => Device::STATUS_ACTIVE,
                'enrolled_at' => now(),
                'last_seen_at' => now(),
                'enrolled_by' => null,
            ]);

            $token = $device->createToken('device', ['device'])->plainTextToken;

            foreach (array_keys(DeviceConsent::catalog()) as $type) {
                DeviceConsent::query()->create([
                    'device_id' => $device->id,
                    'type' => $type,
                    'status' => DeviceConsent::STATUS_GRANTED,
                    'version' => '1',
                    'notes' => 'auto-granted on open enroll',
                    'decided_at' => now(),
                ]);
            }

            $this->audit->log('device.enrolled', null, $device, $device, [
                'mode' => 'open',
            ]);

            return ['device' => $device, 'token' => $token];
        });
    }

    /**
     * @deprecated Prefer enrollOpen — codes are no longer required.
     *
     * @param  array<string, mixed>  $deviceData
     * @return array{device: Device, token: string}
     */
    public function enroll(string $plaintext, array $deviceData): array
    {
        // Ignore legacy codes; register the device open.
        unset($plaintext);

        return $this->enrollOpen($deviceData);
    }
}
