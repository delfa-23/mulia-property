<?php

namespace App\Http\Controllers\Reports;

use App\Exports\ProgressReportsByDivisionExport;
use App\Http\Controllers\Controller;
use App\Models\Lot;
use App\Models\ProgressReport;
use App\Models\Property;
use App\Models\ReportReview;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            ->with(['division', 'creator', 'property', 'lot.block'])
            ->when($user->isAdmin(), fn ($query) => $query->whereIn('status', ['submitted', 'approved']))
            ->when(! $user->isAdmin(), fn ($query) => $query->where('division_id', $user->division_id))
            ->when($user->isStaff(), fn ($query) => $query->where('created_by', $user->id))
            ->orderByDesc('report_date')
            ->latest();

        $isAdmin = $user->isAdmin();
        $reports = $isAdmin ? $query->get() : $query->paginate(15);
        $reportCollection = $isAdmin ? $reports : $reports->getCollection();
        $reportGroups = $reportCollection
            ->groupBy(fn (ProgressReport $report) => $report->division_id ?? 'unknown')
            ->sortBy(fn ($divisionReports) => strtolower($divisionReports->first()->division?->name ?? 'zzzz'));
        $canReview = $user->isAdmin() || ($user->isTl() && $user->hasValidDivisionAssignment());
        $canCreate = $this->canCreate($request);

        return view('reports.progress.index', compact('reports', 'reportGroups', 'isAdmin', 'canReview', 'canCreate'));
    }

    public function archive(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        $reports = ProgressReport::query()
            ->with(['division', 'creator', 'property', 'lot.block'])
            ->where('status', 'approved')
            ->whereNotNull('photo_path')
            ->whereNotNull('photo_archived_at')
            ->latest('photo_archived_at')
            ->paginate(20);

        return view('reports.progress.archive', compact('reports'));
    }

    public function photo(Request $request, ProgressReport $progressReport): BinaryFileResponse|RedirectResponse
    {
        abort_unless(is_string($progressReport->photo_path) && $progressReport->photo_path !== '', 404);

        $isAdmin = $request->user()->isAdmin();
        $isArchived = $progressReport->photo_archived_at !== null;
        $isOwner = $progressReport->created_by === $request->user()->id;
        $isCurrentApprovedPhoto = $progressReport->status === 'approved' && ! $isArchived;

        abort_unless($isAdmin || (! $isArchived && ($isOwner || $isCurrentApprovedPhoto)), 403);

        if (! ProgressReport::isPublicPhotoPath($progressReport->photo_path)) {
            $localDisk = Storage::disk('local');
            abort_unless($localDisk->exists($progressReport->photo_path), 404);

            $publicPath = ProgressReport::PUBLIC_PHOTO_DIRECTORY.'/'.basename($progressReport->photo_path);
            Storage::disk(ProgressReport::PUBLIC_PHOTO_DISK)->put(
                $publicPath,
                $localDisk->get($progressReport->photo_path),
            );
            $progressReport->update(['photo_path' => $publicPath]);

            return redirect()->away(asset($publicPath));
        }

        $disk = Storage::disk(
            ProgressReport::PUBLIC_PHOTO_DISK
        );
        abort_unless($disk->exists($progressReport->photo_path), 404);

        return response()->file($disk->path($progressReport->photo_path), [
            'Cache-Control' => 'private, no-store',
        ]);
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
        abort_unless($this->canCreate($request), 403);

        return view('reports.progress.create', $this->formOptions($request));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->canCreate($request), 403);

        $validated = $this->validated($request);
        $photo = $validated['photo'] ?? null;
        unset($validated['photo']);

        if ($photo !== null) {
            $photoPath = $photo->store(ProgressReport::PUBLIC_PHOTO_DIRECTORY, ProgressReport::PUBLIC_PHOTO_DISK);
            abort_unless(is_string($photoPath), 500, 'Foto laporan gagal disimpan.');
            $validated['photo_path'] = $photoPath;
        }

        $validated['division_id'] = $request->user()->division_id;
        $validated['created_by'] = $request->user()->id;
        $validated['status'] = 'draft';

        $report = ProgressReport::create($validated);

        return redirect()->route('reports.progress.index')->with('success', 'Laporan berhasil disimpan sebagai draft.');
    }

    public function edit(Request $request, ProgressReport $progressReport): View
    {
        $this->ensureCreatorCanEdit($request, $progressReport);

        return view('reports.progress.edit', [
            ...$this->formOptions($request),
            'progressReport' => $progressReport->load(['property', 'lot.block']),
        ]);
    }

    public function update(Request $request, ProgressReport $progressReport): RedirectResponse
    {
        $this->ensureCreatorCanEdit($request, $progressReport);
        $validated = $this->validated($request);
        $photo = $validated['photo'] ?? null;
        unset($validated['photo']);

        if ($photo !== null) {
            $photoPath = $photo->store(ProgressReport::PUBLIC_PHOTO_DIRECTORY, ProgressReport::PUBLIC_PHOTO_DISK);
            abort_unless(is_string($photoPath), 500, 'Foto laporan gagal disimpan.');
            $validated['photo_path'] = $photoPath;
        }

        $oldPhotoPath = $photo !== null ? $progressReport->photo_path : null;
        $progressReport->update($validated);

        if ($oldPhotoPath !== null) {
            $oldPhotoDisk = ProgressReport::isPublicPhotoPath($oldPhotoPath)
                ? ProgressReport::PUBLIC_PHOTO_DISK
                : 'local';
            Storage::disk($oldPhotoDisk)->delete($oldPhotoPath);
        }

        return redirect()->route('reports.progress.index')->with('success', 'Laporan berhasil diperbarui.');
    }

    public function submit(Request $request, ProgressReport $progressReport): RedirectResponse
    {
        $this->ensureCreatorCanEdit($request, $progressReport);

        if ($progressReport->division?->slug === 'pembangunan') {
            abort_if(
                ! $progressReport->property_id || ! $progressReport->lot_id || ! $progressReport->photo_path,
                422,
                'Pilih perumahan dan kavling serta ambil foto lapangan sebelum mengirim laporan.'
            );
        }

        $progressReport->update(['status' => 'submitted', 'submitted_at' => now()]);

        $reviewer = $progressReport->division?->slug === 'pembangunan' ? 'admin' : 'TL divisi';

        return back()->with('success', "Laporan berhasil dikirim untuk review {$reviewer}.");
    }

    public function review(Request $request, ProgressReport $progressReport, ActivityLogger $activityLogger): RedirectResponse
    {
        abort_unless($this->canReview($request, $progressReport), 403);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['approved', 'revision'])],
            'notes' => ['required_if:action,revision', 'nullable', 'string'],
        ]);

        DB::transaction(function () use ($request, $progressReport, $validated, $activityLogger): void {
            $report = ProgressReport::query()
                ->with('division')
                ->lockForUpdate()
                ->findOrFail($progressReport->id);
            abort_unless($report->status === 'submitted', 422, 'Laporan tidak sedang menunggu review.');

            if ($validated['action'] === 'approved' && $report->division?->slug === 'pembangunan') {
                abort_unless(
                    $report->property_id !== null && $report->lot_id !== null && $report->photo_path !== null,
                    422,
                    'Laporan pembangunan harus memiliki perumahan, kavling, dan foto lapangan.'
                );
                Lot::query()->lockForUpdate()->findOrFail($report->lot_id);

                ProgressReport::query()
                    ->where('lot_id', $report->lot_id)
                    ->where('id', '!=', $report->id)
                    ->where('status', 'approved')
                    ->whereNotNull('photo_path')
                    ->whereNull('photo_archived_at')
                    ->update(['photo_archived_at' => now()]);
            }

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
        $isConstructionReport = $request->user()->isAssignedToDivision('pembangunan');
        $isCreate = $request->isMethod('post');
        $validated = $request->validate([
            'report_date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'property_id' => $isConstructionReport
                ? ['required', 'integer', 'exists:properties,id']
                : ['prohibited'],
            'lot_id' => $isConstructionReport
                ? ['required', 'integer', 'exists:lots,id']
                : ['prohibited'],
            'photo' => $isConstructionReport
                ? [
                    $isCreate ? 'required' : 'nullable',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:10240',
                ]
                : ['prohibited'],
        ]);

        if ($isConstructionReport && ! Lot::query()
            ->whereKey($validated['lot_id'])
            ->whereHas('block', fn ($query) => $query->where('property_id', $validated['property_id']))
            ->exists()) {
            throw ValidationException::withMessages([
                'lot_id' => 'Kavling tidak sesuai dengan perumahan yang dipilih.',
            ]);
        }

        $validated['period_start'] = $validated['report_date'];
        $validated['period_end'] = $validated['report_date'];

        return $validated;
    }

    private function ensureCreatorCanEdit(Request $request, ProgressReport $progressReport): void
    {
        abort_unless($this->canCreate($request), 403);
        abort_unless($progressReport->created_by === $request->user()->id, 403);
        abort_unless(in_array($progressReport->status, ['draft', 'revision'], true), 422, 'Laporan sudah dikirim dan tidak dapat diedit.');
    }

    private function canCreate(Request $request): bool
    {
        $user = $request->user();

        return $user->hasValidDivisionAssignment()
            && ($user->isStaff() || ($user->isTl() && $user->isAssignedToDivision('pembangunan')));
    }

    private function canReview(Request $request, ProgressReport $progressReport): bool
    {
        $user = $request->user();

        if ($progressReport->division?->slug === 'pembangunan') {
            return $user->isAdmin();
        }

        return $user->isAdmin()
            || ($user->isTl()
                && $user->hasValidDivisionAssignment()
                && $progressReport->division_id === $user->division_id);
    }

    /**
     * @return array{properties: Collection, lots: Collection, isConstructionReport: bool}
     */
    private function formOptions(Request $request): array
    {
        $isConstructionReport = $request->user()->isAssignedToDivision('pembangunan');

        return [
            'properties' => $isConstructionReport
                ? Property::query()->orderBy('name')->get(['id', 'name'])
                : collect(),
            'lots' => $isConstructionReport
                ? Lot::query()
                    ->with('block:id,property_id,name')
                    ->orderBy('block_id')
                    ->orderBy('lot_number')
                    ->get(['id', 'block_id', 'lot_number'])
                : collect(),
            'isConstructionReport' => $isConstructionReport,
        ];
    }
}
