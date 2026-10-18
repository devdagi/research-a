@extends('admin.layouts.app')

@section('title', 'Live sessions')

@section('content')
@unless ($webrtc_configured)
    <div class="flash error">
        WebRTC media transport is not fully configured (TURN / WEBRTC_CONFIGURED=false).
        Control-plane requests still work; live media connections will remain unavailable until TURN credentials are set.
    </div>
@endunless

<div class="card">
    <table>
        <thead>
        <tr><th>Device</th><th>Requester</th><th>Purpose</th><th>Media</th><th>Status</th><th>Expires</th><th></th></tr>
        </thead>
        <tbody>
        @forelse ($sessions as $session)
            <tr>
                <td>
                    @if ($session->device)
                        <a href="{{ route('admin.devices.show', $session->device) }}">{{ $session->device->name }}</a>
                    @endif
                </td>
                <td>{{ $session->requester?->name }}</td>
                <td>{{ $session->purpose }}</td>
                <td>
                    {{ $session->request_camera ? 'camera' : '' }}
                    {{ $session->request_microphone ? 'mic' : '' }}
                </td>
                <td><span class="badge">{{ $session->status }}</span></td>
                <td>{{ $session->expires_at?->toDayDateTimeString() }}</td>
                <td>
                    @if (in_array($session->status, ['pending','approved'], true))
                        <form method="POST" action="{{ route('admin.live-sessions.cancel', $session) }}" onsubmit="return confirm('Cancel this session?');">
                            @csrf
                            <button class="btn secondary" type="submit">Cancel</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">No live sessions.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div style="margin-top:1rem;">{{ $sessions->links() }}</div>
</div>
@endsection
