<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('ChangeMeNow!123'),
                'role' => User::ROLE_ADMIN,
                'email_verified_at' => now(),
            ]
        );

        AppSetting::setValue('device_online_threshold_minutes', (string) config('cdm.device_online_threshold_minutes'));
        AppSetting::setValue('location_retention_days', (string) config('cdm.location_retention_days'));
        AppSetting::setValue('enrollment_code_ttl_minutes', (string) config('cdm.enrollment_code_ttl_minutes'));
    }
}
