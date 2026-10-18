@extends('admin.layouts.app')

@section('title', 'Pending commands')

@section('content')
<div class="card">
    <table>
        <thead>
        <tr><th>Device</th><th>Type</th><th>Status</th><th>Issued by</th><th>Expires</th><th>Created</th></tr>
        </thead>
        <tbody>
        @forelse ($commands as $command)
            <tr>
                <td>
                    @if ($command->device)
                        <a href="{{ route('admin.devices.show', $command->device) }}">{{ $command->device->name }}</a>
                    @else
                        —
                    @endif
                </td>
                <td>{{ $command->type }}</td>
                <td><span class="badge">{{ $command->status }}</span></td>
                <td>{{ $command->issuer?->name }}</td>
                <td>{{ $command->expires_at?->toDayDateTimeString() ?? '—' }}</td>
                <td>{{ $command->created_at?->diffForHumans() }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No commands found.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div style="margin-top:1rem;">{{ $commands->links() }}</div>
</div>
@endsection
