<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function edit(): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        return view('admin.settings.edit', [
            'settings' => [
                'device_online_threshold_minutes' => AppSetting::getValue(
                    'device_online_threshold_minutes',
                    config('cdm.device_online_threshold_minutes')
                ),
                'location_retention_days' => AppSetting::getValue(
                    'location_retention_days',
                    config('cdm.location_retention_days')
                ),
            ],
            'webrtc_configured' => (bool) config('cdm.webrtc_configured'),
            'turn_urls' => config('cdm.turn_urls'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'device_online_threshold_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'location_retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
        ]);

        foreach ($data as $key => $value) {
            AppSetting::setValue($key, (string) $value);
        }

        $this->audit->log('settings.updated', $request->user(), null, null, $data);

        return back()->with('success', 'Settings updated.');
    }
}
