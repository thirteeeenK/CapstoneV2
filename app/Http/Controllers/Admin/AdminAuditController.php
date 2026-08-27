<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;

class AdminAuditController extends Controller
{
    public function index()
    {
        $logs = AdminAuditLog::with('admin')
            ->orderBy('id', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('admin.audit.index', compact('logs'));
    }
}
