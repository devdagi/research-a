<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeviceCommand;
use Illuminate\View\View;

class CommandController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->canManageDevices() || auth()->user()?->role === 'viewer', 403);

        return view('admin.commands.index', [
            'commands' => DeviceCommand::query()
                ->with(['device', 'issuer'])
                ->latest()
                ->paginate(25),
        ]);
    }
}
