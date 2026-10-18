<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\LiveSession;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $threshold = (int) config('cdm.device_online_threshold_minutes', 5);
        $devices = Device::query()->where('status', Device::STATUS_ACTIVE)->get();
        $online = $devices->filter(fn (Device $d) => $d->isOnline($threshold))->count();

        return view('admin.dashboard', [
            'stats' => [
                'devices_total' => Device::query()->count(),
                'devices_active' => $devices->count(),
                'devices_online' => $online,
                'devices_revoked' => Device::query()->where('status', Device::STATUS_REVOKED)->count(),
                'pending_commands' => DeviceCommand::query()->where('status', DeviceCommand::STATUS_PENDING)->count(),
                'pending_sessions' => LiveSession::query()->where('status', LiveSession::STATUS_PENDING)->count(),
            ],
            'recentDevices' => Device::query()->latest('enrolled_at')->limit(5)->get(),
            'threshold' => $threshold,
        ]);
    }
}
