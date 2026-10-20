<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Division;
use App\Models\Lot;
use App\Models\Property;
use App\Notifications\AlertActivity;
use App\Services\AlertNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AlertController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user?->isAdmin() || $user?->hasValidDivisionAssignment(), 403);

        $user->unreadNotifications()
            ->where('type', AlertActivity::class)
            ->where('data->event', 'resolved')
            ->update(['read_at' => now()]);

        $alerts = Alert::query()
            ->with(['lot.block.property', 'createdBy.division', 'resolvedBy', 'recipientDivisions'])
            ->when(
                ! $user->isAdmin(),
                fn (Builder $query) => $query->where(function (Builder $visibleAlerts) use ($user): void {
                    $visibleAlerts
                        ->whereHas(
                            'recipientDivisions',
                            fn (Builder $divisions) => $divisions->whereKey($user->division_id),
                        )
                        ->orWhere('created_by', $user->id);
                }),
            )
            ->latest()
            ->paginate(20);

        return view('admin.alerts.index', [
            'alerts' => $alerts,
            'isAdmin' => $user->isAdmin(),
            'divisionId' => $user->division_id,
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

    public function store(Request $request, AlertNotificationService $alertNotifications): RedirectResponse
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
        $alertNotifications->notifyReceived($alert);

        return redirect()->route('alerts.create')->with(
            'success',
            $recipientDivisionIds === []
                ? 'Alert berhasil dikirim ke Admin.'
                : 'Alert berhasil dikirim ke Admin dan divisi tujuan.',
        );
    }

    public function resolve(Request $request, Alert $alert, AlertNotificationService $alertNotifications): RedirectResponse
    {
        $user = $request->user();
        $isRecipient = $user?->hasValidDivisionAssignment()
            && $alert->recipientDivisions()->where('divisions.id', $user->division_id)->exists();
        abort_unless($user?->isAdmin() || $isRecipient, 403);

        DB::transaction(function () use ($alert, $user, $alertNotifications): void {
            $lockedAlert = Alert::query()->lockForUpdate()->findOrFail($alert->id);

            if ($lockedAlert->is_resolved) {
                return;
            }

            $lockedAlert->update([
                'is_resolved' => true,
                'resolved_by' => $user->id,
                'resolved_at' => now(),
            ]);
            $alertNotifications->notifyResolved($lockedAlert, $user);
        });

        return back()->with('success', 'Alert berhasil diselesaikan.');
    }

    private function authorizeSender(Request $request): void
    {
        $user = $request->user();

        abort_unless($user?->isAdmin() || $user?->hasValidDivisionAssignment(), 403);
    }
}
