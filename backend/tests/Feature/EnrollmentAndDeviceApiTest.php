<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\DeviceConsent;
use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentAndDeviceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_to_dashboard(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => 'secret-password',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->post('/admin/login', [
            'email' => 'admin@test.com',
            'password' => 'secret-password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_device_registers_without_enrollment_code(): void
    {
        $first = $this->postJson('/api/v1/device/enroll', [
            'device_name' => 'Warehouse Phone',
            'platform' => 'android',
            'model' => 'Pixel',
            'os_version' => '14',
        ])->assertCreated()
            ->assertJsonPath('data.device.name', 'Warehouse Phone');

        $this->assertNotEmpty($first->json('data.token'));
        $this->assertDatabaseHas('devices', ['name' => 'Warehouse Phone', 'status' => 'active']);
    }

    public function test_device_token_auth_heartbeat_and_revocation(): void
    {
        [$device, $token] = $this->enrollDevice();

        $this->withToken($token)
            ->postJson('/api/v1/device/heartbeat')
            ->assertOk()
            ->assertJsonPath('data.device_uuid', $device->uuid);

        // Invalidate credentials first, then mark revoked (mirrors admin revoke flow).
        $this->assertGreaterThan(0, $device->tokens()->count());
        $device->tokens()->delete();
        $this->assertSame(0, $device->tokens()->count());
        $device->forceFill(['status' => Device::STATUS_REVOKED, 'revoked_at' => now()])->save();

        // Clear persisted test auth so Sanctum re-resolves from the bearer token.
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        $this->withToken($token)
            ->postJson('/api/v1/device/heartbeat')
            ->assertUnauthorized();
    }

    public function test_revoked_active_token_rejected_by_device_middleware(): void
    {
        [$device, $token] = $this->enrollDevice();

        $device->forceFill(['status' => Device::STATUS_REVOKED, 'revoked_at' => now()])->save();

        $this->withToken($token)
            ->postJson('/api/v1/device/status', ['battery_level' => 50])
            ->assertForbidden()
            ->assertJsonPath('error', 'device_revoked');
    }

    public function test_location_requires_consent_and_validates_coordinates(): void
    {
        [$device, $token] = $this->enrollDevice();

        $this->withToken($token)
            ->postJson('/api/v1/device/location', [
                'latitude' => 9.03,
                'longitude' => 38.74,
            ])->assertForbidden();

        $this->withToken($token)
            ->postJson('/api/v1/device/consents', [
                'type' => 'location',
                'status' => 'granted',
            ])->assertCreated();

        $this->withToken($token)
            ->postJson('/api/v1/device/location', [
                'latitude' => 200,
                'longitude' => 38.74,
            ])->assertStatus(422);

        $this->withToken($token)
            ->postJson('/api/v1/device/location', [
                'latitude' => 9.03,
                'longitude' => 38.74,
                'accuracy' => 12.5,
            ])->assertCreated();

        $this->assertDatabaseHas('device_locations', [
            'device_id' => $device->id,
        ]);
    }

    public function test_command_expiry_and_duplicate_acknowledgement(): void
    {
        $admin = User::factory()->create();
        [$device, $token] = $this->enrollDevice($admin);

        $command = DeviceCommand::query()->create([
            'device_id' => $device->id,
            'issued_by' => $admin->id,
            'type' => DeviceCommand::TYPE_CHECK_IN,
            'status' => DeviceCommand::STATUS_PENDING,
            'expires_at' => now()->subMinute(),
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/device/commands')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertSame(DeviceCommand::STATUS_EXPIRED, $command->fresh()->status);

        $active = DeviceCommand::query()->create([
            'device_id' => $device->id,
            'issued_by' => $admin->id,
            'type' => DeviceCommand::TYPE_NOTIFY,
            'payload' => ['message' => 'Hello'],
            'status' => DeviceCommand::STATUS_PENDING,
            'expires_at' => now()->addHour(),
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/device/commands')
            ->assertOk()
            ->assertJsonPath('data.0.uuid', $active->uuid);

        $this->withToken($token)
            ->postJson('/api/v1/device/commands/'.$active->uuid.'/ack', [
                'status' => 'acknowledged',
            ])->assertOk()->assertJsonPath('data.duplicate', false);

        $this->withToken($token)
            ->postJson('/api/v1/device/commands/'.$active->uuid.'/ack', [
                'status' => 'acknowledged',
            ])->assertOk()->assertJsonPath('data.duplicate', true);
    }

    public function test_live_session_approval_denial_and_termination(): void
    {
        $admin = User::factory()->create();
        [$device, $token] = $this->enrollDevice($admin);

        $session = LiveSession::query()->create([
            'device_id' => $device->id,
            'requested_by' => $admin->id,
            'purpose' => 'Support',
            'request_camera' => true,
            'request_microphone' => true,
            'status' => LiveSession::STATUS_PENDING,
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/device/live-sessions/'.$session->uuid.'/respond', [
                'decision' => 'denied',
            ])->assertOk()->assertJsonPath('data.status', 'denied');

        $approved = LiveSession::query()->create([
            'device_id' => $device->id,
            'requested_by' => $admin->id,
            'purpose' => 'Support 2',
            'status' => LiveSession::STATUS_PENDING,
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/device/live-sessions/'.$approved->uuid.'/respond', [
                'decision' => 'approved',
            ])->assertOk()->assertJsonPath('data.status', 'approved');

        $this->withToken($token)
            ->postJson('/api/v1/device/live-sessions/'.$approved->uuid.'/start')
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->withToken($token)
            ->postJson('/api/v1/device/live-sessions/'.$approved->uuid.'/end')
            ->assertOk()
            ->assertJsonPath('data.status', 'ended');
    }

    public function test_device_cannot_access_another_devices_command(): void
    {
        $admin = User::factory()->create();
        [, $tokenA] = $this->enrollDevice($admin, 'Phone A');
        [$deviceB] = $this->enrollDevice($admin, 'Phone B');

        $command = DeviceCommand::query()->create([
            'device_id' => $deviceB->id,
            'issued_by' => $admin->id,
            'type' => DeviceCommand::TYPE_CHECK_IN,
            'status' => DeviceCommand::STATUS_PENDING,
            'expires_at' => now()->addHour(),
        ]);

        $this->withToken($tokenA)
            ->postJson('/api/v1/device/commands/'.$command->uuid.'/ack', [
                'status' => 'acknowledged',
            ])->assertNotFound();
    }

    public function test_consent_and_audit_log_created(): void
    {
        [$device, $token] = $this->enrollDevice();

        $this->withToken($token)
            ->postJson('/api/v1/device/consents', [
                'type' => DeviceConsent::TYPE_LOCATION,
                'status' => DeviceConsent::STATUS_DENIED,
            ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'device_id' => $device->id,
            'action' => 'consent.denied',
        ]);
    }

    public function test_session_expires_when_past_deadline(): void
    {
        $admin = User::factory()->create();
        [$device, $token] = $this->enrollDevice($admin);

        $session = LiveSession::query()->create([
            'device_id' => $device->id,
            'requested_by' => $admin->id,
            'purpose' => 'Late',
            'status' => LiveSession::STATUS_PENDING,
            'expires_at' => now()->subMinute(),
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/device/live-sessions/pending')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertSame(LiveSession::STATUS_EXPIRED, $session->fresh()->status);
    }

    /**
     * @return array{0: Device, 1: string}
     */
    private function enrollDevice(?User $admin = null, string $name = 'Test Phone'): array
    {
        $admin ??= User::factory()->create();

        $response = $this->postJson('/api/v1/device/enroll', [
            'device_name' => $name,
            'platform' => 'android',
        ])->assertCreated();

        $device = Device::query()->where('uuid', $response->json('data.device.uuid'))->firstOrFail();

        return [$device, $response->json('data.token')];
    }
}
