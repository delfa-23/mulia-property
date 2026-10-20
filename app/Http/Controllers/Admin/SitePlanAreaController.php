<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lot;
use App\Models\Property;
use App\Models\SitePlanArea;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SitePlanAreaController extends Controller
{
    public function store(Request $request, Property $property): RedirectResponse
    {
        $validated = $this->validatedArea($request, $property);
        $validated['property_id'] = $property->id;
        $validated['site_plan_path'] = $property->site_plan_path;
        $validated['created_by'] = $request->user()->id;
        $validated['updated_by'] = $request->user()->id;

        $area = DB::transaction(function () use ($validated): SitePlanArea {
            $area = SitePlanArea::query()->create($validated);

            if ($area->lot_id !== null) {
                Lot::query()->whereKey($area->lot_id)->update(['map_coordinates' => null]);
            }

            return $area;
        });

        return $this->redirectToEditor($property, $area->id)
            ->with('success', 'Area siteplan berhasil disimpan.');
    }

    public function update(Request $request, Property $property, SitePlanArea $sitePlanArea): RedirectResponse
    {
        abort_unless(
            $sitePlanArea->property_id === $property->id &&
            $sitePlanArea->site_plan_path === $property->site_plan_path,
            404,
        );

        $validated = $this->validatedArea($request, $property, $sitePlanArea);
        $validated['site_plan_path'] = $property->site_plan_path;
        $validated['updated_by'] = $request->user()->id;

        DB::transaction(function () use ($validated, $sitePlanArea): void {
            $sitePlanArea->update($validated);

            if ($sitePlanArea->lot_id !== null) {
                Lot::query()->whereKey($sitePlanArea->lot_id)->update(['map_coordinates' => null]);
            }
        });

        return $this->redirectToEditor($property, $sitePlanArea->id)
            ->with('success', 'Area siteplan berhasil diperbarui.');
    }

    public function destroy(Property $property, SitePlanArea $sitePlanArea): RedirectResponse
    {
        abort_unless(
            $sitePlanArea->property_id === $property->id &&
            $sitePlanArea->site_plan_path === $property->site_plan_path,
            404,
        );
        $sitePlanArea->delete();

        return $this->redirectToEditor($property)
            ->with('success', 'Area siteplan berhasil dihapus.');
    }

    /** @return array{lot_id: int|null, label: string|null, polygon: list<array{x: float, y: float}>} */
    private function validatedArea(Request $request, Property $property, ?SitePlanArea $sitePlanArea = null): array
    {
        abort_unless(filled($property->site_plan_path), 422, 'Unggah gambar siteplan sebelum memetakan area.');

        $validated = $request->validate([
            'lot_id' => ['nullable', 'integer', 'exists:lots,id'],
            'label' => [Rule::requiredIf(! $request->filled('lot_id')), 'nullable', 'string', 'max:100'],
            'polygon' => ['required', 'array', 'min:3', 'max:120'],
            'polygon.*' => ['required', 'array:x,y'],
            'polygon.*.x' => ['required', 'numeric', 'between:0,100'],
            'polygon.*.y' => ['required', 'numeric', 'between:0,100'],
        ]);

        $polygon = array_map(static fn (array $point): array => [
            'x' => (float) $point['x'],
            'y' => (float) $point['y'],
        ], $validated['polygon']);
        $distinctPoints = collect($polygon)
            ->map(fn (array $point): string => number_format($point['x'], 4, '.', '').','.number_format($point['y'], 4, '.', ''))
            ->unique();

        if ($distinctPoints->count() < 3 || abs($this->signedArea($polygon)) < 0.0001) {
            throw ValidationException::withMessages([
                'polygon' => 'Poligon harus memiliki minimal tiga titik berbeda dan membentuk area.',
            ]);
        }

        $lotId = $validated['lot_id'] ?? null;

        if ($lotId !== null) {
            $lotBelongsToProperty = Lot::query()
                ->whereKey($lotId)
                ->whereHas('block', fn (Builder $query) => $query->where('property_id', $property->id))
                ->exists();

            if (! $lotBelongsToProperty) {
                throw ValidationException::withMessages([
                    'lot_id' => 'Kavling harus berasal dari perumahan yang dipilih.',
                ]);
            }

            $lotAlreadyMapped = SitePlanArea::query()
                ->where('property_id', $property->id)
                ->where('site_plan_path', $property->site_plan_path)
                ->where('lot_id', $lotId)
                ->when($sitePlanArea, fn (Builder $query) => $query->where('id', '!=', $sitePlanArea->id))
                ->exists();

            if ($lotAlreadyMapped) {
                throw ValidationException::withMessages([
                    'lot_id' => 'Kavling tersebut sudah terhubung dengan area lain.',
                ]);
            }
        }

        return [
            'lot_id' => $lotId === null ? null : (int) $lotId,
            'label' => $validated['label'] ?? null,
            'polygon' => $polygon,
        ];
    }

    /** @param list<array{x: float, y: float}> $polygon */
    private function signedArea(array $polygon): float
    {
        $sum = 0.0;
        $pointCount = count($polygon);

        for ($index = 0; $index < $pointCount; $index++) {
            $nextIndex = ($index + 1) % $pointCount;
            $sum += ($polygon[$index]['x'] * $polygon[$nextIndex]['y'])
                - ($polygon[$nextIndex]['x'] * $polygon[$index]['y']);
        }

        return $sum / 2;
    }

    private function redirectToEditor(Property $property, ?int $selectedAreaId = null): RedirectResponse
    {
        $parameters = ['property' => $property];

        if ($selectedAreaId !== null) {
            $parameters['site_plan_area_id'] = $selectedAreaId;
        }

        return redirect()->to(route('admin.properties.edit', $parameters).'#site-plan-editor');
    }
}
