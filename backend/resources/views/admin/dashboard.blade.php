@extends('admin.layouts.app')

@section('title', 'Overview')

@section('content')
<div class="grid grid-4">
    <div class="card"><div class="stat-label">Total devices</div><div class="stat-value">{{ $stats['devices_total'] }}</div></div>
    <div class="card"><div class="stat-label">Active</div><div class="stat-value">{{ $stats['devices_active'] }}</div></div>
    <div class="card"><div class="stat-label">Online (&lt; {{ $threshold }}m)</div><div class="stat-value">{{ $stats['devices_online'] }}</div></div>
    <div class="card"><div class="stat-label">Pending live sessions</div><div class="stat-value">{{ $stats['pending_sessions'] }}</div></div>
</div>

<div class="card">
    <h2 style="margin-top:0;">Recent enrollments</h2>
    @if ($recentDevices->isEmpty())
        <p class="muted">No devices yet. Install the app on a phone — it appears here automatically.</p>
    @else
        <table>
            <thead>
            <tr><th>Name</th><th>UUID</th><th>Status</th><th>Last seen</th></tr>
            </thead>
            <tbody>
            @foreach ($recentDevices as $device)
                <tr>
                    <td><a href="{{ route('admin.devices.show', $device) }}">{{ $device->name }}</a></td>
                    <td class="muted">{{ $device->uuid }}</td>
                    <td><span class="badge {{ $device->status === 'active' ? 'ok' : 'danger' }}">{{ $device->status }}</span></td>
                    <td>{{ $device->last_seen_at?->diffForHumans() ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
