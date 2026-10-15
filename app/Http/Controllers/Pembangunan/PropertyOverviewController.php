<?php

namespace App\Http\Controllers\Pembangunan;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PropertyOverviewController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
        ]);

        $properties = Property::query()
            ->with([
                'blocks' => fn ($query) => $query
                    ->with(['lots' => fn ($query) => $query->orderBy('lot_number')])
                    ->orderBy('name'),
            ])
            ->when(
                isset($validated['property_id']),
                fn ($query) => $query->whereKey($validated['property_id'])
            )
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        $propertyOptions = Property::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('pembangunan.properties.index', [
            'properties' => $properties,
            'propertyOptions' => $propertyOptions,
            'propertyId' => isset($validated['property_id']) ? (int) $validated['property_id'] : null,
        ]);
    }
}
