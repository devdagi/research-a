<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LiveSession;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LiveSessionController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(): View
    {
        return view('admin.live_sessions.index', [
            'sessions' => LiveSession::query()
                ->with(['device', 'requester'])
                ->latest()
                ->paginate(25),
            'webrtc_configured' => (bool) config('cdm.webrtc_configured'),
        ]);
    }

    public function cancel(Request $request, LiveSession $liveSession): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $cancellable = [
            LiveSession::STATUS_PENDING,
            LiveSession::STATUS_APPROVED,
            LiveSession::STATUS_ACTIVE,
        ];

        if (! in_array($liveSession->status, $cancellable, true)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Session cannot be cancelled.', 'error' => 'invalid_state'], 409);
            }

            return back()->withErrors(['session' => 'Only pending, approved, or active sessions can be cancelled.']);
        }

        $liveSession->forceFill([
            'status' => $liveSession->status === LiveSession::STATUS_ACTIVE
                ? LiveSession::STATUS_ENDED
                : LiveSession::STATUS_CANCELLED,
            'ended_at' => now(),
            'ended_by' => 'admin',
            'signaling_token_hash' => null,
        ])->save();

        $this->audit->log('live_session.cancelled', $request->user(), $liveSession->device, $liveSession);

        if ($request->expectsJson()) {
            return response()->json(['data' => ['ok' => true, 'status' => $liveSession->status]]);
        }

        return back()->with('success', 'Live session cancelled.');
    }
}
