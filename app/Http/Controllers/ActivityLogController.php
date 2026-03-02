<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = auth()->user()->tenantUserId();
        $action = trim((string) $request->query('action', ''));
        $q = trim((string) $request->query('q', ''));

        $logs = ActivityLog::with('actor:id,name,email')
            ->where('tenant_user_id', $tenantId)
            ->when($action !== '', function ($query) use ($action) {
                $query->where('action', $action);
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('description', 'like', '%' . $q . '%')
                        ->orWhereHas('actor', function ($actorQ) use ($q) {
                            $actorQ->where('name', 'like', '%' . $q . '%')
                                ->orWhere('email', 'like', '%' . $q . '%');
                        });
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('activity-logs.index', compact('logs', 'action', 'q'));
    }
}

