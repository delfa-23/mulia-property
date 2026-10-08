<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConstructionStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ConstructionStageController extends Controller
{
    public function index(): View
    {
        $stages = ConstructionStage::query()
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        return view(
            'admin.construction-stages.index',
            compact('stages')
        );
    }

    public function create(): View
    {
        return view('admin.construction-stages.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('construction_stages', 'name')->whereNull('property_id'),
            ],
            'order' => [
                'required',
                'integer',
                'min:0',
                Rule::unique('construction_stages', 'order')->whereNull('property_id'),
            ],
            'min_progress' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
                'lte:max_progress',
            ],
            'max_progress' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
                'gte:min_progress',
            ],
            'description' => [
                'nullable',
                'string',
            ],
        ]);

        $this->ensureRangeDoesNotOverlap($validated);

        ConstructionStage::create($validated);

        return redirect()
            ->route('admin.construction-stages.index')
            ->with('success', 'Tahapan pembangunan berhasil dibuat.');
    }

    public function edit(ConstructionStage $constructionStage): View
    {
        return view(
            'admin.construction-stages.edit',
            compact('constructionStage')
        );
    }

    public function update(
        Request $request,
        ConstructionStage $constructionStage
    ): RedirectResponse {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('construction_stages', 'name')
                    ->ignore($constructionStage)
                    ->where('property_id', $constructionStage->property_id),
            ],
            'order' => [
                'required',
                'integer',
                'min:0',
                Rule::unique('construction_stages', 'order')
                    ->ignore($constructionStage)
                    ->where('property_id', $constructionStage->property_id),
            ],
            'min_progress' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
                'lte:max_progress',
            ],
            'max_progress' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
                'gte:min_progress',
            ],
            'description' => [
                'nullable',
                'string',
            ],
        ]);

        $this->ensureRangeDoesNotOverlap($validated, $constructionStage);

        $constructionStage->update($validated);

        return redirect()
            ->route('admin.construction-stages.index')
            ->with('success', 'Tahapan pembangunan berhasil diperbarui.');
    }

    public function destroy(
        ConstructionStage $constructionStage
    ): RedirectResponse {
        DB::transaction(function () use ($constructionStage): void {
            $constructionStage->progresses()->delete();
            $constructionStage->delete();
        });

        return redirect()
            ->route('admin.construction-stages.index')
            ->with('success', 'Tahapan pembangunan berhasil dihapus.');
    }

    /** @param array<string, mixed> $validated */
    private function ensureRangeDoesNotOverlap(array $validated, ?ConstructionStage $ignoredStage = null): void
    {
        $overlappingStageExists = ConstructionStage::query()
            ->where('property_id', $ignoredStage?->property_id)
            ->where('min_progress', '<=', $validated['max_progress'])
            ->where('max_progress', '>=', $validated['min_progress'])
            ->when($ignoredStage, fn ($query) => $query->where('id', '!=', $ignoredStage->getKey()))
            ->exists();

        if ($overlappingStageExists) {
            throw ValidationException::withMessages([
                'min_progress' => 'Range persentase bertabrakan dengan tahapan pembangunan lain.',
            ]);
        }
    }
}
