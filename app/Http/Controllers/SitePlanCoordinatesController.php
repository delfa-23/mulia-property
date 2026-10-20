<?php

namespace App\Http\Controllers;

use App\Models\Lot;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SitePlanCoordinatesController extends Controller
{
    public function update(Request $request, Property $property, Lot $lot): JsonResponse|RedirectResponse
    {
        abort_unless($lot->block()->where('property_id', $property->id)->exists(), 404);
        abort_unless(filled($property->site_plan_path), 422, 'Unggah gambar siteplan sebelum memetakan area.');

        $validated = $request->validate([
            'coordinates.x' => ['required', 'numeric', 'between:0,100'],
            'coordinates.y' => ['required', 'numeric', 'between:0,100'],
            'coordinates.width' => ['required', 'numeric', 'gt:0', 'lte:100'],
            'coordinates.height' => ['required', 'numeric', 'gt:0', 'lte:100'],
        ]);

        $coordinates = array_map(
            static fn (string|int|float $coordinate): float => (float) $coordinate,
            $validated['coordinates'],
        );

        if (
            $coordinates['x'] + $coordinates['width'] > 100 ||
            $coordinates['y'] + $coordinates['height'] > 100
        ) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Area kavling harus berada di dalam batas gambar denah.',
                    'errors' => [
                        'coordinates' => ['Area kavling harus berada di dalam batas gambar denah.'],
                    ],
                ], 422);
            }

            return back()
                ->withErrors(['coordinates' => 'Area kavling harus berada di dalam batas gambar denah.'])
                ->withInput();
        }

        $lot->update(['map_coordinates' => $coordinates]);
        $message = 'Posisi kavling berhasil disimpan.';

        if ($request->expectsJson()) {
            $block = $lot->block;
            $mappedLotCount = $property->lots()->whereNotNull('map_coordinates')->count();
            $totalLotCount = $property->lots()->count();

            return response()->json([
                'message' => $message,
                'mapped_lot_count' => $mappedLotCount,
                'total_lot_count' => $totalLotCount,
                'mapped_block_count' => $block->lots()->whereNotNull('map_coordinates')->count(),
                'lot' => [
                    'id' => $lot->id,
                    'block_id' => $block->id,
                    'label' => $block->name.'/'.$lot->lot_number,
                    'coordinates' => $coordinates,
                ],
            ]);
        }

        $nextLot = $property->lots()
            ->whereNull('map_coordinates')
            ->orderBy('lot_number')
            ->first();
        $routeParameters = ['property' => $property];

        if ($nextLot !== null) {
            $routeParameters['lot_id'] = $nextLot->id;
        }

        $redirectUrl = $request->routeIs('admin.*')
            ? route('admin.properties.edit', $routeParameters).'#site-plan-editor'
            : route('site-plans.index', ['property_id' => $property->id]);

        return redirect($redirectUrl)->with(
            'success',
            $nextLot === null
                ? 'Semua posisi kavling berhasil ditandai.'
                : $message.' Lanjutkan ke kavling '.$nextLot->lot_number.'.',
        );
    }
}
