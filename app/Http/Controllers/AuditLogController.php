<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;

class AuditLogController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', AuditLog::class);
        $logs = AuditLog::query()->with(['user', 'department'])->latest()->paginate(30);

        return view('admin.audit.index', compact('logs'));
    }
}
