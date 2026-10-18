@extends('admin.layouts.app')

@section('title', 'Enrollment codes')

@section('content')
@if ($plaintext)
    <div class="flash">
        <strong>One-time plaintext code (copy now):</strong>
        <div style="font-size:1.35rem;letter-spacing:0.08em;margin-top:0.4rem;">{{ $plaintext }}</div>
        <div class="muted" style="margin-top:0.35rem;">This value is not stored and will not be shown again.</div>
    </div>
@endif

<div class="card">
    <form method="POST" action="{{ route('admin.enrollment.store') }}" class="actions">
        @csrf
        <input type="text" name="label" placeholder="Optional label (e.g. warehouse tablet)" style="max-width:320px;">
        <button class="btn" type="submit">Issue enrollment code</button>
    </form>
    <p class="muted">Codes are hashed at rest, single-use, and expire automatically.</p>
</div>

<div class="card">
    <table>
        <thead>
        <tr><th>Label</th><th>Hint</th><th>Created by</th><th>Expires</th><th>Used</th><th>Attempts</th></tr>
        </thead>
        <tbody>
        @forelse ($codes as $code)
            <tr>
                <td>{{ $code->label ?? '—' }}</td>
                <td>****{{ $code->code_hint }}</td>
                <td>{{ $code->creator?->name }}</td>
                <td>{{ $code->expires_at?->toDayDateTimeString() }}</td>
                <td>
                    @if ($code->used_at)
                        {{ $code->usedByDevice?->name ?? 'device' }} ({{ $code->used_at->diffForHumans() }})
                    @else
                        <span class="badge warn">unused</span>
                    @endif
                </td>
                <td>{{ $code->attempt_count }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No enrollment codes yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div style="margin-top:1rem;">{{ $codes->links() }}</div>
</div>
@endsection
