<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Division;
use App\Models\Lot;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AlertController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user?->isAdmin() || $user?->hasValidDivisionAssignment(), 403);

        $alerts = Alert::query()
            ->with(['lot.block.property', 'createdBy.division', 'resolvedBy', 'recipientDivisions'])
            ->when(
                ! $user->isAdmin(),
                fn ($query) => $query->whereHas(
                    'recipientDivisions',
                    fn ($divisions) => $divisions->whereKey($user->division_id),
                ),
            )
            ->latest()
            ->paginate(20);

        return view('admin.alerts.index', [
            'alerts' => $alerts,
            'isAdmin' => $user->isAdmin(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeSender($request);

        return view('alerts.create', [
            'lots' => Lot::query()->with('block.property')->orderBy('lot_number')->get(),
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'divisions' => Division::query()
                ->where('is_active', true)
                ->when(
                    ! $request->user()->isAdmin(),
                    fn ($query) => $query->where('id', '!=', $request->user()->division_id),
                )
                ->orderBy('name')
                ->get(['id', 'name']),
            'isAdmin' => $request->user()->isAdmin(),
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
            'recipient_division_ids' => [
                Rule::requiredIf($request->user()->isAdmin()),
                'array',
                Rule::when($request->user()->isAdmin(), ['min:1']),
            ],
            'recipient_division_ids.*' => [
                'integer',
                Rule::exists('divisions', 'id')->where('is_active', true),
                ...($request->user()->isAdmin()
                    ? []
                    : [Rule::notIn([$request->user()->division_id])]),
            ],
        ]);
        $recipientDivisionIds = $validated['recipient_division_ids'] ?? [];
        unset($validated['recipient_division_ids']);
        $validated['created_by'] = $request->user()->id;

        $alert = Alert::create($validated);
        $alert->recipientDivisions()->sync($recipientDivisionIds);

        return redirect()->route('alerts.create')->with(
            'success',
            $recipientDivisionIds === []
                ? 'Alert berhasil dikirim ke Admin.'
                : 'Alert berhasil dikirim ke Admin dan divisi tujuan.',
        );
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
