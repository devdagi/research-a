<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        return view('admin.audit.index', [
            'logs' => AuditLog::query()
                ->with(['user', 'device'])
                ->latest()
                ->paginate(30),
        ]);
    }
}
