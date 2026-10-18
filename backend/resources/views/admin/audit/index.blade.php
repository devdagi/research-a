@extends('admin.layouts.app')

@section('title', 'Audit log')

@section('content')
<div class="card">
    <table>
        <thead>
        <tr><th>When</th><th>Action</th><th>User</th><th>Device</th><th>IP</th></tr>
        </thead>
        <tbody>
        @forelse ($logs as $log)
            <tr>
                <td>{{ $log->created_at?->toDayDateTimeString() }}</td>
                <td>{{ $log->action }}</td>
                <td>{{ $log->user?->email ?? '—' }}</td>
                <td>{{ $log->device?->name ?? '—' }}</td>
                <td class="muted">{{ $log->ip_address ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">No audit events yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div style="margin-top:1rem;">{{ $logs->links() }}</div>
</div>
@endsection
