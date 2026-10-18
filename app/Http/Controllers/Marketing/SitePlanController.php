<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

class SitePlanController extends Controller
{
    public function edit(Property $property): View
    {
        $lots = $property->lots()
            ->with('block')
            ->orderBy('lot_number')
            ->get();
        $mappedLotCount = $lots->whereNotNull('map_coordinates')->count();
        $blocks = $property->blocks()->withCount('lots')->orderBy('name')->get();
        $mappedLotCountsByBlock = $lots
            ->whereNotNull('map_coordinates')
            ->countBy('block_id');
        $hasLots = $lots->isNotEmpty()
            && $blocks->isNotEmpty()
            && $blocks->every(fn ($block) => $block->lots_count > 0);
        $incompleteBlocks = $blocks
            ->where('lots_count', 0)
            ->pluck('name');

        return view('marketing.site-plans.edit', [
            'property' => $property,
            'blocks' => $blocks,
            'lots' => $lots,
            'mappedLotCount' => $mappedLotCount,
            'mappedLotCountsByBlock' => $mappedLotCountsByBlock,
            'hasLots' => $hasLots,
            'incompleteBlocks' => $incompleteBlocks,
            'coordinateRoute' => 'marketing.site-plans.coordinates.update',
            'manageLotsUrl' => null,
        ]);
    }

    public function update(Request $request, Property $property): RedirectResponse
    {
        $validated = $request->validate([
            'site_plan' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $sitePlanPath = $this->storeSitePlan($validated['site_plan']);
        $previousSitePlanPath = $property->site_plan_path;

        $property->update(['site_plan_path' => $sitePlanPath]);

        Lot::query()
            ->whereHas('block', fn ($query) => $query->where('property_id', $property->id))
            ->update(['map_coordinates' => null]);

        if ($previousSitePlanPath !== null) {
            Storage::disk('site-plans')->delete($previousSitePlanPath);
            Storage::disk('public')->delete($previousSitePlanPath);
        }

        return redirect()
            ->route('marketing.site-plans.edit', $property)
            ->with('success', 'Gambar denah berhasil disimpan. Tandai ulang posisi kavling pada gambar terbaru.');
    }

    private function storeSitePlan(UploadedFile $sitePlan): string
    {
        $path = $sitePlan->store('site-plans', 'site-plans');

        if ($path === false) {
            throw new RuntimeException('Denah perumahan gagal disimpan ke penyimpanan.');
        }

        return $path;
    }
}
