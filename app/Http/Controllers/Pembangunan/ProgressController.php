<?php

namespace App\Http\Controllers\Pembangunan;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\ConstructionProgressHistory;
use App\Models\ConstructionStage;
use App\Models\Lot;
use App\Models\Property;
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
                'budgets',
                'expenseTransactions',
                'constructionProgresses',
            ])
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
            'budgets',
            'expenseTransactions',
            'constructionProgresses.stage',
            'constructionProgresses.updatedBy',
            'constructionProgressHistories.constructionProgress.stage',
            'constructionProgressHistories.updatedBy',
        ]);

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
                'stages'
            )
        );
    }

    public function update(
        Request $request,
        Property $property,
        Block $block,
        Lot $lot
    ): RedirectResponse {
        abort_unless(
            $block->property_id === $property->id &&
                $lot->block_id === $block->id,
            404
        );

        $propertyHasStages = $property->constructionStages()->exists();
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
            'notes' => ['nullable', 'string'],
        ]);

        $stage = ConstructionStage::findOrFail($validated['stage_id']);

        // Pastikan progress mengikuti range yang ditentukan Admin
        if (
            $validated['progress'] < $stage->min_progress ||
            $validated['progress'] > $stage->max_progress
        ) {
            return back()
                ->withErrors([
                    'progress' => sprintf(
                        'Progress tahap %s harus berada di antara %s%% sampai %s%%.',
                        $stage->name,
                        $stage->min_progress,
                        $stage->max_progress
                    ),
                ])
                ->withInput();
        }

        DB::transaction(function () use ($lot, $request, $validated): void {
            $constructionProgress = $lot->constructionProgresses()
                ->where('stage_id', $validated['stage_id'])
                ->first();

            $progress = $lot->constructionProgresses()->updateOrCreate(
                ['stage_id' => $validated['stage_id']],
                [
                    'progress' => $validated['progress'],
                    'updated_by' => $request->user()->id,
                    'notes' => $validated['notes'] ?? null,
                ]
            );

            ConstructionProgressHistory::create([
                'construction_progress_id' => $progress->id,
                'progress' => $progress->progress,
                'previous_progress' => $constructionProgress?->progress,
                'updated_by' => $request->user()->id,
                'notes' => $progress->notes,
            ]);
        });

        return redirect()
            ->route('pembangunan.progress.show', [
                'property' => $property,
                'block' => $block,
                'lot' => $lot,
            ])
            ->with('success', 'Progress pembangunan berhasil diperbarui.');
    }
}
