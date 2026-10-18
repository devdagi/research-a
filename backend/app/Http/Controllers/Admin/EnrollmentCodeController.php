<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeviceEnrollmentCode;
use App\Services\EnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EnrollmentCodeController extends Controller
{
    public function __construct(private readonly EnrollmentService $enrollment)
    {
    }

    public function index(): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        return view('admin.enrollment.index', [
            'codes' => DeviceEnrollmentCode::query()
                ->with(['creator', 'usedByDevice'])
                ->latest()
                ->paginate(20),
            'plaintext' => session('enrollment_plaintext'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->enrollment->issueCode($request->user(), $data['label'] ?? null);

        return redirect()
            ->route('admin.enrollment.index')
            ->with('enrollment_plaintext', $result['plaintext'])
            ->with('success', 'Enrollment code created. Copy it now — it will not be shown again.');
    }
}
