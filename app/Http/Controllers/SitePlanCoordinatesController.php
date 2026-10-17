<?php

namespace App\Http\Controllers;

use App\Models\Lot;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SitePlanCoordinatesController extends Controller
{
    public function update(Request $request, Property $property, Lot $lot): RedirectResponse
    {
        abort_unless($lot->block()->where('property_id', $property->id)->exists(), 404);

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
            return back()
                ->withErrors(['coordinates' => 'Area kavling harus berada di dalam batas gambar denah.'])
                ->withInput();
        }

        $lot->update(['map_coordinates' => $coordinates]);

        return back()->with('success', 'Posisi kavling berhasil disimpan.');
    }
}
