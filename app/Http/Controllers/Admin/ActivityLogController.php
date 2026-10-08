<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'date_from' => array_filter([
                'nullable',
                'date',
                $request->filled('date_to') ? 'before_or_equal:date_to' : null,
            ]),
            'date_to' => array_filter([
                'nullable',
                'date',
                $request->filled('date_from') ? 'after_or_equal:date_from' : null,
            ]),
            'action' => ['nullable', 'string', 'max:255'],
        ]);

        $logs = ActivityLog::query()
            ->with('user')
            ->when($validated['date_from'] ?? null, function ($query, $dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($validated['date_to'] ?? null, function ($query, $dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            })
            ->when($validated['action'] ?? null, function ($query, $action) {
                $query->where('action', $action);
            })
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $actions = ActivityLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return view('admin.activity-logs.index', compact('logs', 'actions'));
    }
}
