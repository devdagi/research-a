@extends('admin.layouts.app')

@section('title', 'Application settings')

@section('content')
<div class="card" style="max-width:640px;">
    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf
        <div class="field">
            <label>Online threshold (minutes)</label>
            <input type="number" name="device_online_threshold_minutes" min="1" max="1440"
                   value="{{ old('device_online_threshold_minutes', $settings['device_online_threshold_minutes']) }}" required>
        </div>
        <div class="field">
            <label>Location retention (days)</label>
            <input type="number" name="location_retention_days" min="1" max="3650"
                   value="{{ old('location_retention_days', $settings['location_retention_days']) }}" required>
        </div>
        <button class="btn" type="submit">Save settings</button>
    </form>
</div>

<div class="card">
    <h2 style="margin-top:0;">WebRTC / TURN</h2>
    <p>Configured: <strong>{{ $webrtc_configured ? 'yes' : 'no' }}</strong></p>
    <p class="muted">TURN URLs: {{ $turn_urls ? implode(', ', $turn_urls) : 'none (set TURN_URLS in .env)' }}</p>
    <p class="muted">MFA: set <code>mfa_enabled</code> / integrate TOTP provider; secrets are encrypted at rest when stored.</p>
</div>
@endsection
