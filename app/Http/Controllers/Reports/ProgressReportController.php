<?php

namespace App\Http\Controllers\Reports;

use App\Exports\ProgressReportsByDivisionExport;
use App\Http\Controllers\Controller;
use App\Models\ProgressReport;
use App\Models\ReportReview;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProgressReportController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->hasValidDivisionAssignment(), 403);

        $query = ProgressReport::query()
            ->with(['division', 'creator'])
            ->when($user->isAdmin(), fn ($query) => $query->where('status', 'approved'))
            ->when(! $user->isAdmin(), fn ($query) => $query->where('division_id', $user->division_id))
            ->when($user->isStaff(), fn ($query) => $query->where('created_by', $user->id))
            ->latest();

        $isAdmin = $user->isAdmin();
        $reports = $isAdmin ? $query->get() : $query->paginate(15);
        $reportCollection = $isAdmin ? $reports : $reports->getCollection();
        $reportGroups = $reportCollection
            ->groupBy(fn (ProgressReport $report) => $report->division_id ?? 'unknown')
            ->sortBy(fn ($divisionReports) => strtolower($divisionReports->first()->division?->name ?? 'zzzz'));
        $canReview = $this->canReview($request);

        return view('reports.progress.index', compact('reports', 'reportGroups', 'isAdmin', 'canReview'));
    }

    public function export(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        return Excel::download(
            new ProgressReportsByDivisionExport,
            'progress-seluruh-divisi-'.now()->format('Ymd-His').'.xlsx'
        );
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->isStaff() && $request->user()->hasValidDivisionAssignment(), 403);

        return view('reports.progress.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isStaff() && $request->user()->hasValidDivisionAssignment(), 403);

        $validated = $this->validated($request);
        $validated['division_id'] = $request->user()->division_id;
        $validated['created_by'] = $request->user()->id;
        $validated['status'] = 'draft';

        $report = ProgressReport::create($validated);

        return redirect()->route('reports.progress.index')->with('success', 'Laporan berhasil disimpan sebagai draft.');
    }

    public function edit(Request $request, ProgressReport $progressReport): View
    {
        $this->ensureStaffCanEdit($request, $progressReport);

        return view('reports.progress.edit', compact('progressReport'));
    }

    public function update(Request $request, ProgressReport $progressReport): RedirectResponse
    {
        $this->ensureStaffCanEdit($request, $progressReport);
        $progressReport->update($this->validated($request));

        return redirect()->route('reports.progress.index')->with('success', 'Laporan berhasil diperbarui.');
    }

    public function submit(Request $request, ProgressReport $progressReport): RedirectResponse
    {
        $this->ensureStaffCanEdit($request, $progressReport);
        $progressReport->update(['status' => 'submitted', 'submitted_at' => now()]);

        return back()->with('success', 'Laporan berhasil dikirim untuk review TL divisi.');
    }

    public function review(Request $request, ProgressReport $progressReport, ActivityLogger $activityLogger): RedirectResponse
    {
        abort_unless($this->canReview($request), 403);
        abort_unless($request->user()->isAdmin() || $progressReport->division_id === $request->user()->division_id, 403);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['approved', 'revision'])],
            'notes' => ['required_if:action,revision', 'nullable', 'string'],
        ]);

        DB::transaction(function () use ($request, $progressReport, $validated, $activityLogger): void {
            $report = ProgressReport::query()->lockForUpdate()->findOrFail($progressReport->id);
            abort_unless($report->status === 'submitted', 422, 'Laporan tidak sedang menunggu review.');

            $report->update([
                'status' => $validated['action'],
                'approved_at' => $validated['action'] === 'approved' ? now() : null,
            ]);
            ReportReview::create([
                'progress_report_id' => $report->id,
                'reviewed_by' => $request->user()->id,
                'action' => $validated['action'],
                'notes' => $validated['notes'] ?? null,
            ]);
            $activityLogger->record($request, 'progress_report.reviewed', $report, 'Progress report direview: '.$validated['action'].'.');
        });

        return back()->with('success', 'Review laporan berhasil disimpan.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'progress' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    private function ensureStaffCanEdit(Request $request, ProgressReport $progressReport): void
    {
        abort_unless($request->user()->isStaff() && $request->user()->hasValidDivisionAssignment(), 403);
        abort_unless($progressReport->created_by === $request->user()->id, 403);
        abort_unless(in_array($progressReport->status, ['draft', 'revision'], true), 422, 'Laporan sudah dikirim dan tidak dapat diedit.');
    }

    private function canReview(Request $request): bool
    {
        $user = $request->user();

        return $user->isAdmin() || ($user->isTl() && $user->hasValidDivisionAssignment());
    }
}
