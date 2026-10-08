<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Lot;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertController extends Controller
{
    public function index(): View
    {
        $alerts = Alert::query()->with(['lot.block.property', 'createdBy', 'resolvedBy'])->latest()->paginate(20);

        return view('admin.alerts.index', compact('alerts'));
    }

    public function create(Request $request): View
    {
        $this->authorizeSender($request);

        return view('alerts.create', [
            'lots' => Lot::query()->with('block.property')->orderBy('lot_number')->get(),
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSender($request);

        $validated = $request->validate([
            'lot_id' => ['nullable', 'exists:lots,id'],
            'type' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'severity' => ['required', 'in:low,medium,high,critical'],
        ]);
        $validated['created_by'] = $request->user()->id;

        Alert::create($validated);

        return redirect()->route('alerts.create')->with('success', 'Alert berhasil dikirim ke Admin.');
    }

    public function resolve(Request $request, Alert $alert): RedirectResponse
    {
        $alert->update(['is_resolved' => true, 'resolved_by' => $request->user()->id, 'resolved_at' => now()]);

        return back()->with('success', 'Alert berhasil diselesaikan.');
    }

    private function authorizeSender(Request $request): void
    {
        $user = $request->user();

        abort_unless($user?->isAdmin() || $user?->hasValidDivisionAssignment(), 403);
    }
}
