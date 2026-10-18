@extends('admin.layouts.app')

@section('title', 'Device inventory')

@section('content')
<div class="card">
    <form method="GET" class="actions" style="margin-bottom:1rem;">
        <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search name, UUID, model, OS" style="max-width:280px;">
        <select name="status" style="max-width:180px;">
            <option value="">All statuses</option>
            @foreach (['active','revoked','unenrolled'] as $status)
                <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <button class="btn" type="submit">Filter</button>
    </form>

    @if ($devices->isEmpty())
        <p class="muted">No devices match your filters.</p>
    @else
        <div style="overflow-x:auto;">
        <table>
            <thead>
            <tr>
                <th>Device</th>
                <th>Platform / model</th>
                <th>OS / app</th>
                <th>Status</th>
                <th>Online</th>
                <th>Battery</th>
                <th>Network</th>
                <th>Last location</th>
                <th>Accuracy</th>
                <th>Enrolled</th>
                <th>Last seen</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($devices as $device)
                @php
                    $online = $device->isOnline($threshold);
                    $loc = $device->latestLocation;
                    $st = $device->latestStatus;
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('admin.devices.show', $device) }}"><strong>{{ $device->name }}</strong></a>
                        <div class="muted" style="font-size:0.75rem;word-break:break-all;">{{ $device->uuid }}</div>
                    </td>
                    <td>{{ $device->platform }}<br><span class="muted">{{ $device->model ?? '—' }}</span></td>
                    <td>{{ $device->os_version ?? '—' }}<br><span class="muted">{{ $device->app_version ?? '—' }}</span></td>
                    <td><span class="badge {{ $device->status === 'active' ? 'ok' : 'danger' }}">{{ $device->status }}</span></td>
                    <td><span class="badge {{ $online ? 'ok' : 'warn' }}">{{ $online ? 'online' : 'offline' }}</span></td>
                    <td>
                        {{ $st?->battery_level !== null ? $st->battery_level.'%' : '—' }}
                        @if ($st?->is_charging)
                            <span class="muted">(chg)</span>
                        @endif
                    </td>
                    <td>{{ $st?->network_type ?? '—' }}</td>
                    <td>
                        @if ($loc)
                            {{ number_format($loc->latitude, 5) }}, {{ number_format($loc->longitude, 5) }}
                            <div class="muted" style="font-size:0.75rem;">{{ $loc->recorded_at?->diffForHumans() }}</div>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $loc?->accuracy !== null ? round($loc->accuracy).' m' : '—' }}</td>
                    <td>{{ $device->enrolled_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td>{{ $device->last_seen_at?->diffForHumans() ?? '—' }}</td>
                    <td><a class="btn secondary" href="{{ route('admin.devices.show', $device) }}">Open</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        <div style="margin-top:1rem;">{{ $devices->links() }}</div>
    @endif
</div>
@endsection
