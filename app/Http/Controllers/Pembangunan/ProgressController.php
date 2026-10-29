<?php

namespace App\Http\Controllers\Pembangunan;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\ConstructionProgress;
use App\Models\ConstructionProgressHistory;
use App\Models\ConstructionStage;
use App\Models\Lot;
use App\Models\Property;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProgressController extends Controller
{
    public function index(Request $request): View
    {
        $properties = Property::orderBy('name')->get();

        $lotOptions = Lot::query()
            ->with('block')
            ->when($request->filled('property_id'), function ($query) use ($request) {
                $query->whereHas(
                    'block',
                    fn ($q) => $q->where('property_id', $request->property_id)
                );
            })
            ->orderBy('block_id')
            ->orderBy('lot_number')
            ->get();

        $lots = Lot::query()
            ->with([
                'block.property',
                'bookings.customer',
                'budgets' => fn ($query) => $query->where('purpose', 'pembangunan'),
                'constructionProgresses.stage',
            ])
            ->withSum([
                'constructionProgresses as construction_realization_total' => fn ($query) => $query->whereNotNull('stage_id'),
            ], 'realization')
            ->when($request->filled('property_id'), function ($query) use ($request) {
                $query->whereHas(
                    'block',
                    fn ($q) => $q->where(
                        'property_id',
                        $request->property_id
                    )
                );
            })
            ->when($request->filled('lot_id'), function ($query) use ($request) {
                $query->whereKey($request->lot_id);
            })
            ->orderBy('lot_number')
            ->paginate(15)
            ->withQueryString();

        return view(
            'pembangunan.progress.index',
            compact(
                'properties',
                'lotOptions',
                'lots'
            )
        );
    }

    public function show(
        Property $property,
        Block $block,
        Lot $lot
    ): View {
        abort_unless(
            $block->property_id === $property->id &&
                $lot->block_id === $block->id,
            404
        );

        $lot->load([
            'block.property',
            'bookings.customer',
            'budgets' => fn ($query) => $query->where('purpose', 'pembangunan'),
            'constructionProgresses.stage',
            'constructionProgresses.updatedBy',
        ]);
        $constructionRealizationTotal = $lot->constructionProgresses
            ->whereNotNull('stage_id')
            ->sum('realization');
        $progressHistories = ConstructionProgressHistory::query()
            ->where('lot_id', $lot->id)
            ->with(['stage', 'updatedBy'])
            ->latest()
            ->get();

        $propertyHasStages = $property->constructionStages()->exists();
        $stages = ConstructionStage::query()
            ->where('property_id', $propertyHasStages ? $property->id : null)
            ->where('is_active', true)
            ->orderBy('order')
            ->get();

        return view(
            'pembangunan.progress.show',
            compact(
                'property',
                'block',
                'lot',
                'stages',
                'progressHistories',
                'constructionRealizationTotal',
            )
        );
    }

    public function store(
        Request $request,
        Property $property,
        Block $block,
        Lot $lot,
    ): RedirectResponse {
        $this->assertLotScope($property, $block, $lot);
        $validated = $this->validatedProgress($request, $property);

        abort_if(
            $lot->constructionProgresses()->where('stage_id', $validated['stage_id'])->exists(),
            409,
            'Progress tahap ini sudah dibuat. Gunakan aksi edit.'
        );

        $this->saveProgress($request, $lot, $validated);

        return $this->progressRedirect($property, $block, $lot, 'Progress pembangunan berhasil ditambahkan.');
    }

    public function update(
        Request $request,
        Property $property,
        Block $block,
        Lot $lot,
        ?ConstructionProgress $constructionProgress = null,
    ): RedirectResponse {
        $this->assertLotScope($property, $block, $lot);

        if ($constructionProgress !== null) {
            abort_unless($constructionProgress->lot_id === $lot->id, 404);
        }

        $validated = $this->validatedProgress($request, $property);

        if ($constructionProgress !== null && $constructionProgress->stage_id !== (int) $validated['stage_id']) {
            abort(404);
        }

        $this->saveProgress($request, $lot, $validated);

        return $this->progressRedirect($property, $block, $lot, 'Progress pembangunan berhasil diperbarui.');
    }

    public function destroy(
        Request $request,
        Property $property,
        Block $block,
        Lot $lot,
        ConstructionProgress $constructionProgress,
        ActivityLogger $activityLogger,
    ): RedirectResponse {
        abort_unless($request->user()->isAdmin(), 403);
        $this->assertLotScope($property, $block, $lot);
        abort_unless($constructionProgress->lot_id === $lot->id, 404);

        DB::transaction(function () use ($request, $constructionProgress, $activityLogger): void {
            $stageName = $constructionProgress->stage?->name ?? 'tahap pembangunan';
            $activityLogger->record(
                $request,
                'construction_progress.deleted',
                $constructionProgress,
                'Progress '.$stageName.' dihapus; riwayat perubahan tetap disimpan.'
            );
            $constructionProgress->delete();
        });

        return $this->progressRedirect($property, $block, $lot, 'Progress pembangunan dihapus. Riwayat perubahan tetap tersimpan.');
    }

    private function assertLotScope(Property $property, Block $block, Lot $lot): void
    {
        abort_unless(
            $block->property_id === $property->id &&
                $lot->block_id === $block->id,
            404
        );
    }

    /** @return array<string, mixed> */
    private function validatedProgress(Request $request, Property $property): array
    {
        $propertyHasStages = $property->constructionStages()->exists();
        $isAdmin = $request->user()->isAdmin();
        $validated = $request->validate([
            'stage_id' => [
                'required',
                'integer',
                Rule::exists('construction_stages', 'id')->where(
                    fn ($query) => $query
                        ->where('property_id', $propertyHasStages ? $property->id : null)
                        ->where('is_active', true)
                ),
            ],
            'progress' => ['required', 'numeric', 'min:0', 'max:100'],
            'realization' => $isAdmin
                ? ['required', 'numeric', 'decimal:0,2', 'min:0']
                : ['prohibited'],
            'notes' => $isAdmin ? ['nullable', 'string'] : ['prohibited'],
        ]);

        return $validated;
    }

    /** @param array<string, mixed> $validated */
    private function saveProgress(Request $request, Lot $lot, array $validated): ConstructionProgress
    {
        return DB::transaction(function () use ($lot, $request, $validated): ConstructionProgress {
            $existingProgress = $lot->constructionProgresses()
                ->where('stage_id', $validated['stage_id'])
                ->lockForUpdate()
                ->first();
            $progress = $existingProgress ?? $lot->constructionProgresses()->make([
                'stage_id' => $validated['stage_id'],
            ]);
            $attributes = [
                'progress' => $validated['progress'],
                'updated_by' => $request->user()->id,
            ];

            if ($request->user()->isAdmin()) {
                $attributes['realization'] = $validated['realization'];
                $attributes['notes'] = $validated['notes'] ?? null;
            } elseif ($existingProgress === null) {
                $attributes['realization'] = 0;
            }

            $progress->fill($attributes)->save();

            ConstructionProgressHistory::create([
                'construction_progress_id' => $progress->id,
                'lot_id' => $lot->id,
                'stage_id' => $progress->stage_id,
                'progress' => $progress->progress,
                'previous_progress' => $existingProgress?->progress,
                'realization' => $progress->realization,
                'previous_realization' => $existingProgress?->realization,
                'updated_by' => $request->user()->id,
                'notes' => $progress->notes,
            ]);

            return $progress;
        });
    }

    private function progressRedirect(
        Property $property,
        Block $block,
        Lot $lot,
        string $message,
    ): RedirectResponse {
        return redirect()
            ->route('pembangunan.progress.show', [
                'property' => $property,
                'block' => $block,
                'lot' => $lot,
            ])
            ->with('success', $message);
    }
}
