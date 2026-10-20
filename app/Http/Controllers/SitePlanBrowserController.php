<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SitePlanBrowserController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user?->isAdmin() || $user?->hasValidDivisionAssignment(), 403);

        $validated = $request->validate([
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
        ]);

        $properties = Property::query()->orderBy('name')->get();
        $selectedProperty = isset($validated['property_id'])
            ? $properties->firstWhere('id', (int) $validated['property_id'])
            : null;
        $lots = $selectedProperty
            ? $selectedProperty->lots()
                ->with([
                    'block',
                    'bookings' => fn ($query) => $query
                        ->whereIn('status', ['booking', 'process', 'akad', 'finish'])
                        ->with(['customer', 'sales'])
                        ->latest('id'),
                ])
                ->orderBy('lot_number')
                ->get()
            : collect();
        $sitePlanAreas = $selectedProperty
            ? $selectedProperty->sitePlanAreas()
                ->where('site_plan_path', $selectedProperty->site_plan_path)
                ->with([
                    'lot.block',
                    'lot.bookings' => fn ($query) => $query
                        ->whereIn('status', ['booking', 'process', 'akad', 'finish'])
                        ->with(['customer', 'sales'])
                        ->latest('id'),
                ])
                ->orderBy('id')
                ->get()
            : collect();
        $routePrefix = $user->isAdmin()
            ? 'admin'
            : ($user->isAssignedToDivision('marketing') ? 'marketing' : null);
        $indexRoute = match ($routePrefix) {
            'admin' => 'admin.site-plans.index',
            'marketing' => 'marketing.site-plans.index',
            default => 'site-plans.index',
        };
        $manageUrl = match ($routePrefix) {
            'admin' => $selectedProperty
                ? route('admin.properties.edit', $selectedProperty).($selectedProperty->site_plan_path ? '#site-plan-editor' : '#site-plan-upload')
                : null,
            'marketing' => null,
            default => null,
        };

        return view('site-plans.browser', [
            'properties' => $properties,
            'property' => $selectedProperty,
            'lots' => $lots,
            'sitePlanAreas' => $sitePlanAreas,
            'indexRoute' => $indexRoute,
            'manageUrl' => $manageUrl,
            'managementRole' => $routePrefix,
        ]);
    }
}
